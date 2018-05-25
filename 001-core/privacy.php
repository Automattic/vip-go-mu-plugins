<?php

namespace Automattic\VIP\Core\Privacy;

add_action( 'muplugins_loaded', __NAMESPACE__ . '\init_privacy_compat' );

function init_privacy_compat() {
	// Replace core's privacy data export handler with a custom one.
	remove_action( 'wp_privacy_personal_data_export_file', 'wp_privacy_generate_personal_data_export_file', 10 );
	add_action( 'wp_privacy_personal_data_export_file', __NAMESPACE__ . '\generate_personal_data_export_file' );

	// Replace core's privacy data delete handler with a custom one.
	remove_action( 'wp_privacy_delete_old_export_files', 'wp_privacy_delete_old_export_files' );
	add_action( 'wp_privacy_delete_old_export_files', __NAMESPACE__ . '\delete_old_export_files' );
}

function generate_personal_data_export_file( $request_id ) {
}

function delete_old_export_files() {
}
