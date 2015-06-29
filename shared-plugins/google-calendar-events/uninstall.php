<?php
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) )
	die();

$gce_options = get_option( 'gce_options' );

//Remove any cached feed data
foreach ( $gce_options as $gce_feed ) {
	if ( isset( $gce_feed['id'] ) ) {
		delete_transient( 'gce_feed_' . $gce_feed['id'] );
		delete_transient( 'gce_feed_' . $gce_feed['id'] . '_url' );
	}
}

//Delete plugin options
delete_option( 'gce_options' );
delete_option( 'gce_general' );
delete_option( 'gce_version' );
?>