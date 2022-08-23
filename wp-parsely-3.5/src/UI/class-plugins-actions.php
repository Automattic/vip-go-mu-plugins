<?php
/**
 * UI: plugins actions class
 *
 * @package Parsely
 * @since   2.6.0
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
	 * Registers action and filter hook callbacks.
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
		$link_pattern = '<a href="%s">%s</a>';
		if ( is_multisite() && is_plugin_active_for_network( plugin_basename( PARSELY_FILE ) ) ) {
			$actions['siteslist'] = sprintf(
				$link_pattern,
				esc_url( network_admin_url( 'sites.php' ) ),
				esc_html__( 'Sites', 'wp-parsely' )
			);
		}

		$actions['settings'] = sprintf(
			$link_pattern,
			esc_url( Parsely::get_settings_url() ),
			esc_html__( 'Settings', 'wp-parsely' )
		);

		$actions['documentation'] = sprintf(
			$link_pattern,
			'https://www.parse.ly/help/integration/wordpress',
			esc_html__( 'Documentation', 'wp-parsely' )
		);

		return $actions;
	}
}
