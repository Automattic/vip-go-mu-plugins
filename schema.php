<?php

/**
 * Plugin Name: VIP Schema
 * Description: Provides VIP-specific database schema customizations.
 * Author: Automattic
 * License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

namespace Automattic\VIP\Schema;

add_filter( 'dbdelta_create_queries', function( $queries ) {
	global $wpdb;
	foreach ( $queries as $k => $q ) {
		// Replace meta_key index with one that indexes meta_value as well
		if ( false !== strpos( $q, "CREATE TABLE {$wpdb->postmeta}" ) ) {
			$search = 'KEY meta_key (meta_key(191))';
			if ( false !== strpos( $q, $search ) ) {
				$status = _get_table_status( $wpdb->postmeta );
				// For InnoDB, Data_length is the approximate amount of space allocated for the clustered index, in bytes.
				// Each InnoDB table has a special index called the clustered index that stores row data.
				if ( defined( 'WP_TESTS_DOMAIN' ) || ( isset( $status['Data_length'] ) && $status['Data_length'] < 52428800 ) ) {
					$queries[ $k ] = str_replace(
						$search,
						sprintf( 'KEY %s', get_postmeta_key_value_index() ),
						$q
					);
				} else {
					trigger_error( sprintf( 'Skip meta_key index modification because Data_length is %s', esc_html( $status['Data_length'] ?? 'N/A' ) ), E_USER_WARNING );
				}
			}
		}
	}

	return $queries;
});

function _get_table_status( string $table ): array {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return $wpdb->get_results( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', $table ), ARRAY_A );
}

function get_postmeta_key_value_index() {
	// 191 for meta_key is max set by core.
	// 100 for meta_value is arbitrary-ish.
	return '`vip_meta_key_value` (`meta_key`(191), `meta_value`(100))';
}
