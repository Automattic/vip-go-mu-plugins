<?php

/*
 * Schema Customizations
 */

namespace Automattic\VIP\Schema;

add_filter( 'dbdelta_create_queries', function( $queries ) {
	global $wpdb;
	foreach( $queries as $k => $q ) {
		// Replace meta_key index with one that indexes meta_value as well
		if ( preg_match( "|CREATE TABLE ([^ ]*)|", $q, $matches ) && $wpdb->postmeta === $matches[1] ) {
			$queries[$k] = str_replace(
				'KEY meta_key (meta_key(191))',
				sprintf( 'KEY %s', get_postmeta_key_value_index() ),
				$q
			);
		}
	}

	return $queries;
});

function get_postmeta_key_value_index() {
	// 191 for meta_key is max set by core
	// 20 for meta_value is arbitrary
	return '`vip_meta_key_value` (`meta_key`(191), `meta_value`(20))';
}
