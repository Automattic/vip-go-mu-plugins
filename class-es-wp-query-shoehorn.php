<?php

/**
 * Add the 'es' query var. This is a filter for "query_vars".
 *
 * @param  array $vars query vars.
 * @return array modified query vars.
 */
function es_wp_query_arg( $vars ) {
	$vars[] = 'es';
	return $vars;
}
add_filter( 'query_vars', 'es_wp_query_arg' );


/**
 * If a WP_Query object has `'es' => true`, use Elasticsearch to run the meat of the query.
 * This is fires on the "pre_get_posts" action.
 *
 * @param  object $query WP_Query object.
 * @return void
 */
function es_wp_query_shoehorn( &$query ) {
	if ( true == $query->get( 'es' ) ) {
		$conditionals = array(
			'is_single'            => false,
			'is_preview'           => false,
			'is_page'              => false,
			'is_archive'           => false,
			'is_date'              => false,
			'is_year'              => false,
			'is_month'             => false,
			'is_day'               => false,
			'is_time'              => false,
			'is_author'            => false,
			'is_category'          => false,
			'is_tag'               => false,
			'is_tax'               => false,
			'is_search'            => false,
			'is_feed'              => false,
			'is_comment_feed'      => false,
			'is_trackback'         => false,
			'is_home'              => false,
			'is_404'               => false,
			'is_comments_popup'    => false,
			'is_paged'             => false,
			'is_admin'             => false,
			'is_attachment'        => false,
			'is_singular'          => false,
			'is_robots'            => false,
			'is_posts_page'        => false,
			'is_post_type_archive' => false,
		);
		foreach ( $conditionals as $key => $value ) {
			$conditionals[ $key ] = $query->$key;
		}

		$es_query_vars = $query->query_vars;
		$query_args = $query->query;
		$es_query_vars['fields'] = 'ids';
		$es_query = new ES_WP_Query( $es_query_vars );

		$query->parse_query( array(
			'post_type'        => 'any',
			'post_status'      => 'any',
			'post__in'         => $es_query->posts,
			'posts_per_page'   => $es_query->post_count,
			'fields'           => $query->get( 'fields' ),
			'orderby'          => 'post__in',
			'order'            => 'ASC',
		) );
		# Reinsert all the conditionals from the original query
		foreach ( $conditionals as $key => $value ) {
			$query->$key = $value;
		}
		$shoehorn = new ES_WP_Query_Shoehorn( $query, $es_query, $query_args );
	}
}
add_action( 'pre_get_posts', 'es_wp_query_shoehorn', 1000 );


/**
 * Add an 'es' query var to WP_Query which offers seamless integration.
 *
 * It's worth noting the bug in https://core.trac.wordpress.org/ticket/21169,
 * as it could potentially play a role here. Each hook removes itself, and if
 * it were the only action or filter at that priority, and there was another at
 * a later priority (e.g. 1001), that one wouldn't fire.
 */
class ES_WP_Query_Shoehorn {

	private $hash;

	private $do_found_posts = true;

	private $post_count;

	private $found_posts;

	private $original_query_args;

	public function __construct( &$query, &$es_query, $query_args ) {
		$this->hash = spl_object_hash( $query );

		if ( $query->get( 'no_found_rows' ) || -1 == $query->get( 'posts_per_page' ) || true == $query->get( 'nopaging' ) ) {
			$this->do_found_posts = false;
		} else {
			$this->do_found_posts = true;
			$this->found_posts = $es_query->found_posts;
		}
		$this->post_count = $es_query->post_count;
		$this->original_query_args = $query_args;
		$this->add_query_hooks();
	}

	/**
	 * Add hooks to WP_Query to modify the bits that we're replacing.
	 *
	 * Each hook removes itself when it fires so this doesn't affect all WP_Query requests.
	 *
	 * @return void
	 */
	public function add_query_hooks() {
		if ( $this->post_count ) {
			# Kills the FOUND_ROWS() database query
			add_filter( 'found_posts_query', array( $this, 'filter__found_posts_query' ), 1000, 2 );
			# Since the FOUND_ROWS() query was killed, we need to supply the total number of found posts
			add_filter( 'found_posts', array( $this, 'filter__found_posts' ), 1000, 2 );
		}

		add_filter( 'posts_request', array( $this, 'filter__posts_request' ), 1000, 2 );
	}

	/**
	 * Kill the found_posts query if this was run with ES.
	 *
	 * @param string $sql SQL query to kill.
	 * @param object $query WP_Query object.
	 * @return string
	 */
	public function filter__found_posts_query( $sql, $query ) {
		if ( spl_object_hash( $query ) == $this->hash ) {
			remove_filter( 'found_posts_query', array( $this, 'filter__found_posts_query' ), 1000, 2 );
			if ( $this->do_found_posts ) {
				return '';
			}
		}
		return $sql;
	}

	/**
	 * If we killed the found_posts query, set the found posts via ES.
	 *
	 * @param int $found_posts The total number of posts found when running the query.
	 * @param object $query WP_Query object.
	 * @return int
	 */
	public function filter__found_posts( $found_posts, $query ) {
		if ( spl_object_hash( $query ) == $this->hash ) {
			remove_filter( 'found_posts', array( $this, 'filter__found_posts' ), 1000, 2 );
			if ( $this->do_found_posts ) {
				return $this->found_posts;
			}
		}
		return $found_posts;
	}

	/**
	 * IF the ES query didn't find any posts, use a query which returns no results.
	 *
	 * @param string $sql The SQL query to get posts.
	 * @param object $query WP_Query object.
	 * @return string The SQL query to get posts.
	 */
	public function filter__posts_request( $sql, &$query ) {
		if ( spl_object_hash( $query ) == $this->hash ) {
			remove_filter( 'posts_request', array( $this, 'filter__posts_request' ), 1000, 2 );
			$this->reboot_query_vars( $query );

			if ( ! $this->post_count ) {
				global $wpdb;
				return "SELECT * FROM {$wpdb->posts} WHERE 1=0 /* ES_WP_Query Shoehorn */";
			} else {
				return $sql . ' /* ES_WP_Query Shoehorn */';
			}
		}
		return $sql;
	}

	/**
	 * Restore query args/vars to their original glory. This allows us to run
	 * $query->get_posts() multiple times.
	 *
	 * @access private
	 *
	 * @param  object $query WP_Query object, passed by reference.
	 * @return void
	 */
	private function reboot_query_vars( &$query ) {
		$q =& $query->query_vars;

		$current_query_vars = $q;
		unset(
			$current_query_vars['post_type'],
			$current_query_vars['post_status'],
			$current_query_vars['post__in'],
			$current_query_vars['posts_per_page'],
			$current_query_vars['fields'],
			$current_query_vars['orderby'],
			$current_query_vars['order']
		);

		$q = $query->query = $this->original_query_args;
		$query->parse_query();
		$q = array_merge( $current_query_vars, $q );

		// Restore some necessary defaults if we zapped 'em
		if ( empty( $q['posts_per_page'] ) ) {
			$q['posts_per_page'] = get_option( 'posts_per_page' );
		}
	}
}