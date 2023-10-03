<?php

namespace Automattic\VIP\DatabaseMultipleDatasetsConfig;

const MULTIPLE_DATASET_QUERY_ANNOTATION = '/* vip_multiple_dataset_query */';

function dataset_callback( $query, $wpdb ) {
	$table   = $wpdb->table;

	if ( false !== strpos( $query, MULTIPLE_DATASET_QUERY_ANNOTATION ) ) {
		return [ 'dataset' => 'vtgate' ];
	}

	$dataset = get_dataset_for_table( $wpdb->base_prefix, $wpdb->table );
	$wpdb->add_table( $dataset, $table );

	return [ 'dataset' => $dataset ];
}

function query( $query ) {
	global $wpdb;

	if ( ! is_multiple_dataset_query( $wpdb->base_prefix, $query ) ) {
		return $query;
	}

	$regex = "/(?:FROM|JOIN|UPDATE|INTO|,)\s+`?($wpdb->base_prefix(\d+)?_?(?:\w+)+?)`?/i";

	$query = preg_replace_callback( $regex, function ($match) use ( $wpdb ) {
		return str_replace( $match[1], get_dataset_for_table( $wpdb->base_prefix, $match[1] ) . '.' . $match[1] . ' ' . $match[1], $match[0] );
	}, $query );

	$query .= ' ' . MULTIPLE_DATASET_QUERY_ANNOTATION;

	return $query;
}

function is_multiple_dataset_query( $base_prefix, $query ) {
	$regex = "/(?:FROM|JOIN|UPDATE|INTO|,)\s+`?$base_prefix(\d+)?_?(\w+)+?`?/i";

	$matches = [];
	preg_match_all( $regex, $query, $matches, PREG_SET_ORDER );

	$last_global_table = null;
	$last_blog_table   = null;
	$blog_ids          = [];
	foreach ( $matches as $match ) {
		if ( '' === $match[1] ) {
			$last_global_table = $match[2];
		} else {
			$blog_ids[ $match[1] ] = true;
			$last_blog_table       = $match[2];
		}
	}

	$blog_ids_count = count( $blog_ids );

	if ( $last_blog_table && ( $last_global_table || $blog_ids_count > 1 ) ) {
		return true;
	}

	return false;
}


function get_dataset_for_table( $base_prefix, $table ) {
	if ( preg_match( '/^' . $base_prefix . '(\d+)_/i', $table, $matches ) ) {
		$blog_id = $matches[1];

		return get_dataset_name_for_blog_id( $blog_id );
	}

	return get_primary_dataset();
}

function get_dataset_name_for_blog_id( $blog_id ) {
	global $db_datasets;
	foreach ( $db_datasets as $dataset ) {
		if ( in_array( $blog_id, $dataset['blog_ids'] ) ) {
			return $dataset['name'];
		}
	}

	// If the blog_id is not mapped on $db_datasets, we use the latest dataset
	return get_latest_dataset_name();
}

function get_latest_dataset_name() {
	global $db_datasets;

	$latest = end( $db_datasets );

	if ( isset( $latest['name'] ) ) {
		return $latest['name'];
	}

	trigger_error( 'latest dataset not found', E_USER_ERROR );
}

function get_primary_dataset() {
	global $db_datasets;
	foreach ( $db_datasets as $dataset ) {
		if ( $dataset['primary'] ) {
			return $dataset['name'];
		}
	}

	trigger_error( 'primary dataset not found', E_USER_ERROR );
}
