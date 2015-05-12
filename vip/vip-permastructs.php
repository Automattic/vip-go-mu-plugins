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
 * Use a custom CDN host for displaying theme images and media library content.
 *
 * Please get in touch before using this as it can break your site.
 *
 * @param array $args Array of args
 * 		string|array cdn_host_media => Hostname of the CDN for media library assets.
 * 		string|array cdn_host_static => Optional. Hostname of the CDN for static assets.
 * 		bool include_admin => Optional. Whether the custom CDN host should be used in the admin context as well.
 * 		bool disable_ssl => Optional. Whether SSL should be disabled for the custom CDN.
 */
function wpcom_vip_load_custom_cdn( $args ) {
	if ( defined( 'WPCOM_SANDBOXED' ) && WPCOM_SANDBOXED )
		return;

	$is_wpcom = ( defined( 'IS_WPCOM' ) && IS_WPCOM );

	if ( false === WPCOM_IS_VIP_ENV && ! $is_wpcom )
		return;

	$args = wp_parse_args( $args, array(
		'cdn_host_media' => '',
		'cdn_host_static' => '',
		'include_admin' => false,
		'disable_ssl' => false,
	) );

	if ( ! $args['include_admin'] && is_admin() )
		return;

	$cdn_host_static = _wpcom_vip_cdn_clean_hosts( $args['cdn_host_static'] );
	$cdn_host_media = _wpcom_vip_cdn_clean_hosts( $args['cdn_host_media'] );

	if ( ! empty( $cdn_host_static ) ) {
		_wpcom_vip_cdn_load_static( $cdn_host_static );

		if ( true === $args['disable_ssl'] ) {
			_wpcom_vip_cdn_disable_ssl( $cdn_host_static );
		}
	}

	if ( ! empty( $cdn_host_media ) ) {
		_wpcom_vip_cdn_load_media( $cdn_host_media );

		if ( true === $args['disable_ssl'] ) {
			_wpcom_vip_cdn_disable_ssl( $cdn_host_media );
		}
	}

}

/**
 * Sanitizes a set of hosts
 *
 * Private function; do not call directly.
 *
 * @internal
 */
function _wpcom_vip_cdn_clean_hosts( $hosts ) {
	if ( ! is_array( $hosts ) )
		$hosts = array( $hosts );

	$hosts = array_map( function( $host ) {
		return parse_url( esc_url_raw( $host ), PHP_URL_HOST );
	}, $hosts );

	$hosts = array_filter( $hosts );

	return $hosts;
}

/**
 * Pick a random host from a group
 *
 * Private function; do not call directly.
 *
 * @internal
 *
 * @param array $hosts
 * @param string $url
 */
function _wpcom_vip_cdn_pick_random_host( $hosts, $url ) {
	$array_length = count( $hosts );

	if ( 1 === $array_length )
		return array_pop( $hosts );

	// Borrowed from WP.com
	// Makes the random number more closely tied to the current URL.
	// This gives us a more consistent pick between pagelods.
	// It's slightly less efficient at the PHP-level, but we get a higher CDN hit rate.
	srand( crc32( basename( $url ) ) );
	$index = rand( 0, ( $array_length - 1 ) );
	srand();

	return $hosts[ $index ];
}

/**
 * Static CDN
 *
 * Private function; do not call directly.
 *
 * @internal
 */
function _wpcom_vip_cdn_load_static( $cdn_host_static ) {
	$cdn_host_static = _wpcom_vip_cdn_clean_hosts( $cdn_host_static );

	add_filter( 'wpcom_staticize_subdomain_host', function( $host, $url ) use ( $cdn_host_static ) {
		return _wpcom_vip_cdn_pick_random_host( $cdn_host_static, $url );
	}, 999, 2 );
}

/**
 * Media CDN
 *
 * Private function; do not call directly.
 *
 * @internal
 */
function _wpcom_vip_cdn_load_media( $cdn_host_media ) {
	$wpcom_host_media = function_exists( 'wpcom_get_blog_files_url' ) ? parse_url( wpcom_get_blog_files_url(), PHP_URL_HOST ) : '[\w]+.files.wordpress.com';

	add_filter( 'wp_get_attachment_url', function( $url, $attachment_id ) use ( $cdn_host_media ) {
		$host = _wpcom_vip_cdn_pick_random_host( $cdn_host_media, $url );
		return _wpcom_vip_custom_cdn_replace( $url, $host );
	}, 999, 2 );

	add_filter( 'the_content', function( $content ) use ( $wpcom_host_media, $cdn_host_media ) {
		if ( false !== strpos( $content, 'files.wordpress.com' ) ) {
			$content = preg_replace_callback( '#(https?://' . preg_quote( $wpcom_host_media ) . '[^\s\'">]+)#', function( $matches ) use ( $cdn_host_media ) {
				$host = _wpcom_vip_cdn_pick_random_host( $cdn_host_media, $url );
				return _wpcom_vip_custom_cdn_replace( $matches[1], $host );
			}, $content );
		}
		return $content;
	}, 999 );
}

/**
 * Disable SSL for custom CDN
 *
 * Private function; do not call directly
 *
 * @internal
 */
function _wpcom_vip_cdn_disable_ssl( $domains ) {
	add_filter( 'wp_get_attachment_url', function( $url ) {
		return str_replace( 'https', 'http', $url );
	}, 1000 );

	add_filter( 'the_content', function( $content ) use ( $domains ) {
		foreach( $domains as $domain ) {
			return str_replace( "https://{$domain}", "http://{$domain}", $content );
		}
	}, 1000 );

	add_filter( 'wpcom_static_domain_url', function( $url ) {
		return preg_replace( "|^https|", "http", $url );
	} );
}

/**
 * Replace the hostname in a URL
 *
 * @param string $url Original URL
 * @param string $cdn_host Replacement hostname
 * @return string Updated URL
 * @see wpcom_vip_load_custom_cdn()
 */
function _wpcom_vip_custom_cdn_replace( $url, $cdn_host ) {
	return preg_replace( '|://[^/]+?/|', "://$cdn_host/", $url );
}
