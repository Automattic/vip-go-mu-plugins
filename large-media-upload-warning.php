<?php
/*
Plugin Name: VIP Large Media Upload Warning
Description: Warns editors at file-pick time when uploading large images, before bytes reach the file service.
Author: Automattic
Version: 1.0
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/large-media-upload-warning/class-large-media-upload-warning.php';

add_action( 'plugins_loaded', static function () {
	$module = new \Automattic\VIP\LargeMediaUploadWarning\Large_Media_Upload_Warning();

	if ( ! $module->is_enabled() ) {
		return;
	}

	// Wiring of telemetry filter and asset enqueue is added in later tasks.
} );
