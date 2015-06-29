<?php
/**
 * Search AngelList by object type
 *
 * @since 1.0
 */
class AngelList_Search {
	/**
	 * Base URI for API endpoint
	 * We do not authenticate and can therefore do not need the HTTPS overhead
	 *
	 * @since 1.0
	 * @var string
	 */
	const API_BASE_URI = 'http://api.angel.co/1/';

	/**
	 * Search AngelList companies by freeform text
	 *
	 * @since 1.0
	 * @param string $query search query
	 * @return array companies. array of associative arrays with id and name keys
	 */
	public static function startups( $query, $limit = 5 ) {
		// fix possible garbage in
		if ( ! is_string( $query ) || ! $query )
			return new WP_Error( 400, 'No search string provided.' );
		$limit = absint( $limit );
		if ( $limit < 1 )
			$limit = 5;

		$response = wp_remote_get( AngelList_Search::API_BASE_URI . 'search?' . http_build_query( array( 'type' => 'Startup', 'query' => $query ), '', '&' ) );
		if ( is_wp_error( $response ) )
			return new WP_Error( 500, 'AngelList API fail.' );

		$response_body = wp_remote_retrieve_body( $response );
		if ( empty( $response_body ) )
			return new WP_Error( 404, 'No matching companies found.' );
		$results = json_decode( $response_body, true );
		unset( $response_body );
		if ( empty( $results ) )
			return new WP_Error( 404, 'No matching companies found.' );

		$companies = array();
		$num_companies = 0;
		foreach ( $results as $result ) {
			if ( $num_companies === $limit )
				break;
			if ( ! array_key_exists( 'id', $result ) || ! is_int( $result['id'] ) || ! array_key_exists( 'name', $result ) || ! is_string( $result['name'] ) || ! trim( $result['name'] ) )
				continue;
			$companies[] = array( 'id' => absint( $result['id'] ), 'name' => trim( $result['name'] ) );
			$num_companies++;
		}

		if ( empty( $companies ) )
			return new WP_Error( 404, 'No matching companies found.' );
		return $companies;
	}
}
?>