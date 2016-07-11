<?php
/*
Plugin name: Cache Manager
Description: Automatically clears the Varnish cache when necessary
Author: Automattic
Author URI: http://automattic.com/
Version: 1.0
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

class WPCOM_VIP_Cache_Manager {
	private $ban_urls = array();
	private $purge_urls = array();
	private $site_cache_purged = false;

	function __construct() {
		// Execute the healthcheck as quickly as possible
		if ( '/cache-healthcheck?' === $_SERVER['REQUEST_URI'] )
			die( 'ok' );

		add_action( 'init', array( $this, 'init' ) );
	}

	function init() {
		if ( is_super_admin() && isset( $_GET['cm_purge_all'] ) && check_admin_referer( 'manual_purge' ) ) {
			$this->purge_site_cache();
			add_action( 'admin_notices' , array( $this, 'manual_purge_message' ) );
		}

		add_action( 'clean_post_cache', array( $this, 'queue_post_purge' ) );
		add_action( 'clean_term_cache', array( $this, 'queue_term_purge' ), 10, 3 );
		add_action( 'switch_theme', array( $this, 'purge_site_cache' ) );

		add_action( 'added_post_meta',   array( $this, 'changed_post_meta' ) );
		add_action( 'updated_post_meta', array( $this, 'changed_post_meta' ) );
		add_action( 'deleted_post_meta', array( $this, 'changed_post_meta' ) );

		add_action( 'added_term_meta',   array( $this, 'changed_term_meta' ) );
		add_action( 'updated_term_meta', array( $this, 'changed_term_meta' ) );
		add_action( 'deleted_term_meta', array( $this, 'changed_term_meta' ) );

		add_action( 'activity_box_end', array( $this, 'get_manual_purge_link' ), 100 );

		add_action( 'shutdown', array( $this, 'execute_purges' ) );
	}

	function get_manual_purge_link() {
		global $blog_id;

		$url = wp_nonce_url( admin_url( '?cm_purge_all' ), 'manual_purge' );

		$button =  esc_html__( 'Press the button below to force a purge of your entire page cache.' );
		$button .= '</p><p><span class="button"><a href="' . $url . '"><strong>';
		$button .= esc_html__( 'Purge Page Cache' );
		$button .= '</strong></a></span>';

		$nobutton =  esc_html__( 'You do not have permission to purge the cache for the whole site. Please contact your adminstrator.' );

		if ( is_super_admin() ) {
			echo "<p>$button</p>\n";
		} else {
			echo "<p>$nobutton</p>\n";
		}
	}

	function manual_purge_message() {
		echo "<div id='message' class='updated fade'><p><strong>".__('Varnish cache purged!', 'varnish-http-purge')."</strong></p></div>";
	}

	function curl_multi( $requests ) {
		$curl_multi = curl_multi_init();

		foreach ( $requests as $req ) {
			// Purge HTTP
			$curl = curl_init();
			curl_setopt( $curl, CURLOPT_URL, "http://{$req['ip']}{$req['uri']}" );
			curl_setopt( $curl, CURLOPT_PORT, $req['port'] );
			curl_setopt( $curl, CURLOPT_HTTPHEADER, array( "Host: {$req['host']}", "X-Forwarded-Proto: http" ) );
			curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, $req['method'] );
			curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $curl, CURLOPT_NOBODY, true );
			curl_setopt( $curl, CURLOPT_HEADER, true );
			curl_setopt( $curl, CURLOPT_TIMEOUT, 5 );
			curl_multi_add_handle( $curl_multi, $curl );
			// Purge HTTPS
			$curl = curl_init();
			curl_setopt( $curl, CURLOPT_URL, "http://{$req['ip']}{$req['uri']}" );
			curl_setopt( $curl, CURLOPT_PORT, $req['port'] );
			curl_setopt( $curl, CURLOPT_HTTPHEADER, array( "Host: {$req['host']}", "X-Forwarded-Proto: https" ) );
			curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, $req['method'] );
			curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $curl, CURLOPT_NOBODY, true );
			curl_setopt( $curl, CURLOPT_HEADER, true );
			curl_setopt( $curl, CURLOPT_TIMEOUT, 5 );
			curl_multi_add_handle( $curl_multi, $curl );
		}

		$running = true;

		while ( $running ) {
			do {
				$result = curl_multi_exec( $curl_multi, $running );
			} while ( $result == CURLM_CALL_MULTI_PERFORM );

			if ( $result != CURLM_OK )
				error_log( 'curl_multi_exec() returned something different than CURLM_OK' );

			curl_multi_select( $curl_multi, 0.2 );
		}

		while ( $completed = curl_multi_info_read( $curl_multi ) ) {
			$info = curl_getinfo( $completed['handle'] );

			if ( ! $info['http_code'] && curl_error( $completed['handle'] ) )
				error_log( 'Error on: ' . $info['url'] . ' error: ' . curl_error( $completed['handle'] ) . "\n" );

			if ( '200' != $info['http_code'] )
				error_log( 'Request to ' . $info['url'] . ' returned HTTP code ' . $info['http_code'] . "\n" );

			curl_multi_remove_handle( $curl_multi, $completed['handle'] );
		}

		curl_multi_close( $curl_multi );
	}

	function build_purge_request( $url, $method ) {
		global $varnish_servers;

		$requests = array();

		if ( empty( $varnish_servers ) )
			return $requests;

		$parsed = parse_url( $url );
		if ( empty( $parsed['host'] ) )
			return $requests;

		foreach ( $varnish_servers as $server  ) {
			$server = explode( ':', $server[0] );

			$uri = '/';
			if ( isset( $parsed['path'] ) )
				$uri = $parsed['path'];
			if ( isset( $parsed['query'] ) )
				$uri .= $parsed['query'];

			$requests[] = array(
				'ip'     => $server[0],
				'port'   => $server[1],
				'host'   => $parsed['host'],
				'uri'    => $uri,
				'method' => $method
			);
		}

		return $requests;
	}

	function execute_purges() {
		$this->ban_urls = array_unique( $this->ban_urls );
		$this->purge_urls = array_unique( $this->purge_urls );

		if ( empty( $this->ban_urls ) && empty( $this->purge_urls ) )
			return;

		$requests = array();
		foreach( (array) $this->ban_urls as $url )
			$requests = array_merge( $requests, $this->build_purge_request( $url, 'BAN' ) );

		foreach( (array) $this->purge_urls as $url )
			$requests = array_merge( $requests, $this->build_purge_request( $url, 'PURGE' ) );

		$this->ban_urls = $this->purge_urls = array();

		if ( empty( $requests ) )
			return;

		return $this->curl_multi( $requests );
	}

	function purge_site_cache( $when = null ) {
		if ( $this->site_cache_purged )
			return;

		$this->ban_urls[] = untrailingslashit( home_url() ) . '/.*';
		$this->site_cache_purged = true;

		return;
	}

	/**
	 * Hooks the following actions:
	 *
	 * * `added_{$meta_type}_meta` action for post meta
	 * * `updated_{$meta_type}_meta` action for post meta
	 * * `deleted_{$meta_type}_meta` action for post meta
	 *
	 * @param int    $meta_id  ID of updated metadata entry.
	 * @param int    $post_id  Post ID.
	 */
	function changed_post_meta( $meta_id ) {
		// N.B. get_post_meta_by_id is not defined on the front end
		$meta = get_metadata_by_mid( 'post', $meta_id );

		// Some meta keys are irrelevant to content display and we
		// should not purge cache when they change, e.g. we should
		// not purge post cache when post lock is updated
		$meta_key_blacklist = array(
			'_wp_old_slug',
			'_edit_lock',
			'_edit_last',
			'_pingme',
			'_encloseme',
			'_jetpack_related_posts_cache',
		);

		/**
		 * Amend the blacklist of post meta keys which do NOT
		 * trigger cache purges
		 *
		 * @param array $meta_key_blacklist An array of post meta keys
		 */
		$meta_key_blacklist = apply_filters( 'wpcom_vip_cache_post_meta_blacklist', $meta_key_blacklist );
		if ( in_array( $meta->meta_key, $meta_key_blacklist ) ) {
			return;
		}

		$this->queue_post_purge( $meta->post_id );
	}

	/**
	 * Hooks the following actions:
	 *
	 * * `added_{$meta_type}_meta` action for term meta
	 * * `updated_{$meta_type}_meta` action for term meta
	 * * `deleted_{$meta_type}_meta` action for term meta
	 *
	 * @param int    $meta_id  ID of updated metadata entry.*/
	function changed_term_meta( $meta_id ) {
		$meta = get_metadata_by_mid( 'term', $meta_id );

		// Some meta keys are irrelevant to content display and we
		// should not purge cache when they change
		$meta_key_blacklist = array();

		/**
		 * Amend the blacklist of term meta keys which do NOT
		 * trigger cache purges
		 *
		 * @param array $meta_key_blacklist An array of term meta keys
		 */
		$meta_key_blacklist = apply_filters( 'wpcom_vip_cache_term_meta_blacklist', $meta_key_blacklist );
		if ( in_array( $meta->meta_key, $meta_key_blacklist ) ) {
			return;
		}

		$term = get_term( $meta->term_id );
		$this->queue_purge_urls_for_term( $term );
	}

	function queue_post_purge( $post_id ) {
		if ( $this->site_cache_purged ) {
			return;
		}

		if ( defined( 'WP_IMPORTING' ) ) {
			$this->purge_site_cache();
			return;
		}

		$post = get_post( $post_id );
		if ( empty( $post ) ||
			'revision' === $post->post_type ||
			! in_array( get_post_status( $post_id ), array( 'publish', 'trash' ), true ) )
		{
			return;
		}

		$this->purge_urls[] = get_permalink( $post_id );
		$this->purge_urls[] = trailingslashit( home_url() );

		$taxonomies = get_object_taxonomies( $post, 'object' );

		foreach ( $taxonomies as $taxonomy ) {
			if ( true !== $taxonomy->public ) {
				continue;
			}
			$taxonomy_name = $taxonomy->name;
			$terms = get_the_terms( $post_id, $taxonomy_name );
			if ( false === $terms ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$this->queue_purge_urls_for_term( $term );
			}
		}

		$feeds = array(
			get_bloginfo('rdf_url'),
			get_bloginfo('rss_url') ,
			get_bloginfo('rss2_url'),
			get_bloginfo('atom_url'),
			get_bloginfo('comments_atom_url'),
			get_bloginfo('comments_rss2_url'),
			get_post_comments_feed_link( $post_id )
		);

		foreach ( $feeds as $feed ) {
			$this->purge_urls[] = $feed;
		}

		/**
		 * Allows adding URLs to be PURGEd from cache when a given post ID is PURGEd
		 *
		 * Developers can hook this filter and check the post being purged in order
		 * to also purge related URLs, e.g. feeds.
		 *
		 * Related category archives, tag archives, generic feeds, etc, are already
		 * included to be purged (see code above).
		 *
		 * PLEASE NOTE: Your site benefits from the performance that our HTTP
		 * Reverse Proxy Caching provides, and purging URLs from that cache
		 * should be done cautiously. VIP may push back on use of this filter
		 * during initial code review and pre-deployment review where we
		 * see issues.
		 *
		 * @param array $this->purge_urls {
		 *     An array of URLs for you to add to
		 * }
		 * @param int   $post_id The ID of the post which is the primary reason for the purge
		 */
		$this->purge_urls = apply_filters( 'wpcom_vip_cache_purge_urls', $this->purge_urls, $post_id );

		$this->purge_urls = array_unique( $this->purge_urls );
	}

	/**
	 * Purge the cache for some terms
	 *
	 * Hooks the `clean_term_cache` action
	 *
	 * We do not respect requests to clear caches for the entire taxonomy,
	 * as this would be potentially hundreds or thousands of PURGE requests.
	 *
	 * @param array  $ids            An array of term IDs.
	 * @param string $taxonomy       Taxonomy slug.
	 */
	function queue_term_purge( $ids, $taxonomy ) {
		$get_term_args = array(
			'taxonomy'    => $taxonomy,
			'include'     => $ids,
			'hide_empty'  => false,
		);
		$terms = get_terms( $get_term_args );
		if ( is_wp_error( $terms ) ) {
			return;
		}
		foreach ( $terms as $term ) {
			$this->queue_purge_urls_for_term( $term );
		}
	}

	/**
	 * Queue all URLs to be purged for a given term
	 *
	 * @param object $term A WP term object
	 */
	function queue_purge_urls_for_term( $term ) {

		$this->purge_urls[] = trailingslashit( home_url() );

		/**
		 * Allows you to customise the URL suffix used to specify a page for
		 * paged term archives.
		 *
		 * Developers should hook this filter to provide a different page
		 * endpoint if they have custom or translated rewrite rules for
		 * paging in term archives:
		 *
		 * Standard:     example.com/category/news/page/2
		 * Non-standard: example.com/category/news/p/2
		 *
		 * The string should be formatted as for `sprintf`, with a `%d` in place
		 * of the page number.
		 *
		 * @param string $paging_endpoint sprintf formatted string, including `%d`
		 * }
		 */
		$paging_endpoint = apply_filters( 'wpcom_vip_cache_purge_urls_paging_endpoint', $GLOBALS['wp_rewrite']->pagination_base . '/%d/' );

		/**
		 * The maximum page to purge from each term archive when a post associated with
		 * that term is published.
		 *
		 * e.g. if the value is 3, the following pagination URLs will be purged for the
		 * news category archive:
		 *
		 * example.com/category/news/
		 * example.com/category/news/page/2
		 * example.com/category/news/page/3
		 *
		 * @param int $max_pages The maximum page to purge from each term archive
		 * }
		 */
		$max_pages = apply_filters( 'wpcom_vip_cache_purge_urls_max_pages', 5 );

		// Set some limits on max and min values for pages
		$max_pages = max( 1, min( 20, $max_pages ) );

		$taxonomy_name = $term->taxonomy;
		$maybe_purge_url = get_term_link( $term, $taxonomy_name );
		if ( is_wp_error( $maybe_purge_url ) ) {
			return;
		}
		if ( $maybe_purge_url && is_string( $maybe_purge_url ) ) {
			$this->purge_urls[] = $maybe_purge_url;
			// Now add the pages for the archive we're clearing
			for( $i = 2; $i <= $max_pages; $i++ ) {
				$maybe_purge_url_page = rtrim( $maybe_purge_url, '/' ) . '/' . ltrim( $paging_endpoint, '/' );
				$maybe_purge_url_page = sprintf( $maybe_purge_url_page, $i );
				$this->purge_urls[] = user_trailingslashit( $maybe_purge_url_page, 'paged' );
			}
		}
		$maybe_purge_feed_url = get_term_feed_link( $term->term_id, $taxonomy_name );
		if ( false !== $maybe_purge_feed_url ) {
			$this->purge_urls[] = $maybe_purge_feed_url;
		}

		$this->purge_urls = array_unique( $this->purge_urls );
	}
}

new WPCOM_VIP_Cache_Manager();
