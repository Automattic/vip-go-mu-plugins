<?php
/**
 * Endpoints: Parse.ly `/analytics/posts` API proxy endpoint class
 *
 * @package Parsely
 * @since   3.4.0
 */

declare(strict_types=1);

namespace Parsely\Endpoints;

use stdClass;
use WP_REST_Request;

/**
 * Configures the `/analytics/posts` REST API endpoint.
 */
final class Analytics_Posts_API_Proxy extends Base_API_Proxy {

	/**
	 * Registers the endpoint's WP REST route.
	 */
	public function run(): void {
		$this->register_endpoint( '/analytics/posts' );
	}

	/**
	 * Cached "proxy" to the Parse.ly `/analytics/posts` API endpoint.
	 *
	 * @param WP_REST_Request $request The request object.
	 */
	public function get_items( WP_REST_Request $request ): stdClass {
		return $this->get_data( $request );
	}

	/**
	 * Generates the final data from the passed response.
	 *
	 * @param array<string, mixed> $response The response received by the proxy.
	 * @return array<stdClass> The generated data.
	 */
	protected function generate_data( array $response ): array {
		$date_format    = get_option( 'date_format' );
		$stats_base_url = trailingslashit( 'https://dash.parsely.com/' . esc_js( $this->parsely->get_api_key() ) ) . 'find';

		$result = array_map(
			static function( stdClass $item ) use ( $date_format, $stats_base_url ) {
				return (object) array(
					'author'   => $item->author,
					'date'     => wp_date( $date_format, strtotime( $item->pub_date ) ),
					'id'       => $item->url,
					'statsUrl' => $stats_base_url . '?url=' . rawurlencode( $item->url ),
					'title'    => $item->title,
					'url'      => $item->url,
					'views'    => $item->metrics->views,
				);
			},
			$response
		);

		return $result;
	}

	/**
	 * Determines if there are enough permissions to call the endpoint.
	 *
	 * @return bool
	 */
	public function permission_callback(): bool {
		// Unauthenticated.
		return true;
	}
}
