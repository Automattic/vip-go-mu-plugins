<?php

namespace Automattic\VIP\Core\Plugins;

/**
 * Show plugin update notices on the plugins page.
 *
 * Because no one in a VIP env has the `update_plugins` cap, core's notices are never displayed.
 *
 * We've ported over core's `wp_plugin_update_rows()` function and use the `activate_plugins`
 * cap instead of `update_plugins` to restore visibility of the update notices.
 */
function show_plugin_update_notices() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$plugins = get_site_transient( 'update_plugins' );

	if ( isset( $plugins->response ) && is_array( $plugins->response ) ) {
		$plugins = array_keys( $plugins->response );
		foreach ( $plugins as $plugin_file ) {
			add_action( "after_plugin_row_{$plugin_file}", 'wp_plugin_update_row', 10, 2 );
		}
	}
}
add_action(
	'load-plugins.php',
	__NAMESPACE__ . '\show_plugin_update_notices',
	20 // Run after core's wp_update_plugins() is called.
);
