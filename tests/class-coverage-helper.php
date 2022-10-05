<?php

use PHPUnit\Runner\BeforeFirstTestHook;

function _manually_load_plugin() {
	require_once __DIR__ . '/../lib/helpers/php-compat.php';
	require_once __DIR__ . '/../000-vip-init.php';
	require_once __DIR__ . '/../001-core.php';
	require_once __DIR__ . '/../a8c-files.php';

	require_once __DIR__ . '/../async-publish-actions.php';
	require_once __DIR__ . '/../performance.php';

	require_once __DIR__ . '/../security.php';

	require_once __DIR__ . '/../schema.php';

	require_once __DIR__ . '/../vip-jetpack/vip-jetpack.php';

	// Proxy lib
	require_once __DIR__ . '/proxy-helpers.php'; // Needs to be included before ip-forward.php
	require_once __DIR__ . '/../lib/proxy/ip-forward.php';
	require_once __DIR__ . '/../lib/proxy/class-iputils.php';

	require_once __DIR__ . '/../vip-cache-manager.php';
	require_once __DIR__ . '/../vip-mail.php';
	require_once __DIR__ . '/../vip-rest-api.php';
	require_once __DIR__ . '/../vip-plugins.php';

	require_once __DIR__ . '/../wp-cli.php';

	require_once __DIR__ . '/../z-client-mu-plugins.php';
}

/**
 * VIP Cache Manager can potentially pollute other tests,
 * So we explicitly unhook the init callback.
 *
 */
function _remove_init_hook_for_cache_manager() {
	remove_action( 'init', array( WPCOM_VIP_Cache_Manager::instance(), 'init' ) );
}

class TestCaseHelper extends WP_UnitTestCase {
	public static function do_backup_hooks(): void {
		$inst = new self();
		$inst->_backup_hooks();
	}
}

// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
class Coverage_Helper implements BeforeFirstTestHook {
	public function executeBeforeFirstTest(): void {
		tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );
		tests_add_filter( 'muplugins_loaded', '_remove_init_hook_for_cache_manager' );
		do_action( 'muplugins_loaded' );
		TestCaseHelper::do_backup_hooks();
	}
}
