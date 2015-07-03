<?php

/**
 * Query helper used to manipulate the WP_Query for the Taxonomy Connection type handler
 * WP_Query Args:
 *   'o2o_query' array(
 *      'direction' : the side of the connection which the queried objects are pulled in, options are 'to' and 'from'
 * 			'id' : the ID of the object being used as the base of the connections
 *   )
 *   'orderby' => 'connection' : allows the connection to be ordered by the user set connection order if the connection type supports it
 */
class O2O_Query {

	protected $connection_factory;
	protected $initialized = false;

	public function __construct( $connection_factory ) {
		$this->connection_factory = $connection_factory;
	}

	public function init() {
		add_action( 'parse_query', array( $this, '_action_parse_query' ) );
		add_filter( 'posts_clauses', array( $this, '_filter_posts_clauses' ), 10, 2 );
		add_filter( 'posts_results', array( $this, '_filter_posts_results' ), 10, 2 );
	}
	
	public function deinit() {
		remove_action( 'parse_query', array( $this, '_action_parse_query' ) );
		remove_filter( 'posts_clauses', array( $this, '_filter_posts_clauses' ), 10 );
		remove_filter( 'posts_results', array( $this, '_filter_posts_results' ), 10 );
	}

	/**
	 * Filter run on posts to apply paging/reordering to the results.
	 * It sets a o2o_order_handled property on the WP_Query instance to prevent the
	 * filtering from happening a second time on posts_results
	 *
	 * $wp_query->set_found_posts() is re-run since it was previously run before this
	 * filter was able to update the counts
	 *
	 * @param array $posts
	 * @param WP_Query $wp_query
	 * @return array
	 */
	public function _filter_posts_results( $posts, $wp_query ) {
		if ( is_o2o_connection( $wp_query ) ) {

			$connection = $this->connection_factory->get_connection( $wp_query->o2o_connection );
			$query_modifier = $connection->get_query_modifier();

			call_user_func_array(array($query_modifier, 'posts_results'), array($posts, $wp_query, $connection, $wp_query->query_vars['o2o_query']));
		}
		return $posts;
	}

	/**
	 * Filters post query clauses based on the connection
	 * @param array $clauses
	 * @param WP_Query $wp_query
	 * @return array
	 */
	public function _filter_posts_clauses( $clauses, $wp_query ) {
		global $wpdb;
		if ( is_o2o_connection( $wp_query ) ) {
			$connection = $this->connection_factory->get_connection( $wp_query->o2o_connection );
			$o2o_query = $wp_query->query_vars['o2o_query'];

			//if we're doing custom order we need to expand the limits to include the complete set since limits can be done until after sorting
			if ( isset($wp_query->query_vars['o2o_orderby']) && $wp_query->query_vars['o2o_orderby'] == $connection->get_name() ) {
				$connected_ids = $o2o_query['direction'] == 'to' ? $connection->get_connected_to_objects( $o2o_query['id'] ) : $connection->get_connected_from_objects( $o2o_query['id'] );
				if(count($connected_ids) > 1) {
					$clauses['orderby'] = " find_in_set($wpdb->posts.ID, '" . implode(', ', $connected_ids) . "') ". $wp_query->query_vars['order'];
				}
			}
		}
		return $clauses;
	}

	/**
	 * Runs on the parse_query action to alter the WP_Query->query_vars
	 * based on any used connections in the query.  This standardizes the o2o_query
	 * args and makes any needed property changes to the WP_Query instance
	 * @param WP_Query $wp_query
	 *
	 * @todo add check that ID is valid post type
	 * @todo add handling for returning empty result for invalid/empty connection queries
	 */
	public function _action_parse_query( $wp_query ) {
		self::_transform_query_vars( $wp_query->query_vars );

		if ( isset( $wp_query->query_vars['o2o_query'] ) && is_array( $wp_query->query_vars['o2o_query'] ) && isset( $wp_query->query_vars['o2o_query']['connection'] ) ) {
			$o2o_query = &$wp_query->query_vars['o2o_query'];
			if ( $connection = $this->connection_factory->get_connection( $o2o_query['connection'] ) ) {
				$wp_query->o2o_connection = $o2o_query['connection'];

				$o2o_query = wp_parse_args( $o2o_query, array(
					'direction' => 'to',
					'id' => false
					) );

				if ( !in_array( $o2o_query['direction'], array( 'to', 'from' ) ) )
					$o2o_query['direction'] = 'to';

				//set the id if we don't have it
				if ( !$o2o_query['id'] && !empty( $o2o_query['post_name'] ) ) {
					$name_post_types = $o2o_query['direction'] == 'to' ? $connection->from() : $connection->to();
					$o2o_query['id'] = get_post_id_by_name($o2o_query['post_name'], $name_post_types);
				}

				//set the queried object
				if($wp_query->queried_object = get_post($o2o_query['id'])) {
					$wp_query->queried_object_id = $wp_query->queried_object->ID;
					$wp_query->is_home = false;
					$wp_query->is_archive = true;
				} else {
					//make it a 404
					$wp_query->is_404 = true;
				}



				//orderby handling
				if ( $wp_query->get( 'orderby' ) == $connection->get_name() ) {
					if ( $connection->is_sortable( $o2o_query['direction'] ) ) {
						//set temp query_var since WP_Query will override invalid orderby
						$wp_query->query_vars['o2o_orderby'] = $connection->get_name();
					} else {
						//this connection isn't user sortable
						unset( $wp_query->query_vars['orderby'] );
					}

					if ( !isset( $wp_query->query_vars['order'] ) ) {
						//default order to ASC for connection based ordering
						$wp_query->query_vars['order'] = 'ASC';
					}
				}

				if ( !isset( $wp_query->query_vars['post_type'] ) )
					$wp_query->query_vars['post_type'] = $o2o_query['direction'] == 'to' ? $connection->to() : $connection->from();

				$query_modifier = $connection->get_query_modifier();

				call_user_func_array(array($query_modifier, 'parse_query'), array($wp_query, $connection, $o2o_query));
			}
		}
	}

