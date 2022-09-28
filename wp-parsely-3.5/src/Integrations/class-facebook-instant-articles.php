<?php
/**
 * Integrations: Facebook Instant Articles integration class
 *
 * @package Parsely
 * @since   2.6.0
 */

declare(strict_types=1);

namespace Parsely\Integrations;

use Parsely\Parsely;

/**
 * Integrates Parse.ly tracking with the Facebook Instant Articles plugin.
 *
 * @since 2.6.0 Moved from Parsely class to this file.
 */
final class Facebook_Instant_Articles extends Integration {
	private const REGISTRY_IDENTIFIER   = 'parsely-analytics-for-wordpress';
	private const REGISTRY_DISPLAY_NAME = 'Parse.ly Analytics';

	/**
	 * Applies the hooks that integrate the plugin or theme with the Parse.ly
	 * plugin.
	 *
	 * @since 2.6.0
	 */
	public function integrate(): void {
		if ( defined( 'IA_PLUGIN_VERSION' ) ) {
			add_action( 'instant_articles_compat_registry_analytics', array( $this, 'insert_parsely_tracking' ) );
		}
	}

	/**
	 * Adds Parse.ly tracking to Facebook instant articles.
	 *
	 * @since 2.6.0
	 *
	 * @param array $registry The registry info for Facebook Instant Articles.
	 */
	public function insert_parsely_tracking( &$registry ): void {
		$parsely = new Parsely();
		if ( $parsely->api_key_is_missing() ) {
			return;
		}

		$registry[ self::REGISTRY_IDENTIFIER ] = array(
			'name'    => self::REGISTRY_DISPLAY_NAME,
			'payload' => $this->get_embed_code( $parsely->get_api_key() ),
		);
	}

	/**
	 * Returns the payload / embed code.
	 *
	 * @since 2.6.0
	 *
	 * @param string $api_key API key.
	 * @return string Embedded code.
	 */
	private function get_embed_code( string $api_key ): string {
		return '<script>
			PARSELY = {
				autotrack: false,
				onload: function() {
					PARSELY.beacon.trackPageView({
						urlref: \'http://facebook.com/instantarticles\'
					});
					return true;
				}
			}
		</script>
		<script data-cfasync="false" id="parsely-cfg" data-parsely-site="' . esc_attr( $api_key ) . '" src="//cdn.parsely.com/keys/' . esc_attr( $api_key ) . '/p.js"></script>';
	}
}
