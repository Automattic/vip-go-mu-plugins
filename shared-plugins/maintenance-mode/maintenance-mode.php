<?php
/**
 * Plugin Name: Maintenance Mode
 * Description: Shut down your site for a little while and do some maintenance on it!
 * License: GPLv2
 *
 * Usage:
 * - Add a template to your theme's root folder called `template-maintenance-mode.php`.
 * - This should be a simple HTML page that should include the message you want to show your visitors.
 * - Note: the template should include `wp_head()` and `wp_footer()` calls.
 * - Add the VIP_MAINTENANCE_MODE constant to your theme and set to `true`.
 */

if ( defined( 'VIP_MAINTENANCE_MODE' ) && true === VIP_MAINTENANCE_MODE ) {
	add_action( 'template_redirect', function() {
		$required_capability = apply_filters( 'vip_maintenance_mode_reqiured_cap', 'edit_posts' );
		if ( current_user_can( $required_capability ) ) {
			return;
		}

		if ( locate_template( 'template-maintenance-mode.php' ) ) {
			get_template_part( 'template-maintenance-mode' );
		} else {
			include( __DIR__ . '/template-maintenance-mode.php' );
		}
		exit;
	} );
}
