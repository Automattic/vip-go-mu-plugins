<?php

/**
 * Enables a custom permastruct, if the site wants to use one that's not the WP.com default (/yyyy/mm/dd/post-name/)
 *
 * Usage: wpcom_vip_load_permastruct( '/%category%/%postname%/' );
 *
 * @link http://vip.wordpress.com/documentation/change-your-pretty-permalinks-or-add-custom-rewrite-rules/ Change Your Pretty Permalinks
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
 * @link http://vip.wordpress.com/documentation/change-your-pretty-permalinks-or-add-custom-rewrite-rules/ Change Your Pretty Permalinks
 * @param string $permastruct The new permastruct
 * @return string The new permastruct
 */
function _wpcom_vip_filter_permalink_structure( $permastruct ) {
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
 * @link http://vip.wordpress.com/documentation/change-your-pretty-permalinks-or-add-custom-rewrite-rules/ Change Your Pretty Permalinks
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
 * @link http://vip.wordpress.com/documentation/change-your-pretty-permalinks-or-add-custom-rewrite-rules/ Change Your Pretty Permalinks
 * @param string $category_base New category base prefix
 * @return string The category base prefix
 */
function _wpcom_vip_filter_category_base( $category_base ) {
	global $wpcom_vip_category_base;
	return $wpcom_vip_category_base;
}

/**
 * Enables a custom or no tag base, if the site wants to use one that's not the WP.com default (/tag/)
 *
 * Usage:
 *     wpcom_vip_load_tag_base( 'section' );
 *
 * @link http://vip.wordpress.com/documentation/change-your-pretty-permalinks-or-add-custom-rewrite-rules/ Change Your Pretty Permalinks
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
 * @link http://vip.wordpress.com/documentation/change-your-pretty-permalinks-or-add-custom-rewrite-rules/ Change Your Pretty Permalinks
 * @param string $tag_base New tag base prefix
 * @return string The tag base prefix
 */
function _wpcom_vip_filter_tag_base( $tag_base ) {
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
