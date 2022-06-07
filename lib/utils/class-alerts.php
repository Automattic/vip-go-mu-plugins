<?php

namespace Automattic\VIP\Utils;

use WP_Error;

// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_print_r

class Alerts {
	/**
	 * The alerts service address
	 *
	 * @var string
	 */
	public $service_address;

	/**
	 * The alerts service port
	 *
	 * @var string
	 */
	public $service_port;

	/**
	 * Full path to the alerts service
	 *
	 * @var string
	 */
	public $service_url;

	/**
	 * Instance of his Alerts class
	 *
	 * @var Alerts
	 */
	private static $instance = null;

	/**
	 * Contructor
	 *
	 * Set to protected to prevent direct instantiation.
	 * Use Alerts::instance() to get an instance of Alerts
	 */
	private function __construct() {
		// empty private constructor
	}

	/**
	 * Send the alert
	 *
	 * @param array $body The alert message body
	 *
	 * @return array|WP_Error Response details from wp_remote_post
	 */
	protected function send( array $body ) {
		$fallback_error = new WP_Error( 'alerts-send-failed', 'There was an error connecting to the alerts service' );

		$response = vip_safe_wp_remote_request( $this->service_url, $fallback_error, 3, 1, 10, [
			'method' => 'POST',
			'body'   => wp_json_encode( $body ),
		] );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'alert-send-failed', sprintf( 'Error sending alert: %s', $response->get_error_message() ) );
		}

		// The request got a response
		$code = wp_remote_retrieve_response_code( $response );
		if ( ! is_int( $code ) || $code >= 400 ) {
			return new WP_Error( 'alert-send-failed', sprintf( 'The request returned an invalid response: %s', $response['response']['message'] ) );
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
	private function add_cache( $key, $expire ) {
		if ( function_exists( 'wp_cache_add' ) && function_exists( 'wp_cache_add_global_groups' ) ) {
			wp_cache_add_global_groups( [ 'irc-ratelimit' ] );

			// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined -- it doesn't :-(
			return wp_cache_add( $key, 1, 'irc-ratelimit', $expire );
		}

		return true;
	}

	/**
	 * Validate IRC channel or user name
	 *
	 * @param $channel_or_user string
	 *
	 * @return string|WP_Error Validated and cleaned channel or user name or a WP_Error object
	 */
	protected function validate_channel_or_user( $channel_or_user ) {
		$channel_or_user = preg_replace( '/[^0-9a-z#@|.-]/', '', $channel_or_user );

		if ( ! $channel_or_user ) {
			return new WP_Error( 'invalid-channel-or-user', "Invalid \$channel_or_user: Alerts\:\:chat( '$channel_or_user' );" );
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
	 * @return string|WP_Error Validated and trimmed message or a WP_Error object
	 */
	protected function validate_message( $message ) {
		if ( ! is_string( $message ) || ! trim( $message ) ) {
			return new WP_Error( 'invalid-alert-message', 'Invalid $message: Alerts\:\:chat( ' . print_r( $message, true ) . ' );' );
		}

		return trim( $message );
	}

	/**
	 * Validate Opsgenie details array
	 *
	 * @param $details array
	 *
	 * @return array|WP_Error
	 */
	protected function validate_opsgenie_details( $details ) {
		$required_keys = [ 'alias', 'description', 'entity', 'priority', 'source' ];

		if ( ! is_array( $details ) ) {
			return new WP_Error( 'invalid-opsgenie-details', 'Invalid $details: Alerts\:\:opsgenie( ' . print_r( $details, true ) . ' );' );
		}

		foreach ( $details as $key => $value ) {
			if ( ! in_array( $key, $required_keys ) ) {
				return new WP_Error( 'invalid-opsgenie-details', 'Invalid $details: Alerts\:\:opsgenie( ' . print_r( $details, true ) . ' );' );
			}

			if ( ! $value ) {
				return new WP_Error( 'invalid-opsgenie-details', 'Invalid $details: Alerts\:\:opsgenie( ' . print_r( $details, true ) . ' );' );
			}
		}

		return $details;
	}

	/**
	 * Generate a default kind value for rate limiting
	 *
	 * Uses md5() to generate a hash of $key as the default kind value
	 *
	 * @param $key string String to generate the kind value from
	 *
	 * @return string The generated kind value
	 */
	private function generate_kind( $key ) {
		return md5( $key );
	}

	/**
	 * Send a message to IRC and/or Slack
	 *
	 * See Alerts::chat()
	 *
	 */
	public function send_to_chat( $channel_or_user, $message, $level = 0, $kind = '', $interval = 1 ) {
		if ( '' === $kind ) {
			// Generate default kind value
			$kind = $this->generate_kind( $channel_or_user . $message );
		}

		if ( ! $this->add_cache( $kind, $interval ) ) {
			error_log( sprintf( 'Alert rate limited: chat( %s, %s, %s, %s, %s );', $channel_or_user, $message, $level, $kind, $interval ) );

			return false;
		}

		$channel_or_user = $this->validate_channel_or_user( $channel_or_user );

		if ( is_wp_error( $channel_or_user ) ) {
			error_log( $channel_or_user->get_error_message() );

			return false;
		}

		$message = $this->validate_message( $message );

		if ( is_wp_error( $message ) ) {
			error_log( $message->get_error_message() );

			return false;
		}

		$body = [
			'channel' => $channel_or_user,
			'type'    => $level,
			'text'    => $message,
		];

		$response = $this->send( $body );

		if ( is_wp_error( $response ) ) {
			error_log( $response->get_error_message() );

			return false;
		}

		return true;
	}

	/**
	 * Send an alert to Opsgenie
	 *
	 * See Alerts::opsgenie()
	 */
	public function send_to_opsgenie( $message, $details, $kind = '', $interval = 1 ) {
		if ( '' === $kind ) {
			// Generate default kind value
			$kind = $this->generate_kind( $message . wp_json_encode( $details ) );
		}

		if ( ! $this->add_cache( $kind, $interval ) ) {
			error_log( sprintf( 'Alert rate limited: opsgenie( %s, %s, %s, %s );', $message, print_r( $details, true ), $kind, $interval ) );

			return false;
		}

		$message = $this->validate_message( $message );

		if ( is_wp_error( $message ) ) {
			error_log( $message->get_error_message() );

			return false;
		}

		$details = $this->validate_opsgenie_details( $details );

		if ( is_wp_error( $details ) ) {
			error_log( $details->get_error_message() );

			return false;
		}

		$details['message'] = $message;

		$body = [
			'ops_alert' => $details,
		];

		$response = $this->send( $body );

		if ( is_wp_error( $response ) ) {
			error_log( $response->get_error_message() );

			return false;
		}

		return true;
	}

	/**
	 * Get an instance of this Alerts class
	 *
	 * @return static|WP_Error
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			$alerts = new static();

			$service_address = static::get_service_address();
			if ( null === $service_address ) {
				return new WP_Error( 'missing-service-address', 'Missing alerts service host configuration in ALERT_SERVICE_ADDRESS constant' );
			}

			$service_port = static::get_service_port();
			if ( null === $service_port ) {
				return new WP_Error( 'missing-service-port', 'Missing alerts service port configuration in ALERT_SERVICE_PORT constant' );
			}

			if ( ! is_int( $service_port ) || $service_port > 65535 || $service_port < 1 ) {
				return new WP_Error( 'incorrect-service-port', 'Service port must be an integer value in the 1-65535 range.' );
			}

			$alerts->service_address = $service_address;
			$alerts->service_port    = $service_port;
			$alerts->service_url     = sprintf( 'http://%s:%s/v1.0/alert', $alerts->service_address, $alerts->service_port );

			self::$instance = $alerts;
		}

		return self::$instance;
	}

	protected static function clear_instance() {
		self::$instance = null;
	}

	protected static function get_service_address() {
		if ( ! defined( 'ALERT_SERVICE_ADDRESS' ) || ! ALERT_SERVICE_ADDRESS ) {
			return null;
		}

		return (string) ALERT_SERVICE_ADDRESS;
	}

	protected static function get_service_port() {
		if ( ! defined( 'ALERT_SERVICE_PORT' ) || ! ALERT_SERVICE_PORT ) {
			return null;
		}

		return ALERT_SERVICE_PORT;
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
	 * Alerts::chat( '@testuser', 'test message' );         // send testuser a pm on IRC from "a8c"
	 * Alerts::chat( '@testuser', 'test message', 3 );  // send testuser a pm on IRC with level 'critical'
	 * Alerts::chat( 'testing', 'test message' );               // have "a8c" join #testing and say something
	 * Alerts::chat( 'testing', 'test message', 4 );        // have "a8c-test" join #testing and say something with level 'recovery'
	 *
	 * @param $target (string) Channel or Username.  Usernames prefixed with an @, channel optionally prefixed by #.
	 * @param $message (string) Message
	 * @param $level (int) Level The severity level of the message
	 * @param $kind string Cache slug
	 * @param $interval integer Interval in seconds between two messages sent from one DC
	 *
	 * @return bool True if successful. Else, will return false
	 */
	public static function chat( $channel_or_user, $message, $level = 0, $kind = '', $interval = 1 ) {
		$alerts = self::instance();

		if ( is_wp_error( $alerts ) ) {
			error_log( $alerts->get_error_message() );

			return false;
		}


		return $alerts->send_to_chat( $channel_or_user, $message, $level, $kind, $interval );
	}

	/**
	 * Send an alert to Opsgenie
	 *
	 * @param $message string Opsgenie alert message
	 * @param $details array Array of opsgenie details values. Expected values: `alias`, `description`, `entity`, `priority`, `source`
	 * @param $kind string Cache slug
	 * @param $interval integer Interval in seconds between two messages sent from one DC
	 *
	 * @return bool True if successful. Else, will return false
	 */
	public static function opsgenie( $message, $details, $kind = '', $interval = 1 ) {
		$alerts = self::instance();

		if ( is_wp_error( $alerts ) ) {
			error_log( $alerts->get_error_message() );

			return false;
		}

		return $alerts->send_to_opsgenie( $message, $details, $kind, $interval );
	}
}
