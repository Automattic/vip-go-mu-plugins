<?php

/**
 * These helper functions can be used inside your VIP code to modify how New Relic works on your site.
 */

/**
 * Disable New Relic's browser metrics
 *
 * Removes NR's JavaScript for tracking browser metrics, including page load times, Apdex score, and more.
 *
 * Must be called at or before the `template_redirect` action.
 */
function wpcom_vip_disable_new_relic_js() {
	if ( did_action( 'template_redirect' ) && ! doing_action( 'template_redirect' ) ) {
		_doing_it_wrong( __FUNCTION__, 'New Relic&#8217;s browser tracking can only be disabled at or before the `template_redirect` action.', '1.0' );
		return;
	}

	if ( function_exists( 'newrelic_disable_autorum' ) ) {
		newrelic_disable_autorum();
	}
}


/**
 * The following functions are for platform level changes and should only be changed after consulting with WordPress.com VIP
 */

/**
 * Add the exact URI to NewRelic tracking but only if we're not in the admin
 */
function wpcom_vip_add_uri_to_newrelic() {
	if ( ! is_admin() && function_exists( 'newrelic_add_custom_parameter' ) ) {
		newrelic_capture_params();
		newrelic_add_custom_parameter( 'HTTP_REFERER', isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : '' );
		newrelic_add_custom_parameter( 'HTTP_USER_AGENT', isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '' );
		newrelic_add_custom_parameter( 'HTTPS', is_ssl() );
	}
}
add_action( 'muplugins_loaded', 'wpcom_vip_add_URI_to_newrelic' );

/**
 * Name cron correctly in New Relic and do not count it as part of the Apdex score.
 *
 * We don't want to count cron as part of the apdex because it is not a user facing function and if cron tasks are slow it doesn't imply that the site's performance is impacted. Without removing these, long running cron tasks could flag the site as having performance problems, which would cause false positives in the monitoring.
 */
function wpcom_vip_cron_for_newrelic() {
	if ( wp_doing_cron()
		&& function_exists( 'newrelic_ignore_apdex' )
		&& function_exists( 'newrelic_add_custom_parameter' )
		&& function_exists( 'newrelic_name_transaction' ) ) {
		newrelic_name_transaction( 'wp-cron' );
		newrelic_add_custom_parameter( 'wp-cron', 'true' );
		newrelic_ignore_apdex();
	}
}
add_action( 'muplugins_loaded', 'wpcom_vip_cron_for_newrelic', 11 ); // We are attaching this at muplugins_loaded because Cron-Control is loaded at muplugins_loaded priority 10

/**
 * Name wp-cli correctly in New Relic and do not count it as part of the Apdex score
 *
 * We don't want to count ongoing WP-CLI requests as part of the apdex because it is not a user facing function and if a WP-CLI request is slow it doesn't imply that the site's performance is impacted. Without removing these WP-CLI requests from the apdex calculation it could flag the site as having performance problems, which would cause false positives in the monitoring.
 */
function wpcom_vip_wpcli_for_newrelic() {
	if ( defined( 'WP_CLI' )
	     && WP_CLI
	     && ! wp_doing_cron()  // Cron is going to be run via WP-CLI in the near term. We want to keep Cron and WP-CLI separated for better monitoring so we're not going to flag WP_CLI requests that are actually cron requests as WP-CLI.
	     && function_exists( 'newrelic_ignore_apdex' )
	     && function_exists( 'newrelic_add_custom_parameter' )
	     && function_exists( 'newrelic_name_transaction' ) ) {
		newrelic_name_transaction( 'wp-cli' );
		newrelic_add_custom_parameter( 'wp-cli', 'true' );
		newrelic_ignore_apdex();
	}
}
add_action( 'muplugins_loaded', 'wpcom_vip_wpcli_for_newrelic', 11 );  // We are attaching this at muplugins_loaded because Cron-Control is loaded at muplugins_loaded priority 10
