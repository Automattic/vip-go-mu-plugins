<?php
/*
Plugin Name: VIP Go Plugin Compat
Description: A collection of compatibility fixes to make sure various plugins run smoothly on VIP Go.
Author: Automattic
Version: 1.0
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

/**
 * Contact Form 7 (https://en-ca.wordpress.org/plugins/contact-form-7/)
 *
 * The plugin attempts to write to a `wp-content` path which will fail.
 * These files are transient and only meant to be included as attachment,
 * so let's just tell CF7 to put them in `/tmp/cf7/`.
 */
if ( ! defined( 'WPCF7_UPLOADS_TMP_DIR' ) ) {
	define( 'WPCF7_UPLOADS_TMP_DIR', get_temp_dir() . 'cf7/' );
}

/**
 * Recent versions of CF7 attempt to clean-up uploaded attachments 
 * somewhere within `wp-content`, ignoring the value of WPCF7_UPLOADS_TMP_DIR if 
 * it is not within `wp-content`. This does not work because we disable `open_dir()`, 
 * flooding the PHP logs with `opendir() Failed to open directory` warnings.
 *
 * @return void
 */
function vip_disable_wpcf7_cleanup_upload_files() {
    $priority = has_action( 'shutdown', 'wpcf7_cleanup_upload_files' );
    if ( false !== $priority ){
        if ( function_exists( 'wpcf7_upload_tmp_dir' ) ) {
            // Check if the directories match (e.g. plugin is an old version, or has been patched)
            if ( WPCF7_UPLOADS_TMP_DIR !== wpcf7_upload_tmp_dir() ){
                remove_action( 'shutdown', 'wpcf7_cleanup_upload_files', $priority );
            }
        }
    }
}
add_action( 'plugins_loaded', 'vip_disable_wpcf7_cleanup_upload_files' );

/**
 * AMP for WordPress (https://github.com/ampproject/amp-wp)
 * Make sure the `amp` query var has an explicit value.
 * Avoids issues when filtering the deprecated `query_string` hook.
 *
 * Copy of upstream fix: https://github.com/Automattic/amp-wp/pull/910
 */
function vip_go_amp_force_query_var_value( $query_vars ) {
	// Don't bother if AMP is not active
	if ( ! defined( 'AMP_QUERY_VAR' ) ) {
		return $query_vars;
	}

	if ( isset( $query_vars[ AMP_QUERY_VAR ] ) ) {
		if ( '' === $query_vars[ AMP_QUERY_VAR ] ) {
			$query_vars[ AMP_QUERY_VAR ] = 1;
		} elseif ( '1' !== $query_vars[ AMP_QUERY_VAR ] && 1 !== $query_vars[ AMP_QUERY_VAR ] ) { // Allow for some fuzziness on string/integer type matching.
			global $wp_rewrite;

			// If there is something after /amp/, pretend we didn't hit that rewrite rule.
			if ( $wp_rewrite->use_trailing_slashes && '' !== $query_vars[ AMP_QUERY_VAR ] ) {

				// Check for extra-long slugs, which will truncate and remove the /amp/* part.
				if ( strlen( $query_vars['name'] ) > 190 ) {
					// Give WP_Query an improbable string to get a 404.
					$query_vars['name'] = substr( $query_vars['name'], 0, - 20 ) . substr( md5( $query_vars['name'] ), 0, 20 );
				} else {
					$query_vars['name'] = $query_vars['name'] . '/amp/' . $query_vars[ AMP_QUERY_VAR ];
				}
				unset( $query_vars[ AMP_QUERY_VAR ] );
			}
		}
	}

	return $query_vars;
}
add_filter( 'request', 'vip_go_amp_force_query_var_value', 9, 1 );

/**
 * Enable support for multi-file uploads since VIP disables open_dir()
 *
 * @link https://docs.gravityforms.com/gform_cleanup_target_dir/
 *
 * @return void
 */
function vip_disable_gform_cleanup_target_dir() {
	if ( class_exists( 'GFForms' ) ) {
		if ( version_compare( GFForms::$version, '2.6.3.4', '>=' ) ) {
			add_filter( 'gform_cleanup_target_dir', '__return_false' );
		}
	}
}
add_action( 'plugins_loaded', 'vip_disable_gform_cleanup_target_dir' );
