<?php

namespace Automattic\VIP\Utils;

class Alerts {
	/**
	 * The alerts service address
	 *
	 * @var string
	 */
	protected $service_address;

	/**
	 * The alerts service port
	 *
	 * @var string
	 */
	protected $service_port;

	/**
	 * Full path to the alerts service
	 *
	 * @var string
	 */
	protected $service_url;

	/**
	 * Instance of his Alerts class
	 *
	 * @var Alerts
	 */
	protected static $instance = null;

	/**
	 * Contructor
	 *
	 * Set to protected to prevent direct instantiation.
	 * Use Alerts::instance() to get an instance of Alerts
	 */
	protected function __construct() {
		if ( ! defined( 'ALERT_SERVICE_ADDRESS' ) || ! ALERT_SERVICE_ADDRESS ) {
			throw new Exception( 'Missing alerts service host configuration in ALERT_SERVICE_ADDRESS constant' );
		}

		if ( ! defined( 'ALERT_SERVICE_PORT' ) || ! ALERT_SERVICE_PORT ) {
			throw new Exception( 'Missing alerts service port configuration in ALERT_SERVICE_PORT constant' );
		}

		$this->service_address = ALERT_SERVICE_ADDRESS;
		$this->service_port = ALERT_SERVICE_PORT;
		$this->service_url = sprintf( 'http://%s:%s/v1.0/alerts', $this->service_address, $this->service_port );
	}

	/**
	 * Send the alert
	 *
	 * @param array $body The alert message body
	 *
	 * @return array Response details from wp_remote_post
	 */
	protected function send( $body ) {
		$fallback_error = new \WP_Error( 'alerts-send-failed', 'There was an error connecting to the alerts service' );

		$response = vip_safe_wp_remote_request( $this->service_url, $fallback_error, 3, 1, 10, [
			'method' => 'POST',
			'body' => json_encode( $body ),
		] );

		if ( is_wp_error( $response ) ) {
			throw new Exception( sprintf( 'Error sending alert: %s', $response->get_error_message() ) );
		}

		return $response;
	}

	/**
	 * Cache alert
	 *
	 * Used to throttle alerts requests
	 *
	 * @param $kind string Cache key
	 * @param $interval integer When the cache data should expire, in seconds
	 *
	 * @return bool
	 */
	protected function add_cache( $key, $expire ) {
		if ( function_exists( 'wp_cache_add' ) && function_exists( 'wp_cache_add_global_groups' ) ) {
			wp_cache_add_global_groups( [ 'irc-ratelimit' ] );

			return wp_cache_add( $key, 1, 'irc-ratelimit', $expire );
		}

		return true;
	}

	/**
	 * Validate IRC channel or user name
	 *
	 * @param $channel_or_user string
	 *
	 * @return string Validated and cleaned channel or user name
	 */
	protected function validate_channel_or_user( $channel_or_user ) {
		$channel_or_user = preg_replace( '/[^0-9a-z#@|.-]/', '', $channel_or_user );

		if ( ! $channel_or_user ) {
			throw new Exception( "Invalid \$channel_or_user: Alerts\:\:chat( '$channel_or_user' );" );
		}

		return $channel_or_user;
	}

	/**
	 * Validate alert message
	 *
	 * Ensure the message is a valid string
	 *
	 * @param $message mixed
	 *
	 * @return string Validated and trimmed message
	 */
	protected function validate_message( $message ) {
		if ( is_array( $message ) || is_object( $message ) ) {
			throw new Exception( "Invalid \$message: Alerts\:\:chat( " . print_r( $message, true ) . " );" );
		}

		$message = trim( $message );

		if ( ! $message ) {
			throw new Exception( "Invalid \$message: Alerts\:\:chat( '$message' );" );
		}

		return $message;
	}

