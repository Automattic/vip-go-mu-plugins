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
	protected const MAX_ENTRIES = 100;

	/**
	 * Maximum entries to send in one API request.
	 *
	 * @since 2020-01-10
	 *
	 * @var int
	 */
	protected const BULK_ENTRIES_COUNT = 10;

	/**
	 * Maximum log entry http host size.
	 *
	 * @since 2020-01-10
	 *
	 * @var int 255 bytes.
	 */
	protected const MAX_ENTRY_HTTP_HOST_SIZE = 255;

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
	 * Maximum log entry extra (data) size.
	 *
	 * @since 2020-01-10
	 *
	 * @var int 256kbs.
	 */
	protected const MAX_ENTRY_EXTRA_SIZE = 262144;

	/**
	 * Array of allowed parameters
	 *
	 * @var Array
	 */
	protected const ALLOWED_PARAMS = [
		'activity_timestamp',
		'api_url',
		'api_auth_hint',
		'blog_id',
		'browser_name',
		'browser_version',
		'calypso_env',
		'calypso_path',
		'calypso_section',
		'client_id',
		'comment_id',
		'comment_type',
		'commit',
		'connection_id',
		'datacenter',
		'destination_ip',
		'dest',
		'dest_target',
		'duration',
		'error_code',         // string error code
		'extra',              // string with additional data. eg json encoded, lists of ids
		'feature',
		'file',
		'http_host',
		'http_response_code',
		'http_user_agent',
		'index',
		'line',
		'message',
		'method',
		'note_id',
		'jetpack_version',
		'hosting_provider',
		'plugin',
		'path',
		'post_id',
		'pid',
		'plan_id',
		'redirect_location',
		'score',
		'severity',
		'site_id',
		'size',
		'source_ip',
		'success',
		'tags',
		'devtags',
		'tests',
		'timestamp',
		'trace',
		'url',
		'user_id',
		'external_user_id',
		'user_locale',
		'gt_id',          // Guided Transfer ID
		'job_type',       // async job type
		'job_id',         // async job id (long)
		'job_priority',   // integer
		'es_index',       // Elasticsearch index name
		'id',             // any generic numeric long id
		'ids',            // any list of numeric long ids
	];

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
	 * Parse logstash parameters
	 *
	 * @param array $params An associative array of parameters to save to logstash
	 *
	 * @return array An array of parsed logstash parameters
	 */
	protected static function parse_params( array $params ) : array {
		// Prepare data.
		$default_params = [
			'site_id'         => get_current_network_id(),                    // Required.
			'blog_id'         => get_current_blog_id(),                       // Required.
			'http_host'       => strtolower( $_SERVER['HTTP_HOST'] ?? '' ),   // phpcs:ignore -- Optional.
			'severity'        => '',                                          // Optional.
			'feature'         => '',                                          // Required.
			'message'         => '',                                          // Required.
			'user_id'         => get_current_user_id(),                       // Optional.
			'extra'           => [],                                          // Optional.
			'timestamp'       => gmdate( 'Y-m-d H:i:s' ),                     // Required.
			'index'           => 'log2logstash',                              // Required.
			'http_user_agent' => self::get_user_agent(),                      // Optional.
		];

		if ( ! isset( $params['file'] ) && ! isset( $params['line'] ) ) {
			/*
			 * Stack frames:
			 *   - 0: Logger::log2logstash()
			 *   - 1: Caller of Logger::log2logstash(); it could be \Automattic\VIP\Logstash\log2logstash() function
			 *   - 2: Caller of the caller
			 */
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
			$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 3 );

			/**
			 * For
			 *
			 *     log2logstash( [ 'message' => 'test' ] );
			 *
			 * the backtrace will look like this:
			 * [
			 *     0 => [
			 *         'file'     => '/chroot/var/www/wp-content/mu-plugins/logstash/class-logger.php',
			 *         'line'     => 402,
			 *         'function' => 'parse_params',
			 *         'class'    => 'Automattic\VIP\Logstash\Logger',
			 *         'type'     => '::',
			 *     ],
			 *     1 => [
			 *         'file'     => '/chroot/var/www/wp-content/mu-plugins/logstash/logstash.php',
			 *         'line'     => 22,
			 *         'function' => 'log2logstash',
			 *         'class'    => 'Automattic\VIP\Logstash\Logger',
			 *         'type'     => '::',
			 *     ],
			 *     2 => [
			 *         'file'     => '/chroot/var/www/test.php',
			 *         'line'     => 9,
			 *         'function' => 'Automattic\VIP\Logstash\log2logstash',
			 *     ],
			 * ];
			 * 
			 * We need to skip Frame 0 as it is useless for debugging.
			 * If the user invoked `log2logstash()` function (check $frame[1]['file']), we need to go one frame down to see the actual caller (Frame 2);
			 * otherwise, Frame 1 will point to the caller.
			 */

			$index = 1;
			if ( isset( $backtrace[ $index ]['file'] ) && wp_endswith( $backtrace[ $index ]['file'], '/mu-plugins/logstash/logstash.php' ) ) {
				$index = 2;
			}

			if ( isset( $backtrace[ $index ]['file'] ) ) {
				$default_params['file'] = $backtrace[ $index ]['file'];
			}

			if ( isset( $backtrace[ $index ]['line'] ) ) {
				$default_params['line'] = $backtrace[ $index ]['line'];
			}
		}

		$params = array_merge( $default_params, $params );

		// Filter unallowed parameters
		foreach ( $params as $key => $value ) {
			if ( ! in_array( $key, static::ALLOWED_PARAMS ) ) {
				// Not in whitelist so unset
				unset( $params[ $key ] );
				continue;
			}

			// Cast params into proper type
			switch ( $key ) {
				case 'ids':
				case 'devtags':
				case 'tags':
					$params[ $key ] = (array) $value;
					break;
				case 'duration':
				case 'score':
				case 'lag_float':
					$params[ $key ] = (float) $value;
					break;
				case 'queue_size':
					$params[ $key ] = (int) $value;
					break;
				case 'full_sync_items':
					$params[ $key ] = (int) $value;
					break;
				case 'blog_id':
				case 'client_id':
				case 'comment_id':
				case 'connection_id':
				case 'note_id':
				case 'post_id':
				case 'user_id':
				case 'size':
				case 'http_response_code':
				case 'pid':
				case 'id':
				case 'job_priority':
				case 'job_id':
				case 'plan_id':
				case 'site_id':
					$params[ $key ] = (int) $value;
					break;
				case 'extra':
					// Do nothing as extra will parsed into a JSON string
					break;
				default:
					$params[ $key ] = (string) $value;
			}
		}

		return $params;
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
	 *                    - `site_id`   : Required. Default is current network ID.
	 *                    - `blog_id`   : Required. Default is current blog ID.
	 *                    - `http_host` : Optional. Default is current http host.
	 *
	 * @internal          The following user-specific keys are set for you automatically:
	 *                    - `user_id` : Optional. Default is current user ID.
	 *                    - `http_user_agent` : Optional. Default is current user-agent or "cli"
	 */
	public static function log2logstash( array $data ) : void {
		// Prepare data.
		$data               = static::parse_params( $data );
		static $has_alerted = false;

		// Data validations.
		// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
		if ( count( static::$entries ) + 1 > static::get_log_entry_limit() ) {
			if ( ! $has_alerted ) {
				trigger_error( 'Excessive calls to ' . esc_html( __METHOD__ ) . '(). Maximum is ' . esc_html( static::get_log_entry_limit() ) . ' log entries.', E_USER_WARNING );
				$has_alerted = true;
			}
			return; // Failed validation.

		} elseif ( empty( $data['site_id'] ) || ! is_int( $data['site_id'] ) || $data['site_id'] <= 0 ) {
			trigger_error( 'Invalid `site_id` in call to ' . esc_html( __METHOD__ ) . '(). Must be an integer > 0.', E_USER_WARNING );
			return; // Failed validation.

		} elseif ( empty( $data['blog_id'] ) || ! is_int( $data['blog_id'] ) || $data['blog_id'] <= 0 ) {
			trigger_error( 'Invalid `blog_id` in call to ' . esc_html( __METHOD__ ) . '(). Must be an integer > 0.', E_USER_WARNING );
			return; // Failed validation.

		} elseif ( isset( $data['http_host'] ) && strlen( $data['http_host'] ) > static::MAX_ENTRY_HTTP_HOST_SIZE ) {
			trigger_error( 'Invalid `http_host` in call to ' . esc_html( __METHOD__ ) . '(). Must be ' . esc_html( static::MAX_ENTRY_HTTP_HOST_SIZE ) . ' bytes or less.', E_USER_WARNING );
			return; // Failed validation.

		} elseif ( isset( $data['severity'] ) && ! in_array( $data['severity'], [ '', 'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug' ], true ) ) {
			trigger_error( 'Invalid `severity` in call to ' . esc_html( __METHOD__ ) . '(). Must be one of: ``, `emergency`, `alert`, `critical`, `error`, `warning`, `notice`, `info`, `debug`.', E_USER_WARNING );
			return; // Failed validation.

		} elseif ( strlen( $data['feature'] ) > static::MAX_ENTRY_FEATURE_SIZE ) {
			trigger_error( 'Invalid `feature` in call to ' . esc_html( __METHOD__ ) . '(). Must be ' . esc_html( static::MAX_ENTRY_FEATURE_SIZE ) . ' bytes or less.', E_USER_WARNING );
			return; // Failed validation.

		} elseif ( strlen( $data['message'] ) > static::MAX_ENTRY_MESSAGE_SIZE ) {
			trigger_error( 'Invalid `message` in call to ' . esc_html( __METHOD__ ) . '(). Must be ' . esc_html( static::MAX_ENTRY_MESSAGE_SIZE ) . ' bytes or less.', E_USER_WARNING );
			return; // Failed validation.

		} elseif ( isset( $data['extra'] ) && ! is_array( $data['extra'] ) && ! is_object( $data['extra'] ) && ! is_scalar( $data['extra'] ) ) {
			trigger_error( 'Invalid `extra` in call to ' . esc_html( __METHOD__ ) . '(). Must be an object, array, or scalar value.', E_USER_WARNING );
			return; // Failed validation.

		} elseif ( isset( $data['extra'] ) && strlen( wp_json_encode( $data['extra'] ) ) > static::MAX_ENTRY_EXTRA_SIZE ) {
			trigger_error( 'Invalid `extra` in call to ' . esc_html( __METHOD__ ) . '(). Must be ' . esc_html( static::MAX_ENTRY_EXTRA_SIZE ) . ' bytes or less.', E_USER_WARNING );
			return; // Failed validation.
		}

		// Adjust `feature` and JSON-encode pretty-print data.
		$data['feature'] = 'a8c_vip_' . $data['feature'];

		// Adjust `extra` and JSON-encode data that gets sent in the API request.
		$data['extra'] = isset( $data['extra'] ) ? wp_json_encode( $data['extra'], JSON_PRETTY_PRINT ) : '';

		// Adds a new log entry.
		static::$entries[] = $data;

		// Sends data to logstash on shutdown.
		if ( ! has_action( 'shutdown', [ static::class, 'process_entries_on_shutdown' ] ) ) {
			// Due to usage of fastcgi_finish_request, we need to be mindful of priority and potential collisions.
			// One example of a collision is if a fastcgi_finish_request runs before query monitor, it causes it to
			// die silently and not load anything.
			add_action( 'shutdown', [ static::class, 'process_entries_on_shutdown' ], PHP_INT_MAX );
		}
	}

	/**
	 * Save data to logstash log file
	 *
	 * @since 2020-01-10
	 */
	public static function process_entries_on_shutdown() : void {
		if ( static::$processed_entries ) {
			return; // Already done.
		}

		static::$processed_entries = true;

		if ( function_exists( 'fastcgi_finish_request' ) ) {
			// Flush content to client first to prevent slow page load
			fastcgi_finish_request();
		}

		// Process all entries.
		foreach ( static::$entries as $entry ) {
			if ( ! defined( 'VIP_GO_ENV' ) || ! VIP_GO_ENV ) {
				static::maybe_wp_debug_log_entries( $entry );
				continue; // Bypassing logstash log writing below in this case.
			}

			$json_data = wp_json_encode( $entry );

			if ( ! $json_data ) {
				trigger_error( 'log2logstash could not encode your log.', E_USER_WARNING );
				return;
			}

			// Log to file
			// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $json_data . "\n", 3, ( is_dir( '/chroot' ) ? '/chroot' : '' ) . '/tmp/logstash.log' );
		}
	}

	/**
	 * Sends data to `WP_DEBUG_LOG` when applicable.
	 *
	 * @since 2020-01-10
	 *
	 * @param array $entry.
	 */
	public static function maybe_wp_debug_log_entries( array $entry ) : void {
		if ( ! apply_filters( 'enable_wp_debug_mode_checks', true ) || ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return; // Not applicable.
		}

		static::wp_debug_log( $entry );
	}

	/**
	 * Save message to log file
	 *
	 * @since 2020-01-10
	 *
	 * @param array $entry Data.
	 */
	public static function wp_debug_log( array $entry ) : void {
		if ( defined( 'VIP_GO_ENV' ) && VIP_GO_ENV ) {
			// Don't run this on VIP Go
			return;
		}

		$log_path = WP_CONTENT_DIR . '/debug.log';
		$log_path = is_string( WP_DEBUG_LOG ) && WP_DEBUG_LOG ? WP_DEBUG_LOG : $log_path;

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_is_writable -- not a VIP environment
		if ( $log_path && ( ( file_exists( $log_path ) && is_writable( $log_path ) ) || ( ! file_exists( $log_path ) && is_writable( dirname( $log_path ) ) ) ) ) {
			file_put_contents( // phpcs:ignore -- `file_put_contents()` ok.
				$log_path,
				__CLASS__ . ': ' . wp_json_encode( $entry, JSON_PRETTY_PRINT ) . "\n",
				FILE_APPEND
			);
		}
	}

	/**
	 * Get maximum log entry count, any additional log entry after that will be silently discarded.
	 *
	 * @return int max number of entries
	 */
	public static function get_log_entry_limit() {
		return defined( 'WP_CLI' ) && WP_CLI ? self::MAX_ENTRIES * 5 : self::MAX_ENTRIES;
	}

	/**
	 * Get user agent for request if available
	 *
	 * @return string
	 */
	public static function get_user_agent() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return 'cli';
		}

		// phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__HTTP_USER_AGENT__
		return sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' );
	}
}
