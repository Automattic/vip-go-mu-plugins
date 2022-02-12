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
				/* If the table does not exists, simply replace the index definition */
				if ( null === $status ) {
					$queries[ $k ] = str_replace( $search, get_postmeta_key_value_index( 'meta_key' ), $q );
				} else {
					/* The table exists, we need to check the index */
					$indices = _get_table_indices( $wpdb->postmeta );
					if ( isset( $indices['meta_key'] ) ) {
						/* If meta_key index covers both meta_key and meta_value, modify meta_key index definition to match */
						if ( get_postmeta_key_value_index( 'meta_key' ) === $indices['meta_key'] ) { // NOSONAR
							$queries[ $k ] = str_replace( $search, get_postmeta_key_value_index( 'meta_key' ), $q );
						}
						/* Otherwise, do nothing, rely upon a cron job to fix this */
					}
				}
			}
		}
	}

	return $queries;
});

function _get_table_status( string $table ): ?array {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', $table ), ARRAY_A );
}

function _get_table_indices( string $table ): array {
	global $wpdb;
	return _parse_indices( $wpdb->get_results( "SHOW INDEX FROM {$table}", ARRAY_A ) ); // phpcs:ignore WordPress.DB
}

function _parse_indices( array $rows ): array {
	$result = [];
	$flags  = [];
	$keys   = [];
	foreach ( $rows as $row ) {
		$is_primary  = 'PRIMARY' === $row['Key_name'];
		$is_unique   = ! $is_primary && '0' === $row['Non_unique'];
		$is_fulltext = 'FULLTEXT' === $row['Index_type'];
		$index_name  = $row['Key_name'];
		$column      = $row['Column_name'];
		$sequence    = (int) $row['Seq_in_index'];
		$prefix      = null === $row['Sub_part'] ? null : (int) $row['Sub_part'];

		if ( isset( $keys[ $index_name ] ) ) {
			$keys[ $index_name ][ $sequence ] = sprintf( '`%s`%s', $column, null === $prefix ? '' : "({$prefix})" );
		} else {
			$keys[ $index_name ] = [ 
				$sequence => sprintf( '`%s`%s', $column, null === $prefix ? '' : "({$prefix})" ),
			];

			$flags[ $index_name ] = $is_primary ? 'PRIMARY KEY' : ( $is_unique ? 'UNIQUE KEY' : ( $is_fulltext ? 'FULLTEXT KEY' : 'KEY' ) ); // NOSONAR
		}
	}

	foreach ( $keys as $name => $columns ) {
		$columns = join( ', ', $columns );
		if ( 'PRIMARY' === $name ) {
			$result[ $name ] = 'PRIMARY KEY  (' . $columns . ')';
		} else {
			$result[ $name ] = sprintf( '%s `%s` (%s)', $flags[ $name ], $name, $columns );
		}
	}

	return $result;
}

function get_postmeta_key_value_index( string $index_name ) {
	// 191 for meta_key is max set by core.
	// 100 for meta_value is arbitrary-ish.
	return sprintf( 'KEY `%s` (`meta_key`(191), `meta_value`(100))', $index_name );
}
