<?php
/**
 * Parsely plugins actions class
 *
 * @package Parsely
 * @since 2.6.0
 */

declare(strict_types=1);

namespace Parsely\UI;

use Parsely\Parsely;

use const Parsely\PARSELY_FILE;

/**
 * User Interface changes for the plugins actions.
 *
 * @since 2.6.0
 */
final class Plugins_Actions {

	/**
	 * Register action and filter hook callbacks.
	 *
	 * @return void
	 */
	public function run(): void {
		add_filter( 'plugin_action_links_' . plugin_basename( PARSELY_FILE ), array( $this, 'add_plugin_meta_links' ) );
	}

	/**
	 * Adds a 'Settings' action link to the Plugins screen in WP admin.
	 *
	 * @param array $actions An array of plugin action links. By default, this can include 'activate',
	 *                       'deactivate', and 'delete'. With Multisite active this can also include
	 *                       'network_active' and 'network_only' items.
	 * @return array
	 */
	public function add_plugin_meta_links( array $actions ): array {
		if ( is_multisite() && is_plugin_active_for_network( plugin_basename( PARSELY_FILE ) ) ) {
			$actions['siteslist'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( network_admin_url( 'sites.php' ) ),
				esc_html__( 'Sites', 'wp-parsely' )
			);
		}

		$actions['settings'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( Parsely::get_settings_url() ),
			esc_html__( 'Settings', 'wp-parsely' )
		);

		$actions['documentation'] = sprintf(
			'<a href="%s">%s</a>',
			'https://www.parse.ly/help/integration/wordpress',
			esc_html__( 'Documentation', 'wp-parsely' )
		);

		return $actions;
	}
}
