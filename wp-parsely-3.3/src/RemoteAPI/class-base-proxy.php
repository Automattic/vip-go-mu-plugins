<?php
/**
 * Remote API: Base Proxy class for all Parse.ly API endpoints
 *
 * @package Parsely
 * @since   3.2.0
 */

declare(strict_types=1);

namespace Parsely\RemoteAPI;

use Parsely\Parsely;
use RuntimeException;
use WP_Error;

/**
 * Base Proxy for all Parse.ly API endpoints.
 *
 * Child classes must add a protected `ENDPOINT` constant, and a protected
 * QUERY_FILTER constant.
 *
 * @since 3.2.0
 */
abstract class Base_Proxy implements Proxy {
	protected const ENDPOINT     = '';
	protected const QUERY_FILTER = '';

	/**
	 * Parsely Instance.
	 *
	 * @var Parsely
	 */
	private $parsely;

	/**
	 * Constructor.
	 *
	 * @param Parsely $parsely Parsely instance.
	 * @since 3.2.0
	 */
	public function __construct( Parsely $parsely ) {
		$this->parsely = $parsely;
	}

	/**
	 * Gets the URL for a particular Parse.ly API endpoint.
	 *
	 * @since 3.2.0
	 *
	 * @throws RuntimeException If the endpoint constant is not defined.
	 * @throws RuntimeException If the query filter constant is not defined.
	 *
	 * @param array<string, mixed> $query The query arguments to send to the remote API.
	 * @return string
	 */
	public function get_api_url( array $query ): string {
		if ( static::ENDPOINT === '' ) {
			throw new RuntimeException( 'ENDPOINT constant must be defined in child class.' );
		}
		if ( static::QUERY_FILTER === '' ) {
			throw new RuntimeException( 'QUERY_FILTER constant must be defined in child class.' );
		}

		$query['apikey'] = $this->parsely->get_api_key();
		$query           = array_filter( $query );

		// Sort by key so the query args are in alphabetical order.
		ksort( $query );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Hook names are defined in child classes.
		$query = apply_filters( static::QUERY_FILTER, $query );
		return add_query_arg( $query, static::ENDPOINT );
	}

	/**
	 * Implements the fetcher for the proxy interface.
	 *
	 * @since 3.2.0
	 *
	 * @param array<string, mixed> $query The query arguments to send to the remote API.
	 * @return WP_Error|array<string, mixed>
	 */
	public function get_items( array $query ) {
		$full_api_url = $this->get_api_url( $query );

		$result = wp_safe_remote_get( $full_api_url, array() );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$body    = wp_remote_retrieve_body( $result );
		$decoded = json_decode( $body );

		if ( ! is_object( $decoded ) ) {
			return new WP_Error( 400, __( 'Unable to decode upstream API response', 'wp-parsely' ) );
		}

		if ( ! property_exists( $decoded, 'data' ) ) {
			return new WP_Error( $decoded->code ?? 400, $decoded->message ?? __( 'Unable to read data from upstream API', 'wp-parsely' ) );
		}

		if ( ! is_array( $decoded->data ) ) {
			return new WP_Error( 400, __( 'Unable to parse data from upstream API', 'wp-parsely' ) );
		}

		return $decoded->data;
	}
}
