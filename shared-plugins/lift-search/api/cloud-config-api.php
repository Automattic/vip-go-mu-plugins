<?php

require_once('cloud-schemas.php');

class Cloud_Config_API {

	public $access_key;
	public $secret_key;
	public $http_api;
	protected $last_error;
	protected $last_status_code;

	public function __construct( $access_key, $secret_key, $http_api ) {
		$this->access_key = $access_key;
		$this->secret_key = $secret_key;
		$this->http_api = $http_api;
	}

	public function get_last_error() {
		return $this->last_error;
	}

	/**
	 *
	 * @param Array $error with keys 'code' and 'message' set
	 */
	protected function set_last_error( $error ) {
		$this->last_error = $error;
	}

	protected function set_last_status_code( $status_code ) {
		$this->last_status_code = $status_code;
	}

	/**
	 * Turn a nested array into dot-separated 1 dimensional array
	 *
	 * @param $array
	 * @param string $prefix
	 * @return array
	 */
	protected function _flatten_keys( $array, $prefix = '' ) {

		$result = array( );

		foreach ( $array as $key => $value ) {

			if ( is_array( $value ) ) {

				$result += $this->_flatten_keys( $value, ( $prefix . $key . '.' ) );
			} else {

				$result[$prefix . $key] = $value;
			}
		}

		return $result;
	}

	/**
	 * Helper method to make a Configuration API request, stores error if encountered
	 *
	 * @param string $method
	 * @param array $payload
	 * @return array [response string, Cloud_Config_Request object used for request]
	 */
	protected function _make_request( $method, $payload = array( ), $flatten_keys = true, $region = false ) {

		if ( $payload && $flatten_keys ) {
			$payload = $this->_flatten_keys( $payload );
		}

		$credentials['access-key-id'] = $this->access_key;
		$credentials['secret-access-key'] = $this->secret_key;

		if ( !( $region && Lift_Search::is_valid_region($region) ) ) {
			$region = Lift_Search::get_domain_region();
		}

		$config = new Cloud_Config_Request( $credentials, $this->http_api, $region );

		$r = $config->send_request( $method, $payload );

		$this->set_last_status_code( $config->status_code );

		if ( $r && false !== ($r_json = json_decode( $r ) ) ) {

			$r_json = json_decode( $r );

			if ( isset( $r_json->Error ) || $this->last_status_code != '200' ) {

				$this->set_last_error( array('code' => $r_json->Error->Code, 'message' => $r_json->Error->Message ) );
				return false;
			}
			$response_name = $method . 'Response';
			$result_name = $method . 'Result';
			return $r_json->$response_name->$result_name;
		} else {
			$this->set_last_error( array('code' => 'invalid_response', 'message' => $config->error ) );
		}

		return $r;
	}

	/**
	 * @method DescribeDomains
	 * @return boolean
	 */
	public function DescribeDomains( $domain_names = array( ), $region = false ) {
		$payload = array( );

		if ( !empty( $domain_names ) ) {

			foreach ( array_values( $domain_names ) as $i => $domain_name ) {
				$payload['DomainNames.member.' . ($i + 1)] = $domain_name;
			}
		}

		return $this->_make_request( 'DescribeDomains', $payload, true, $region );
	}

	/**
	 * @method CreateDomain
	 * @param string $domain_name
	 */
	public function CreateDomain( $domain_name, $region ) {
		return $this->_make_request( 'CreateDomain', array( 'DomainName' => $domain_name ), true, $region );
	}

	public function DescribeServiceAccessPolicies( $domain_name ) {
		return $this->_make_request( 'DescribeServiceAccessPolicies', array( 'DomainName' => $domain_name ) );
	}

	/**
	 * Define a new Rank Expression
	 *
	 * @param string $domain_name
	 * @param string $rank_name
	 * @param string $rank_expression
	 * @return array|bool|mixed
	 */
	public function DefineRankExpression( $domain_name, $rank_name, $rank_expression ) {
		$payload = array(
			'DomainName' => $domain_name,
			'RankExpression' => array(
				'RankName' => $rank_name,
				'RankExpression' => $rank_expression
			)
		);

		return $this->_make_request( 'DefineRankExpression', $payload );
	}

	/**
	 * Delete a Rank Expression
	 *
	 * @param string $domain_name
	 * @param string $rank_name
	 * @return array|bool|mixed
	 */
	public function DeleteRankExpression( $domain_name, $rank_name ) {
		$payload = array(
			'DomainName' => $domain_name,
			'RankName' => $rank_name,
		);

		return $this->_make_request( 'DeleteRankExpression', $payload );
	}

	/**
	 * @method IndexDocuments
	 * @param string $domain_name
	 *
	 * @return bool true if request completed and documents will be/are being
	 * indexed or false if request could not be completed or domain was in a
	 * status that documents could not be indexed
	 */
	public function IndexDocuments( $domain_name, $region = false ) {
		return $this->_make_request( 'IndexDocuments', array( 'DomainName' => $domain_name ), true, $region );
	}

