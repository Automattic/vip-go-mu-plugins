<?php
/*
Plugin Name: WordPress.com Related Posts
Plugin URI: http://automattic.com
Description: Related posts using the WordPress.com Elastic Search infrastructure
Author: Daniel Bachhuber
Version: 0.0
Author URI: http://automattic.com

GNU General Public License, Free Software Foundation <http://creativecommons.org/licenses/GPL/2.0/>

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/

class WPCOM_Related_Posts {

	public $options_capability = 'manage_options';
	public $default_options = array();
	public $options = array();

	/**
	 * Store the $args for a get_related_posts() query so they are
	 * accessible in the 'posts_where' filter for non-ElasticSearch
	 * queries. Is unset at the end of get_related_posts()
	 */
	protected $args = array();

	const key = 'wpcom-related-posts';

	const CACHE_GROUP 		= 'wpcom-related-posts';
	const CACHE_LIFETIME 	= 300;

	protected static $instance;

	private $_generation_method;

	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new WPCOM_Related_Posts;
			self::$instance->setup_actions();
			self::$instance->setup_filters();
		}
		return self::$instance;
	}

	protected function __construct() {
		/** Don't do anything **/
	}

	protected function setup_actions() {

		add_action( 'init', array( self::$instance, 'action_init' ) );
		add_action( 'wp_head', array( self::$instance, 'action_wp_head' ) );

		add_action( 'admin_init', array( self::$instance, 'action_admin_init' ) );
		add_action( 'admin_menu', array( self::$instance, 'action_admin_menu' ) );
	}

	protected function setup_filters() {

		add_filter( 'the_content', array( self::$instance, 'filter_the_content' ) );
	}

	public function action_init() {

		$this->default_options = array(
				'post-types' => array(),
				'post-count' => 5,
			);
		$this->options = get_option( self::key, array() );

		$this->options = wp_parse_args( $this->options, $this->default_options );
	}

	public function action_admin_init() {

		register_setting( self::key, self::key, array( self::$instance, 'sanitize_options' ) );
		add_settings_section( 'general', false, '__return_false', self::key );
		add_settings_field( 'post-types', __( 'Enable for these post types:', 'wpcom-related-posts' ), array( self::$instance, 'setting_post_types' ), self::key, 'general' );
		add_settings_field( 'post-count', __( 'Number of posts to display:', 'wpcom-related-posts' ), array( self::$instance, 'setting_post_count' ), self::key, 'general' );
	}

	public function action_admin_menu() {

		add_options_page( __( 'WordPress.com Related Posts', 'wpcom-related-posts' ), __( 'Related Posts', 'wpcom-related-posts' ), $this->options_capability, self::key, array( self::$instance, 'view_settings_page' ) );
	}

	public function setting_post_types() {
		$all_post_types = get_post_types( array( 'publicly_queryable' => true ), 'objects' );
		foreach( $all_post_types as $post_type ) {
			echo '<label for="' . esc_attr( 'post-type-' . $post_type->name ) . '">';
			echo '<input id="' . esc_attr( 'post-type-' . $post_type->name ) . '" type="checkbox" name="' . self::key . '[post-types][]" ';
			if ( ! empty( $this->options['post-types'] ) && in_array( $post_type->name, $this->options['post-types'] ) )
				echo ' checked="checked"';
			echo ' value="' . esc_attr( $post_type->name ) . '" />&nbsp&nbsp;';
			echo $post_type->labels->name;
			echo '</label><br />';
		}
	}

	public function setting_post_count() {
		echo '<select name="' . self::key . '[post-count]">';
		for( $i = 1; $i <= 10; $i++ ) {
			echo '<option value="' . $i . '" ' . selected( $i, $this->options['post-count'], false ) . '>' . $i . '</selected>';
		}
		echo '</select>';
	}

	public function sanitize_options( $in ) {

		$out = $this->default_options;

		// Validate the post types
		$valid_post_types = get_post_types( array( 'publicly_queryable' => true ) );
		foreach( $in['post-types'] as $maybe_post_type ) {
			if ( in_array( $maybe_post_type, $valid_post_types ) )
				$out['post-types'][] = $maybe_post_type;
		}

		// Validate the post count
		$out['post-count'] = (int) $in['post-count'];
		if ( $out['post-count'] < 1 || $out['post-count'] > 10 )
			$out['post-count'] = $this->default_options['post-count'];

		return $out;
	}

	public function view_settings_page() {
	?><div class="wrap">
		<h2><?php _e( 'WordPress.com Related Posts', 'wpcom-related-posts' ); ?></h2>
		<p><?php _e( 'Related posts for the bottom of your content using WordPress.com infrastructure', 'wpcom-related-posts' ); ?></p>
		<form action="options.php" method="POST">
			<?php settings_fields( self::key ); ?>
			<?php do_settings_sections( self::key ); ?>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
	}

	/**
	 * Basic styling for the related posts so they don't look terrible
	 */
	public function action_wp_head() {
		?>
		<style>
			.wpcom-related-posts ul li {
				list-style-type: none;
				display: inline-block;
			}
		</style>
		<?php
	}

	/**
	 * Append related posts to the post content
	 */
	public function filter_the_content( $the_content ) {

		// Related posts should only be appended on the main loop for is_singular() of acceptable post types
		if ( true === apply_filters( 'wpcom_related_posts_suppress_the_content', false ) || ! is_main_query() || ! in_the_loop() || ! in_array( get_post_type(), $this->options['post-types'] ) || ! is_singular( get_post_type() ) )
			return $the_content;

		$related_posts = $this->get_related_posts();

		$related_posts_html = array(
				'<div class="wpcom-related-posts" id="' . esc_attr( 'wpcom-related-posts-' . get_the_ID() ) . '">',
			);

		if ( $related_posts ) {
			$related_posts_html[] = '<ul>';
			foreach( $related_posts as $related_post ) {
				if ( $this->_can_bump_stats() && $this->_generation_method )
					$related_post_url = BumpAndRedirect::generate_url( 'ES-Related-Post-Hit', $this->_generation_method, get_permalink( $related_post->ID ), true );
				else
					$related_post_url = get_permalink( $related_post->ID );


				$related_posts_html[] = '<li>';

				if ( has_post_thumbnail( $related_post->ID ) ) {
					$related_posts_html[] = '<a href="' . $related_post_url . '">' . get_the_post_thumbnail( $related_post->ID, apply_filters( 'wrp_thumbnail_size', 'post-thumbnail' ) ) . '</a>';
				}

				$related_posts_html[] = '<a href="' . $related_post_url . '">' . apply_filters( 'the_title', $related_post->post_title ) . '</a>';
				$related_posts_html[] = '</li>';
			}
			$related_posts_html[] = '</ul>';
		}

		$related_posts_html[] = '</div>';

		return $the_content . implode( PHP_EOL, $related_posts_html );
	}

	/**
	 * @return array $related_posts An array of related WP_Post objects
	 */
	public function get_related_posts( $post_id = null, $args = array() ) {

		if ( is_null( $post_id ) )
			$post_id = get_the_ID();

		$defaults = array(
				'posts_per_page'          => $this->options['post-count'],
				'post_type'               => get_post_type( $post_id ),
				'has_terms'               => array(),
				'date_range'			  => array(
					'from'	=> strtotime( '-1 year' ),
					'to' 	=> time()
				)
			);
		$args = wp_parse_args( $args, $defaults );

		$args['date_range'] = apply_filters( 'wrp_date_range', $args['date_range'], $post_id );

		if ( is_array( $args['date_range'] ) )
			$args['date_range'] = array_map( 'intval', $args['date_range'] );

		// To have a reasonable cache hitrate, we must ignore the date range, as it's comprised (by default) of 
		// time() calls
		$cache_key_args = $args;

		unset( $cache_key_args['date_range'] );

		$cache_key = 'related_posts_' . $post_id . '_' . md5( serialize( $cache_key_args ) );

		$related_posts = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $related_posts ) {
			return $related_posts;
		}

		// Store the args so any 'posts_where' filters can access them
		$this->args = $args;

		$related_posts = array();
		$this->_generation_method = null;

		if ( $this->_use_related_api() ) {
			// Use related posts API
			$response = Jetpack_RelatedPosts::init_raw()->get_for_post_id(
				$post_id,
				array(
					'size' => (int)$args['posts_per_page'],
					'post_type' => $args['post_type'],
					'has_terms' => $args['has_terms'],
					'date_range' => $args['date_range'],
				)
			);

			$related_posts = array();
			foreach( $response as $hit ) {
				$related_posts[] = get_post( $hit['id'] );
			}

			$this->_generation_method = 'MLT-VIP-Raw';
		} else { 
			$related_query_args = array(
				'posts_per_page' => (int)$args['posts_per_page'],
				'post__not_in'   => array( $post_id ),
				'post_type'      => $args['post_type'],
			);
			$categories = get_the_category( $post_id );
			if ( ! empty( $categories ) )
				$related_query_args[ 'cat' ] = $categories[0]->term_id;

			if ( ! empty( $args['has_terms'] ) ) {
				$tax_query = array();
				foreach( (array)$args['has_terms'] as $term ) {
					$tax_query[] = array(
							'taxonomy'          => $term->taxonomy,
							'field'             => 'slug',
							'terms'             => $term->slug,
						);
				}
				$related_query_args['tax_query'] = $tax_query;
			}

			add_filter( 'posts_where', array( $this, 'filter_related_posts_where' ) );

			$related_query = new WP_Query( $related_query_args );

			remove_filter( 'posts_where', array( $this, 'filter_related_posts_where' ) );

			$related_posts = $related_query->get_posts();

			$this->_generation_method = 'WP-Query';
		}

		if ( $this->_can_bump_stats() )
			a8c_bump_stat( 'ES-Related-Post-Gen', $this->_generation_method );

		// Clear out the $args, as they are only meaningful inside get_related_posts()
		$this->args = array();

		wp_cache_set( $cache_key, $related_posts, self::CACHE_GROUP, self::CACHE_LIFETIME );

		return $related_posts;
	}

	/**
	 * Rewrite the WHERE clause for non-ElasticSearch queries
	 *
	 * @param string $where The WHERE clause to filter
	 * @return string The WHERE clause, filtered with the date range
	 */
	public function filter_related_posts_where( $where = '' ) {
		global $wpdb;

		if ( ! is_array( $this->args['date_range'] ) ||
			empty( $this->args['date_range']['from'] ) ||
			empty( $this->args['date_range']['to'] ) )
				return $where;

		$where .= $wpdb->prepare( ' AND post_date >= %s', date( 'Y-m-d', $this->args['date_range']['from'] ) );
		$where .= $wpdb->prepare( ' AND post_date < %s', date( 'Y-m-d', $this->args['date_range']['to'] ) );

		return $where;
	}

	private function _use_related_api() {
		return class_exists( 'Jetpack_RelatedPosts' );
	}

	private function _can_bump_stats() {
		return function_exists( 'a8c_bump_stat' );
	}
}

function WPCOM_Related_Posts() {
	return WPCOM_Related_Posts::instance();
}
add_action( 'plugins_loaded', 'WPCOM_Related_Posts' );
