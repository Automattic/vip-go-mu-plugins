<?php
/**
 * Media library caching.
 * 
 * @package vip-performance
 */

 // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery

namespace Automattic\VIP\Performance;

/**
 * Media Library Caching class.
 */
class Media_Library_Caching {
	public const MINIMUM_WORDPRESS_VERSION          = '6.4';
	public const AVAILABLE_MIME_TYPES_CACHE_KEY     = 'vip_available_mime_types_';
	public const USING_DEFAULT_MIME_TYPES_CACHE_KEY = 'vip_using_default_mime_types_';
	public const MAX_POSTS_TO_QUERY                 = 100000;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		global $wp_version;

		if ( ! defined( 'VIP_DISABLE_MIME_TYPE_CACHING' ) &&
			isset( $wp_version ) &&
			version_compare( $wp_version, self::MINIMUM_WORDPRESS_VERSION, '>=' ) ) {
			$this->enable_post_mime_types_caching();
		}
	}

	/**
	 * Enable MIME type caching.
	 */
	private function enable_post_mime_types_caching() {
		add_filter( 'pre_get_available_post_mime_types', array( $this, 'get_cached_post_mime_types' ), 10, 2 );
		add_action( 'add_attachment', array( $this, 'update_post_mime_types_cache_on_add', 10, 1 ) );
		add_action( 'attachment_updated', array( $this, 'update_post_mime_types_cache_on_edit' ), 10, 3 );
		add_action( 'delete_attachment', array( $this, 'update_post_mime_types_cache_on_delete' ), 10, 3 );
	}

	/**
	 * Get cached results for get_available_post_mime_types() to avoid a query on every page load.
	 * 
	 * @param string[]|null $filtered_mime_types An array of MIME types. Default null.
	 * @param string        $type                The post type name. Usually 'attachment' but can be any post type.
	 * @return array An array of MIME types.
	 */
	public function get_cached_post_mime_types( $filtered_mime_types, $type ) {
		global $wpdb;

		$cache_key  = self::AVAILABLE_MIME_TYPES_CACHE_KEY . $type;
		$mime_types = wp_cache_get( $cache_key );

		if ( false === $mime_types ) {
			$attachment_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(1) FROM {$wpdb->posts} WHERE post_type = %s", $type ) );
			$use_defaults     = $attachment_count > self::MAX_POSTS_TO_QUERY;

			if ( $use_defaults ) {
				// If there are too many posts to query, use the default mime types.
				$mime_types = $this->get_default_mime_types();
			} else {
				// Otherwise, use the same query from core.
				$mime_types = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT post_mime_type FROM $wpdb->posts WHERE post_type = %s", $type ) );
			}

			// Cache the results.
			wp_cache_set( $cache_key, $mime_types );
			wp_cache_set( self::USING_DEFAULT_MIME_TYPES_CACHE_KEY . $type, $use_defaults );
		}

		// If there were any previous mime types, merge them with the cached mime types.
		if ( is_array( $filtered_mime_types ) ) {
			$mime_types = array_unique( array_merge( $filtered_mime_types, $mime_types ) );
		}

		return $mime_types;
	}

	/**
	 * Get the default MIME types.
	 * 
	 * @return string[] An array of default MIME types.
	 */
	public function get_default_mime_types() {
		// Massage the results from get_post_mime_types() into a flat array.
		return array_reduce(
			array_keys( get_post_mime_types() ),
			function ( $carry, $mime_type ) {
				return array_merge( $carry, explode( ',', $mime_type ) );
			},
			array()
		);
	}

	/**
	 * Update the MIME types cache when a new post is added.
	 * 
	 * @param int $post_id The post ID.
	 */
	public function update_post_mime_types_cache_on_add( $post_id ) {
		$type = get_post_type( $post_id );

		if ( wp_cache_get( self::USING_DEFAULT_MIME_TYPES_CACHE_KEY . $type ) ) {
			return;
		}

		$mime_type = get_post_mime_type( $post_id );
		$this->add_mime_type_to_cache( $mime_type, $type );
	}

	/**
	 * Update the MIME types cache when a post is edited.
	 * 
	 * @param int     $post_id     The post ID.
	 * @param WP_Post $post_after  The post object after the update.
	 * @param WP_Post $post_before The post object before the update.
	 */
	public function update_post_mime_types_cache_on_edit( $post_id, $post_after, $post_before ) {
		$old_mime_type = $post_before->post_mime_type;
		$old_post_type = $post_before->post_type;
		$new_mime_type = $post_after->post_mime_type;
		$new_post_type = $post_after->post_type;

		// Do nothing if the mime type didn't change.
		if ( $new_mime_type === $old_mime_type ) {
			return;
		}

		if ( ! wp_cache_get( self::USING_DEFAULT_MIME_TYPES_CACHE_KEY . $old_post_type ) ) {
			$this->remove_mime_type_from_cache( $old_mime_type, $old_post_type, $post_id );
		}
		
		if ( ! wp_cache_get( self::USING_DEFAULT_MIME_TYPES_CACHE_KEY . $new_post_type ) ) {
			$this->add_mime_type_to_cache( $new_mime_type, $new_post_type );
		}
	}

	/**
	 * Update the MIME types cache when a post is deleted.
	 * 
	 * @param int     $post_id The post ID.
	 * @param WP_Post $post    The post object.
	 */
	public function update_post_mime_types_cache_on_delete( $post_id, $post ) {
		$type = $post->post_type;

		if ( wp_cache_get( self::USING_DEFAULT_MIME_TYPES_CACHE_KEY . $type ) ) {
			return;
		}
		
		$mime_type = $post->post_mime_type;
		$this->remove_mime_type_from_cache( $mime_type, $type, $post_id );
	}

	/**
	 * Add a MIME type to the cache.
	 * 
	 * @param string $mime_type The mime type to add.
	 * @param string $type      The post type name.
	 */
	private function add_mime_type_to_cache( $mime_type, $type ) {
		if ( false !== $mime_type ) {
			$cache_key  = self::AVAILABLE_MIME_TYPES_CACHE_KEY . $type;
			$mime_types = wp_cache_get( $cache_key );
	
			if ( false !== $mime_types ) {
				// Add the new mime type to the cache if not present.
				if ( ! in_array( $mime_type, $mime_types, true ) ) {
					$mime_types[] = $mime_type;
					wp_cache_set( $cache_key, $mime_types );
				}
			}
		}
	}

	/**
	 * Remove a MIME type from the cache.
	 * 
	 * @param string   $mime_type       The mime type to remove.
	 * @param string   $type            The post type name.
	 * @param int|null $exclude_post_id The post ID to exclude from the query. Default null.
	 */
	private function remove_mime_type_from_cache( $mime_type, $type, $exclude_post_id = null ) {
		global $wpdb;

		if ( false !== $mime_type ) {
			$cache_key  = self::AVAILABLE_MIME_TYPES_CACHE_KEY . $type;
			$mime_types = wp_cache_get( $cache_key );

			if ( false !== $mime_types ) {
				// Check if there are any posts left with the mime type before removing it from the cache.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
				$count = $wpdb->get_col( $wpdb->prepare( "SELECT 1 FROM $wpdb->posts WHERE ID != %d AND post_type = %s AND post_mime_type = %s LIMIT 1", $exclude_post_id, $type, $mime_type ) );

				if ( $count < 1 ) {
					// Remove the mime type from the cache if present.
					if ( in_array( $mime_type, $mime_types, true ) ) {
						$mime_types = array_diff( $mime_types, array( $mime_type ) );
						wp_cache_set( $cache_key, $mime_types );
					}
				}
			}
		}
	}
}

new Media_Library_Caching();
