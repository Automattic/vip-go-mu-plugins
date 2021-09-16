<?php
/*
Plugin name: Cache Manager
Description: Automatically clears the Varnish cache when necessary
Author: Automattic
Author URI: http://automattic.com/
Version: 1.1
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

// We have to use cURL for parallel requests, disabling cURL-related check
// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_init
// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_setopt
// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_error
// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_getinfo
// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_multi_init
// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_multi_close
// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_multi_add_handle
// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_multi_select
// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_multi_exec
// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_multi_info_read
// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_multi_remove_handle
// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_close

require_once __DIR__ . '/api.php';

class WPCOM_VIP_Cache_Manager {
	const MAX_PURGE_URLS         = 100;
	const MAX_PURGE_BATCH_URLS   = 4000;
	const MAX_BAN_URLS           = 10;
	const CACHE_PURGE_BATCH_SIZE = 2000;

	private $ban_urls          = array();
	private $purge_urls        = array();
	private $site_cache_purged = false;

	public static function instance() {
		static $instance = false;
		if ( ! $instance ) {
			$instance = new WPCOM_VIP_Cache_Manager();
		}
		return $instance;
	}

	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	public function init() {
		// Cache purging disabled, bail
		if ( defined( 'VIP_GO_DISABLE_CACHE_PURGING' ) && true === VIP_GO_DISABLE_CACHE_PURGING ) {
			return;
		}

		if ( $this->can_purge_cache() && isset( $_GET['cm_purge_all'] ) && check_admin_referer( 'manual_purge' ) ) {
			$this->purge_site_cache();
			\Automattic\VIP\Stats\send_pixel( [
				'vip-cache-action'            => 'dashboard-site-purge',
				'vip-cache-url-purge-by-site' => VIP_GO_APP_ID,
			] );
			add_action( 'admin_notices', array( $this, 'manual_purge_message' ) );
		}

		add_action( 'clean_post_cache', array( $this, 'queue_post_purge' ) );
		add_action( 'clean_term_cache', array( $this, 'queue_terms_purges' ), 10, 2 );
		add_action( 'switch_theme', array( $this, 'purge_site_cache' ) );
		add_action( 'post_updated', array( $this, 'queue_old_permalink_purge' ), 10, 3 );

		add_action( 'activity_box_end', array( $this, 'get_manual_purge_link' ), 100 );

		add_action( 'shutdown', array( $this, 'execute_purges' ) );
		add_action( 'admin_bar_menu', [ $this, 'admin_bar_callback' ], 100, 1 );
		add_action( 'wp_enqueue_scripts', [ $this, 'button_enqueue_scripts' ] );
		add_action( 'wp_ajax_vip_purge_page_cache', [ $this, 'ajax_vip_purge_page_cache' ] );
	}

	public function get_queued_purge_urls() {
		return $this->purge_urls;
	}

	public function clear_queued_purge_urls() {
		$this->purge_urls = [];
	}

	/**
	 * Display a button to purge the cache for the specific URL and its assets
	 *
	 * @return void
	 */
	public function admin_bar_callback( WP_Admin_Bar $admin_bar ) {
		if ( ! is_admin() && $this->current_user_can_purge_cache() ) {
			$admin_bar->add_menu(
				[
					'id'     => 'vip-purge-page',
					'parent' => null,
					'group'  => null,
					'title'  => 'Flush Cache for Page',
					'href'   => '#',
					'meta'   => [
						'title' => 'Flush Page cache for this page and its assets',
					],
				]
			);
		}
	}

	/**
	 * Enqueue the button for users who have the needed caps.
	 *
	 * @return void
	 */
	public function button_enqueue_scripts() {
		if ( $this->current_user_can_purge_cache() ) {
			wp_enqueue_script( 'purge-page-cache-btn', plugins_url( '/js/admin-bar.js', __FILE__ ), [], '1.1', true );
			wp_localize_script( 'purge-page-cache-btn', 'VIPPageFlush', [
				'nonce'   => wp_create_nonce( 'purge-page' ),
				'ajaxurl' => add_query_arg( [ 'action' => 'vip_purge_page_cache' ], admin_url( 'admin-ajax.php' ) ),
			] );
		}
	}

	/**
	 * AJAX callback that performs basic security checks and payload validation and queues urls for the purge.
	 *
	 * @return void
	 */
	public function ajax_vip_purge_page_cache() {
		// phpcs:disable WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsRemoteFile -- OK to read php://input
		$req = json_decode( file_get_contents( 'php://input' ) );

		if ( json_last_error() ) {
			\Automattic\VIP\Stats\send_pixel( [
				'vip-cache-url-purge-status' => 'bad-payload',
			] );
			wp_send_json_error( [ 'error' => 'Malformed payload' ], 400 );
		}

		if ( ! $this->current_user_can_purge_cache() ) {
			\Automattic\VIP\Stats\send_pixel( [
				'vip-cache-url-purge-status' => 'deny-permissions',
			] );

			wp_send_json_error( [ 'error' => 'Unauthorized' ], 403 );
		}

		if ( ! ( isset( $req->nonce ) && wp_verify_nonce( $req->nonce, 'purge-page' ) ) ) {
			\Automattic\VIP\Stats\send_pixel( [
				'vip-cache-url-purge-status' => 'deny-nonce',
			] );

			wp_send_json_error( [ 'error' => 'Unauthorized' ], 403 );
		}

		$urls = is_array( $req->urls ) && ! empty( $req->urls ) ? $req->urls : [];

		if ( empty( $urls ) ) {
			\Automattic\VIP\Stats\send_pixel( [
				'vip-cache-url-purge-status' => 'deny-no-urls',
			] );

			wp_send_json_error( [ 'error' => 'No URLs' ], 400 );
		}

		// URLs are validated in queue_purge_url.
		foreach ( $urls as $url_to_purge ) {
			$this->queue_purge_url( $url_to_purge );
		}

		\Automattic\VIP\Stats\send_pixel( [
			'vip-cache-action'            => 'user-url-purge',
			'vip-cache-url-purge-by-site' => VIP_GO_APP_ID,
			'vip-cache-url-purge-status'  => 'success',
		] );

		// Optimistically tell that the operation is successful and bail.
		wp_send_json_success(
			[
				'result' => sprintf( '✅ %d URLS purged', count( $urls ) ),
			]
		);
	}

	public function get_manual_purge_link() {
		if ( ! $this->can_purge_cache() ) {
			return;
		}

		echo '<hr>';

		$url = wp_nonce_url( admin_url( '?cm_purge_all' ), 'manual_purge' );

		$button_html  = esc_html__( 'Press the button below to force a purge of the entire page cache. If you are sandboxed, it will purge the sandbox cache by default. ' );
		$button_html .= '<strong>' . esc_html__( 'This button is visible to Automatticans only.' ) . '</strong>';
		$button_html .= '</p><p><span class="button"><a href="' . esc_url( $url ) . '"><strong>';
		$button_html .= esc_html__( 'Purge Page Cache' );
		$button_html .= '</strong></a></span>';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html() and esc_url() were used above
		echo "<p>{$button_html}</p>\n";
	}

	public function manual_purge_message() {
		echo "<div id='message' class='updated fade'><p><strong>" . esc_html__( 'Varnish cache purged!', 'varnish-http-purge' ) . '</strong></p></div>';
	}

	public function curl_multi( $requests ) {
		$curl_multi = curl_multi_init();

		if ( defined( 'PURGE_BATCH_SERVER_URL' ) && defined( 'PURGE_SERVER_TYPE' ) && 'mangle' === PURGE_SERVER_TYPE ) {
			$req_chunks = array_chunk( $requests, self::CACHE_PURGE_BATCH_SIZE, true );
			foreach ( $req_chunks as $req_chunk ) {
				$req_array = array();
				foreach ( $req_chunk as $req ) {
					$req_array[] = array(
						'group' => 'vip-go',
						'scope' => 'global',
						'type'  => $req['method'],
						'uri'   => $req['host'] . $req['uri'],
					);
				}
				$data = wp_json_encode( $req_array );

				$curl = curl_init( constant( 'PURGE_BATCH_SERVER_URL' ) );

				curl_setopt( $curl, CURLOPT_HEADER, false );
				curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
				curl_setopt( $curl, CURLOPT_TIMEOUT, 5 );
				curl_setopt( $curl, CURLOPT_POST, true );

				if ( 500 < strlen( $data ) ) {
					$compressed_data = gzencode( $data );
					curl_setopt( $curl, CURLOPT_HTTPHEADER, array( 'Content-Type: application/json', 'Content-Encoding: gzip' ) );
					curl_setopt( $curl, CURLOPT_POSTFIELDS, $compressed_data );
				} else {
					curl_setopt( $curl, CURLOPT_HTTPHEADER, array( 'Content-Type: application/json' ) );
					curl_setopt( $curl, CURLOPT_POSTFIELDS, $data );
				}

				curl_multi_add_handle( $curl_multi, $curl );
			}
		} elseif ( defined( 'PURGE_SERVER_TYPE' ) && 'mangle' === PURGE_SERVER_TYPE ) {
			foreach ( $requests as $req ) {
				$data = array(
					'group' => 'vip-go',
					'scope' => 'global',
					'type'  => $req['method'],
					'uri'   => $req['host'] . $req['uri'],
					'cb'    => 'nil',
				);
				$json = wp_json_encode( $data );
				$curl = curl_init();
				curl_setopt( $curl, CURLOPT_URL, constant( 'PURGE_SERVER_URL' ) );
				curl_setopt( $curl, CURLOPT_POST, true );
				curl_setopt( $curl, CURLOPT_POSTFIELDS, $json );
				curl_setopt( $curl, CURLOPT_HTTPHEADER, array(
					'Content-Type: application/json',
					'Content-Length: ' . strlen( $json ),
				) );
				curl_setopt( $curl, CURLOPT_TIMEOUT, 5 );
				curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
				curl_multi_add_handle( $curl_multi, $curl );
			}
		} else {
			foreach ( $requests as $req ) {
				// Purge HTTP
				$curl = curl_init();
				curl_setopt( $curl, CURLOPT_URL, "http://{$req['ip']}{$req['uri']}" );
				curl_setopt( $curl, CURLOPT_PORT, $req['port'] );
				curl_setopt( $curl, CURLOPT_HTTPHEADER, array( "Host: {$req['host']}", 'X-Forwarded-Proto: http' ) );
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
				curl_setopt( $curl, CURLOPT_HTTPHEADER, array( "Host: {$req['host']}", 'X-Forwarded-Proto: https' ) );
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
			} while ( CURLM_CALL_MULTI_PERFORM === $result );

			if ( CURLM_OK !== $result ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'curl_multi_exec() returned something different than CURLM_OK' );
			}

			curl_multi_select( $curl_multi, 0.2 );
		}

		// phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- assignment is OK here
		while ( $completed = curl_multi_info_read( $curl_multi ) ) {
			$info = curl_getinfo( $completed['handle'] );

			if ( ! $info['http_code'] && curl_error( $completed['handle'] ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'Error on: ' . $info['url'] . ' error: ' . curl_error( $completed['handle'] ) );
			}

			if ( '200' != $info['http_code'] ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'Request to ' . $info['url'] . ' returned HTTP code ' . $info['http_code'] );
			}

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
		if ( ! defined( 'PURGE_SERVER_TYPE' ) || 'varnish' === PURGE_SERVER_TYPE ) {
			global $varnish_servers;
		} else {
			$varnish_servers = array( constant( 'PURGE_SERVER_URL' ) );
		}

		$requests = array();

		if ( empty( $varnish_servers ) ) {
			return $requests;
		}

		$parsed = wp_parse_url( $url );
		if ( empty( $parsed['host'] ) ) {
			return $requests;
		}

		foreach ( $varnish_servers as $server ) {
			if ( 'BAN' == $method ) {
				$uri = $parsed['path'] . '?' . $parsed['query'];
			} else {
				$uri = '/';
				if ( isset( $parsed['path'] ) ) {
					$uri = $parsed['path'];
				}
				if ( isset( $parsed['query'] ) ) {
					$uri .= $parsed['query'];
				}
			}

			$request = array(
				'host'   => $parsed['host'],
				'uri'    => $uri,
				'method' => $method,
			);

			if ( ! defined( 'PURGE_SERVER_TYPE' ) || 'varnish' == PURGE_SERVER_TYPE ) {
				$srv             = explode( ':', $server[0] );
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

		$this->ban_urls   = array_unique( $this->ban_urls );
		$this->purge_urls = array_unique( $this->purge_urls );

		if ( empty( $this->ban_urls ) && empty( $this->purge_urls ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
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

		$num_ban_urls   = count( $this->ban_urls );
		$num_purge_urls = count( $this->purge_urls );
		if ( $num_ban_urls > self::MAX_BAN_URLS ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			trigger_error( sprintf( 'vip-cache-manager: Trying to BAN too many URLs (total count %s); limiting count to %d', number_format( $num_ban_urls ), (int) self::MAX_BAN_URLS ), E_USER_WARNING );
			array_splice( $this->ban_urls, self::MAX_BAN_URLS );
		}

		$max_purge_urls = defined( 'PURGE_BATCH_SERVER_URL' ) ? self::MAX_PURGE_BATCH_URLS : self::MAX_PURGE_URLS;
		if ( $num_purge_urls > $max_purge_urls ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			trigger_error( sprintf( 'vip-cache-manager: Trying to PURGE too many URLs (total count %s); limiting count to %d', number_format( $num_purge_urls ), number_format( $max_purge_urls ) ), E_USER_WARNING );
			array_splice( $this->purge_urls, $max_purge_urls );
		}

		$requests = array();
		foreach ( (array) $this->ban_urls as $url ) {
			$requests = array_merge( $requests, $this->build_purge_request( $url, 'BAN' ) );
		}

		foreach ( (array) $this->purge_urls as $url ) {
			$requests = array_merge( $requests, $this->build_purge_request( $url, 'PURGE' ) );
		}

		$this->ban_urls   = [];
		$this->purge_urls = [];

		if ( empty( $requests ) ) {
			return;
		}

		$this->curl_multi( $requests );
	}

	public function purge_site_cache() {
		if ( $this->site_cache_purged ) {
			return;
		}

		$this->ban_urls[]        = untrailingslashit( home_url() ) . '/(?!wp\-content\/uploads\/).*';
		$this->site_cache_purged = true;
	}

	public function queue_post_purge( $post_id ) {
		if ( $this->site_cache_purged ) {
			return false;
		}

		if ( defined( 'WP_IMPORTING' ) && true === WP_IMPORTING ) {
			return false;
		}

		$post = get_post( $post_id );
		if ( empty( $post ) ||
				'revision' === $post->post_type ||
				! in_array( get_post_status( $post_id ), array( 'publish', 'inherit', 'trash' ), true ) ) {
			return false;
		}

		if ( ! is_post_type_viewable( $post->post_type ) ) {
			return false;
		}

		// Skip purge if it is a new attachment
		if ( 'attachment' === $post->post_type && $post->post_date === $post->post_modified ) {
			return false;
		}

		$post_purge_urls   = array();
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
			$terms         = get_the_terms( $post_id, $taxonomy_name );
			if ( false === $terms ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$post_purge_urls = array_merge( $post_purge_urls, $this->get_purge_urls_for_term( $term ) );
			}
		}

		if ( 'post' === get_post_type( $post ) ) {
			$page_for_posts = get_option( 'page_for_posts' );
			if ( $page_for_posts ) {
				$post_purge_urls = array_merge( $post_purge_urls, [ get_permalink( $page_for_posts ) ] );
			}
		}

		// Purge the standard site feeds
		// @TODO Do we need to PURGE the comment feeds if the post_status is publish?
		$site_feeds      = array(
			get_bloginfo( 'rdf_url' ),
			get_bloginfo( 'rss_url' ),
			get_bloginfo( 'rss2_url' ),
			get_bloginfo( 'atom_url' ),
			get_bloginfo( 'comments_atom_url' ),
			get_bloginfo( 'comments_rss2_url' ),
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
		$taxonomy_object = get_taxonomy( $taxonomy );
		if ( ! $taxonomy_object ) {
			return;
		}

		if ( false === $taxonomy_object->public
			&& false === $taxonomy_object->publicly_queryable
			&& false === $taxonomy_object->show_in_rest ) {
			return;
		}

		$get_term_args = array(
			'taxonomy'   => $taxonomy,
			'include'    => $ids,
			'hide_empty' => false,
		);
		$terms         = get_terms( $get_term_args );
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

		$taxonomy_name   = $term->taxonomy;
		$maybe_purge_url = get_term_link( $term, $taxonomy_name );
		if ( is_wp_error( $maybe_purge_url ) ) {
			return array();
		}
		if ( $maybe_purge_url && is_string( $maybe_purge_url ) ) {
			$term_purge_urls[] = $maybe_purge_url;
			// Now add the pages for the archive we're clearing
			for ( $i = 2; $i <= $max_pages; $i++ ) {
				$maybe_purge_url_page = rtrim( $maybe_purge_url, '/' ) . '/' . ltrim( sprintf( $paging_endpoint, $i ), '/' );
				$term_purge_urls[]    = user_trailingslashit( $maybe_purge_url_page, 'paged' );
			}
		}
		$maybe_purge_feed_url = get_term_feed_link( $term->term_id, $taxonomy_name );
		if ( false !== $maybe_purge_feed_url ) {
			$term_purge_urls[] = $maybe_purge_feed_url;
		}

		/**
		 * Allows adding URLs to be PURGEd from cache when a given term_id is PURGEd.
		 *
		 * This is the taxonomy-agnostic version of the filter.
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
		 * @param array $term_purge_urls {
		 *     An array of URLs for you to add to
		 * }
		 * @param int    $term_id The ID of the term
		 */
		$term_purge_urls = apply_filters( 'wpcom_vip_cache_purge_term_urls', $term_purge_urls, $term->term_id );

		/**
		 * Allows adding URLs to be PURGEd from cache when a given term_id is PURGEd
		 *
		 * This is the taxonomy-specific version of the filter.
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
		 * @param array $term_purge_urls {
		 *     An array of URLs for you to add to
		 * }
		 * @param int  $term_id The ID of the term which is the primary reason for the purge
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
		$normalized_url = $this->normalize_purge_url( $url );
		$is_valid_url   = $this->is_valid_purge_url( $normalized_url );

		if ( false === $is_valid_url ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			trigger_error( sprintf( 'vip-cache-manager: Tried to PURGE invalid URL: %s', esc_html( $url ) ), E_USER_WARNING );
			return false;
		}

		$this->purge_urls[] = $normalized_url;
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

	protected function normalize_purge_url( $url ) {
		$normalized_url = esc_url_raw( $url );

		// Easy way to strip off query params and fragments since we don't have access to `http_build_url`.
		$query_index = mb_strpos( $normalized_url, '?' );
		if ( false !== $query_index ) {
			$normalized_url = mb_substr( $normalized_url, 0, $query_index );
		}

		$fragment_index = mb_strpos( $normalized_url, '#' );
		if ( false !== $fragment_index ) {
			$normalized_url = mb_substr( $normalized_url, 0, $fragment_index );
		}

		return $normalized_url;
	}

	protected function is_valid_purge_url( $url ) {
		return wp_http_validate_url( $url );
	}

	private function can_purge_cache() {
		if ( ! function_exists( 'is_proxied_automattician' ) ) {
			// Local environment; no purging necessary here
			return false;
		}

		return is_proxied_automattician();
	}

	private function current_user_can_purge_cache(): bool {
		return apply_filters( 'vip_cache_manager_can_purge_cache', current_user_can( 'manage_options' ), wp_get_current_user() );
	}
}

WPCOM_VIP_Cache_Manager::instance();
