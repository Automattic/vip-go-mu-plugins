<?php

namespace Automattic\VIP\CodebaseManager;

require_once __DIR__ . '/plugins/plugins-manager.php';
require_once __DIR__ . '/plugins/plugin.php';

add_action( 'admin_init', function() {
	if ( wp_doing_ajax() ) {
		// Avoid unnecessarily loading stuff for potential frontend ajax requests.
		return;
	}

	( new PluginsManager() )->init();
} );
