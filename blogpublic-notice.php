<?php

namespace Automattic\VIP\Blog_Public;

if ( defined( 'VIP_GO_APP_ENVIRONMENT' ) && 'production' === VIP_GO_APP_ENVIRONMENT ) {
	return;
}

/**
 * Notice for blog_public value
 */
function notice() {
	$home_url_parsed = wp_parse_url( home_url() );
	if (
		! current_user_can( 'manage_options' ) ||
		get_option( 'blogpublic_notice_dismissed', false ) ||
		! get_option( 'blog_public' ) ||
		'-1' === get_option( 'blog_public' ) ||
		is_vip_convenience_domain( $home_url_parsed['host'] ?? '' )
	) {
		return;
	}

	printf(
		'<div id="blogpublic-notice" class="notice notice-warning is-dismissible"><p>Your site may be discoverable. You can change this by <a href="%s">disabling content distribution</a>.</p></div>',
		'https://docs.wpvip.com/wordpress-on-vip/jetpack/content-distribution/#h-disabling-content-distribution',
	);
	add_action( 'admin_footer', __NAMESPACE__ . '\dismiss_handler' );
}
add_action( 'admin_notices', __NAMESPACE__ . '\notice' );

/**
 * JS to handle the dismiss button in the notice
 */
function dismiss_handler() {
	?>
	<script>
	jQuery('#blogpublic-notice').on('click', 'button', function() {
		jQuery.ajax( ajaxurl, {
			data : {
				action:'blogpublic_notice_dismiss'
			},
			dataType: 'json',
			success: function(data) {
			},
			error: function( data ) {
			}
		} );

	});
	</script>
	<?php
}

/**
 * Ajax callback for dismiss button
 */
function dismiss_callback() {
	if ( current_user_can( 'manage_options' ) ) {
		update_option( 'blogpublic_notice_dismissed', true, 'no' );
		wp_send_json_success();
	}
	wp_send_json_error();
}
add_action( 'wp_ajax_blogpublic_notice_dismiss', __NAMESPACE__ . '\dismiss_callback' );

/**
 * If blog_public is being updated to 1
 * remove 'dismiss' flag to reshow the notice
 */
function reset_notice_dismissal( $old_value, $value ) {
	if ( 1 === $value || '1' === $value ) {
		delete_option( 'blogpublic_notice_dismissed' );
	}
}
add_action( 'update_option_blog_public', __NAMESPACE__ . '\reset_notice_dismissal', 10, 2 );
