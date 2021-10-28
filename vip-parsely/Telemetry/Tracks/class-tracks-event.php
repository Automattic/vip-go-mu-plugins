<?php
/**
 * Tracks_Event class
 *
 * @package Automattic\VIP\Parsely\Telemetry
 */

declare(strict_types=1);

namespace Automattic\VIP\Parsely\Telemetry;

use WP_Error;

/**
 * Instances of this class are fit for recording to the Automattic Tracks system (unless an error occurs during instantiation).
 */
class Tracks_Event {
	/**
	 * The object containing the event itself.
	 *
	 * @var object|WP_Error Event.
	 */
	public $data;

	/**
	 * Jetpack_Tracks_Event constructor.
	 *
	 * @param array $event Tracks event.
	 */
	public function __construct( array $event ) {
		$this->data = self::validate_and_sanitize( $event );
	}

	/**
	 * Determine the user id and type from the environment.
	 *
	 * @param object $event The "midput" event object that needs identity information.
	 * @return object The "midput" event object including identity information (if present)
	 */
	protected static function annotate_with_id_and_type( $event ) {
		$wp_user_id = get_current_user_id();
		if ( ! $wp_user_id ) {
			// This lib is only for tracking logged in users, bail if we're not logged in.
			return $event;
		}

		if ( defined( 'VIP_GO_APP_ID' ) && VIP_GO_APP_ID ) {
			$event->_ui = VIP_GO_APP_ID . '_' . $wp_user_id;
			$event->_ut = 'vip_go_app_wp_user';
			return $event;
		}

		$home_option = get_option( 'home' );
		if ( ! $home_option ) {
			return $event;
		}

		// _ui stands for User ID in A8c Tracks.
		$event->_ui = 'wpparsely:' . md5( "$home_option|$wp_user_id" );

		// _ut stands for User Type in A8c Tracks.
		$event->_ut = 'anon';

		return $event;
	}

	/**
	 * Determine environment-specific props and include them in the event.
	 *
	 * @param object $event The "midput" event object that needs environment information.
	 * @return object The "midput" event object including identity environment (if present)
	 */
	protected static function annotate_with_env_props( $event ) {
		if ( defined( 'VIP_GO_ENV' ) && VIP_GO_ENV ) {
			$event->vipgo_env = VIP_GO_ENV;
		}
		return $event;
	}

	/**
	 * Annotate the event with all relevant info.
	 *
	 * @param  array $event_data Object or (flat) array.
	 *
	 * @return mixed        The transformed event array or WP_Error on failure.
	 */
	protected static function validate_and_sanitize( array $event_data ) {
		// The rest of this process expects an object. Cast it!
		$event = (object) $event_data;

		// _en stands for Event Name in A8c tracks. It is required.
		if ( ! $event->_en ) {
			return new WP_Error( 'invalid_event', 'A valid event must be specified via `_en`', 400 );
		}

		// delete non-routable addresses otherwise geoip will discard the record entirely.
		if ( property_exists( $event, '_via_ip' ) && preg_match( '/^192\.168|^10\./', $event->_via_ip ) ) {
			unset( $event->_via_ip );
		}

		// Make sure we have an event timestamp.
		if ( ! isset( $event->_ts ) ) {
			$event->_ts = self::milliseconds_since_epoch();
		}

		$event = self::annotate_with_id_and_type( $event );

		if ( ! ( property_exists( $event, '_ui' ) && property_exists( $event, '_ut' ) ) ) {
			return new WP_Error( 'empty_ui', 'Could not determine user identity and type', 400 );
		}

		return self::annotate_with_env_props( $event );
	}

	/**
	 * Builds a timestamp.
	 *
	 * Milliseconds since 1970-01-01.
	 *
	 * @return string
	 */
	protected static function milliseconds_since_epoch(): string {
		$ts = round( microtime( true ) * 1000 );
		return number_format( $ts, 0, '', '' );
	}
}