	public function UpdateServiceAccessPolicies( $domain_name, $policies, $region = false ) {
		$payload = array(
			'AccessPolicies' => $policies,
			'DomainName' => $domain_name,
		);

		return $this->_make_request( 'UpdateServiceAccessPolicies', $payload, false, $region );
	}

	public function __parse_index_options( $field_type, $passed_options = array( ) ) {
		$field_types = array(
			'uint' => array(
				'option_name' => 'UIntOptions',
				'options' => array(
					'default' => array(
						'name' => 'DefaultValue',
						'default' => null
					)
				)
			),
			'text' => array(
				'option_name' => 'TextOptions',
				'options' => array(
					'default' => array(
						'name' => 'DefaultValue',
						'default' => null
					),
					'facet' => array(
						'name' => 'FacetEnabled',
						'default' => 'false'
					),
					'result' => array(
						'name' => 'ResultEnabled',
						'default' => 'false'
					)
				)
			),
			'literal' => array(
				'option_name' => 'LiteralOptions',
				'options' => array(
					'default' => array(
						'name' => 'DefaultValue',
						'default' => null
					),
					'facet' => array(
						'name' => 'FacetEnabled',
						'default' => 'false'
					),
					'result' => array(
						'name' => 'ResultEnabled',
						'default' => 'false'
					),
					'search' => array(
						'name' => 'SearchEnabled',
						'default' => 'false'
					)
				)
			)
		);

		$index_option_name = $field_types[$field_type]['option_name'];
		$index_options = array( );

		foreach ( $field_types[$field_type]['options'] as $option_key => $option_info ) {

			$option_name = $option_info['name'];
			$option_value = $option_info['default'];

			if ( isset( $passed_options[$option_key] ) ) {

				$option_value = $passed_options[$option_key];
			}

			if ( !is_null( $option_value ) ) {

				$index_options[$option_name] = $option_value;
			}
		}

		return array( $index_option_name => $index_options );
	}

	/**
	 * Define a new index field
	 *
	 * @param string $domain_name
	 * @param string $field_name
	 * @param string $field_type
	 * @param array $options
	 * @return bool
	 */
	public function DefineIndexField( $domain_name, $field_name, $field_type, $options = array( ) ) {
		if ( !in_array( $field_type, array( 'uint', 'text', 'literal' ) ) ) {

			return false;
		}

		$payload = array(
			'DomainName' => $domain_name,
			'IndexField' => array(
				'IndexFieldName' => $field_name,
				'IndexFieldType' => $field_type
			)
		);

		$payload['IndexField'] += $this->__parse_index_options( $field_type, $options );

		return $this->_make_request( 'DefineIndexField', $payload, true );
	}

	public function DescribeIndexFields( $domain_name, $region = false ) {
		$payload = array(
			'DomainName' => $domain_name,
		);

		return $this->_make_request( 'DescribeIndexFields', $payload, true, $region );
	}

}

class Cloud_Config_Request {

	const DATE_FORMAT_SIGV4 = 'Ymd\THis\Z';

	private $endpoint = 'https://cloudsearch.us-east-1.amazonaws.com';
	private $api_version = '2011-02-01';
	private $key;
	private $secret_key;
	private $region = false;
	public $status_code;
	public $error;

	/**
	 *
	 * @var iLift_HTTP
	 */
	private $http_interface;

	public function __construct( $credentials, $http_interface, $region = false ) {
		$this->key = $credentials['access-key-id'];
		$this->secret_key = $credentials['secret-access-key'];
		$this->http_interface = $http_interface;
		if ( $region ) {
			$this->endpoint = str_replace('us-east-1', $region, $this->endpoint);
			$this->region   = $region;
		}
	}

	public function send_request( $operation, $payload = array( ) ) {
		// Determine signing values
		$current_time = time();
		$timestamp = gmdate( self::DATE_FORMAT_SIGV4, $current_time );

		// Initialize
		$this->headers = array( );
		$this->signed_headers = array( );
		$this->canonical_headers = array( );
		$this->query = array( 'body' => is_array( $payload ) ? $payload : array( ) );

		//
		$this->query['body']['Action'] = $operation;
		$this->query['body']['Version'] = $this->api_version;

		// Do a case-sensitive, natural order sort on the array keys.
		uksort( $this->query['body'], 'strcmp' );

		// Parse our request.
		$parsed_url = parse_url( $this->endpoint );
		$host_header = strtolower( $parsed_url['host'] );

		// Generate the querystring from $this->query
		$this->querystring = $this->to_query_string( $this->query );

		// Compose the request.
		$request_url = $this->endpoint . ( isset( $parsed_url['path'] ) ? '' : '/' );

		$this->querystring = $this->canonical_querystring();

		$this->headers['Content-Type'] = 'application/x-www-form-urlencoded; charset=utf-8';
		$this->headers['X-Amz-Date'] = $timestamp;
		$this->headers['Content-Length'] = strlen( $this->querystring );
		$this->headers['Content-MD5'] = $this->hex_to_base64( md5( $this->querystring ) );
		$this->headers['Host'] = $host_header;
		$this->headers['Accept'] = 'application/json';

		// Sort headers
		uksort( $this->headers, 'strnatcasecmp' );

		// Add headers to request and compute the string to sign
		foreach ( $this->headers as $header_key => $header_value ) {
			// Strip line breaks and remove consecutive spaces. Services collapse whitespace in signature calculation
			$this->headers[$header_key] = preg_replace( '/\s+/', ' ', trim( $header_value ) );

			$this->canonical_headers[] = strtolower( $header_key ) . ':' . $header_value;
			$this->signed_headers[] = strtolower( $header_key );
		}

		$this->headers['Authorization'] = $this->authorization( $timestamp );

		$post = $this->http_interface->post( $request_url, $this->canonical_querystring(), $this->headers );
		$this->error = ($post === false) ? $this->http_interface->getLastError() : false;
		$this->status_code = $this->http_interface->getStatusCode();
		return $post;
	}

