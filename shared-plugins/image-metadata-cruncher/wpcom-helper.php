<?php
/**
 * The page is normally added under "Plugins"
 */
add_action( 'admin_menu', function() {
	global $image_metadata_cruncher;

	$page = add_options_page(
		'Image Metadata Cruncher',
		'Image Metadata Cruncher',
		'manage_options',
		"{$image_metadata_cruncher->prefix}-options",
		array( $image_metadata_cruncher, 'options_cb' )
	);

	add_action( 'admin_print_scripts-' . $page, array( $image_metadata_cruncher, 'js_rangy_core' ) );
	add_action( 'admin_print_scripts-' . $page, array( $image_metadata_cruncher, 'js_rangy_selectionsaverestore' ) );
	add_action( 'admin_print_scripts-' . $page, array( $image_metadata_cruncher, 'js' ) );
	add_action( 'admin_print_styles-' . $page, array( $image_metadata_cruncher, 'css' ) );
});
