<?php

namespace Automattic\VIP\Logstash;

/**
 * VIP Logstash Logger.
 *
 * @since 2020-01-10
 */
class Logger {
	/**
	 * Log entries.
	 *
	 * @since 2020-01-10
	 *
	 * @var array
	 */
	protected static $entries = [];

	/**
	 * Processed entries?
	 *
	 * @since 2020-01-10
	 *
	 * @var bool
	 */
	protected static $processed_entries = false;

	/**
	 * Maximum log entries.
	 *
	 * @since 2020-01-10
	 *
	 * @var int
	 */
	protected const MAX_ENTRIES = 30;

	/**
	 * Maximum entries to send in one API request.
	 *
	 * @since 2020-01-10
	 *
	 * @var int
	 */
	protected const BULK_ENTRIES_COUNT = 10;

	/**
	 * Maximum log entry host size.
	 *
	 * @since 2020-01-10
	 *
	 * @var int 255 bytes.
	 */
	protected const MAX_ENTRY_HOST_SIZE = 255;

	/**
	 * Maximum log entry feature size.
	 *
	 * @since 2020-01-10
	 *
	 * @var int 200 bytes.
	 */
	protected const MAX_ENTRY_FEATURE_SIZE = 200;

	/**
	 * Maximum log entry message size.
	 *
	 * @since 2020-01-10
	 *
	 * @var int 256kbs.
	 */
	protected const MAX_ENTRY_MESSAGE_SIZE = 262144;

	/**
	 * Maximum log entry user UA size.
	 *
	 * @since 2020-01-10
	 *
	 * @var int 255 bytes.
	 */
	protected const MAX_ENTRY_USER_UA_SIZE = 255;

	/**
	 * Maximum log entry extra (data) size.
	 *
	 * @since 2020-01-10
	 *
	 * @var int 256kbs.
	 */
	protected const MAX_ENTRY_EXTRA_SIZE = 262144;

	/**
	 * Add a log entry.
	 *
	 * @since 2020-01-10
	 *
	 * @param string $level   One of:
	 *     - 'emergency': System is unusable.
	 *     - 'alert'    : Action must be taken immediately.
	 *     - 'critical' : Critical conditions.
	 *     - 'error'    : Error conditions.
	 *     - 'warning'  : Warning conditions.
	 *     - 'notice'   : Normal but significant condition.
	 *     - 'info'     : Informational messages.
	 *     - 'debug'    : Debug-level messages.
	 *
	 * @param string $feature Log feature; e.g., `my_feature_slug`.
	 * @param string $message Log message; e.g., `My log message.`.
	 * @param array  $context Optional. Additional information for log handlers.
	 */
	public function log( string $level, string $feature, string $message, array $context = [] ) : void {
		static::log2logstash( [
			'severity' => $level,
			'feature'  => $feature,
			'message'  => $message,
			'extra'    => $context,
		] );
	}

	/**
	 * Adds an emergency level message.
	 *
	 * @since 2020-01-10
	 *
	 * @param string $feature Log feature; e.g., `my_feature_slug`.
	 * @param string $message Log message; e.g., `My emergency message.`.
	 * @param array  $context Optional. Additional information for log handlers.
	 */
	public function emergency( string $feature, string $message, array $context = [] ) : void {
		$this->log( __FUNCTION__, $feature, $message, $context );
	}

	/**
	 * Adds an alert level message.
	 *
	 * @since 2020-01-10
	 *
	 * @param string $feature Log feature; e.g., `my_feature_slug`.
	 * @param string $message Log message; e.g., `My alert message.`.
	 * @param array  $context Optional. Additional information for log handlers.
	 */
	public function alert( string $feature, string $message, array $context = [] ) : void {
		$this->log( __FUNCTION__, $feature, $message, $context );
	}

	/**
	 * Adds a critical level message.
	 *
	 * @since 2020-01-10
	 *
	 * @param string $feature Log feature; e.g., `my_feature_slug`.
	 * @param string $message Log message; e.g., `My critical message.`.
	 * @param array  $context Optional. Additional information for log handlers.
	 */
	public function critical( string $feature, string $message, array $context = [] ) : void {
		$this->log( __FUNCTION__, $feature, $message, $context );
	}

	/**
	 * Adds an error level message.
	 *
	 * @since 2020-01-10
	 *
	 * @param string $feature Log feature; e.g., `my_feature_slug`.
	 * @param string $message Log message; e.g., `My error message.`.
	 * @param array  $context Optional. Additional information for log handlers.
	 */
	public function error( string $feature, string $message, array $context = [] ) : void {
		$this->log( __FUNCTION__, $feature, $message, $context );
	}

