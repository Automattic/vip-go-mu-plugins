<?php

/**
 * Enables a custom permastruct, if the site wants to use one that's not the WP.com default (/yyyy/mm/dd/post-name/)
 *
 * Usage: wpcom_vip_load_permastruct( '/%category%/%postname%/' );
 *
 * @link https://lobby.vip.wordpress.com/wordpress-com-documentation/pretty-permalinks-and-custom-rewrite-rules/ Change Your Pretty Permalinks
 * @param string $new_permastruct
 */
function wpcom_vip_load_permastruct( $new_permastruct ) {
	define( 'WPCOM_VIP_CUSTOM_PERMALINKS', true );
	global $wpcom_vip_permalink_structure;
	$wpcom_vip_permalink_structure = $new_permastruct;
	add_filter( 'pre_option_permalink_structure', '_wpcom_vip_filter_permalink_structure', 99 ); // needs to be higher priority so we don't conflict with the WP.com filter
}

/**
 * Applies the new permalink structure to the option value
 *
 * @link https://lobby.vip.wordpress.com/wordpress-com-documentation/pretty-permalinks-and-custom-rewrite-rules/ Change Your Pretty Permalinks
 * @return string The new permastruct
 */
function _wpcom_vip_filter_permalink_structure() {
	global $wpcom_vip_permalink_structure;
	return $wpcom_vip_permalink_structure;
}

/**
 * Enables a custom or no category base, if the site wants to use one that's not the WP.com default (/category/)
 *
 * Usage:
 *     wpcom_vip_load_category_base( '' );
 *     wpcom_vip_load_category_base( 'section' );
 *
 * @link https://lobby.vip.wordpress.com/wordpress-com-documentation/pretty-permalinks-and-custom-rewrite-rules/ Change Your Pretty Permalinks
 * @param string $new_category_base New category base prefix
 */
function wpcom_vip_load_category_base( $new_category_base ) {
	define( 'WPCOM_VIP_CUSTOM_CATEGORY_BASE', true );
	global $wpcom_vip_category_base;
	$wpcom_vip_category_base = $new_category_base;
	add_filter( 'pre_option_category_base', '_wpcom_vip_filter_category_base', 99 ); // needs to be higher priority so we don't conflict with the WP.com filter


	// For empty category base, remove the '/category/' from the base, but include the parent category if it's a child
	if ( '' === $new_category_base ) {
		add_filter( 'category_link', function ( $link, $term_id ) {
			return '/' . get_category_parents( $term_id, false, '/', true );
		}, 9, 2 );
	}
}

/**
 * Applies the new category base to the option value
 *
 * @link https://lobby.vip.wordpress.com/wordpress-com-documentation/pretty-permalinks-and-custom-rewrite-rules/ Change Your Pretty Permalinks
 * @return string The category base prefix
 */
function _wpcom_vip_filter_category_base() {
	global $wpcom_vip_category_base;
	return $wpcom_vip_category_base;
}

/**
 * Enables a custom or no tag base, if the site wants to use one that's not the WP.com default (/tag/)
 *
 * Usage:
 *     wpcom_vip_load_tag_base( 'section' );
 *
 * @link https://lobby.vip.wordpress.com/wordpress-com-documentation/pretty-permalinks-and-custom-rewrite-rules/ Change Your Pretty Permalinks
 * @param string $new_tag_base New tag base prefix
 */
function wpcom_vip_load_tag_base( $new_tag_base ) {
	define( 'WPCOM_VIP_CUSTOM_TAG_BASE', true );
	global $wpcom_vip_tag_base;
	$wpcom_vip_tag_base = $new_tag_base;
	add_filter( 'pre_option_tag_base', '_wpcom_vip_filter_tag_base', 99 ); // needs to be higher priority so we don't conflict with the WP.com filter/ needs to be higher priority so we don't conflict with the WP.com filter
}

/**
 * Applies the new tag base to the option value
 *
 * @link https://lobby.vip.wordpress.com/wordpress-com-documentation/pretty-permalinks-and-custom-rewrite-rules/ Change Your Pretty Permalinks
 * @return string The tag base prefix
 */
function _wpcom_vip_filter_tag_base() {
	global $wpcom_vip_tag_base;
	return $wpcom_vip_tag_base;
}

/**
 * Use secure URLs in rel_canonical
 */
function wpcom_vip_https_canonical_url() {
	// Note: rel_canonical is not in core yet
	// https://core.trac.wordpress.org/ticket/30581
	add_filter( 'rel_canonical', function( $link ) {
		return str_replace( 'http://', 'https://', $link );
	}, 99 );
}

/**
 * VIPs and other themes can declare the permastruct, tag and category bases in their themes.
 * This is done by filtering the option.
 *
 * To ensure we're using the freshest values, and that the option value is available earlier
 * than when the theme is loaded, we need to get each option, save it again, and then
 * reinitialize wp_rewrite.
 *
 * This is most commonly used in our code to flush rewrites
 */
function wpcom_vip_refresh_wp_rewrite() {
	global $wp_rewrite;

	// Permastructs available in the options table and their core defaults
	$permastructs = array(
		'permalink_structure' => '/%year%/%monthnum%/%day%/%postname%/',
		'category_base'       => '',
		'tag_base'            => '',
	);
	foreach ( $permastructs as $option_key => $default_value ) {
		$filter   = 'pre_option_' . $option_key;
		$callback = '_wpcom_vip_filter_' . $option_key;

		$option_value = get_option( $option_key );

		$reapply = has_filter( $filter, $callback );
		// If this value isn't filtered by the VIP, used the default wpcom value
		if ( ! $reapply ) {
			$option_value = $default_value;
		} else {
			remove_filter( $filter, $callback, 99 );
		}
		// Save the precious
		update_option( $option_key, $option_value );
		// Only reapply the filter if it was applied previously
		// as it overrides the option value with a global variable
		if ( $reapply ) {
			add_filter( $filter, $callback, 99 );
		}
	}

	// Reconstruct WP_Rewrite and make sure we persist any custom endpoints, etc.
	$old_values   = array();
	$custom_rules = array(
		'extra_rules',
		'non_wp_rules',
		'endpoints',
	);
	foreach ( $custom_rules as $key ) {
		$old_values[ $key ] = $wp_rewrite->$key;
	}
	$wp_rewrite->init();
	foreach ( $custom_rules as $key ) {
		$wp_rewrite->$key = array_merge( $old_values[ $key ], $wp_rewrite->$key );
	}
}
