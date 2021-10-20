<?php

namespace Automattic\VIP\Performance;

class Last_Post_Modified {
	const OPTION_PREFIX        = 'wpcom_vip_lastpostmodified';
	const DEFAULT_TIMEZONE     = 'gmt';
	const LOCK_TIME_IN_SECONDS = 30;

	public static function init() {
		if ( true === apply_filters( 'wpcom_vip_disable_lastpostmodified', false ) ) {
			return;
		}

		add_filter( 'pre_get_lastpostmodified', [ __CLASS__, 'override_get_lastpostmodified' ], 10, 3 );
		add_action( 'transition_post_status', [ __CLASS__, 'handle_post_transition' ], 10, 3 );
		add_action( 'wpcom_vip_bump_lastpostmodified', [ __CLASS__, 'bump_lastpostmodified' ] );
	}

	public static function handle_post_transition( $new_status, $old_status, $post ) {
		if ( ! in_array( 'publish', array( $old_status, $new_status ) ) ) {
			return;
		}

		$public_post_types = get_post_types( array( 'public' => true ) );
		if ( ! in_array( $post->post_type, $public_post_types ) ) {
			return;
		}

		$is_locked = self::is_locked( $post->post_type );
		if ( $is_locked ) {
			return;
		}

		do_action( 'wpcom_vip_bump_lastpostmodified', $post );
	}

	public static function override_get_lastpostmodified( $lastpostmodified, $timezone, $post_type ) {
		$stored_lastpostmodified = self::get_lastpostmodified( $timezone, $post_type );
		if ( false === $stored_lastpostmodified ) {
			return $lastpostmodified;
		}

		return $stored_lastpostmodified;
	}

	public static function bump_lastpostmodified( $post ) {
		// Update default of `any`
		self::update_lastpostmodified( $post->post_modified_gmt, 'gmt' );
		self::update_lastpostmodified( $post->post_modified_gmt, 'server' );
		self::update_lastpostmodified( $post->post_modified, 'blog' );

		// Update value for post_type
		self::update_lastpostmodified( $post->post_modified_gmt, 'gmt', $post->post_type );
		self::update_lastpostmodified( $post->post_modified_gmt, 'server', $post->post_type );
		self::update_lastpostmodified( $post->post_modified, 'blog', $post->post_type );
	}

	public static function get_lastpostmodified( $timezone, $post_type ) {
		$option_name = self::get_option_name( $timezone, $post_type );
		return get_option( $option_name );
	}

	public static function update_lastpostmodified( $time, $timezone, $post_type = 'any' ) {
		$option_name = self::get_option_name( $timezone, $post_type );
		return update_option( $option_name, $time, false );
	}

	private static function is_locked( $post_type ) {
		$key = self::get_lock_name( $post_type );
		// if the add fails, then we already have a lock set
		// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined
		return false === wp_cache_add( $key, 1, false, self::LOCK_TIME_IN_SECONDS );
	}

	private static function get_lock_name( $post_type ) {
		return sprintf( '%s_%s_lock', self::OPTION_PREFIX, $post_type );
	}

	private static function get_option_name( $timezone, $post_type ) {
		$timezone = strtolower( $timezone );
		return sprintf( '%s_%s_%s', self::OPTION_PREFIX, $timezone, $post_type );
	}
}

add_action( 'init', [ 'Automattic\VIP\Performance\Last_Post_Modified', 'init' ] );
