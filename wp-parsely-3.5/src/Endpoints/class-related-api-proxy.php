<?php
/**
 * Endpoints: Parse.ly `/related` API proxy endpoint class
 *
 * @package Parsely
 * @since   3.2.0
 */

declare(strict_types=1);

namespace Parsely\Endpoints;

use stdClass;
use WP_REST_Request;

/**
 * Configures the `/related` REST API endpoint.
 */
final class Related_API_Proxy extends Base_API_Proxy {

	/**
	 * Registers the endpoint's WP REST route.
	 */
	public function run(): void {
		$this->register_endpoint( '/related' );
	}

	/**
	 * Cached "proxy" to the Parse.ly `/related` API endpoint.
	 *
	 * @param WP_REST_Request $request The request object.
	 */
	public function get_items( WP_REST_Request $request ): stdClass {
		return $this->get_data( $request, false, 'query' );
	}

	/**
	 * Generates the final data from the passed response.
	 *
	 * @param array<string, mixed> $response The response received by the proxy.
	 * @return array<stdClass> The generated data.
	 */
	protected function generate_data( array $response ): array {
		$result = array_map(
			static function( stdClass $item ) {
				return (object) array(
					'image_url'        => $item->image_url,
					'thumb_url_medium' => $item->thumb_url_medium,
					'title'            => $item->title,
					'url'              => $item->url,
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
