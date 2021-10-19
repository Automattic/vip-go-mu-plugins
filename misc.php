<?php
/*
Plugin Name: VIP Hosting Miscellaneous
Description: Handles CSS and JS concatenation, Nginx compatibility, SSL verification
Author: Automattic
Version: 1.1
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

// phpcs:ignore WordPressVIPMinimum.Hooks.RestrictedHooks.upload_mimes
add_filter( 'upload_mimes', function( $mimes ) {
	unset( $mimes['flv'] );
	return $mimes;
}, 99999 );

// Ensure we do not send the cache headers through to Varnish,
// so responses obey the cache settings we have configured.
function wpcom_vip_check_for_404_and_remove_cache_headers( $headers ) {
	global $wp_query;

	if ( isset( $wp_query ) && is_404() ) {
		unset( $headers['Expires'] );
		unset( $headers['Cache-Control'] );
		unset( $headers['Pragma'] );
	}
	return $headers;
}
add_filter( 'nocache_headers', 'wpcom_vip_check_for_404_and_remove_cache_headers' );

// Disable admin notice for jetpack_force_2fa
add_filter( 'jetpack_force_2fa_dependency_notice', '__return_false' );

// Cleaner permalink options
add_filter( 'got_url_rewrite', '__return_true' );

// Disable custom fields meta box dropdown (very slow)
add_filter( 'postmeta_form_keys', '__return_false' );

/**
 * This function uses the VIP_VERIFY_STRING and VIP_VERIFY_PATH
 * constants to respond with a verification string at a particular
 * path. So if you have a VIP_VERIFY_STRING of `Hello` and a
 * VIP_VERIFY_PATH of `whatever.html`, then the URL
 * yourdomain.com/whatever.html will return `Hello`.
 *
 * We suggest adding these constants in your `vip-config.php`
 *
 * @return void
 */
function action_wpcom_vip_verify_string() {
	if ( ! defined( 'VIP_VERIFY_PATH' ) || ! defined( 'VIP_VERIFY_STRING' ) || ! isset( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}
	$verification_path = '/' . VIP_VERIFY_PATH;
	if ( $verification_path === $_SERVER['REQUEST_URI'] ) {
		status_header( 200 );
		nocache_headers();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo VIP_VERIFY_STRING;
		exit;
	}
}
add_action( 'parse_request', 'action_wpcom_vip_verify_string', 0 );

/**
 * Disable New Relic browser monitoring on AMP pages, as the JS isn't AMP-compatible
 */
add_action( 'pre_amp_render_post', 'wpcom_vip_disable_new_relic_js' );

/**
 * Fix a race condition in alloptions caching
 *
 * See https://core.trac.wordpress.org/ticket/31245
 */
function _wpcom_vip_maybe_clear_alloptions_cache( $option ) {
	if ( ! wp_installing() ) {
		$alloptions = wp_load_alloptions(); //alloptions should be cached at this point

		if ( isset( $alloptions[ $option ] ) ) { //only if option is among alloptions
			wp_cache_delete( 'alloptions', 'options' );
		}
	}
}

add_action( 'added_option', '_wpcom_vip_maybe_clear_alloptions_cache' );
add_action( 'updated_option', '_wpcom_vip_maybe_clear_alloptions_cache' );
add_action( 'deleted_option', '_wpcom_vip_maybe_clear_alloptions_cache' );

/**
 * Fix a race condition in notoptions caching
 *
 * The scenario is that the option exists in DB but also in notoptions. This could be cause by a race condition when updating notoptions.
 * Further updates trying to store the same value would then fail to change any row and therefore not clear the notoptions key.
 * The solution is that we clear notoptions BEFORE the DB operation as well.
 */
function _vip_maybe_clear_notoptions_cache( $option ) {
	if ( ! wp_installing() ) {
		$notoptions = wp_cache_get( 'notoptions', 'options' );

		if ( isset( $notoptions[ $option ] ) ) {
			wp_cache_delete( 'notoptions', 'options' );
		}
	}
}

add_action( 'add_option', '_vip_maybe_clear_notoptions_cache' );

/**
 * On Go, all API usage must be over HTTPS for security
 *
 * Filter `rest_url` to always return the https:// version
 *
 * This is disabled for local development, but be aware
 * that HTTPS is enforced at the web server level in production,
 * meaning non-HTTPS API calls will result in a 406 error.
 */
if ( defined( 'WPCOM_IS_VIP_ENV' ) && true === WPCOM_IS_VIP_ENV ) {
	add_filter( 'rest_url', '_vip_filter_rest_url_for_ssl' );
}

function _vip_filter_rest_url_for_ssl( $url ) {
	$url = set_url_scheme( $url, 'https' );

	return $url;
}


function wpcom_vip_query_log() {
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$request_uri = $_SERVER['REQUEST_URI'] ?? '';
	if ( '/cache-healthcheck?' === $request_uri ) {
		return;
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended
	$action      = $_REQUEST['action'] ?? 'N/A';
	$num_queries = count( $GLOBALS['wpdb']->queries );
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions
	error_log( 'WPCOM VIP Query Log for ' . $request_uri . '  (action: ' . $action . ') ' . $num_queries . 'q: ' . PHP_EOL . print_r( $GLOBALS['wpdb']->queries, true ) );
}

/**
 * Think carefully before enabling this on a production site. Then
 * if you still want to do it, think again, and talk it over with
 * someone else.
 */
if ( defined( 'WPCOM_VIP_QUERY_LOG' ) && WPCOM_VIP_QUERY_LOG ) {
	if ( ! defined( 'SAVEQUERIES' ) || ! SAVEQUERIES ) {
		define( 'SAVEQUERIES', true );
	}
	// For hyperdb, which doesn't use SAVEQUERIES
	$GLOBALS['wpdb']->save_queries = SAVEQUERIES;
	add_action( 'shutdown', 'wpcom_vip_query_log' );
}

/**
 * Improve perfomance of the `_WP_Editors::wp_link_query` method
 *
 * The WordPress core is currently not setting `no_found_rows` inside the `_WP_Editors::wp_link_query`
 * See https://core.trac.wordpress.org/ticket/38784
 *
 * Since the `_WP_Editors::wp_link_query` method is not using the `found_posts` nor `max_num_pages`
 * properties of `WP_Query` class, the `SQL_CALC_FOUND_ROWS` in produced SQL query is extra and
 * useless.
 *
 */
function wpcom_vip_wp_link_query_args( $query ) {
	//Since the WP_Query is not checking the $found_posts nor $max_num_pages properties
	//we don't need to know the total number of matching posts in the database
	$query['no_found_rows'] = true;

	return $query;
}

add_filter( 'wp_link_query_args', 'wpcom_vip_wp_link_query_args', 10, 1 );

/**
 * Stop Woocommerce from trying to create files on read-only filesystem
 */
add_filter( 'woocommerce_install_skip_create_files', '__return_true' );

/**
 * On Go, multisites are technically subdirectory installs so site search only
 * searches the 'path' on /network/sites.php. This improves site search results by
 * adding 'domain' to the columns to search.
 */
add_filter( 'site_search_columns', function( $cols ) {
	$cols[] = 'domain';
	return $cols;
});
