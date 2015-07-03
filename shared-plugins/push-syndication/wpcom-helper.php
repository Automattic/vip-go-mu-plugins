<?php
add_action( 'syn_after_init_server', function() {
	if ( true !== WPCOM_IS_VIP_ENV )
		return;

	global $push_syndication_server;

	// Override the plugin's default wp-cron based push handling and use the WP.com jobs system instead
	remove_action( 'syn_schedule_push_content', array( $push_syndication_server, 'schedule_push_content' ), 10 );
	add_action( 'syn_schedule_push_content', function( $post_id, $sites ) {
		$data = new stdClass;
		$data->post_id = $post_id;
		$job_id = queue_async_job( $data, 'vip_async_syndication_push_post' );

		xmpp_message( 'batmoo@im.wordpress.com', '[syn_schedule_push_content] job id for pushing post #' . $post_id . ' ('. home_url() .'): ' . $job_id );
	}, 10, 2 );

	// TODO: override schedule_delete_content and schedule_pull_content

} );

// Failure notifications
add_action( 'syn_post_push_new_post', 'wpcom_vip_push_syndication_debug', 10, 6 );
add_action( 'syn_post_push_edit_post', 'wpcom_vip_push_syndication_debug', 10, 6 );

function wpcom_vip_push_syndication_debug( $result, $post_id, $site, $transport_type, $client, $info ) {
	if ( ! is_wp_error( $result ) ) {
		return;
	}

    // Temp disable
    return;

	$debug_output = '';

	$debug_output .= 'Result: ' . var_export( $result, true ) . PHP_EOL . PHP_EOL;
	$debug_output .= 'Post Id: ' . var_export( $post_id, true ) . PHP_EOL . PHP_EOL;
	$debug_output .= 'Blog Id: ' . var_export( get_current_blog_id(), true ) . PHP_EOL . PHP_EOL;
	$debug_output .= 'Site: ' . var_export( $site, true ) . PHP_EOL . PHP_EOL;
	$debug_output .= 'Transport Type: ' . var_export( $transport_type, true ) . PHP_EOL . PHP_EOL;
	$debug_output .= 'Client: ' . var_export( $client, true ) . PHP_EOL . PHP_EOL;
	$debug_output .= 'Info: ' . var_export( $info, true ) . PHP_EOL . PHP_EOL;

	wp_mail( 'nick.daugherty@a8c.com', 'Push Syndication Failure Debug', $debug_output );
}

// === Stats ===
add_action( 'syn_post_pull_edit_post', function() {
	wpcom_push_syndication_stats( 'vip-syndication-pull', 'edit' );
} );
add_action( 'syn_post_pull_new_post', function() {
	wpcom_push_syndication_stats( 'vip-syndication-pull', 'new' );
} );
add_action( 'syn_post_push_delete_post', function() {
	wpcom_push_syndication_stats( 'vip-syndication-push', 'delete' );
} );
add_action( 'syn_post_push_edit_post', function() {
	wpcom_push_syndication_stats( 'vip-syndication-push', 'edit' );
} );
add_action( 'syn_post_push_new_post', function() {
	wpcom_push_syndication_stats( 'vip-syndication-push', 'new' );
} );

function wpcom_push_syndication_stats( $stat, $action ) {
	if ( function_exists( 'bump_stats_extras' ) ) {
		bump_stats_extras( $stat, $action );
	}	
}
