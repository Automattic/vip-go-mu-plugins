<?php
/*
Plugin Name: VIP Hosting Miscellaneous
Description: Handles CSS and JS concatenation, Nginx compatibility, SSL verification
Author: Automattic
Version: 1.1
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

// Ensure we do not send the cache headers through to Varnish,
// so responses obey the cache settings we have configured.
function wpcom_vip_check_for_404_and_remove_cache_headers( $headers ) {
	if ( is_404() ) {
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

// Checking for VIP_GO_ENV allows this code to work outside VIP Go environments,
// albeit without concatenation of JS and CSS.
if ( defined( 'VIP_GO_ENV' ) && false !== VIP_GO_ENV ) {
	// Activate concatenation
	if ( ! isset( $_GET['concat_js'] ) || 'yes' === $_GET['concat_js'] ) {
		require __DIR__ .'/http-concat/jsconcat.php';
	}

	if ( ! isset( $_GET['concat_css'] ) || 'yes' === $_GET['concat_css'] ) {
		require __DIR__ .'/http-concat/cssconcat.php';
	}
}

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
	if ( ! defined( 'VIP_VERIFY_PATH' ) || ! defined( 'VIP_VERIFY_STRING' ) ) {
		return;
	}
	$verification_path = '/' . VIP_VERIFY_PATH;
	if ( $verification_path === $_SERVER['REQUEST_URI'] ) {
		status_header( 200 );
		echo VIP_VERIFY_STRING;
		exit;
	}
}
add_action( 'template_redirect', 'action_wpcom_vip_verify_string' );

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

add_action( 'added_option',   '_wpcom_vip_maybe_clear_alloptions_cache' );
add_action( 'updated_option', '_wpcom_vip_maybe_clear_alloptions_cache' );
add_action( 'deleted_option', '_wpcom_vip_maybe_clear_alloptions_cache' );

if ( defined( 'VIP_CUSTOM_PINGS' ) && true === VIP_CUSTOM_PINGS ) {
	remove_action( 'do_pings', 'do_all_pings' );
	add_action( 'do_pings', function() {
		global $wpdb;

		// Do pingbacks
		if ( apply_filters( 'vip_do_pingbacks', true ) ) {
			while ($ping = $wpdb->get_row("SELECT ID, post_content, meta_id FROM {$wpdb->posts}, {$wpdb->postmeta} WHERE {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id AND {$wpdb->postmeta}.meta_key = '_pingme' LIMIT 1")) {
				delete_metadata_by_mid( 'post', $ping->meta_id );
				pingback( $ping->post_content, $ping->ID );
			}
		}


		// Do Enclosures
		if ( apply_filters( 'vip_do_enclosures', true ) ) {
			while ($enclosure = $wpdb->get_row("SELECT ID, post_content, meta_id FROM {$wpdb->posts}, {$wpdb->postmeta} WHERE {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id AND {$wpdb->postmeta}.meta_key = '_encloseme' LIMIT 1")) {
				delete_metadata_by_mid( 'post', $enclosure->meta_id );
				do_enclose( $enclosure->post_content, $enclosure->ID );
			}
		}

		// Do Trackbacks
		if ( apply_filters( 'vip_do_trackbacks', false ) ) {
			$trackbacks = $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE to_ping <> '' AND post_status = 'publish' LIMIT 10");
			if ( is_array($trackbacks) )
				foreach ( $trackbacks as $trackback )
					do_trackbacks($trackback);
		}

		// Do Update Services/Generic Pings
		generic_ping();
	});
}

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
	if ( '/cache-healthcheck?' === $_SERVER['REQUEST_URI'] ) {
		return;
	}
	$num_queries = count( $GLOBALS['wpdb']->queries );
	error_log( 'WPCOM VIP Query Log for ' . $_SERVER['REQUEST_URI'] . '  (action: ' . $_REQUEST['action'] . ') ' . $num_queries . 'q: ' . PHP_EOL . print_r( $GLOBALS['wpdb']->queries, true ) );
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
