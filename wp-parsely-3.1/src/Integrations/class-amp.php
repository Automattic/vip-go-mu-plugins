<?php
/**
 * AMP integration class
 *
 * @package Parsely\Integrations
 * @since 2.6.0
 */

declare(strict_types=1);

namespace Parsely\Integrations;

use Parsely\Parsely;

/**
 * Integrates Parse.ly tracking with the AMP plugin.
 *
 * @since 2.6.0 Moved from Parsely class to this file.
 */
class Amp implements Integration {
	/**
	 * Apply the hooks that integrate the plugin or theme with the Parse.ly plugin.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	public function integrate(): void {
		if ( defined( 'AMP__VERSION' ) ) {
			add_action( 'template_redirect', array( $this, 'add_actions' ) );
		}
	}

	/**
	 * Verify if request is an AMP request.
	 *
	 * This is needed to make it easier to mock whether the function exists ot not during tests.
	 *
	 * @since 2.6.0
	 *
	 * @return bool True is an AMP request, false otherwise.
	 */
	public function is_amp_request(): bool {
		return function_exists( 'amp_is_request' ) && amp_is_request();
	}

	/**
	 * Verify if request is an AMP request, and that AMP support is not disabled.
	 *
	 * @since 2.6.0
	 *
	 * @return bool True is an AMP request and not disabled, false otherwise.
	 */
	public function can_handle_amp_request(): bool {
		$options = get_option( Parsely::OPTIONS_KEY );

		return $this->is_amp_request() && is_array( $options ) && ! $options['disable_amp'];
	}

	/**
	 * Add AMP actions.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	public function add_actions(): void {
		if ( $this->can_handle_amp_request() ) {
			add_filter( 'amp_post_template_analytics', array( $this, 'register_parsely_for_amp_analytics' ) );
			add_filter( 'amp_analytics_entries', array( $this, 'register_parsely_for_amp_native_analytics' ) );
		}
	}

	/**
	 * Register Parse.ly for AMP analytics.
	 *
	 * @since 2.6.0
	 *
	 * @param array|null $analytics The analytics registry.
	 * @return array The analytics registry.
	 */
	public function register_parsely_for_amp_analytics( ?array $analytics ): array {
		if ( null === $analytics ) {
			$analytics = array();
		}

		$options = get_option( Parsely::OPTIONS_KEY );

		if ( empty( $options['apikey'] ) ) {
			return $analytics;
		}

		$analytics['parsely'] = array(
			'type'        => 'parsely',
			'attributes'  => array(),
			'config_data' => array(
				'vars' => array(
					'apikey' => $options['apikey'],
				),
			),
		);

		return $analytics;
	}

	/**
	 * Register Parse.ly for AMP native analytics.
	 *
	 * @since 2.6.0
	 *
	 * @param array|null $analytics The analytics registry.
	 * @return array The analytics registry.
	 */
	public function register_parsely_for_amp_native_analytics( ?array $analytics ): array {
		if ( null === $analytics ) {
			$analytics = array();
		}

		$options = get_option( Parsely::OPTIONS_KEY );

		if ( ! empty( $options['disable_amp'] ) && true === $options['disable_amp'] ) {
			return $analytics;
		}

		if ( empty( $options['apikey'] ) ) {
			return $analytics;
		}

		$analytics['parsely'] = array(
			'type'       => 'parsely',
			'attributes' => array(),
			'config'     => wp_json_encode(
				array(
					'vars' => array(
						'apikey' => $options['apikey'],
					),
				)
			),
		);

		return $analytics;
	}
}
