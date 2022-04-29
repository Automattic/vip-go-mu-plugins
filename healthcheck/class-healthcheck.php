<?php
namespace Automattic\VIP;

/**
 * Utility Healthcheck class that runs further than cache-healthcheck,
 * and allows to verify the app code is working as expected.
 *
 * This functionality is not intended for public use.
 * Please DO NOT try to implement your healthchecks on top of it,
 * It may lead to unintended consequences.
 */
class Healthcheck {
	/**
	 * By default, an app is considered healthy unless filter value returns a non-empty array
	 *
	 * @var string[] - Either empty array (no errors) or array of strings: [ 'an error has occurred', 'another error' ].
	 */
	protected array $errors = [];

	/**
	 * Apply the filters to and set the `$errors` property accordingly.
	 *
	 * @return void
	 */
	public function check() {
		/**
		 * Filters the errors that would indicate the app is unhealthy.
		 *
		 * @param string[] $errors Either empty array (no errors) or array of strings: [ 'an error has occurred', 'another error' ].
		 */
		$errors = [];
		if ( ! is_array( $errors ) ) {
			$errors = [ 'Unexpected value in Healthcheck->errors' ];
		}

		foreach ( $errors as $error ) {
			$this->add_error( $error );
		}
	}

	/**
	 * Check if the app is healthy.
	 * @return bool True if there are no errors, false if there are.
	 */
	public function is_healthy() {
		return 0 === count( $this->errors );
	}

	/**
	 * Public setter for errors
	 *
	 * @param null|string $error error description.
	 * @return void
	 */
	public function add_error( ?string $error = 'unspecified error' ) {
		$this->errors[] = $error;
	}

	/**
	 * Public getters for errors
	 *
	 * @return string[] array of errors
	 */
	public function get_errors() {
		return $this->errors;
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
