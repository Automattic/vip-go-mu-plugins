<?php
/**
 * Tracks_Event class
 *
 * @package Parsely\Telemetry
 * @since 3.0.0
 */

declare(strict_types=1);

namespace Automattic\VIP\Parsely\Telemetry;

/**
 * Instances of this class are fit for recording to the Automattic Tracks system (unless an error occurs during instantiation).
 */
class Tracks_Event {
	/**
	 * Tracks Event Error.
	 * If this is set to a `WP_Error` instance, the event will not be tracked.
	 *
	 * @see Tracks::record_event
	 *
	 * @var mixed Error.
	 */
	public $error;

	/**
	 * Jetpack_Tracks_Event constructor.
	 *
	 * @param object $event Tracks event.
	 */
	public function __construct( $event ) {
		$_event = self::validate_and_sanitize( $event );
		if ( is_wp_error( $_event ) ) {
			$this->error = $_event;
			return;
		}

		foreach ( $_event as $key => $value ) {
			$this->{$key} = $value;
		}
	}

	/**
	 * Determine the user id and type from the environment.
	 *
	 * @param [object] $event The "midput" event object that needs identity information.
	 * @return [object] The "midput" event object including identity information (if present)
	 */
	protected static function annotate_with_id_and_type( $event ) {
		$wp_user_id = get_current_user_id();
		if ( ! $wp_user_id ) {
			// This lib is only for tracking logged in users, bail if we're not logged in.
			return $event;
		}

		if ( defined( 'VIP_GO_APP_ID' ) && VIP_GO_APP_ID ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.InterpolatedVariableNotSnakeCase
			$event->_ui = "${VIP_GO_APP_ID}_$wp_user_id";

			// TODO: _ut needs to be in the allowed list.
			$event->_ut = 'vip_go_app_wp_user';
			return $event;
		}

		$home_option = get_option( 'home' );
		if ( ! $home_option ) {
			return $event;
		}

		// TODO: This probably needs some improvement as well.
		$event->_ui = 'wpparsely:' . md5( "$home_option|$wp_user_id" );
		$event->_ut = 'anon';
		return $event;
	}

	/**
	 * Determine environment-specific props and include them in the event.
	 *
	 * @param [object] $event The "midput" event object that needs environment information.
	 * @return [object] The "midput" event object including identity environment (if present)
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
	 * @param  mixed $event Object or (flat) array.
	 * @return mixed        The transformed event array or WP_Error on failure.
	 */
	protected static function validate_and_sanitize( $event ) {
		// The rest of this process expects an object. Cast it!
		$_event = (object) $event;

		// Required.
		if ( ! $_event->_en ) {
			return new WP_Error( 'invalid_event', 'A valid event must be specified via `_en`', 400 );
		}

		// delete non-routable addresses otherwise geoip will discard the record entirely.
		if ( property_exists( $_event, '_via_ip' ) && preg_match( '/^192\.168|^10\./', $_event->_via_ip ) ) {
			unset( $_event->_via_ip );
		}

		// Make sure we have an event timestamp.
		if ( ! isset( $_event->_ts ) ) {
			$_event->_ts = self::build_timestamp();
		}

		$_event = self::annotate_with_id_and_type( $_event );

		if ( ! ( property_exists( $_event, '_ui' ) && property_exists( $_event, '_ut' ) ) ) {
			return new WP_Error( 'empty_ui', 'Could not determine user identity and type', 400 );
		}

		$_event = self::annotate_with_env_props( $_event );

		return $_event;
	}

	/**
	 * Builds a timestamp.
	 *
	 * Milliseconds since 1970-01-01.
	 *
	 * @return string
	 */
	protected static function build_timestamp(): string {
		$ts = round( microtime( true ) * 1000 );
		return number_format( $ts, 0, '', '' );
	}
}
