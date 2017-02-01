<?php

/*
 * Schema Customizations
 */

add_filter( 'dbdelta_create_queries', function( $queries ) {
	global $wpdb;
	
	foreach( $queries as $k => $q ) {
	
		// Add meta_key_value index
		if ( preg_match( "|CREATE TABLE ([^ ]*)|", $q, $matches ) && $wpdb->postmeta === $matches[1] ) {
			$queries[$k] = str_replace( 'KEY meta_key (meta_key)', 'KEY meta_key (meta_key), KEY `meta_key_value` (`meta_key`(191), `meta_value`(20))', $q );
			return $queries;
		}
	}

	return $queries;
});
