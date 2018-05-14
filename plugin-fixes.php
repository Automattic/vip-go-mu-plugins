<?php
/*
Plugin Name: VIP Go Plugin Fixes
Description: A collection of fixes for various plugins.
Author: Automattic
Version: 1.0
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

// Make sure the `amp` query var has an explicit value.
// Avoids issues when filtering the deprecated `query_string` hook.
// Copy of upstream fix: https://github.com/Automattic/amp-wp/pull/910
function vip_go_amp_force_query_var_value( $query_vars ) {

	if ( isset( $query_vars[ AMP_QUERY_VAR ] ) ) {
		if ( '' === $query_vars[ AMP_QUERY_VAR ] ) {
			$query_vars[ AMP_QUERY_VAR ] = 1;
		} else if ( '1' !== $query_vars[ AMP_QUERY_VAR ] && 1 !== $query_vars[ AMP_QUERY_VAR ] ) { // Allow for some fuzziness on string/integer type matching.
			global $wp_rewrite;

			// If there is something after /amp/, pretend we didn't hit that rewrite rule.
			if ( $wp_rewrite->use_trailing_slashes && '' !== $query_vars[ AMP_QUERY_VAR ] ) {

				// Check for extra-long slugs, which will truncate and remove the /amp/* part.
				if ( strlen( $query_vars['name'] ) > 190 ) {
					// Give WP_Query an improbable string to get a 404.
					$query_vars['name'] = substr( $query_vars['name'], 0, - 20 ) . substr( md5( $query_vars['name'] ), 0, 20 );
				} else {
					$query_vars['name'] = $query_vars['name'] . "/amp/" . $query_vars[ AMP_QUERY_VAR ];
				}
				unset( $query_vars[ AMP_QUERY_VAR ] );
			}
		}
	}

	return $query_vars;
}
add_filter( 'request', 'vip_go_amp_force_query_var_value', 9, 1 );
