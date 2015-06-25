<?php
require_once 'OoyalaApi.php';

$ooyala = get_option( 'ooyala' );
if ( empty( $ooyala['api_key'] ) || empty( $ooyala['api_secret'] ) )
	die();

/**
 * Parse out the path of the object being accessed. Ensures that we are posting
 * a request and on the assets path. Your application should have something
 * much more robust and sane than this.
 *
 * @param $path_info
 * A string containg the path being requested, with the leading slash.
 *
 * @return
 * An array of the path components, or FALSE if the path could not be parsed.
 */
function parsePath( $path_info ) {
	$path = explode( '/', substr( $path_info, 1 ) );
	if ( !in_array( $_SERVER['REQUEST_METHOD'], array( 'POST', 'PUT' ) ) || $path[0] != 'assets' ) {
		return FALSE;
	}
	return $path;
}

/**
 * Kill this request with a 403.
 */
function http403() {
	header( "HTTP/1.1 403 Access denied" );
	exit;
}

/**
 * Kill this request with a 500.
 *
 * @param $message
 * The exception message that caused this request to fail.
 */
function http500( $message ) {
	header( "HTTP/1.1 500 Internal Server Error" );
	//error_log( $message );
	exit;
}

/**
 * Create a new asset in preparation for uploading.
 *
 * @param $clientAsset
 * An object with the properties of the new asset to upload.
 *
 * @return
 * An object with the new embed code and the urls to upload each chunk to.
 */
function createAsset( $clientAsset ) {
	$ooyala = get_option( 'ooyala' );
	// Build our request to create the new asset.
	$asset = new stdClass();
	$properties = array(
		'name',
		'description',
		'file_name',
		'file_size',
		'chunk_size',
		'asset_type',
	);
	foreach ( $properties as $property ) {
		if ( !isset( $clientAsset->$property ) ) {
			http500( "The $property is missing from from the asset." );
		}
		$asset->{$property} = $clientAsset->{$property};
	}

	try {
		$api = new OoyalaApi( $ooyala['api_key'], $ooyala['api_secret'] );

		$asset = $api->post( "assets", $asset );
		return $asset;
	}
	catch( Exception $e ){
		http500( $e->getMessage() );
	}
}

function uploadAsset($clientAsset) {
	$ooyala = get_option( 'ooyala' );
	// Build our request to create the new asset.
	$asset = new stdClass();
	$properties = array(
		'asset_id'
	);
	foreach ( $properties as $property ) {
		if ( !isset( $clientAsset->$property ) ) {
			http500( "The $property is missing from from the asset." );
		}
		$asset->{$property} = $clientAsset->{$property};
	}

	try {
		$api = new OoyalaApi( $ooyala['api_key'], $ooyala['api_secret'] );

		$uploading_urls = $api->get( "assets/" . $asset->asset_id . "/uploading_urls" );
		return $uploading_urls;
	}
	catch( Exception $e ){
		http500( $e->getMessage() );
	}
}

/**
 * Set the upload status for an asset.
 *
 * @param $asset
 * An object representing the asset, containing at a minimum "embed_code" and
 * "status" properties.
 *
 * @return
 * The status of the asset.
 */
function uploadStatus( $asset_id ) {
	$ooyala = get_option( 'ooyala' );
	try {
		$api = new OoyalaApi( $ooyala['api_key'], $ooyala['api_secret'] );

		$response = $api->put( "assets/" . $asset_id . "/upload_status", array( 'status' => 'uploaded' ) );
		return $response;
	}
	catch( Exception $e ){
		http500( $e->getMessage() );
	}
}

// End of functions, begin our script here.

// We can't use $_POST since that only works if we are posting urlencoded data
// and not pure JSON.
$requestObject = json_decode( file_get_contents( "php://input" ) );

switch ( $_GET['request'] ) {
	case 'asset-create':
		$response = createAsset( (object) $_POST );
		echo json_encode( $response );
		break;
	case 'asset-upload':
		$response = uploadAsset( (object) $_GET );
		echo json_encode( $response );
		break;
	case 'asset-status':
		if ( !empty( $_GET['asset_id'] ) ) {
			$response = uploadStatus( sanitize_text_field( $_GET['asset_id'] ) );
			echo json_encode( $response );			
		}
		break;
	case 'labels-create':
		break;
	case 'labels-assign':
		break;
	case 'embed-code':
		$asset = new stdClass();
		$asset->embed_code = $path[1];
		$asset->status = $requestObject->status;
		$response = uploadStatus( $asset );
		echo json_encode( $response );
		break;
	default:
		// Invalid request
		http403();
}