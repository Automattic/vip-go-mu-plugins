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

$vip_lmw_class_file = __DIR__ . '/large-media-upload-warning/class-large-media-upload-warning.php';

if ( ! file_exists( $vip_lmw_class_file ) ) {
	// Defensive: on non-atomic deploys this root file may load before the module directory
	// finishes syncing. Fail open to "module disabled" rather than fatal.
	return;
}

require_once $vip_lmw_class_file;
unset( $vip_lmw_class_file );

add_action( 'plugins_loaded', static function () {
	$module = new \Automattic\VIP\LargeMediaUploadWarning\Large_Media_Upload_Warning();

	if ( ! $module->is_enabled() ) {
		return;
	}

	add_filter( 'wp_handle_upload_prefilter', [ $module, 'maybe_log_large_upload' ], 5 );
	add_filter( 'wp_handle_sideload_prefilter', [ $module, 'maybe_log_large_upload' ], 5 );
	add_action( 'admin_enqueue_scripts', [ $module, 'enqueue_assets' ] );
} );
