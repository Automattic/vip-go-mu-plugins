<?php
/*
  Plugin Name: Voce Error Logging
  Plugin URI: http://plugins.voceconnect.com
  Description: Allows error logging as a post type, for VIP sites and developers that don't have access to log files.
  Version: 0.2
  Author: jeffstieler
  License: GPL2
 */

// TODO: Admin notice with Enable toggle
// TODO: Auto-clean-up of old error logs, either by age or max total errors
// TODO: Collapsable post rows on edit.php
// TODO: Highlight 'tools > logged errors' menu item on ?post_type=error

class Voce_Error_Logging {

	const POST_TYPE = 'voce-error-log';
	const TAXONOMY = 'voce-error-log-status';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'create_post_type' ), 9 );
		add_action( 'init', array( __CLASS__, 'create_status_taxonomy' ), 9 );
		add_action( 'init', array( __CLASS__, 'redirect_to_error_listing' ) );

		if ( is_admin() ) {
			add_action( 'admin_menu', array( __CLASS__, 'add_menu_items' ) );
			add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'set_error_columns' ) );
			add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'display_error_columns' ), 10, 2 );
			add_action( 'restrict_manage_posts', array( __CLASS__, 'add_tag_filter' ) );
			add_action('admin_print_styles', array(__CLASS__, 'admin_styles'));
		}
	}

	public static function create_post_type() {
		register_post_type( self::POST_TYPE, array(
			'labels' => array(
				'name' => 'Logged Errors',
				'singular_name' => 'Logged Error',
				'view_item' => 'View',
				'search_items' => 'Search Errors',
				'not_found' => 'No logged errors found.',
				'not_found_in_trash' => 'No logged errors in trash.'
			),
			'show_ui' => true,
			'show_in_menu' => false,
			'supports' => array( ),
		) );
	}

	public static function create_status_taxonomy() {
		register_taxonomy( self::TAXONOMY, self::POST_TYPE, array(
			'public' => true,
			'show_in_nav_menus' => true,
			'show_ui' => true,
			'hierarchical' => false,
			'label' => 'Log Types',
			'show_admin_column' => true,
		) );
	}

	public static function add_menu_items() {
		add_submenu_page( 'tools.php', 'Logged Errors', 'Error Log', 'manage_options', 'error_log', array( __CLASS__, 'logged_errors_page' ) );
	}

	public static function redirect_to_error_listing() {
		if ( isset( $_GET['page'] ) && ('error_log' == $_GET['page']) ) {
			wp_redirect( admin_url( 'edit.php?post_type=' . self::POST_TYPE ) );
			die();
		}
	}

	public static function error_log( $post_title, $error, $tags = array( ) ) {

		$post_content = (is_string( $error ) ? $error : print_r( $error, true )) . "\n\n";
		$backtrace = debug_backtrace();

		// remove calls to this function and template tags that call it
		foreach ( $backtrace as $i => $call ) {
			if ( ('voce_error_log' === $call['function']) || ('error_log' === $call['function']) ) {
				unset( $backtrace[$i] );
			}
		}
		$post_content .= "<hr>\n";

		foreach ( $backtrace as $call ) {
			if ( isset( $call['file'] ) && isset( $call['line'] ) ) {
				$post_content .= sprintf( "%s - (%s:%d)\n", $call['function'], $call['file'], $call['line'] );
			} else {
				$post_content .= $call['function'] . "\n";
				break; // stop when we get to the function containing the voce_error_log() call
			}
		}

		$postarr = compact( 'post_title', 'post_content' );
		$postarr = array_merge( $postarr, array( 'post_type' => self::POST_TYPE, 'post_status' => 'publish', 'post_tag' => $tags ) );

		$log_id = wp_insert_post( $postarr );
		wp_set_post_terms( $log_id, $tags, self::TAXONOMY );
	}

	public static function set_error_columns( $columns ) {
		return array(
			'cb' => $columns['cb'],
			'error' => 'Error',
			'taxonomy-' . self::TAXONOMY => 'Log Types',
			'date' => $columns['date']
		);
	}

	public static function display_error_columns( $column_name, $post_id ) {
		switch ( $column_name ) {
			case 'error':
				$post = get_post( $post_id );
				?>
				<strong><?php echo esc_html( $post->post_title ); ?></strong>
				<pre><?php echo wpautop( $post->post_content ); ?></pre>
				<?php
				break;
		}
	}

	public static function add_tag_filter() {
		$screen = get_current_screen();
		if ( is_a( $screen, 'WP_Screen' ) && $screen->id == 'edit-voce-error-log' ) {
			$args = array(
				'hide_empty' => 1,
				'orderby' => 'name',
			);
			$current = get_query_var( self::TAXONOMY );
			$terms = get_terms( self::TAXONOMY, $args );
			printf( '<select name="%1$s" id="%1$s" class="postform">', self::TAXONOMY );
			print('<option value="">View all log types</option>' );

			foreach ( $terms as $term ) {
				printf( '<option class="level-0" value="%1$s"%2$s>%3$s</option>', $term->slug, selected( $term->slug, $current, false ), $term->name );
			}

			print '</select>';
		}
	}
	
	public static function admin_styles() {
		echo '<style>.column-taxonomy-voce-error-log-status{width:15%;}</style>';
	}

	public static function delete_logs( $terms = array( ) ) {
		$args = array(
			'posts_per_page' => -1,
			'post_type' => self::POST_TYPE
		);

		if ( $terms ) {
			if ( !is_array( $terms ) ) {
				$terms = array( $terms );
			}


			$args['tax_query'] = array(
				array(
					'taxonomy' => Voce_Error_Logging::TAXONOMY,
					'field' => 'slug',
					'terms' => $terms,
					'operator' => 'AND',
				)
			);
		}

		$q = new WP_Query( $args );

		$deleted = array( );
		$not_deleted = array( );

		if ( $q->have_posts() ) {
			foreach ( $q->posts as $post ) {
				if ( $p = wp_delete_post( $post->ID ) ) {
					$deleted[] = $post->ID;
				} else {
					$not_deleted[] = $post->ID;
				}
			}
		}

		$response['success'] = ( bool ) (!$not_deleted);
		$response['error'] = ( bool ) ($not_deleted);
		$response['deleted'] = $deleted;
		$response['failed'] = $not_deleted;
		return $response;
	}

	public static function get_log_count() {
		global $wpdb;

		if ( false === ($log_count = wp_cache_get( 'lift_log_count' )) ) {
			$log_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT( 1 ) FROM $wpdb->posts
				WHERE post_type = %s", self::POST_TYPE ) );

			wp_cache_set( 'lift_log_count', $log_count );
		}

		return $log_count;
	}

}

Voce_Error_Logging::init();

// create convenience function for logging
if ( !function_exists( 'voce_error_log' ) ) {

	function voce_error_log( $title, $error, $tags = array( ) ) {
		return Voce_Error_Logging::error_log( $title, $error, $tags );
	}

}


