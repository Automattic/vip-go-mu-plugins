<?php

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
 * Add the exact URI to NewRelic tracking but only if we're not in the admin
 */
function wpcom_vip_add_URI_to_newrelic(){
	if ( ! is_admin() && function_exists( 'newrelic_add_custom_parameter' ) ){
		newrelic_add_custom_parameter( 'REQUEST_URI', isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '' );
		newrelic_add_custom_parameter( 'HTTP_REFERER', isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : '' );
		newrelic_add_custom_parameter( 'HTTP_USER_AGENT', isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '' );
	}
}
add_action( 'muplugins_loaded', 'wpcom_vip_add_URI_to_newrelic' );

/**
 * Name cron correctly in New Relic and do not count it as part of the Apdex score.
 *
 * We don't want to count cron as part of the apdex because it is not a user facing function and if cron tasks are slow it doesn't imply that the site's performance is impacted. Without removing these, long running cron tasks could flag the site as having performance problems, which would cause false positives in the monitoring.
 */
function wpcom_vip_cron_for_newrelic(){
	if ( defined( 'DOING_CRON' ) && DOING_CRON && function_exists( 'newrelic_ignore_apdex' ) && function_exists( 'newrelic_name_transaction' ) ){
		newrelic_name_transaction( 'wp-cron' );
		newrelic_ignore_apdex();
	}
}
add_action( 'muplugins_loaded', 'wpcom_vip_cron_for_newrelic', 11 ); //We are attaching this at plugins_loaded because it's possible for Cron-Control to trigger at plugins_loaded priority 10 (at the latest)

/**
 * Name wp-cli correctly in New Relic and do not count it as part of the Apdex score
 *
 * We don't want to count ongoing WP-CLI requests as part of the apdex because it is not a user facing function and if a WP-CLI request is slow it doesn't imply that the site's performance is impacted. Without removing these WP-CLI requests from the apdex calculation it could flag the site as having performance problems, which would cause false positives in the monitoring.
 */
function wpcom_vip_wpcli_for_newrelic(){
	if ( defined( 'WP_CLI' )
	     && WP_CLI
	     && ( ! defined( 'DOING_CRON' ) || ! DOING_CRON ) // Cron is going to be run via WP-CLI in the near term. We want to keep Cron and WP-CLI separated for better monitoring so we're not going to flag WP_CLI requests that are actually cron requests as WP-CLI.
	     && function_exists( 'newrelic_ignore_apdex' )
	     && function_exists( 'newrelic_name_transaction' ) ){
		newrelic_name_transaction( 'wp-cli' );
		newrelic_ignore_apdex();
	}
}
add_action( 'muplugins_loaded', 'wpcom_vip_wpcli_for_newrelic', 11 ); //We are attaching this at plugins_loaded because it's possible for Cron-Control to trigger at plugins_loaded priority 10 (at the latest), we therefore can't tell until then if this is a WP-CLI request that is also a cron request.
