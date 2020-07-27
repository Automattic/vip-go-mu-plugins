<?php
/**
 * HTTP API request collector.
 *
 * @package query-monitor
 */

class QM_Collector_HTTP extends QM_Collector {

	public $id = 'http';
	private $transport = null;
	private $info = null;

	public function name() {
		return __( 'HTTP API Requests', 'query-monitor' );
	}

	public function __construct() {

		parent::__construct();

		add_filter( 'http_request_args', array( $this, 'filter_http_request_args' ), 99, 2 );
		add_filter( 'pre_http_request',  array( $this, 'filter_pre_http_request' ), 99, 3 );
		add_action( 'http_api_debug',    array( $this, 'action_http_api_debug' ), 99, 5 );

		add_action( 'requests-curl.before_request',      array( $this, 'action_curl_before_request' ), 99 );
		add_action( 'requests-curl.after_request',       array( $this, 'action_curl_after_request' ), 99, 2 );
		add_action( 'requests-fsockopen.before_request', array( $this, 'action_fsockopen_before_request' ), 99 );
		add_action( 'requests-fsockopen.after_request',  array( $this, 'action_fsockopen_after_request' ), 99, 2 );

	}

	/**
	 * Filter the arguments used in an HTTP request.
	 *
	 * Used to log the request, and to add the logging key to the arguments array.
	 *
	 * @param  array  $args HTTP request arguments.
	 * @param  string $url  The request URL.
	 * @return array        HTTP request arguments.
	 */
	public function filter_http_request_args( array $args, $url ) {
		$trace = new QM_Backtrace;
		if ( isset( $args['_qm_key'] ) ) {
			// Something has triggered another HTTP request from within the `pre_http_request` filter
			// (eg. WordPress Beta Tester does this). This allows for one level of nested queries.
			$args['_qm_original_key'] = $args['_qm_key'];
			$start = $this->data['http'][ $args['_qm_key'] ]['start'];
		} else {
			$start = microtime( true );
		}
		$key = microtime( true ) . $url;
		$this->data['http'][ $key ] = array(
			'url'   => $url,
			'args'  => $args,
			'start' => $start,
			'trace' => $trace,
		);
		$args['_qm_key'] = $key;
		return $args;
	}

	/**
	 * Log the HTTP request's response if it's being short-circuited by another plugin.
	 * This is necessary due to https://core.trac.wordpress.org/ticket/25747
	 *
	 * $response should be one of boolean false, an array, or a `WP_Error`, but be aware that plugins
	 * which short-circuit the request using this filter may (incorrectly) return data of another type.
	 *
	 * @param bool|array|WP_Error $response The preemptive HTTP response. Default false.
	 * @param array               $args     HTTP request arguments.
	 * @param string              $url      The request URL.
	 * @return bool|array|WP_Error          The preemptive HTTP response.
	 */
	public function filter_pre_http_request( $response, array $args, $url ) {

		// All is well:
		if ( false === $response ) {
			return $response;
		}

		// Something's filtering the response, so we'll log it
		$this->log_http_response( $response, $args, $url );

		return $response;
	}

	/**
	 * Debugging action for the HTTP API.
	 *
	 * @param mixed  $response A parameter which varies depending on $action.
	 * @param string $action   The debug action. Currently one of 'response' or 'transports_list'.
	 * @param string $class    The HTTP transport class name.
	 * @param array  $args     HTTP request arguments.
	 * @param string $url      The request URL.
	 */
	public function action_http_api_debug( $response, $action, $class, $args, $url ) {

		switch ( $action ) {

			case 'response':
				if ( ! empty( $class ) ) {
					$this->data['http'][ $args['_qm_key'] ]['transport'] = str_replace( 'wp_http_', '', strtolower( $class ) );
				} else {
					$this->data['http'][ $args['_qm_key'] ]['transport'] = null;
				}

				$this->log_http_response( $response, $args, $url );

				break;

			case 'transports_list':
				# Nothing
				break;

		}

	}

	public function action_curl_before_request() {
		$this->transport = 'curl';
	}

	public function action_curl_after_request( $headers, array $info = null ) {
		$this->info = $info;
	}

	public function action_fsockopen_before_request() {
		$this->transport = 'fsockopen';
	}