	/**
	 * Helper function to tranform less structured or older version of o2o query vars into
	 * the core version
	 * @param array $qv
	 */
	private static function _transform_query_vars( &$qv ) {
		$o2o_query = array();
		$arr = array(
			'connection_name' => 'connection',
			'connection_dir' => 'direction',
			'connected_id' => 'id',
			'connected_name' => 'post_name',
		);

		foreach($arr as $old_name => $new_name) {
			if(isset($qv[$old_name])) {
				$o2o_query[$new_name] = $qv[$old_name];
			}
		}

		if(count($o2o_query)) {
			$qv['o2o_query'] = $o2o_query;
		}
	}

}

function is_o2o_connection( $a_wp_query = null, $connection_name = null ) {
	global $wp_query;
	if ( is_null( $a_wp_query ) ) {
		$a_wp_query = $wp_query;
	}

	if ( !isset( $a_wp_query->o2o_connection ) ) {
		return false;
	}

	return is_null( $connection_name ) || $connection_name == $a_wp_query->o2o_connection;
}

if ( !function_exists( 'get_post_id_by_name' ) ) {

	/**
	 * @global DB $wpdb
	 * @param string $post_name
	 * @param string $post_type
	 * @return $post_id
	 */
	function get_post_id_by_name( $post_name, $post_types ) {
		global $wpdb;

		//clean up the post_name
		$post_name = rawurlencode( urldecode( $post_name ) );
		$post_name = str_replace( '%2F', '/', $post_name );
		$post_name = str_replace( '%20', ' ', $post_name );
		$post_name = array_pop( explode( '/', trim( $post_name, '/' ) ) );

		$post_types = array_map( 'esc_sql', (array) $post_types);

		sort($post_types); //put the post types in an order for cachekey purposes

		$cache_bucket_key = 'post_by_name_' . $post_name;
		$cache_key = substr(md5(serialize($post_types)), 0, 25);

		$cache_bucket = wp_cache_get($cache_bucket_key);
		if(!is_array( $cache_bucket)) {
			$cache_bucket = array();
		}

		$post_id = isset($cache_bucket[$cache_key]) ? $cache_bucket[$cache_key] : null;

		if(!$post_id) {
			$post_types_in = "('" . implode(', ', $post_types) . "')";

			$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_name = %s AND post_type in {$post_types_in} limit 1", $post_name ) );

			$cache_bucket[$cache_key] = $post_id;
			wp_cache_set($cache_bucket_key, $cache_bucket);
		}

		return $post_id;
	}
	/**
	 * Hook to delete cached post_names if a post changes it's name
	 */
	add_action('post_updated', function($post_id, $post_after, $post_before) {
		if(isset($post_before->post_name) && strlen( $post_before->post_name) > 0 && $post_after->post_name != $post_before->post_name){
			wp_cache_delete('post_by_name_' . $post_before->post_name);
			wp_cache_delete('post_by_name_' . $post_after->post_name);
		}
	}, 10, 3);

	/**
	 * Hook to delete cache post names when a new post is added with a name
	 */
	add_filter('wp_insert_post_data', function($data, $post_arr) {
		if(!isset($post_arr['ID']) && !empty($data['post_name'])) {
			wp_cache_delete('post_by_name', $data['post_name']);
		}
		return $data;
	}, 10, 2);


}

class O2O_Query_Modifier {
	public static function parse_query($wp_query, $connection, $o2o_query) {
		//set the post_ids based on the connection
		$connected_ids = $o2o_query['direction'] == 'to' ? $connection->get_connected_to_objects( $o2o_query['id'] ) : $connection->get_connected_from_objects( $o2o_query['id'] );

		if ( !empty($wp_query->query_vars['post__in'] ) ) {
			$post__in = array_map( 'absint', $wp_query->query_vars['post__in'] );
			$wp_query->query_vars['post__in'] = array_intersect( $connected_ids, $post__in );
		} elseif ( $wp_query->query_vars['post__not_in'] ) {
			$post__not_in = implode( ',', array_map( 'absint', $wp_query->query_vars['post__not_in'] ) );
			$wp_query->query_vars['post__in'] = array_diff( $connected_ids, $post__not_in );
			unset( $wp_query->query_vars['post__not_in'] );
		} else {
			$wp_query->query_vars['post__in'] = empty($connected_ids) ? array(0) : $connected_ids;
		}
	}

	public static function posts_results( $wp_query, $connection, $o2o_query ) {
		//do nothing
	}
}