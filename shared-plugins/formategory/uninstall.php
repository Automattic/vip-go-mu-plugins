<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) )
	exit;

delete_option( 'formategory_version' );

// Delete all Formategory templates.

global $wpdb;

$formategory_templates = get_posts( array( 'post_type' => 'formategory_template', 'post_status' => null, 'numberposts' => -1 ) );

foreach ( $formategory_templates as $template ) {
	wp_delete_post( $template->ID, true );
}