	private function to_query_string( $array ) {
		$temp = array( );

		foreach ( $array as $key => $value ) {
			if ( is_string( $key ) && !is_array( $value ) ) {
				$temp[] = rawurlencode( $key ) . '=' . rawurlencode( $value );
			}
		}

		return implode( '&', $temp );
	}

	private function hex_to_base64( $str ) {
		$raw = '';

		for ( $i = 0; $i < strlen( $str ); $i += 2 ) {
			$raw .= chr( hexdec( substr( $str, $i, 2 ) ) );
		}

		return base64_encode( $raw );
	}

	protected function canonical_querystring() {
		if ( !isset( $this->canonical_querystring ) ) {
			$this->canonical_querystring = $this->to_signable_string( $this->query['body'] );
		}

		return $this->canonical_querystring;
	}

	public function encode_signature2( $string ) {
		$string = rawurlencode( $string );
		return str_replace( '%7E', '~', $string );
	}

	public function to_signable_string( $array ) {
		$t = array( );

		foreach ( $array as $k => $v ) {
			if ( is_array( $v ) ) {
				// json encode value if it is an array
				$value = $this->encode_signature2( json_encode( $v ) );
			} else {
				$value = $this->encode_signature2( $v );
			}
			$t[] = $this->encode_signature2( $k ) . '=' . $value;
		}

		return implode( '&', $t );
	}

	protected function authorization( $datetime ) {
		$access_key_id = $this->key;

		$parts = array( );
		$parts[] = "AWS4-HMAC-SHA256 Credential=${access_key_id}/" . $this->credential_string( $datetime );
		$parts[] = 'SignedHeaders=' . implode( ';', $this->signed_headers );
		$parts[] = 'Signature=' . $this->hex16( $this->signature( $datetime ) );

		return implode( ',', $parts );
	}

	protected function signature( $datetime ) {
		$k_date = $this->hmac( 'AWS4' . $this->secret_key, substr( $datetime, 0, 8 ) );
		$k_region = $this->hmac( $k_date, $this->region() );
		$k_service = $this->hmac( $k_region, $this->service() );
		$k_credentials = $this->hmac( $k_service, 'aws4_request' );
		$signature = $this->hmac( $k_credentials, $this->string_to_sign( $datetime ) );

		return $signature;
	}

	protected function string_to_sign( $datetime ) {
		$parts = array( );
		$parts[] = 'AWS4-HMAC-SHA256';
		$parts[] = $datetime;
		$parts[] = $this->credential_string( $datetime );
		$parts[] = $this->hex16( $this->hash( $this->canonical_request() ) );

		$this->string_to_sign = implode( "\n", $parts );

		return $this->string_to_sign;
	}

	protected function credential_string( $datetime ) {
		$parts = array( );
		$parts[] = substr( $datetime, 0, 8 );
		$parts[] = $this->region();
		$parts[] = $this->service();
		$parts[] = 'aws4_request';

		return implode( '/', $parts );
	}

	protected function canonical_request() {
		$parts = array( );
		$parts[] = 'POST';
		$parts[] = $this->canonical_uri();
		$parts[] = ''; // $parts[] = $this->canonical_querystring();
		$parts[] = implode( "\n", $this->canonical_headers ) . "\n";
		$parts[] = implode( ';', $this->signed_headers );
		$parts[] = $this->hex16( $this->hash( $this->canonical_querystring() ) );

		$this->canonical_request = implode( "\n", $parts );

		return $this->canonical_request;
	}

	protected function region() {
		if ( $this->region ) {
			return $this->region;
		}
		return 'us-east-1';
	}

	protected function service() {
		return 'cloudsearch';
	}

	protected function canonical_uri() {
		return '/';
	}

	protected function hex16( $value ) {
		$result = unpack( 'H*', $value );
		return reset( $result );
	}

	protected function hmac( $key, $string ) {
		return hash_hmac( 'sha256', $string, $key, true );
	}

	protected function hash( $string ) {
		return hash( 'sha256', $string, true );
	}

}