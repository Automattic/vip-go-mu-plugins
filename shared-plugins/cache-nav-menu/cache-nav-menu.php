<?php

/*
 * Plugin Name: Cache Nav Menus
 * Description: Allows Core Nave Menus to be cached using WP.com's Advanced Post Cache
 * Author: Automattic
 */

function cache_nav_menu_parse_query( &$query ) {
	if ( !isset( $query->query_vars['post_type'] ) || 'nav_menu_item' !== $query->query_vars['post_type'] ) {
		return;
	}

	$query->query_vars['suppress_filters'] = false;
	$query->query_vars['cache_results'] = true;
}

add_action( 'parse_query', 'cache_nav_menu_parse_query' );

/**
 * Wrapper function around wp_nav_menu() that will cache the wp_nav_menu for all tag/category
 * pages used in the nav menus
 */
function wpcom_vip_cached_nav_menu( $args = array(), $prime_cache = false ) {
	global $wp_query;

	$queried_object_id = empty( $wp_query->queried_object_id ) ? 0 : (int) $wp_query->queried_object_id;

	$nav_menu_key = md5( serialize( $args ) . '-' . $queried_object_id );
	$my_args = wp_parse_args( $args );
	$my_args = apply_filters( 'wp_nav_menu_args', $my_args );
	$my_args = (object) $my_args;

	if ( ( isset( $my_args->echo ) && true === $my_args->echo ) || !isset( $my_args->echo ) ) {
		$echo = true;
	} else {
		$echo = false;
	}

	if ( true === $prime_cache || false === ( $nav_menu = wp_cache_get( $nav_menu_key, 'cache-nav-menu' ) ) ) {
		if ( false === $echo ) {
			$nav_menu = wp_nav_menu( $args );
		} else {
			ob_start();
			wp_nav_menu( $args );
			$nav_menu = ob_get_clean();
		}
		
		wp_cache_set( $nav_menu_key, $nav_menu, 'cache-nav-menu', MINUTE_IN_SECONDS * 15 );
	}
	if ( true === $echo )
		echo $nav_menu;
	else
		return $nav_menu;
}

function wpcom_vip_get_nav_menu_cache_objects( $use_cache = true ) {
	$cache_key = 'wpcom_vip_nav_menu_cache_object_ids';
	$object_ids = wp_cache_get( $cache_key, 'cache-nav-menu' );
	if ( true === $use_cache && !empty( $object_ids ) ) {
		return $object_ids;
	}

	$object_ids = $objects = array();

	$menus = wp_get_nav_menus();
	foreach ( $menus as $menu_maybe ) {
		if ( $menu_items = wp_get_nav_menu_items( $menu_maybe->term_id ) ) {
			foreach( $menu_items as $menu_item ) {
				if ( preg_match( "#.*/category/([^/]+)/?$#", $menu_item->url, $match ) )
					$objects['category'][] = $match[1];
				if ( preg_match( "#.*/tag/([^/]+)/?$#", $menu_item->url, $match ) )
					$objects['post_tag'][] = $match[1];
			}
		}
	}
	if ( !empty( $objects ) ) {
		foreach( $objects as $taxonomy => $term_names ) {
			foreach( $term_names as $term_name ) {
				$term = get_term_by( 'slug', $term_name, $taxonomy );
				if ( $term )
					$object_ids[] = $term->term_id;
			}
		}
	}

	$object_ids[] = 0; // that's for the homepage

	wp_cache_set( $cache_key, $object_ids, 'cache-nav-menu' );
	return $object_ids;
}
