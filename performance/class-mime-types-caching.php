<?php
/**
 * MIME Types Caching.
 *
 * @package vip-performance
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery

namespace Automattic\VIP\Performance;

/**
 * MIME Types Caching class.
 */
class Mime_Types_Caching {
	public const MINIMUM_WORDPRESS_VERSION  = '6.4';
	public const CACHE_GROUP                = 'mime_types';
	public const MIME_TYPES_CACHE_KEY       = 'vip_mime_types';
	public const MAX_POSTS_TO_QUERY_DEFAULT = 500000;

	/**
	 * Class initialization.
	 */
	public static function init() {
		global $wp_version;

		if ( isset( $wp_version ) &&
			version_compare( $wp_version, self::MINIMUM_WORDPRESS_VERSION, '>=' ) ) {
			static::enable_post_mime_types_caching();
		}
	}

	/**
	 * Enable MIME type caching.
	 */
	private static function enable_post_mime_types_caching() {
		add_filter( 'pre_get_available_post_mime_types', array( __CLASS__, 'get_cached_post_mime_types' ), 10, 2 );
		add_action( 'add_attachment', array( __CLASS__, 'add_mime_type_to_cache' ), 10, 1 );
		add_action( 'attachment_updated', array( __CLASS__, 'update_mime_types_cache' ), 10, 3 );
		add_action( 'delete_attachment', array( __CLASS__, 'delete_mime_types_cache' ), 10 );
	}

	/**
	 * Get cached results for get_available_post_mime_types() to avoid a query on every page load.
	 *
	 * @param string[]|null $filtered_mime_types An array of MIME types. Default null.
	 * @param string        $type                The post type name.
	 * @return array An array of MIME types.
	 */
	public static function get_cached_post_mime_types( $filtered_mime_types, $type ) {
		if ( 'attachment' !== $type ) {
			return $filtered_mime_types;
		}

		$mime_types = wp_cache_get( self::MIME_TYPES_CACHE_KEY, self::CACHE_GROUP );

		if ( false === $mime_types ) {

			/**
			 * Filters the max number of posts to query for dynamic MIME type caching.
			 *
			 * @param bool $max_posts_to_query Max number of posts to query.
			 */
			$max_posts_to_query = apply_filters( 'vip_max_posts_to_query_for_mime_type_caching', self::MAX_POSTS_TO_QUERY_DEFAULT );

			global $wpdb;
			$attachment_count = $max_posts_to_query > 0 ? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(1) FROM {$wpdb->posts} WHERE post_type = %s", $type ) ) : null;
			$use_defaults     = is_null( $attachment_count ) || $attachment_count > $max_posts_to_query;

			if ( $use_defaults ) {
				// If there are too many posts to query, use the default MIME types.
				$available_types = static::get_default_mime_types();
			} else {
				// Otherwise, use the same query from core.
				$available_types = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT post_mime_type FROM $wpdb->posts WHERE post_type = %s", $type ) );
			}

			$mime_types = array(
				'available_types' => $available_types,
				'using_defaults'  => $use_defaults,
			);

			wp_cache_set( self::MIME_TYPES_CACHE_KEY, $mime_types, self::CACHE_GROUP );
		} else {
			$available_types = $mime_types['available_types'] ?? null;
		}

		// If there were any previous MIME types, merge them with the cached MIME types.
		if ( is_array( $filtered_mime_types ) ) {
			$available_types = is_array( $available_types ) ? array_unique( array_merge( $filtered_mime_types, $available_types ) ) : $filtered_mime_types;
		}

		return $available_types;
	}

	/**
	 * Get the default MIME types.
	 *
	 * @return string[] An array of default MIME types.
	 */
	public static function get_default_mime_types() {
		return explode( ',', join( ',', array_keys( get_post_mime_types() ) ) );
	}

	/**
	 * Check if the default MIME types are being used.
	 *
	 * @param array|bool $mime_types MIME type data from cache.
	 * @return bool Whether the default MIME types are being used.
	 */
	public static function is_using_default_mime_types( $mime_types ) {
		return false === $mime_types || ( $mime_types['using_defaults'] ?? true );
	}

	/**
	 * Update the MIME types cache when a new attachment is added.
	 *
	 * @param int $post_id The post ID.
	 */
	public static function add_mime_type_to_cache( $post_id ) {
		$mime_types = wp_cache_get( self::MIME_TYPES_CACHE_KEY, self::CACHE_GROUP );

		if ( static::is_using_default_mime_types( $mime_types ) ) {
			return;
		}

		$mime_type       = get_post_mime_type( $post_id );
		$available_types = $mime_types['available_types'] ?? false;

		if ( false !== $mime_type &&
			is_array( $available_types ) &&
			! in_array( $mime_type, $available_types, true ) ) {
			// Add the new MIME type to the cache if not present.
			$mime_types['available_types'][] = $mime_type;
			wp_cache_set( self::MIME_TYPES_CACHE_KEY, $mime_types, self::CACHE_GROUP );
		}
	}

	/**
	 * Update the MIME types cache when an attachment is edited.
	 *
	 * @param int     $post_id     The post ID.
	 * @param WP_Post $post_after  The post object after the update.
	 * @param WP_Post $post_before The post object before the update.
	 */
	public static function update_mime_types_cache( $post_id, $post_after, $post_before ) {
		// Only if the MIME type changed.
		if ( $post_before->post_mime_type !== $post_after->post_mime_type ) {
			static::delete_mime_types_cache();
		}
	}

	/**
	 * Delete the MIME types cache when a post is deleted.
	 */
	public static function delete_mime_types_cache() {
		$mime_types = wp_cache_get( self::MIME_TYPES_CACHE_KEY, self::CACHE_GROUP );

		if ( ! static::is_using_default_mime_types( $mime_types ) ) {
			wp_cache_delete( self::MIME_TYPES_CACHE_KEY, self::CACHE_GROUP );
		}
	}
}
