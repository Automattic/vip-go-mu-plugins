<?php

add_filter( 'mce_external_plugins', function( $plugins ) {
	if ( isset( $plugins['table'] ) )
		$plugins['table'] = wpcom_vip_themes_root_uri() . '/plugins/mce-table-buttons/tinymce4-table/plugin.min.js'; // hack around cross-domain issues because plugins_url is passed through our CDN
	return $plugins;
}, 99 ); // run later so we override the plugin