	/**
	 * Validate Opsgenie details array
	 *
	 * @param $details array
	 *
	 * @return array
	 */
	protected function validate_details( $details) {
		$required_keys = [ 'alias', 'description', 'entity', 'priority', 'source' ];

		if ( ! is_array( $details ) ) {
			throw new Exception( "Invalid \$details: Alerts\:\:opsgenie( " . print_r( $details, true ) ." );" );
		}

		foreach( $details as $key => $value ) {
			if ( ! array_key_exists( $key, $required_keys ) ) {
				throw new Exception( "Invalid \$details: Alerts\:\:opsgenie( " . print_r( $details, true ) ." );" );
			}

			if ( ! $value ) {
				throw new Exception( "Invalid \$details: Alerts\:\:opsgenie( " . print_r( $details, true ) ." );" );
			}
		}

		return $details;
	}

	/**
	 * Get an instance of this Alerts class
	 *
	 * @return Alerts
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new Alerts();
		}

		return self::$instance;
	}

	/**
	 * Send a message to IRC
	 *
	 * $level can be an int of one of the following
	 * NONE = 0
	 * WARNING = 1
	 * ALERT = 2
	 * CRITICAL = 3
	 * RECOVERY = 4
	 * INFORMATION = 5
	 * SCALE = 6
	 *
	 * Example Usage
	 *
	 * Alerts::chat( '@testuser', 'test message' );			// send testuser a pm on IRC from "a8c"
	 * Alerts::chat( '@testuser', 'test message', 3 );	// send testuser a pm on IRC with level 'critical'
	 * Alerts::chat( 'testing', 'test message' );				// have "a8c" join #testing and say something
	 * Alerts::chat( 'testing', 'test message', 4 );		// have "a8c-test" join #testing and say something with level 'recovery'
	 *
	 * @param $target (string) Channel or Username.  Usernames prefixed with an @, channel optionally prefixed by #.
	 * @param $message (string) Message
	 * @param $level (int) Level The severity level of the message
	 * @param $kind string Cache slug
	 * @param $interval integer Interval in seconds between two messages sent from one DC
	 *
	 * @return bool	True if successful. Else, will return false
	 */
	public static function chat( $channel_or_user, $message, $level = 0, $kind = '', $interval = 0 ) {
		try {
			$alerts = self::instance();

			if ( $kind && $interval ) {
				if ( ! $alerts->add_cache( $kind, $interval ) ) {
					error_log( sprintf( 'Alert rate limited: chat( %s, %s, %s, %s, %s );', $channel_or_user, $message, $level, $kind, $interval ) );

					return false;
				}
			}

			$channel_or_user = $alerts->validate_channel_or_user( $channel_or_user );

			$message = $alerts->validate_message( $message );

			$body = [
				'channel' => $channel_or_user,
				'type'    => $level,
				'text'    => $message,
			];

			$alerts->send( $body );

			return true;
		} catch( Exception $e ) {
			error_log( $e->getMessage() );

			return false;
		}
	}

	/**
	 * Send an alert to Opsgenie
	 *
	 * @param $message string Opsgenie alert message
	 * @param $details array Array of opsgenie details values. Expected values: `alias`, `description`, `entity`, `priority`, `source`
	 * @param $kind string Cache slug
	 * @param $interval integer Interval in seconds between two messages sent from one DC
	 *
	 * @return bool	True if successful. Else, will return false
	 */
	public static function opsgenie( $message, $details, $kind = '', $interval = 0 ) {
		try {
			$alerts = self::instance();

			if ( $kind && $interval ) {
				if ( ! $alerts->add_cache( $kind, $interval ) ) {
					error_log( sprintf( 'Alert rate limited: opsgenie( %s, %s, %s, %s );', $message, print_r( $details, true ), $kind, $interval ) );

					return false;
				}
			}

			$message = $alerts->validate_message( $message );

			$details = $alerts->validate_details( $details );

			$details[ 'message' ] = $message;

			$body = [
				'ops_alert' => $details,
			];

			$alerts->send( $body );

			return true;
		} catch( Exception $e ) {
			error_log( $e->getMessage() );

			return false;
		}
	}
}
