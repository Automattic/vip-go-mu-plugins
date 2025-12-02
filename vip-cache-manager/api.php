<?php

/**
 * Clear the VIP Go edge cache for a specific URL
 *
 * @param string $url The specific URL to purge the cache for
 *
 * @return bool True on success
 */
function wpcom_vip_purge_edge_cache_for_url( $url ) {
	return WPCOM_VIP_Cache_Manager::instance()->queue_purge_url( $url );
}

/**
 * Clear the VIP Go edge cache at the URLs associated with a post
 *
 * This includes:
 * * The singular Post URL
 * * The homepage
 * * Main site posts feed
 * * Main site comments feed
 * * The archive URLs for all terms associated with the post, paged
 *   back five pages as default
 * * The feeds for all terms associated with the post
 *
 * You can filter how many of the pages of the archives are cleared
 * using the `wpcom_vip_cache_purge_urls_max_pages` filter.
 *
 * @param object|int $post Either the WP Post object, or the post ID
 *
 * @return bool True on success
 */
function wpcom_vip_purge_edge_cache_for_post( $post ) {
	return WPCOM_VIP_Cache_Manager::instance()->queue_post_purge( $post );
}

/**
 * Clear the VIP Go edge cache at the URLs associated with a term
 *
 * This includes:
 * * The term archive URL, paged back five pages as default
 * * The term feed
 *
 * You can filter how many of the pages of the archives are cleared
 * using the `wpcom_vip_cache_purge_urls_max_pages` filter.
 *
 * @param object|int $term Either the WP Term object, or the term_id
 *
 * @return bool True on success
 */
function wpcom_vip_purge_edge_cache_for_term( $term ) {
	return WPCOM_VIP_Cache_Manager::instance()->queue_term_purge( $term );
}

/**
 * Ban the full VIP Go site cache.
 *
 * @return bool True when the ban was queued.
 */
function wpcom_vip_purge_edge_cache_for_site() {
	return WPCOM_VIP_Cache_Manager::instance()->purge_site_cache();
}

/**
 * Purge cached content that originated from origin servers.
 *
 * @return bool True when the purge was queued.
 */
function wpcom_vip_purge_edge_cache_for_origin_content() {
	return WPCOM_VIP_Cache_Manager::instance()->purge_origin_cache();
}

/**
 * Purge cached uploads/media objects only.
 *
 * @return bool True when the purge was queued.
 */
function wpcom_vip_purge_edge_cache_for_uploads() {
	return WPCOM_VIP_Cache_Manager::instance()->purge_uploads_cache();
}

/**
 * Purge cached static assets (CSS/JS) only.
 *
 * @return bool True when the purge was queued.
 */
function wpcom_vip_purge_edge_cache_for_static_files() {
	return WPCOM_VIP_Cache_Manager::instance()->purge_static_files_cache();
}

/**
 * Purge cached visibility metadata for private files.
 *
 * @return bool True when the purge was queued.
 */
function wpcom_vip_purge_edge_cache_for_private_files() {
	return WPCOM_VIP_Cache_Manager::instance()->purge_private_files_cache();
}
