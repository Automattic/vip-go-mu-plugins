<?php
/*
Plugin name: Cache Manager
Description: Automatically clears the Varnish cache when necessary
Author: Automattic
Author URI: http://automattic.com/
Version: 1.1
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

require_once( __DIR__ . '/api.php' );

class WPCOM_VIP_Cache_Manager {
	private $ban_urls = array();
	private $purge_urls = array();
	private $site_cache_purged = false;

	static public function instance() {
		static $instance = false;
		if ( ! $instance ) {
			$instance = new WPCOM_VIP_Cache_Manager();
		}
		return $instance;
	}

	public function __construct() {
		// Execute the healthcheck as quickly as possible
		if ( '/cache-healthcheck?' === $_SERVER['REQUEST_URI'] ) {
			if ( function_exists( 'newrelic_end_transaction' ) ) {
				# See: https://docs.newrelic.com/docs/agents/php-agent/configuration/php-agent-api#api-end-txn
				newrelic_end_transaction( true );
			}
			die( 'ok' );
		}

		add_action( 'init', array( $this, 'init' ) );
	}

	public function init() {
		if ( $this->can_purge_cache() && isset( $_GET['cm_purge_all'] ) && check_admin_referer( 'manual_purge' ) ) {
			$this->purge_site_cache();
			add_action( 'admin_notices' , array( $this, 'manual_purge_message' ) );
		}

		add_action( 'clean_post_cache', array( $this, 'queue_post_purge' ) );
		add_action( 'clean_term_cache', array( $this, 'queue_terms_purges' ), 10, 2 );
		add_action( 'switch_theme', array( $this, 'purge_site_cache' ) );
		add_action( 'post_updated', array( $this, 'queue_old_permalink_purge' ), 10, 3 );

		add_action( 'activity_box_end', array( $this, 'get_manual_purge_link' ), 100 );

		add_action( 'shutdown', array( $this, 'execute_purges' ) );
	}

	public function get_manual_purge_link() {
		if ( ! $this->can_purge_cache() ) {
			return;
		}

		$url = wp_nonce_url( admin_url( '?cm_purge_all' ), 'manual_purge' );

		$button_html =  esc_html__( 'Press the button below to force a purge of your entire page cache.' );
		$button_html .= '</p><p><span class="button"><a href="' . esc_url( $url ) . '"><strong>';
		$button_html .= esc_html__( 'Purge Page Cache' );
		$button_html .= '</strong></a></span>';

		echo "<p>$button_html</p>\n";
	}

	public function manual_purge_message() {
		echo "<div id='message' class='updated fade'><p><strong>".__('Varnish cache purged!', 'varnish-http-purge')."</strong></p></div>";
	}

	public function curl_multi( $requests ) {
		$curl_multi = curl_multi_init();

		foreach ( $requests as $req ) {
			if ( defined( 'PURGE_SERVER_TYPE' ) && 'mangle' == PURGE_SERVER_TYPE ) {
				$data = array(
					'group' => 'vip-go',
					'scope' => 'global',
					'type'  => $req['method'],
					'uri'   => $req['host'] . $req['uri'],
					'cb'    => 'nil',
				);
				$json = json_encode( $data );
				$curl = curl_init();
				curl_setopt( $curl, CURLOPT_URL, constant( 'PURGE_SERVER_URL' ) );
				curl_setopt( $curl, CURLOPT_POST, true );
				curl_setopt( $curl, CURLOPT_POSTFIELDS, $json );
				curl_setopt( $curl, CURLOPT_HTTPHEADER, array(
						'Content-Type: application/json',
						'Content-Length: ' . strlen( $json )
					) );
				curl_setopt( $curl, CURLOPT_TIMEOUT, 5 );
				curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
				curl_multi_add_handle( $curl_multi, $curl );
			} else {
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
				error_log( 'Error on: ' . $info['url'] . ' error: ' . curl_error( $completed['handle'] ) );

			if ( '200' != $info['http_code'] )
				error_log( 'Request to ' . $info['url'] . ' returned HTTP code ' . $info['http_code'] );

			curl_multi_remove_handle( $curl_multi, $completed['handle'] );
		}

		curl_multi_close( $curl_multi );
	}

	/**
	 * Instead of using this method directly, please use the API
	 * functions provided; see `api.php`.
	 *
	 * @access private Please do not use this method directly
	 * @param string $url A URL to PURGE
	 * @param string $method
	 *
	 * @return array
	 */
	public function build_purge_request( $url, $method ) {
		if ( ! defined( 'PURGE_SERVER_TYPE' ) || 'varnish' == PURGE_SERVER_TYPE ) {
			global $varnish_servers;
		} else {
			$varnish_servers = array( constant( 'PURGE_SERVER_URL' ) );
		}

		$requests = array();

		if ( empty( $varnish_servers ) )
			return $requests;

		$parsed = parse_url( $url );
		if ( empty( $parsed['host'] ) )
			return $requests;

		foreach ( $varnish_servers as $server ) {
			if ( 'BAN' == $method ) {
				$uri = $parsed['path'] . '?' . $parsed['query'];
			} else {
				$uri = '/';
				if ( isset( $parsed['path'] ) )
					$uri = $parsed['path'];
				if ( isset( $parsed['query'] ) )
					$uri .= $parsed['query'];
			}

			$request = array(
				'host'   => $parsed['host'],
				'uri'    => $uri,
				'method' => $method,
			);

			if ( ! defined( 'PURGE_SERVER_TYPE' ) || 'varnish' == PURGE_SERVER_TYPE ) {
				$srv = explode( ':', $server[0] );
				$request['ip']   = $srv[0];
				$request['port'] = $srv[1];
			}

			$requests[] = $request;
		}

		return $requests;
	}

	public function execute_purges() {

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$this->ban_urls = array_unique( $this->ban_urls );
		$this->purge_urls = array_unique( $this->purge_urls );

		if ( empty( $this->ban_urls ) && empty( $this->purge_urls ) ) {
			return;
		}

		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
			return;
		}

		/**
		 * Before PURGE URLs are assembled for execution.
		 *
		 * @param array $this->purge_urls {
		 *     An array of URLs to be PURGEd
		 * }
		 */
		do_action( 'wpcom_vip_cache_pre_execute_purges', $this->purge_urls );

		/**
		 * Before BAN requests are assembled for execution.
		 *
		 * @param array $this->ban_urls {
		 *     An array of BAN requests
		 * }
		 */
		do_action( 'wpcom_vip_cache_pre_execute_bans', $this->ban_urls );

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

	public function purge_site_cache( $when = null ) {
		if ( $this->site_cache_purged )
			return;

		$this->ban_urls[] = untrailingslashit( home_url() ) . '/(?!wp\-content\/uploads\/).*';
		$this->site_cache_purged = true;

		return;
	}

	public function queue_post_purge( $post_id ) {
		if ( $this->site_cache_purged )
			return false;

		if ( defined( 'WP_IMPORTING' ) ) {
			return false;
		}

		$post = get_post( $post_id );
		if ( empty( $post ) ||
		     'revision' === $post->post_type ||
		     ! in_array( get_post_status( $post_id ), array( 'publish', 'inherit', 'trash' ), true ) )
		{
			return false;
		}

		if ( ! is_post_type_viewable( $post->post_type ) ) {
			return;
		}

		$post_purge_urls = array();
		$post_purge_urls[] = get_permalink( $post_id );
		$post_purge_urls[] = home_url( '/' );

		// Don't just purge the attachment page, but also include the file itself
		if ( 'attachment' === $post->post_type ) {
			$this->purge_urls[] = wp_get_attachment_url( $post_id );
		}

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
				$post_purge_urls = array_merge( $post_purge_urls, $this->get_purge_urls_for_term( $term ) );
			}
		}

		// Purge the standard site feeds
		// @TODO Do we need to PURGE the comment feeds if the post_status is publish?
		$site_feeds = array(
			get_bloginfo('rdf_url'),
			get_bloginfo('rss_url') ,
			get_bloginfo('rss2_url'),
			get_bloginfo('atom_url'),
			get_bloginfo('comments_atom_url'),
			get_bloginfo('comments_rss2_url'),
			get_post_comments_feed_link( $post_id ),
		);
		$post_purge_urls = array_merge( $post_purge_urls, $site_feeds );

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
		 * @deprecated 1.1 Use `wpcom_vip_cache_purge_{post_type}_urls` instead
		 * @param array $this->purge_urls {
		 *     An array of URLs for you to add to
		 * }
		 * @param type  $post_id The ID of the post which is the primary reason for the purge
		 */
		$post_purge_urls = apply_filters( 'wpcom_vip_cache_purge_urls', $post_purge_urls, $post_id );

		$this->purge_urls = array_merge( $this->purge_urls, $post_purge_urls );

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
		 * @param type  $post_id The ID of the post which is the primary reason for the purge
		 */
		$this->purge_urls = apply_filters( "wpcom_vip_cache_purge_{$post->post_type}_post_urls", $this->purge_urls, $post_id );

		$this->purge_urls = array_unique( $this->purge_urls );

		return true;
	}

	/**
	 * Purge the cache for a terms
	 *
	 * @param object|int $term A WP Term object, or a term ID
	 * @return bool True on success
	 */
	public function queue_term_purge( $term ) {
		$term = get_term( $term );
		if ( is_wp_error( $term ) ) {
			return false;
		}
		if ( empty( $term ) ) {
			return false;
		}
		$term_ids = array( $term->term_id );
		$this->queue_terms_purges( $term_ids, $term->taxonomy );
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
	 * @param bool   $clean_taxonomy Whether or not to clean taxonomy-wide caches
	 */
	public function queue_terms_purges( $ids, $taxonomy ) {
		$get_term_args = array(
			'taxonomy'    => $taxonomy,
			'include'     => $ids,
			'hide_empty'  => false,
		);
		$terms = get_terms( $get_term_args );
		if ( is_wp_error( $terms ) ) {
			return;
		}
		$term_purge_urls = array();
		foreach ( $terms as $term ) {
			$term_purge_urls = array_merge( $term_purge_urls, $this->get_purge_urls_for_term( $term ) );
		}

		$this->purge_urls = array_merge( $this->purge_urls, $term_purge_urls );
		$this->purge_urls = array_unique( $this->purge_urls );
	}

	/**
	 * Get all URLs to be purged for a given term
	 *
	 * @param object $term A WP term object
	 *
	 * @return array An array of URLs to be purged
	 */
	protected function get_purge_urls_for_term( $term ) {
		// Belt and braces: get the term object,
		// in case something sent us a term ID
		$term = get_term( $term );

		if ( is_wp_error( $term ) || empty( $term ) ) {
			return array();
		}

		$term_purge_urls = array();

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
		 * @param string sprintf formatted string, including `%d`
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
		 * @access private Please do not hook this filter at the moment
		 * @param int The maximum page to purge from each term archive
		 * }
		 */
		$max_pages = apply_filters( 'wpcom_vip_cache_purge_urls_max_pages', 2, $term );

		// Set some limits on max and min values for pages
		$max_pages = max( 1, min( 5, $max_pages ) );

		$taxonomy_name = $term->taxonomy;
		$maybe_purge_url = get_term_link( $term, $taxonomy_name );
		if ( is_wp_error( $maybe_purge_url ) ) {
			return array();
		}
		if ( $maybe_purge_url && is_string( $maybe_purge_url ) ) {
			$term_purge_urls[] = $maybe_purge_url;
			// Now add the pages for the archive we're clearing
			for( $i = 2; $i <= $max_pages; $i++ ) {
				$maybe_purge_url_page = rtrim( $maybe_purge_url, '/' ) . '/' . ltrim( $paging_endpoint, '/' );
				$maybe_purge_url_page = sprintf( $maybe_purge_url_page, $i );
				$term_purge_urls[] = user_trailingslashit( $maybe_purge_url_page, 'paged' );
			}
		}
		$maybe_purge_feed_url = get_term_feed_link( $term->term_id, $taxonomy_name );
		if ( false !== $maybe_purge_feed_url ) {
			$term_purge_urls[] = $maybe_purge_feed_url;
		}

		/**
		 * Allows adding URLs to be PURGEd from cache when a given term_id is PURGEd
		 *
		 * Developers can hook this filter and check the term being purged in order
		 * to also purge related URLs, e.g. feeds.
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
		 * @param type  $term_id The ID of the term which is the primary reason for the purge
		 */
		$term_purge_urls = apply_filters( "wpcom_vip_cache_purge_{$taxonomy_name}_term_urls", $term_purge_urls, $term->term_id );

		return $term_purge_urls;
	}

	/**
	 * PURGE a single URL
	 *
	 * @param string $url The specific URL to purge the cache for
	 *
	 * @return bool True on success
	 */
	public function queue_purge_url( $url ) {
		$url = esc_url_raw( $url );
		$url = wp_http_validate_url( $url );
		if ( false === $url ) {
			return false;
		}
		$this->purge_urls[] = $url;
		return true;
	}

	/**
	 * Schedule purge of old permalink in case it was changed during post update
	 * and only if the post's status was publish before the update
	 *
	 * @param int $post_ID The post ID of update post
	 * @param WP_Post $post_after The post object as it looks after the update
	 * @param WP_Post $post_before The post object as it looked before the update
	 *
	 * @return void
	 */
	public function queue_old_permalink_purge( $post_ID, $post_after, $post_before ) {
		if ( get_permalink( $post_before ) !== get_permalink( $post_after ) &&
			 'publish' === $post_before->post_status
		) {
			$this->queue_purge_url( get_permalink( $post_before ) );
		}
	}

	private function can_purge_cache() {
		if ( ! function_exists( 'is_proxied_automattician' ) ) {
			// Local environment; no purging necessary here
			return false;
		}

		return is_proxied_automattician();
	}
}

WPCOM_VIP_Cache_Manager::instance();
