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
	public const CACHE_GROUP                        = 'mime_types';
	public const AVAILABLE_MIME_TYPES_CACHE_KEY     = 'vip_available_mime_types_';
	public const USING_DEFAULT_MIME_TYPES_CACHE_KEY = 'vip_using_default_mime_types_';
	public const MAX_POSTS_TO_QUERY_DEFAULT         = 100000; // TODO: benchmark this value.

	/**
	 * Class initialization.
	 */
	public static function init() {
		global $wp_version;

		/**
		 * Filters whether the MIME type caching is enabled.
		 *
		 * @param bool $cache_mime_types Whether the MIME type caching is enabled. Default false.
		 */
		$vip_cache_mime_types = apply_filters( 'vip_cache_mime_types', true );

		if ( $vip_cache_mime_types &&
			isset( $wp_version ) &&
			version_compare( $wp_version, self::MINIMUM_WORDPRESS_VERSION, '>=' ) ) {
			self::enable_post_mime_types_caching();
		}
	}

	/**
	 * Enable MIME type caching.
	 */
	private static function enable_post_mime_types_caching() {
		add_filter( 'pre_get_available_post_mime_types', array( __CLASS__, 'get_cached_post_mime_types' ), 10, 2 );
		add_action( 'add_attachment', array( __CLASS__, 'update_post_mime_types_cache_on_add', 10, 1 ) );
		add_action( 'attachment_updated', array( __CLASS__, 'update_post_mime_types_cache_on_edit' ), 10, 3 );
		add_action( 'delete_attachment', array( __CLASS__, 'update_post_mime_types_cache_on_delete' ), 10, 2 );
	}

	/**
	 * Get cached results for get_available_post_mime_types() to avoid a query on every page load.
	 *
	 * @param string[]|null $filtered_mime_types An array of MIME types. Default null.
	 * @param string        $type                The post type name. Usually 'attachment' but can be any post type.
	 * @return array An array of MIME types.
	 */
	public static function get_cached_post_mime_types( $filtered_mime_types, $type ) {
		global $wpdb;

		$cache_key  = self::AVAILABLE_MIME_TYPES_CACHE_KEY . $type;
		$mime_types = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false === $mime_types ) {

			/**
			 * Filters the max number of posts to query for dynamic MIME type caching.
			 *
			 * @param bool $max_posts_to_query Max number of posts to query.
			 */
			$max_posts_to_query = apply_filters( 'vip_max_posts_to_query_for_mime_type_caching', self::MAX_POSTS_TO_QUERY_DEFAULT );

			$attachment_count = $max_posts_to_query > 0 ? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(1) FROM {$wpdb->posts} WHERE post_type = %s", $type ) ) : 1;
			$use_defaults     = $attachment_count > $max_posts_to_query;

			if ( $use_defaults ) {
				// If there are too many posts to query, use the default mime types.
				$mime_types = self::get_default_mime_types();
			} else {
				// Otherwise, use the same query from core.
				$mime_types = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT post_mime_type FROM $wpdb->posts WHERE post_type = %s", $type ) );
			}

			// Cache the results.
			wp_cache_set( $cache_key, $mime_types, self::CACHE_GROUP );
			wp_cache_set( self::USING_DEFAULT_MIME_TYPES_CACHE_KEY . $type, $use_defaults, self::CACHE_GROUP );
		}

		// If there were any previous MIME types, merge them with the cached MIME types.
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
	public static function get_default_mime_types() {
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
	public static function update_post_mime_types_cache_on_add( $post_id ) {
		$type = get_post_type( $post_id );

		if ( wp_cache_get( self::USING_DEFAULT_MIME_TYPES_CACHE_KEY . $type, self::CACHE_GROUP ) ) {
			return;
		}

		$mime_type = get_post_mime_type( $post_id );
		self::add_mime_type_to_cache( $mime_type, $type );
	}

	/**
	 * Update the MIME types cache when a post is edited.
	 *
	 * @param int     $post_id     The post ID.
	 * @param WP_Post $post_after  The post object after the update.
	 * @param WP_Post $post_before The post object before the update.
	 */
	public static function update_post_mime_types_cache_on_edit( $post_id, $post_after, $post_before ) {
		$old_mime_type = $post_before->post_mime_type;
		$old_post_type = $post_before->post_type;
		$new_mime_type = $post_after->post_mime_type;
		$new_post_type = $post_after->post_type;

		// Do nothing if the mime type didn't change.
		if ( $new_mime_type === $old_mime_type ) {
			return;
		}

		if ( ! wp_cache_get( self::USING_DEFAULT_MIME_TYPES_CACHE_KEY . $old_post_type, self::CACHE_GROUP ) ) {
			self::delete_mime_type_cache( $old_post_type );
		}

		if ( ! wp_cache_get( self::USING_DEFAULT_MIME_TYPES_CACHE_KEY . $new_post_type, self::CACHE_GROUP ) ) {
			self::add_mime_type_to_cache( $new_mime_type, $new_post_type );
		}
	}

	/**
	 * Update the MIME types cache when a post is deleted.
	 *
	 * @param int     $post_id The post ID.
	 * @param WP_Post $post    The post object.
	 */
	public static function update_post_mime_types_cache_on_delete( $post_id, $post ) {
		$type = $post->post_type;

		if ( wp_cache_get( self::USING_DEFAULT_MIME_TYPES_CACHE_KEY . $type, self::CACHE_GROUP ) ) {
			return;
		}

		self::delete_mime_type_cache( $type );
	}

	/**
	 * Add a MIME type to the cache.
	 *
	 * @param string $mime_type The MIME type to add.
	 * @param string $type      The post type name.
	 */
	private static function add_mime_type_to_cache( $mime_type, $type ) {
		if ( false !== $mime_type ) {
			$cache_key  = self::AVAILABLE_MIME_TYPES_CACHE_KEY . $type;
			$mime_types = wp_cache_get( $cache_key, self::CACHE_GROUP );

			if ( false !== $mime_types ) {
				// Add the new mime type to the cache if not present.
				if ( ! in_array( $mime_type, $mime_types, true ) ) {
					$mime_types[] = $mime_type;
					wp_cache_set( $cache_key, $mime_types, self::CACHE_GROUP );
				}
			}
		}
	}

	/**
	 * Delete the MIME type the cache.
	 *
	 * @param string $type The post type name.
	 */
	private static function delete_mime_type_cache( $type ) {
		$cache_key = self::AVAILABLE_MIME_TYPES_CACHE_KEY . $type;
		wp_cache_delete( $cache_key, self::CACHE_GROUP );
	}
}

add_action( 'init', array( 'Automattic\VIP\Performance\Media_Library_Caching', 'init' ) );
