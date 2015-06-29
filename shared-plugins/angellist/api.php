<?php
/**
 * Talk to the AngelList API
 *
 * @since 1.1
 */
class AngelList_API {
	/**
	 * Base API URL used in all requests
	 *
	 * @since 1.1
	 * @var string
	 */
	const BASE_URL = 'http://api.angel.co/1/';

	/**
	 * AngelList sends a 144x144 blank image for objects without their own image
	 * Compare against this value if you would like to remove the default image
	 *
	 * @since 1.1
	 * @var string
	 */
	const DEFAULT_IMAGE = 'http://angel.co/images/icons/startup-nopic.png';

	/**
	 * Special HTTP arguments to customize each request to the AngelList API
	 *
	 * @since 1.1
	 * @var array
	 */
	public static $http_args = array( 'httpversion' => '1.1', 'redirection' => 0, 'timeout' => 3, 'headers' => array( 'Accept' => 'application/json' ) );

	/**
	 * AngelList uses a HTTPS URL for static assets such as images to avoid mixed content issues if the parent page is served over HTTPS
	 * As of May 2012 these images are stored on Amazon S3. If we don't need HTTPS and Amazon's certificate we can construct a new URL based on an assumed CNAME entry for the bucket
	 * Avoids unncessary overhead of HTTPS when we know we are on HTTP (the majority case) & makes the URL a bit more pretty without the vendor hostname
	 *
	 * @since 1.1
	 * @param string $url AngelList static asset URL
	 * @return string cleaned up URL if incoming request was HTTP
	 */
	public static function filter_static_asset_url( $url ) {
		if ( is_ssl() ) {
			// reject including a non-SSL asset on the page if it will generate mixed content warnings
			return esc_url( $url, array( 'https' ) );
		}/* else if ( strlen( $url ) > 41 && substr_compare( $url, 'https://s3.amazonaws.com/photos.angel.co/', 0, 41 ) === 0 ) {
			return esc_url( 'http://photos.angel.co/' . substr( $url, 41 ), array( 'http' ) );
		} */
		return esc_url( $url, array( 'http', 'https' ) );
	}

	/**
	 * Basic check for parameter validity before sending a request
	 *
	 * @since 1.2
	 * @param int $company_id AngelList company identifier
	 * @return bool true if positive integer else false
	 */
	public static function is_valid_company_id( $company_id ) {
		if ( is_int( $company_id ) && $company_id > 0 )
			return true;
		return false;
	}

	/**
	 * Request a JSON URL from AngelList
	 *
	 * @since 1.2
	 * @param string relative path to be added to BASE_URL
	 * @return null|stdClass json_decode response as stdClass or null if request or JSON decode failed
	 */
	public static function get_json_url( $path ) {
		if ( ! ( is_string( $path ) && $path ) )
			return;

		$response = wp_remote_get( AngelList_API::BASE_URL . $path, AngelList_API::$http_args );
		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) != '200' )
			return;

		$response_body = wp_remote_retrieve_body( $response );
		if ( empty( $response_body ) )
			return;
		return json_decode( $response_body );
	}

	/**
	 * AngelList data for a single company
	 *
	 * @since 1.1
	 * @param int $company_id AngelList company identifer
	 * @return null|stdClass json_decode response as stdClass or null if request or JSON decode failed
	 */
	public static function get_company( $company_id ) {
		if ( ! AngelList_API::is_valid_company_id( $company_id ) )
			return;

		return AngelList_API::get_json_url( 'startups/' . $company_id );
	}

	/**
	 * Get a list of people associated with a company
	 *
	 * @since 1.1
	 * @param int $company_id AngelList company identifier
	 * @return null|stdClass json_decode response as stdClass or null if request or JSON decode failed or startup_roles not present
	 */
	public static function get_roles_by_company( $company_id ) {
		if ( ! AngelList_API::is_valid_company_id( $company_id ) )
			return;

		$json = AngelList_API::get_json_url( 'startup_roles?v=1&startup_id=' . $company_id );
		if ( ! empty( $json ) && isset( $json->startup_roles ) && ! empty( $json->startup_roles ) )
			return $json->startup_roles;
	}

	/**
	 * Get open job listings for a given company
	 *
	 * @since 1.2
	 * @param int $company_id AngelList company identifier
	 * @return null|stdClass json_decode response as stdClass or null if request or JSON decode failed
	 */
	public static function get_jobs_by_company( $company_id ) {
		if ( ! AngelList_API::is_valid_company_id( $company_id ) )
			return;

		return AngelList_API::get_json_url( 'startups/' . $company_id . '/jobs' );
	}
}
?>