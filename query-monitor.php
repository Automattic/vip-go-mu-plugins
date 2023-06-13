<?php
/**
 * Integration of the Query Monitor plugin for WordPress
 */

/**
 * Determines if Query Monitor should be enabled. We don't
 * want to load it if we don't have to.
 *
 *  - If logged-in user has the `view_query_monitor`
 *    capability, Query Monitor is enabled.
 *  - If a QM_COOKIE is detected, Query Monitor is enabled.
 *
 * Note that we have to set the value for QM_COOKIE here,
 * in order to detect it.
 *
 * Note that we cannot use is_automattician this early, as
 * the user has not yet been set.
 *
 * @param $enable
 *
 * @return bool
 */
function wpcom_vip_qm_enable( $enable ) {

	if ( ! defined( 'QM_COOKIE' ) ) {
		define( 'QM_COOKIE', 'query_monitor_' . COOKIEHASH );
	}

	if ( defined( 'WP_INSTALLING' ) ) {
		return false;
	}

	if ( current_user_can( 'view_query_monitor' ) ) {
		return true;
	}

	// We're not validating the cookie here as QM will do that later
	if ( isset( $_COOKIE[ QM_COOKIE ] ) ) {
		return true;
	}

	if ( defined( 'VIP_GO_APP_ENVIRONMENT' ) && 'production' !== constant( 'VIP_GO_APP_ENVIRONMENT' ) ) {
		return true;
	}

	return $enable;
}
add_filter( 'wpcom_vip_qm_enable', 'wpcom_vip_qm_enable' );

// Enable by default for non-prod environments and only when admin bar is showing.
if ( defined( 'VIP_GO_APP_ENVIRONMENT' ) && 'production' !== constant( 'VIP_GO_APP_ENVIRONMENT' ) && true === apply_filters( 'show_admin_bar', false ) ) {
	add_filter( 'wpcom_vip_qm_enable', '__return_true' );
}

/**
 * Require the plugin files for Query Monitor, faking a
 * plugin activation, if it's the first time.
 */
function wpcom_vip_qm_require() {
	/**
	 * Filter whether Query Monitor is activated; return true,
	 * if QM should be activated.
	 *
	 * @param bool $enable False by default
	 */
	if ( ! apply_filters( 'wpcom_vip_qm_enable', false ) ) {
		return;
	}

	if ( ! defined( 'SAVEQUERIES' ) ) {
		define( 'SAVEQUERIES', true );
	}

	// For hyperdb, which doesn't use SAVEQUERIES
	global $wpdb;
	$wpdb->save_queries = SAVEQUERIES;

	$wpcom_vip_qm_file = __DIR__ . '/query-monitor/query-monitor.php';

	require_once $wpcom_vip_qm_file;

	// Something stopped QueryMonitor from loading; bail.
	if ( ! class_exists( 'QueryMonitor' ) ) {
		return;
	}

	// Because we're including Query Monitor as an MU plugin, we need to
	// manually call the `activate` method on `activation`.
	if ( 0 === get_option( 'wpcom_vip_qm_activated', 0 ) ) {
		QM_Activation::init( $wpcom_vip_qm_file )->activate( true );
		update_option( 'wpcom_vip_qm_activated', 1, true );
	}

	// We know we haven't got the QM DB drop-in in place, so don't show the message
	add_filter( 'qm/show_extended_query_prompt', '__return_false' );

	/**
	 * Load QM plugins
	 */
	if ( file_exists( __DIR__ . '/qm-plugins/qm-alloptions/qm-alloptions.php' ) ) {
		require_once __DIR__ . '/qm-plugins/qm-alloptions/qm-alloptions.php';
	}
	if ( file_exists( __DIR__ . '/qm-plugins/qm-object-cache/qm-object-cache.php' ) ) {
		require_once __DIR__ . '/qm-plugins/qm-object-cache/qm-object-cache.php';
	}
	if ( file_exists( __DIR__ . '/qm-plugins/qm-apcu-cache/qm-apcu-cache.php' ) ) {
		require_once __DIR__ . '/qm-plugins/qm-apcu-cache/qm-apcu-cache.php';
	}
	if ( file_exists( __DIR__ . '/qm-plugins/qm-cron/qm-cron.php' ) ) {
		require_once __DIR__ . '/qm-plugins/qm-cron/qm-cron.php';
	}
	if ( file_exists( __DIR__ . '/qm-plugins/qm-vip/qm-vip.php' ) ) {
		require_once __DIR__ . '/qm-plugins/qm-vip/qm-vip.php';
	}
	if ( file_exists( __DIR__ . '/qm-plugins/qm-db-connections/qm-db-connections.php' ) ) {
		require_once __DIR__ . '/qm-plugins/qm-db-connections/qm-db-connections.php';
	}
}
add_action( 'plugins_loaded', 'wpcom_vip_qm_require', 1 );

/**
 * Hooks the wp action to avoid showing Query Monitor on 404 pages
 * to non-logged in users, as it is likely to get caught in the
 * Varnish cache.
 */
function wpcom_vip_qm_disable_on_404() {
	if ( is_404() && ! is_user_logged_in() && isset( $_COOKIE[ QM_COOKIE ] ) ) {
		add_filter( 'qm/dispatch/ajax', '__return_false' );
		add_filter( 'qm/dispatch/html', '__return_false' );
	}
}
add_action( 'wp', 'wpcom_vip_qm_disable_on_404' );

// We are putting dispatchers as last so that QM still can catch other operations in shutdown action
// See https://github.com/johnbillion/query-monitor/pull/549
function change_dispatchers_shutdown_priority( array $dispatchers ) {
	if ( is_array( $dispatchers ) ) {
		if ( isset( $dispatchers['html'] ) ) {
			$html_dispatcher = $dispatchers['html'];
			remove_action( 'shutdown', array( $html_dispatcher, 'dispatch' ), 9 );

			// To prevent collision with log2logstashs fastcgi_finish_request, set this priority just a bit before it.
			add_action( 'shutdown', array( $html_dispatcher, 'dispatch' ), PHP_INT_MAX - 1 );
		}
		if ( isset( $dispatchers['ajax'] ) ) {
			$ajax_dispatcher = $dispatchers['ajax'];
			remove_action( 'shutdown', array( $ajax_dispatcher, 'dispatch' ), 0 );

			// To prevent collision with log2logstashs fastcgi_finish_request, set this priority just a bit before it.
			add_action( 'shutdown', array( $ajax_dispatcher, 'dispatch' ), PHP_INT_MAX - 1 );
		}
	}

	return $dispatchers;
}
add_filter( 'qm/dispatchers', 'change_dispatchers_shutdown_priority', PHP_INT_MAX, 1 );
