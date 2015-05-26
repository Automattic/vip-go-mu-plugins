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
		add_action( 'switch_theme', array( $this, 'purge_site_cache' ) );

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
			$curl = curl_init();
			curl_setopt( $curl, CURLOPT_URL, "http://{$req['ip']}{$req['uri']}" );
			curl_setopt( $curl, CURLOPT_PORT, $req['port'] );
			curl_setopt( $curl, CURLOPT_HTTPHEADER, array( "Host: {$req['host']}" ) );
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

	function queue_post_purge( $post_id ) {
		if ( $this->site_cache_purged )
			return;

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

		$categories = get_the_category( $post_id );
		if ( $categories ) {
			$category_base = get_option( 'category_base' ) ? get_option( 'category_base' ) : '/category';
			$category_base = trailingslashit( $category_base );

			foreach ( $categories as $cat )
				$this->purge_urls[] = home_url( $category_base . $cat->slug . '/' );
		}

		$tags = get_the_tags( $post_id );
		if ( $tags ) {
			$tag_base = get_option( 'tag_base' ) ? get_option( 'tag_base' ) : '/tag';
			$tag_base = trailingslashit( str_replace( '..', '', $tag_base ) );

			foreach ( $tags as $tag )
				$this->purge_urls[] = home_url( $tag_base . $tag->slug . '/' );
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

		foreach ( $feeds as $feed )
			$this->purge_urls[] = $feed;
	}
}

new WPCOM_VIP_Cache_Manager();