	/**
	 * Adds a warning level message.
	 *
	 * @since 2020-01-10
	 *
	 * @param string $feature Log feature; e.g., `my_feature_slug`.
	 * @param string $message Log message; e.g., `My warning message.`.
	 * @param array  $context Optional. Additional information for log handlers.
	 */
	public function warning( string $feature, string $message, array $context = [] ) : void {
		$this->log( __FUNCTION__, $feature, $message, $context );
	}

	/**
	 * Adds a notice level message.
	 *
	 * @since 2020-01-10
	 *
	 * @param string $feature Log feature; e.g., `my_feature_slug`.
	 * @param string $message Log message; e.g., `My notice message.`.
	 * @param array  $context Optional. Additional information for log handlers.
	 */
	public function notice( string $feature, string $message, array $context = [] ) : void {
		$this->log( __FUNCTION__, $feature, $message, $context );
	}

	/**
	 * Adds a info level message.
	 *
	 * @since 2020-01-10
	 *
	 * @param string $feature Log feature; e.g., `my_feature_slug`.
	 * @param string $message Log message; e.g., `My info message.`.
	 * @param array  $context Optional. Additional information for log handlers.
	 */
	public function info( string $feature, string $message, array $context = [] ) : void {
		$this->log( __FUNCTION__, $feature, $message, $context );
	}

	/**
	 * Adds a debug level message.
	 *
	 * @since 2020-01-10
	 *
	 * @param string $feature Log feature; e.g., `my_feature_slug`.
	 * @param string $message Log message; e.g., `My debug message.`.
	 * @param array  $context Optional. Additional information for log handlers.
	 */
	public function debug( string $feature, string $message, array $context = [] ) : void {
		$this->log( __FUNCTION__, $feature, $message, $context );
	}

