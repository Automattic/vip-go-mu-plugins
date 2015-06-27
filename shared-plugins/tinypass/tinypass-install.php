<?php

/**
 * Activate Tinypass plugin.  Will perform upgrades and check compatibility
 */
function tinypass_activate() {

	tinypass_upgrades();

	$data = get_plugin_data( TINYPASS_PLUGIN_FILE_PATH );
	$version = $data['Version'];
	update_option( 'tinypass_version', $version );
}

function tinypass_upgrades() {
	
}

function tinypass_deactivate() {
	
}

function tinypass_uninstall() {
	tinypass_include();
	delete_option( 'tinypass_legacy' );
	$storage = new TPStorage();
	$storage->deleteAll();
}