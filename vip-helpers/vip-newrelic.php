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
 * The following functions are for platform level changes and should only be changed after consulting with WordPress VIP
 */
if ( extension_loaded( 'newrelic' ) ) {
	add_action( 'muplugins_loaded', 'wpcom_vip_add_uri_to_newrelic' );

	// We are attaching this at muplugins_loaded because Cron-Control is loaded at muplugins_loaded priority 10
	add_action( 'muplugins_loaded', 'wpcom_vip_cron_for_newrelic', 11 );

	// This must be hooked later than wpcom_vip_cron_for_newrelic to allow values to be overwritten
	add_action( 'muplugins_loaded', 'wpcom_vip_wpcli_for_newrelic', 12 );

	add_filter( 'rest_dispatch_request', 'wpcom_vip_rest_routes_for_newrelic', 10, 3 );
}

/**
 * Add the exact URI to NewRelic tracking but only if we're not in the admin
 */
function wpcom_vip_add_uri_to_newrelic() {
	if ( ! is_admin() && function_exists( 'newrelic_add_custom_parameter' ) ) {
		newrelic_capture_params();
		newrelic_add_custom_parameter( 'HTTP_REFERER', $_SERVER['HTTP_REFERER'] ?? '' );        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		newrelic_add_custom_parameter( 'HTTP_USER_AGENT', $_SERVER['HTTP_USER_AGENT'] ?? '' );  // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__HTTP_USER_AGENT__
		newrelic_add_custom_parameter( 'HTTPS', is_ssl() );
	}
}

/**
 * Name cron correctly in New Relic and do not count it as part of the Apdex score.
 *
 * We don't want to count cron as part of the apdex because it is not a user facing function and if cron tasks are slow it doesn't imply that the site's performance is impacted. Without removing these, long running cron tasks could flag the site as having performance problems, which would cause false positives in the monitoring.
 */
function wpcom_vip_cron_for_newrelic() {
	if ( wp_doing_cron()
		&& function_exists( 'newrelic_name_transaction' )
		&& function_exists( 'newrelic_background_job' )
		&& function_exists( 'newrelic_ignore_apdex' )
	) {
		newrelic_name_transaction( 'wp-cron' );
		newrelic_background_job( true );
		// N.B. VIP Go sites execute cron events through WP CLI, so the event
		// can be determined in the New Relic 'wp-cli-cmd-args' custom parameter
		newrelic_ignore_apdex();
	}
}

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

		$wp_cli_arguments = \WP_CLI::get_runner()->arguments;
		if ( empty( $wp_cli_arguments ) ) {
			// Not much to do at this point
			return;
		}
		if ( ! is_array( $wp_cli_arguments ) ) {
			$wp_cli_arguments = [ $wp_cli_arguments ];
		}
		array_unshift( $wp_cli_arguments, 'wp' );
		$cmd = implode( ' ', $wp_cli_arguments );
		// e.g. `wp option get siteurl`
		newrelic_add_custom_parameter( 'wp-cli-cmd', $cmd );

		newrelic_ignore_apdex();
	}
}

/**
 * Name wp-api requests correctly in New Relic
 *
 * By default wp-api requests are tagged under index.php
 * We'd want to have them tagged with the proper rest route used.
 * While we are using the rest_dispatch_request filter, we're using it as an action without modifying the results.
 */
function wpcom_vip_rest_routes_for_newrelic( $dispatch_results, $request, $route ) {
	$functions_exist = function_exists( 'newrelic_add_custom_parameter' ) && function_exists( 'newrelic_name_transaction' );
	$is_cli          = defined( 'WP_CLI' ) && WP_CLI;

	if ( $functions_exist && ! wp_doing_cron() && ! $is_cli ) {
		newrelic_name_transaction( $route );
		newrelic_add_custom_parameter( 'wp-api', 'true' );
		newrelic_add_custom_parameter( 'wp-api-route', $route );
	}

	return $dispatch_results;
}