	/**
	 * Adds a new log entry, which is processed later on shutdown.
	 *
	 * @since 2020-01-10
	 *
	 * @param array $data An associative array of data to logstash.
	 *                    Note: `feature` and `message` are required keys.
	 *
	 * @internal          Typically, you'll want to pass in the following keys:
	 *                    - `severity` : Optional. ``, `emergency`, `alert`, `critical`, `error`, `warning`, `notice`, `info`, `debug`. Default is ``.
	 *                    - `feature`  : Required. A feature slug; e.g., `my_feature_slug`.
	 *                    - `message`  : Required. A log message; e.g, `My log message.`.
	 *                    - `extra`    : Optional. Any array, object, or scalar value. Default is `[]`.
	 *
	 * @internal          The following blog-specific keys are set for you automatically:
	 *                    - `site_id` : Required. Default is current network ID.
	 *                    - `blog_id` : Required. Default is current blog ID.
	 *                    - `host`    : Optional. Default is current hostname.
	 *
	 * @internal          The following user-specific keys are set for you automatically:
	 *                    - `user_id` : Optional. Default is current user ID.
	 *                    - `user_ua` : Optional. Default is current user-agent.
	 */
	public static function log2logstash( array $data ) : void {
		// Prepare data.
		$default_data = [
			'site_id'  => get_current_network_id(),                  // Required.
			'blog_id'  => get_current_blog_id(),                     // Required.
			'host'     => strtolower( $_SERVER['HTTP_HOST'] ?? '' ), // phpcs:ignore -- Optional.

			'severity' => '',                                        // Optional.
			'feature'  => '',                                        // Required.
			'message'  => '',                                        // Required.

			'user_id'  => get_current_user_id(),                     // Optional.
			'user_ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',          // phpcs:ignore -- Optional.

			'extra'    => [],                                        // Optional.
		];
		$data         = array_merge( $default_data, $data );
		$data         = array_intersect_key( $data, $default_data );

		// Data validations.
		// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
		if ( count( static::$entries ) + 1 > static::MAX_ENTRIES ) {
			trigger_error( 'Excessive calls to ' . esc_html( __METHOD__ ) . '(). Maximum is ' . esc_html( static::MAX_ENTRIES ) . ' log entries.', E_USER_WARNING );
			return; // Failed validation.

		} elseif ( empty( $data['site_id'] ) || ! is_int( $data['site_id'] ) || $data['site_id'] <= 0 ) {
			trigger_error( 'Invalid `site_id` in call to ' . esc_html( __METHOD__ ) . '(). Must be an integer > 0.', E_USER_WARNING );
			return; // Failed validation.

		} elseif ( empty( $data['blog_id'] ) || ! is_int( $data['blog_id'] ) || $data['blog_id'] <= 0 ) {
			trigger_error( 'Invalid `blog_id` in call to ' . esc_html( __METHOD__ ) . '(). Must be an integer > 0.', E_USER_WARNING );
			return; // Failed validation.

		} elseif ( isset( $data['host'] ) && ! is_string( $data['host'] ) ) {
			trigger_error( 'Invalid `host` in call to ' . esc_html( __METHOD__ ) . '(). Must be a string.', E_USER_WARNING );
			return; // Failed validation.

		} elseif ( isset( $data['host'] ) && strlen( $data['host'] ) > static::MAX_ENTRY_HOST_SIZE ) {
			trigger_error( 'Invalid `host` in call to ' . esc_html( __METHOD__ ) . '(). Must be ' . esc_html( static::MAX_ENTRY_HOST_SIZE ) . ' bytes or less.', E_USER_WARNING );
			return; // Failed validation.

		} elseif ( isset( $data['severity'] ) && ! in_array( $data['severity'], [ '', 'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug' ], true ) ) {
			trigger_error( 'Invalid `severity` in call to ' . esc_html( __METHOD__ ) . '(). Must be one of: ``, `emergency`, `alert`, `critical`, `error`, `warning`, `notice`, `info`, `debug`.', E_USER_WARNING );
			return; // Failed validation.

		} elseif ( empty( $data['feature'] ) || ! is_string( $data['feature'] ) ) {
			trigger_error( 'Missing required `feature` in call to ' . esc_html( __METHOD__ ) . '(). Must be a string.', E_USER_WARNING );
			return; // Failed validation.

		} elseif ( strlen( $data['feature'] ) > static::MAX_ENTRY_FEATURE_SIZE ) {
			trigger_error( 'Invalid `feature` in call to ' . esc_html( __METHOD__ ) . '(). Must be ' . esc_html( static::MAX_ENTRY_FEATURE_SIZE ) . ' bytes or less.', E_USER_WARNING );
			return; // Failed validation.

		} elseif ( empty( $data['message'] ) || ! is_string( $data['message'] ) ) {
			trigger_error( 'Missing required `message` in call to ' . esc_html( __METHOD__ ) . '(). Must be a string.', E_USER_WARNING );
			return; // Failed validation.

		} elseif ( strlen( $data['message'] ) > static::MAX_ENTRY_MESSAGE_SIZE ) {
			trigger_error( 'Invalid `message` in call to ' . esc_html( __METHOD__ ) . '(). Must be ' . esc_html( static::MAX_ENTRY_MESSAGE_SIZE ) . ' bytes or less.', E_USER_WARNING );
			return; // Failed validation.

		} elseif ( isset( $data['user_id'] ) && ! is_int( $data['user_id'] ) || $data['user_id'] < 0 ) {
			trigger_error( 'Invalid `user_id` in call to ' . esc_html( __METHOD__ ) . '(). Must be an integer >= 0.', E_USER_WARNING );
			return; // Failed validation.

		} elseif ( isset( $data['user_ua'] ) && ! is_string( $data['user_ua'] ) ) {
			trigger_error( 'Invalid `user_ua` in call to ' . esc_html( __METHOD__ ) . '(). Must be a string.', E_USER_WARNING );
			return; // Failed validation.

		} elseif ( isset( $data['user_ua'] ) && strlen( $data['user_ua'] ) > static::MAX_ENTRY_USER_UA_SIZE ) {
			trigger_error( 'Invalid `user_ua` in call to ' . esc_html( __METHOD__ ) . '(). Must be ' . esc_html( static::MAX_ENTRY_USER_UA_SIZE ) . ' bytes or less.', E_USER_WARNING );
			return; // Failed validation.

		} elseif ( isset( $data['extra'] ) && ! is_array( $data['extra'] ) && ! is_object( $data['extra'] ) && ! is_scalar( $data['extra'] ) ) {
			trigger_error( 'Invalid `extra` in call to ' . esc_html( __METHOD__ ) . '(). Must be an object, array, or scalar value.', E_USER_WARNING );
			return; // Failed validation.

		} elseif ( isset( $data['extra'] ) && strlen( wp_json_encode( $data['extra'] ) ) > static::MAX_ENTRY_EXTRA_SIZE ) {
			trigger_error( 'Invalid `extra` in call to ' . esc_html( __METHOD__ ) . '(). Must be ' . esc_html( static::MAX_ENTRY_EXTRA_SIZE ) . ' bytes or less.', E_USER_WARNING );
			return; // Failed validation.
		}

		// Adjust `feature` and JSON-encode pretty-print data.
		$data['feature']        = 'a8c_vip_' . $data['feature'];

		// Adjust `extra` and JSON-encode data that gets sent in the API request.
		$data['extra'] = isset( $data['extra'] ) ? wp_json_encode( $data['extra'], JSON_PRETTY_PRINT ) : '';

		// Adds a new log entry.
		static::$entries[] = $data;

		// Sends data to logstash on shutdown.
		if ( ! has_action( 'shutdown', [ static::class, 'process_entries_on_shutdown' ] ) ) {
			add_action( 'shutdown', [ static::class, 'process_entries_on_shutdown' ] );
		}
	}

