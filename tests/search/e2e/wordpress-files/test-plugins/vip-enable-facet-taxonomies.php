<?php
// phpcs:ignoreFile
/**
 * Plugin Name: Enable taxonomies for facets
 * Description: By default, no taxonomies are configured for aggregation. This plugin configures the taxonomies for facet use.
 * Version:     1.0.0
 * Author:      Automattic
 * License:     GPLv2 or later
 */


/**
 * Add post_tag to taxonomies for faceting.
 */
add_filter( 'ep_facet_include_taxonomies', 'vip_taxonomy_facets_included' );
function vip_taxonomy_facets_included( $taxonomies ) {
	$post_tag = get_taxonomy( 'post_tag' );
	$taxonomies = array( 'post_tag' => $post_tag );
	return $taxonomies;
}
