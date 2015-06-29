<?php
/**
 * WordPress Daylife API.
 *
 * @package Daylife
 * @subpackage API
 * @version 1.0
 * @author Pete Mall
 * @license GPL
 */
class WP_Daylife_API {
	var $access_key;
	var $shared_secret;
	var $source_filter_id;
	var $url;
	var $galleries_url;
	private $_options;

	const protocol = 'jsonrest';
	const version = '4.10';

	public function __construct( $args ) {
		$this->access_key = $args['access_key'];
		$this->shared_secret = $args['shared_secret'];
		$this->source_filter_id = $args['source_filter_id'];
		$this->url = trailingslashit( $args['api_endpoint'] ) . self::protocol . '/publicapi/' . self::version . '/';
		$this->galleries_url = trailingslashit( $args['galleries_endpoint'] ) . 'json/galleryapi/1.0/';
	}

	private function request( $call, $args = array() ) {
		$args['accesskey'] = $this->access_key;
		$args['signature'] = isset( $args['query'] ) ? $this->signature( $args['query'] ) : $this->signature( $args['content'] );
		$url = preg_match("~^(http)s?://~i", $this->url ) ? $this->url . $call : 'http://' . $this->url . $call;

		foreach ( $args as &$arg )
			$arg = rawurlencode( $arg );

		$response = wp_remote_get( add_query_arg( $args, $url ), array( 'timeout' => 15 ) );
		if ( 200 != wp_remote_retrieve_response_code( $response ) )
			return false;

		return json_decode( wp_remote_retrieve_body( $response ) );
	}

	private function request_galleries( $call, $args = array() ) {
		$url = preg_match("~^(http)s?://~i", $this->galleries_url ) ? $this->galleries_url . $call : 'http://' . $this->galleries_url . $call;

		$response = wp_remote_get( add_query_arg( $args, $url ) );
		if ( 200 != wp_remote_retrieve_response_code( $response ) )
			return false;

		return json_decode( wp_remote_retrieve_body( $response ) );

	}

	public function get_all_galleries( $args = array() ) {

		$defaults = array(
			'page'	=>	1,
			'sort'	=>	'created'
		);

		$response = $this->request_galleries( 'getGalleries', wp_parse_args( $args, $defaults ) );
		if ( $response )
			return $response->response->payload->galleries;

		return false;

	}

	public function get_gallery_by_id( $args = array() ) {

		$defaults = array(
			'api_image_width' => apply_filters( 'daylife_api_gallery_image_width', 650 )
		);

		$response = $this->request_galleries( 'getGallery', wp_parse_args( $args, $defaults ) );
		if ( $response )
			return $response->response->payload->gallery->images;

		return false;

	}

	private function _get_start_time() {
		if ( empty( $this->_options ) )
			$this->_options = get_option( 'daylife', array() );

		if ( ! isset( $this->_options[ 'start_time' ] ) )
			$this->_options[ 'start_time' ] = '-1 year';

		return $this->_options[ 'start_time' ];
	}

	public function search_getRelatedImages( $args = array() ) {
		$defaults = array(
			'source_filter_id' => $this->source_filter_id,
			'offset'           => 0,
			'limit'            => 8,
			'start_time'       => strtotime( $this->_get_start_time() ),
			'sort'             => 'date'
		);
		$response = $this->request( 'search_getRelatedImages', wp_parse_args( $args, $defaults ) );
		if ( $response )
		 	return $response->response->payload->image;

		return false;
	}

	public function content_getRelatedImages( $args ) {
		$defaults = array(
			'source_filter_id' => $this->source_filter_id,
			'offset'           => 0,
			'limit'            => 8,
			'start_time'       => strtotime( $this->_get_start_time() ),
			'sort'             => 'relevance'
		);
		$response = $this->request( 'content_getRelatedImages', wp_parse_args( $args, $defaults ) );
		if ( $response )
	 		return $response->response->payload->image;

		return false;
	}

	private function signature( $param ) {
		return md5( $this->access_key . $this->shared_secret . $param );
	}
}
