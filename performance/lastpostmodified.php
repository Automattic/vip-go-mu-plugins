<?php

namespace Automattic\VIP\Performance;

class Last_Post_Modified {
	const OPTION_PREFIX = 'wpcom_vip_lastpostmodified';
	const DEFAULT_TIMEZONE = 'gmt';

	public static function init() {
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
		if ( false === $stored_lastpostmodified ) {
			return $lastpostmodified;
		}

		return $stored_lastpostmodified;
	}

	public static function bump_lastpostmodified( $post ) {
		self::update_lastpostmodified( $post->post_modified_gmt, 'gmt', $post->post_type );
		self::update_lastpostmodified( $post->post_modified_gmt, 'server', $post->post_type );
		self::update_lastpostmodified( $post->post_modified, 'blog', $post->post_type );
	}

	public static function get_lastpostmodified( $timezone, $post_type ) {
		$option_name = self::get_option_name( $timezone, $post_type );
		return get_option( $option_name );
	}

	public static function update_lastpostmodified( $time, $timezone, $post_type ) {
		$option_name = self::get_option_name( $timezone, $post_type );
		return update_option( $option_name, $time );
	}

	private static function get_option_name( $timezone, $post_type ) {
		$timezone = strtolower( $timezone );
		return sprintf( '%s_%s_%s', self::OPTION_PREFIX, $timezone, $post_type );
	}
}

// TODO: make this opt-in to start
add_action( 'init', [ 'Automattic\VIP\Performance\Last_Post_Modified', 'init' ] );
