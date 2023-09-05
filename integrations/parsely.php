<?php
/**
 * Integration: Parse.ly.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

/**
 * Loads Parse.ly VIP Integration.
 *
 * @private
 */
class ParselyIntegration extends Integration {
	/**
	 * Loads the plugin.
	 *
	 * @private
	 */
	public function load(): void {
		// Do not load plugin if already loaded by customer code.
		if ( class_exists( 'Parsely\Parsely' ) ) {
			return;
		}

		// Activating the integration via setting constant whose implementation is already
		// handled via Automattic\VIP\WP_Parsely_Integration (ideally we should move
		// all the implementation here such that there will be only one way of managing
		// the plugin).
		define( 'VIP_PARSELY_ENABLED', true );

		add_filter( 'wp_parsely_credentials', array( $this, 'wp_parsely_credentials_callback' ) );
		add_filter( 'wp_parsely_managed_options', array( $this, 'wp_parsely_managed_options_callback' ) );
	}

	/**
	 * Callback for `wp_parsely_credentials` filter.
	 *
	 * @param array $original_credentials Credentials coming from plugin.
	 *
	 * @return array
	 */
	public function wp_parsely_credentials_callback( $original_credentials ) {
		$config      = $this->get_config();
		$credentials = array();

		// If config provided by VIP is empty then take original credentials else take
		// credentials from config.
		$credentials = empty( $config ) ? $original_credentials : array(
			'site_id'    => $config['site_id'] ?? null,
			'api_secret' => $config['api_secret'] ?? null,
		);

		// Adds `is_managed` flag to indicate that platform is managing this integration
		// and we have to hide the credential banner warning or more.
		return array_merge( array( 'is_managed' => true ), $credentials );
	}

	/**
	 * Callback for `wp_parsely_managed_options` filter.
	 *
	 * @param array $value Value passed to filter.
	 *
	 * @return array
	 */
	public function wp_parsely_managed_options_callback( $value ) {
		return array(
			'force_https_canonicals' => true,
			'meta_type'              => 'repeated_metas',
			// Managed options that will obey the values on database.
			'cats_as_tags'           => null,
			'content_id_prefix'      => null,
			'logo'                   => null,
			'lowercase_tags'         => null,
			'use_top_level_cats'     => null,
		);
	}
}
