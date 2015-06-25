<?php

$JWPLAYER_PROXY_METHODS = array(
	'/videos/list',
	'/channels/list',
	'/videos/create',
	'/videos/thumbnails/show',
	'/players/list',
);

function jwplayer_json_error( $message ) {
	$error = array(
		'status' => 'error',
		'message' => $message,
	);

	header( 'Content-Type: application/json' );
	echo json_encode( $error );
}

function jwplayer_proxy() {
	global $JWPLAYER_PROXY_METHODS;
	$nonce = '';

	if ( ! empty( $_GET['token'] ) ) {
		$nonce = sanitize_text_field( $_GET['token'] ); // input var okay
	}
	if ( ! wp_verify_nonce( $nonce, 'jwplayer-widget-nonce' ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_posts' ) ) {
		jwplayer_json_error( 'Access denied' );
		return;
	}

	if ( ! empty( $_GET['method'] ) ) {
		$method = sanitize_text_field( $_GET['method'] ); // input var okay
	}

	if ( null === $method ) {
		jwplayer_json_error( 'Method was not specified' );
		return;
	}

	if ( ! in_array( $method, $JWPLAYER_PROXY_METHODS ) ) {
		jwplayer_json_error( 'Access denied' );
		return;
	}

	$jwplayer_api = jwplayer_get_api_instance();

	if ( null === $jwplayer_api ) {
		jwplayer_json_error( 'Enter your API key and secret first' );
		return;
	}

	$params = array();

	foreach ( $_GET as $name => $value ) {
		if ( 'method' != $name ) {
			$params[ $name ] = sanitize_text_field( $value ); // input var okay
		}
	}

	$params['api_format'] = 'php';
	$response = $jwplayer_api->call( $method, $params );

	header( 'Content-Type: application/json' );
	echo json_encode( $response );
}