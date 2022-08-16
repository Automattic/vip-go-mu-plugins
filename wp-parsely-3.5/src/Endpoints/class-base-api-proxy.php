<?php
/**
 * Endpoints: Base API proxy endpoint class for all API proxy endpoints
 *
 * @package Parsely
 * @since   3.4.0
 */

declare(strict_types=1);

namespace Parsely\Endpoints;

use Parsely\Parsely;
use Parsely\RemoteAPI\Proxy;
use stdClass;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Configures a REST API endpoint for use.
 */
abstract class Base_API_Proxy {
	/**
	 * Parsely object instance.
	 *
	 * @var Parsely
	 */
	private $parsely;

	/**
	 * Proxy object which does the actual calls to the Parse.ly API.
	 *
	 * @var Proxy
	 */
	private $proxy;

	/**
	 * Registers the endpoint's WP REST route.
	 */
	abstract public function run(): void;

	/**
	 * Generates the final data from the passed response.
	 *
	 * @param array<string, mixed> $response The response received by the proxy.
	 * @return array<stdClass> The generated data.
	 */
	abstract protected function generate_data( array $response ): array;

	/**
	 * Cached "proxy" to the Parse.ly API endpoint.
	 *
	 * @param WP_REST_Request $request The request object.
	 */
	abstract public function get_items( WP_REST_Request $request ): stdClass;

	/**
	 * Determines if there are enough permissions to call the endpoint.
	 *
	 * @return bool
	 */
	abstract public function permission_callback(): bool;

	/**
	 * Constructor.
	 *
	 * @param Parsely $parsely Instance of Parsely class.
	 * @param Proxy   $proxy   Proxy object which does the actual calls to the
	 *                         Parse.ly API.
	 */
	public function __construct( Parsely $parsely, Proxy $proxy ) {
		$this->parsely = $parsely;
		$this->proxy   = $proxy;
	}

	/**
	 * Registers the endpoint's WP REST route.
	 *
	 * @param string $endpoint The endpoint (e.g. /analytics/posts).
	 */
	protected function register_endpoint( string $endpoint ): void {
		$filter_key = trim( str_replace( '/', '_', $endpoint ), '_' );
		if ( ! apply_filters( 'wp_parsely_enable_' . $filter_key . '_api_proxy', true ) ) {
			return;
		}

		$get_items_args = array(
			'query' => array(
				'default'           => array(),
				'sanitize_callback' => function ( array $query ) {
					$sanitized_query = array();
					foreach ( $query as $key => $value ) {
						$sanitized_query[ sanitize_key( $key ) ] = sanitize_text_field( $value );
					}

					return $sanitized_query;
				},
			),
		);

		$rest_route_args = array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'permission_callback' ),
				'args'                => $get_items_args,
			),
		);

		register_rest_route( 'wp-parsely/v1', $endpoint, $rest_route_args );
	}

	/**
	 * Cached "proxy" to the Parsely `/analytics` endpoint.
	 *
	 * @param WP_REST_Request $request            The request object.
	 * @param bool            $require_api_secret Specifies if the API Secret is required.
	 * @param string          $param_item         The param element to use to get the items.
	 * @return stdClass
	 */
	protected function get_data( WP_REST_Request $request, bool $require_api_secret = true, string $param_item = null ): stdClass {
		if ( false === $this->parsely->api_key_is_set() ) {
			return (object) array(
				'data'  => array(),
				'error' => new WP_Error( 400, __( 'A Parse.ly API Key must be set in site options to use this endpoint', 'wp-parsely' ) ),
			);
		}

		if ( true === $require_api_secret && false === $this->parsely->api_secret_is_set() ) {
			return (object) array(
				'data'  => array(),
				'error' => new WP_Error( 400, __( 'A Parse.ly API Secret must be set in site options to use this endpoint', 'wp-parsely' ) ),
			);
		}

		if ( null !== $param_item ) {
			$params = $request->get_param( $param_item );
		} else {
			$params = $request->get_params();
		}

		// A proxy with caching behavior is used here.
		$response = $this->proxy->get_items( $params );

		if ( is_wp_error( $response ) ) {
			return (object) array(
				'data'  => array(),
				'error' => $response,
			);
		}

		return (object) array( 'data' => $this->generate_data( $response ) );
	}
}
