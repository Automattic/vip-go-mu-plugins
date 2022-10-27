<?php

namespace Automattic\VIP\CodebaseManager;

require_once __DIR__ . '/plugins/plugins-manager.php';
require_once __DIR__ . '/plugins/plugin.php';

add_action( 'admin_init', function() {
	if ( wp_doing_ajax() ) {
		// Avoid unnecessarily loading stuff for potential frontend ajax requests.
		return;
	}

	if ( ! isset( $_REQUEST['s'] ) ) {
		// Avoid loading stuff due to bug in plugin manager list for search requests.
		$plugins_manager = new PluginsManager();
		$plugins_manager->init();
	}
} );
