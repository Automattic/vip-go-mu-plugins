<?php

namespace Automattic\VIP\DatabaseMultipleDatasetsConfig;

function dataset_callback( $query, $wpdb ) {
	$table   = $wpdb->table;
	$dataset = get_dataset_for_table( $wpdb );
	$wpdb->add_table( $dataset, $table );

	return [ 'dataset' => $dataset ];
}

function get_dataset_for_table( $wpdb ) {
	if ( preg_match( '/^' . $wpdb->base_prefix . '(\d+)_/i', $wpdb->table, $matches ) ) {
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

/**
 * Prevent 'has_published_posts' and 'orderby=post_count' query parameters from being used on sites with
 * multiple datasets.
 *
 * @param \WP_User_Query $wp_user_query The current WP_User_Query instance, passed by reference.
 */
function multiple_datasets_pre_get_users_cleanup( $wp_user_query ) {
	if ( ! is_null( $wp_user_query->query_vars['has_published_posts'] ) ) {
		trigger_error(
			'WP_User_Query was called incorrectly. `has_published_posts` can not be used on sites with multiple datasets, users and posts tables use different DBs.',
			E_USER_NOTICE
		);

		$wp_user_query->query_vars['has_published_posts'] = null;
	}

	if ( ! is_null( $wp_user_query->query_vars['orderby'] ) ) {
		$has_post_count_orderby = false;
		if ( 'post_count' === $wp_user_query->query_vars['orderby'] ) {
			$has_post_count_orderby               = true;
			$wp_user_query->query_vars['orderby'] = null;
		}

		if ( is_array( $wp_user_query->query_vars['orderby'] ) && in_array( 'post_count', $wp_user_query->query_vars['orderby'] ) ) {
			$has_post_count_orderby = true;
			$key                    = array_search( 'post_count', $wp_user_query->query_vars['orderby'] );
			unset( $wp_user_query->query_vars['orderby'][ $key ] );
		}

		if ( isset( $wp_user_query->query_vars['orderby']['post_count'] ) ) {
			$has_post_count_orderby = true;
			unset( $wp_user_query->query_vars['orderby']['post_count'] );
		}

		if ( $has_post_count_orderby ) {
			trigger_error(
				'WP_User_Query was called incorrectly. `orderby = post_count` can not be used on sites with multiple datasets, users and posts tables use different DBs.',
				E_USER_NOTICE
			);
		}
	}
}
