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
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( Parsely::get_settings_url() ),
			esc_html__( 'Settings', 'wp-parsely' )
		);

		$actions['settings'] = $settings_link;

		return $actions;
	}
}