	/**
	 * Sends data to logstash via REST API on shutdown, viewable in Kibana.
	 *
	 * @since 2020-01-10
	 */
	public static function process_entries_on_shutdown() : void {
		if ( static::$processed_entries ) {
			return; // Already done.
		}

		$fallback_error = new \WP_Error( 'logstash-send-failed', 'There was an error connecting to the logstash endpoint' );

		static::$processed_entries = true;
		$endpoint = 'https://public-api.wordpress.com/rest/v1.1/logstash/bulk';

		$entry_chunks = array_chunk( static::$entries, static::BULK_ENTRIES_COUNT );

		// Process all entries.
		foreach ( $entry_chunks as $entries ) {
			if ( ! defined( 'VIP_GO_ENV' ) || ! VIP_GO_ENV ) {
				static::maybe_wp_debug_log_entries( $entries );
				continue; // Bypassing REST API below in this case.
			}

			$json_data = wp_json_encode( $entries );

			// Send to logstash via REST API endpoint with payload containing log entry details.
			$_wp_remote_response = vip_safe_wp_remote_request( $endpoint, $fallback_error, 3, 2, 5, [
					'method' => 'POST',
					'redirection' => 0,
					'blocking' => false,
					'body' => [
						'params' => $json_data,
					],
				] );

			$_wp_remote_response_code    = wp_remote_retrieve_response_code( $_wp_remote_response );
			$_wp_remote_response_message = wp_remote_retrieve_response_message( $_wp_remote_response );

			if ( 200 !== $_wp_remote_response_code ) {
				static::maybe_wp_debug_log_entries( $entries );
				trigger_error( 'Unable to ' . esc_html( __METHOD__ ) . '(). Response from <' . esc_html( $endpoint ) . '> was [' . esc_html( $_wp_remote_response_code ) . ']: ' . esc_html( $_wp_remote_response_message ) . '.', E_USER_WARNING );
			}
		}
	}

	/**
	 * Sends data to `WP_DEBUG_LOG` when applicable.
	 *
	 * @since 2020-01-10
	 *
	 * @param array $entries.
	 */
	public static function maybe_wp_debug_log_entries( array $entries ) : void {
		if ( ! apply_filters( 'enable_wp_debug_mode_checks', true ) ) {
			return; // Not applicable.
		} elseif ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return; // Not applicable.
		}

		foreach ( $entries as $entry ) {
			static::wp_debug_log( $entry );
		}
	}

	/**
	 * Save message to log file
	 *
	 * @since 2020-01-10
	 *
	 * @param array $entry Data.
	 */
	public static function wp_debug_log( array $entry ) : void {
		if ( ! defined( 'VIP_GO_ENV' ) || ! VIP_GO_ENV ) {
			// Don't run this on VIP Go
			return;
		}

		$log_path = WP_CONTENT_DIR . '/debug.log';
		$log_path = is_string( WP_DEBUG_LOG ) && WP_DEBUG_LOG ? WP_DEBUG_LOG : $log_path;

		if ( $log_path && ( ( file_exists( $log_path ) && is_writable( $log_path ) ) || ( ! file_exists( $log_path ) && is_writable( dirname( $log_path ) ) ) ) ) {
			file_put_contents( // phpcs:ignore -- `file_put_contents()` ok.
				$log_path,
				__CLASS__ . ': ' . wp_json_encode( $entry, JSON_PRETTY_PRINT ) . "\n",
				FILE_APPEND
			);
		}
	}
}
