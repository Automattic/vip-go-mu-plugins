<?php

//Backward compat class for new Ooyala plugin

class Ooyala_Video extends Ooyala {

	private static $instance;

	var $plugin_url;

	protected function __construct() {
		$this->plugin_url = plugin_dir_url( __FILE__ );
	}

    public static function init() {

		//init new Ooyala class just in case it was not initialized yet
		$ooyala = Ooyala::instance();

		if (null === static::$instance) {
            static::$instance = new static();
        }

		return static::$instance;
	}

	public function media_button() {
		$ooyala = Ooyala::instance();
		return $ooyala->media_buttons();
	}

	private function __clone() {}

	private function __wakeup() {}
}

/**
 * WordPress Class for interfacing with the Ooyola Backlot API v2
 *
 * @package Ooyola
 * @subpackage API
 */
class WP_Ooyala_Backlot {
	var $partner_code;
	var $api_key;
	var $api_secret;

	public function __construct( $args ) {
		$this->partner_code = $args['partner_code'];
		$this->api_key = $args['api_key'];
		$this->api_secret = $args['api_secret'];
	}

	private function sign_request( $request, $params ) {
		$defaults = array(
			'api_key' => $this->api_key,
			'expires' => time() + 900,
		);
		$params = wp_parse_args( $params, $defaults );

		$signature = $this->api_secret . $request['method'] . $request['path'];
		ksort( $params );
		foreach ( $params as $key => $val )
			$signature .= $key . '=' . $val;

		$signature .= empty( $request['body'] ) ? '' : $request['body'];

		$signature = hash( 'sha256', $signature, true );
	    $signature = preg_replace( '#=+$#', '', trim( base64_encode( $signature ) ) );

		return $signature;
	}

	public function update( $body, $path ) {
		global $wp_version;
		$params = array(
			'api_key' => $this->api_key,
			'expires' => time() + 900
		);
		$path = '/v2/assets/' . $path;
		$params['signature'] = $this->sign_request( array( 'path' => $path, 'method' => 'PATCH', 'body' => $body ), $params );
		foreach ( $params as &$param )
			$param = rawurlencode( $param );

		$url = add_query_arg( $params, 'https://api.ooyala.com' . $path );

		if ( $wp_version >= 3.4 )
			return wp_remote_request( $url, array( 'headers' => array( 'Content-Type' => 'application/json' ), 'method' => 'PATCH', 'body' => $body, 'timeout' => apply_filters( 'ooyala_http_request_timeout', 10 ) ) );

		// Workaround for core bug - http://core.trac.wordpress.org/ticket/18589
		$curl = curl_init( $url );
		curl_setopt( $curl, CURLOPT_HEADER, false );
		curl_setopt( $curl, CURLOPT_HTTPHEADER, array( 'Content-type: application/json' ) );
		curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, "PATCH" );
		curl_setopt( $curl, CURLOPT_POSTFIELDS, $body );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );

		$response = curl_exec( $curl );
		$status = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
		curl_close( $curl );

		return array( 'body' => $response, 'response' => array( 'code' => $status ) );
	}

	public function query( $params, $request = array(), $return = false ) {
		$default_request = array(
			'method' => 'GET',
			'path'   => '/v2/assets'
		);
		$default_params = array(
			'api_key' => $this->api_key,
			'expires' => time() + 900,
			'where'   => "status='live'",
			'limit'   => 8,
			'orderby' => 'created_at descending'
		);
		$params = wp_parse_args( $params, $default_params );
		$request = wp_parse_args( $request, $default_request );

		$params['signature'] = $this->sign_request( $request, $params );
		foreach ( $params as &$param )
			$param = rawurlencode( $param );

		$url = add_query_arg( $params, 'https://api.ooyala.com' . $request['path'] );

		$response = wp_remote_get( $url, array( 'timeout' => apply_filters( 'ooyala_http_request_timeout', 10 ) ) );

		if ( $return )
			return $response;
		if ( 200 == wp_remote_retrieve_response_code( $response ) )
			$this->render_popup( wp_remote_retrieve_body( $response ) );
	}

	private function render_popup( $response ) {
		$videos = json_decode( $response );

		if ( empty( $videos->items ) ) {
			esc_html_e( 'No videos found.', 'ooyalavideo' );
			return;
		}

		$output = $page_token = $next = '';
		if ( !empty( $videos->next_page ) ) {
			parse_str( urldecode( $videos->next_page ) );
			$next = '<a href="' . esc_url( '#' . $page_token ) . '" class="next page-numbers ooyala-paging">Next &raquo;</a>';
		}

		$ids = isset( $_REQUEST['ooyala_ids'] ) ? $_REQUEST['ooyala_ids'] : '';
		$ids = explode( ',', $ids );
		$prev_token = -1;

		if ( $page_token ) {
			if ( in_array( $page_token, $ids ) ) {
				$key = array_keys( $ids, $page_token );
				$key = $key[0];
				$prev_token = $key > 1 ? $ids[ $key - 2 ] : '-1';
			} else {
				$c = count( $ids );
				$prev_token = $c > 1 ? $ids[ count( $ids ) - 2 ] : '-1';
				$ids[] = $page_token;
			}
		} elseif ( count( $ids ) > 1 ) {
			$prev_token = $ids[ count( $ids ) - 2 ];
		}

		if ( $next || $prev_token != -1 ) {
			$output .= '<div class="tablenav"><div class="tablenav-pages">';
			if ( $prev_token != -1 )
				$output .= '<a href="#' . esc_url( '#' . $prev_token ) . '" class="prev page-numbers ooyala-paging">&laquo; Prev</a>';

			if ( $next )
				$output .= $next;

			$output .= '</div></div>';
		}

		$ids = implode( ',', $ids );
		$output .= '<input type="hidden" id="ooyala-ids" value="' . esc_attr( $ids ) . '" />';

		$output .= '<div id="ooyala-items">';
		foreach ( $videos->items as $video ) {
			$output .= '
			<div id="ooyala-item-' . esc_attr( $video->embed_code ) . '" class="ooyala-item">
				<div class="item-title"><a href="#" title="' . esc_attr( $video->embed_code ) .'" class="use-shortcode">' . esc_attr( $video->name ) .'</a></div>
				<div class="photo">
					<a href="#" title="' . esc_attr( $video->embed_code ) .'" class="use-shortcode"><img src="' . esc_url( $video->preview_image_url ) . '"></a>';

			if ( current_theme_supports( 'post-thumbnails' ) )
				$output .= '	<p><a href="#" class="use-featured">Use as featured image</a></p>';

			$output.='
				</div>
			</div>';
		}
		$output.='</div><div style="clear:both;"></div>';
		echo $output;
	}

	static function get_promo_thumbnail( $xml ) {

		$results = simplexml_load_string( $xml );
		if ( !$results )
			return new WP_Error( 'noresults', __( 'Malformed XML' , 'ooyalavideo'));

		return $results->promoThumbnail;
	}
}
