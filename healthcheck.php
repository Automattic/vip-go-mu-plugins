<?php
namespace Automattic\VIP;

/**
 * Utility Healthcheck class that runs further than cache-healthcheck,
 * and allows to verify the app code is working as expected.
 */
class Healthcheck {
	/**
	 * By default, an app is considered healthy unless filter value returns a non-empty array
	 *
	 * @var array
	 */
	protected array $errors = [];

	public function __construct() {
		$this->check();
	}

	/**
	 * Apply the filters to and set the `$errors` property accordingly.
	 *
	 * @return void
	 */
	public function check() {
		/**
		 * Filters the errors that would indicate the app is unhealthy.
		 *
		 * @param string[]     $errors either empty array (no errors) or array of strings: [ 'an error has occurred', 'another error' ]
		 */
		$this->errors = apply_filters( 'vip_site_healthcheck_errors_array', $this->errors );
		if ( ! is_array( $this->errors ) ) {
			$this->errors = [ 'Unexpected value in Healthcheck->errors' ];
		}
	}

	/**
	 * Check if the app is healthy.
	 * @return bool True if there are no errors, false if there are.
	 */
	public function is_healthy() {
		return is_array( $this->errors ) && empty( $this->errors );
	}

	/**
	 * Render the response:
	 * it should always be uncached and result either in
	 * 200 or 500 with the reasons listed in the response body.
	 *
	 * @return void
	 */
	public function render() {
		$is_healthy = $this->is_healthy();

		nocache_headers();
		header( 'Content-Type: text/plain' );
		http_response_code( $is_healthy ? 200 : 500 );

		foreach ( $this->errors as $error ) {
			echo "{$error}\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- we're using text/plain content-type
		}

		exit;
	}
}

/**
 * `parse_request` provides a good balance between making sure the codebase is loaded and not running the main query.
 */
if ( isset( $_SERVER['REQUEST_URI'] ) && '/vip-healthcheck' === $_SERVER['REQUEST_URI'] ) {
	add_action( 'parse_request', fn( $wp ) =>
		( new Healthcheck() )->render(),
	PHP_INT_MIN );
}
