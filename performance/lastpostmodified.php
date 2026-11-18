<?php

namespace Automattic\VIP\Performance;

class Last_Post_Modified {
	const OPTION_PREFIX    = 'wpcom_vip_lastpostmodified';
	const DEFAULT_TIMEZONE = 'gmt';

	public static function init() {
		$disable = defined( 'WP_RUN_CORE_TESTS' ) && WP_RUN_CORE_TESTS;
		if ( true === apply_filters( 'wpcom_vip_disable_lastpostmodified', $disable ) ) {
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

		do_action( 'wpcom_vip_bump_lastpostmodified', $post );
	}

	public static function override_get_lastpostmodified( $lastpostmodified, $timezone, $post_type ) {
		$stored_lastpostmodified = self::get_lastpostmodified( $timezone, $post_type );
		// Bumps used to store the post's modified date, which can be zero. A zero date makes feeds answer every request with a 304.
		if ( false === $stored_lastpostmodified || '0000-00-00 00:00:00' === $stored_lastpostmodified ) {
			return $lastpostmodified;
		}

		return $stored_lastpostmodified;
	}

	public static function bump_lastpostmodified( $post ) {
		// Feeds derive Last-Modified and ETag from this value, so record when the change happened. The post's own modified
		// date can be zero, or older than the stored value (a scheduled post keeps the one from when it was scheduled).
		// update_option() skips unchanged values, so further bumps in the same second don't write again.
		$time_gmt = current_time( 'mysql', true );
		$time     = current_time( 'mysql' );

		// Update the default of `any` and the value for the post type.
		foreach ( [ 'any', $post->post_type ] as $post_type ) {
			self::update_lastpostmodified( $time_gmt, 'gmt', $post_type );
			self::update_lastpostmodified( $time_gmt, 'server', $post_type );
			self::update_lastpostmodified( $time, 'blog', $post_type );
		}
	}

	public static function get_lastpostmodified( $timezone, $post_type ) {
		$option_name = self::get_option_name( $timezone, $post_type );
		return get_option( $option_name );
	}

	public static function update_lastpostmodified( $time, $timezone, $post_type = 'any' ) {
		$option_name = self::get_option_name( $timezone, $post_type );
		return update_option( $option_name, $time, false );
	}

	private static function get_option_name( $timezone, $post_type ) {
		$timezone = strtolower( $timezone );
		return sprintf( '%s_%s_%s', self::OPTION_PREFIX, $timezone, $post_type );
	}
}

add_action( 'init', [ 'Automattic\VIP\Performance\Last_Post_Modified', 'init' ] );