	public function action_fsockopen_after_request( $headers, array $info = null ) {
		$this->info = $info;
	}

	/**
	 * Log an HTTP response.
	 *
	 * @param array|WP_Error $response The HTTP response.
	 * @param array          $args     HTTP request arguments.
	 * @param string         $url      The request URL.
	 */
	public function log_http_response( $response, array $args, $url ) {
		$this->data['http'][ $args['_qm_key'] ]['end']      = microtime( true );
		$this->data['http'][ $args['_qm_key'] ]['response'] = $response;
		$this->data['http'][ $args['_qm_key'] ]['args']     = $args;
		if ( isset( $args['_qm_original_key'] ) ) {
			$this->data['http'][ $args['_qm_original_key'] ]['end']      = $this->data['http'][ $args['_qm_original_key'] ]['start'];
			$this->data['http'][ $args['_qm_original_key'] ]['response'] = new WP_Error( 'http_request_not_executed', sprintf(
				/* translators: %s: Hook name */
				__( 'Request not executed due to a filter on %s', 'query-monitor' ),
				'pre_http_request'
			) );
		}

		$this->data['http'][ $args['_qm_key'] ]['info']      = $this->info;
		$this->data['http'][ $args['_qm_key'] ]['transport'] = $this->transport;
		$this->info = null;
		$this->transport = null;
	}

	public function process() {

		foreach ( array(
			'WP_PROXY_HOST',
			'WP_PROXY_PORT',
			'WP_PROXY_USERNAME',
			'WP_PROXY_PASSWORD',
			'WP_PROXY_BYPASS_HOSTS',
			'WP_HTTP_BLOCK_EXTERNAL',
			'WP_ACCESSIBLE_HOSTS',
		) as $var ) {
			if ( defined( $var ) and constant( $var ) ) {
				$val = constant( $var );
				if ( true === $val ) {
					# @TODO this transformation should happen in the output, not the collector
					$val = 'true';
				}
				$this->data['vars'][ $var ] = $val;
			}
		}

		$this->data['ltime'] = 0;

		if ( ! isset( $this->data['http'] ) ) {
			return;
		}

		$silent = apply_filters( 'qm/collect/silent_http_errors', array(
			'http_request_not_executed',
			'airplane_mode_enabled',
		) );

		foreach ( $this->data['http'] as $key => & $http ) {

			if ( ! isset( $http['response'] ) ) {
				// Timed out
				$http['response'] = new WP_Error( 'http_request_timed_out', __( 'Request timed out', 'query-monitor' ) );
				$http['end']      = floatval( $http['start'] + $http['args']['timeout'] );
			}

			if ( is_wp_error( $http['response'] ) ) {
				if ( ! in_array( $http['response']->get_error_code(), $silent, true ) ) {
					$this->data['errors']['alert'][] = $key;
				}
				$http['type'] = __( 'Error', 'query-monitor' );
			} elseif ( ! $http['args']['blocking'] ) {
				/* translators: A non-blocking HTTP API request */
				$http['type'] = __( 'Non-blocking', 'query-monitor' );
			} else {
				$http['type'] = intval( wp_remote_retrieve_response_code( $http['response'] ) );
				if ( $http['type'] >= 400 ) {
					$this->data['errors']['warning'][] = $key;
				}
			}

			$http['ltime'] = ( $http['end'] - $http['start'] );

			if ( isset( $http['info'] ) ) {
				if ( isset( $http['info']['total_time'] ) ) {
					$http['ltime'] = $http['info']['total_time'];
				}

				if ( ! empty( $http['info']['url'] ) ) {
					if ( rtrim( $http['url'], '/' ) !== rtrim( $http['info']['url'], '/' ) ) {
						$http['redirected_to'] = $http['info']['url'];
					}
				}
			}

			$this->data['ltime'] += $http['ltime'];

			$http['component'] = $http['trace']->get_component();

			$this->log_type( $http['type'] );
			$this->log_component( $http['component'], $http['ltime'], $http['type'] );

		}

	}

}

# Load early in case a plugin is doing an HTTP request when it initialises instead of after the `plugins_loaded` hook
QM_Collectors::add( new QM_Collector_HTTP );
