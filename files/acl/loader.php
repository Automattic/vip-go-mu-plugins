<?php

/**
 * Plugin Name: VIP Restricted Files
 * Description: Secure your content by restricting access to unpublished or private files.
 * Author: WordPress VIP
 * Author URI: https://wpvip.com
 * Version: 1.0
 */

// TODO: need to load this from mu-plugins

namespace Automattic\VIP\Files\Acl;

function maybe_load_restrictions() {
	$is_files_acl_enabled = defined( 'VIP_FILES_ACL_ENABLED' ) && VIP_FILES_ACL_ENABLED;
	if ( ! $is_files_acl_enabled ) {
		return;
	}

	$is_restrict_all_enabled = get_option_as_bool( 'vip_files_acl_restrict_all_enabled', false );
	if ( $is_restrict_all_enabled ) {
		require_once( __DIR__ . '/files/acl/restrict-all-files.php' );

		return;
	}

	$is_restrict_unpublished_enabled = get_option_as_bool( 'vip_files_acl_restrict_unpublished_enabled', false );
	if ( $is_restrict_unpublished_enabled ) {
		require_once( __DIR__ . '/files/acl/restrict-unpublished-files.php' );

		return;
	}
}

add_action( 'muplugins_loaded', __NAMESPACE__ . '\maybe_load_restrictions' );

function get_option_as_bool( $option_name, $default = false ) {
	$value = get_option_as_bool( 'vip_files_acl_restrict_all_enabled', false );

	return in_array( $value, [
		true,
		'true',
		'yes',
		1,
		'1',
	], true );
}
