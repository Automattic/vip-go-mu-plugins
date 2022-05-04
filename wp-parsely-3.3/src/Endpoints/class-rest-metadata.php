<?php
/**
 * Endpoints: REST Metadata endpoint class
 *
 * @package Parsely
 * @since   3.1.0
 */

declare(strict_types=1);

namespace Parsely\Endpoints;

use Parsely\Metadata;
use WP_Post;

/**
 * Injects Parse.ly Metadata to WordPress REST API.
 *
 * @since 3.1.0
 * @since 3.2.0 Renamed FQCN from `Parsely\Rest` to `Parsely\Endpoints\Rest_Metadata`.
 */
class Rest_Metadata extends Metadata_Endpoint {
	private const REST_VERSION = '1.1.0';

	/**
	 * Registers fields in WordPress REST API.
	 *
	 * @since 3.1.0
	 */
	public function run(): void {
		/**
		 * Filter whether REST API support is enabled or not.
		 *
		 * @since 3.1.0
		 *
		 * @param bool $enabled True if enabled, false if not.
		 */
		if ( apply_filters( 'wp_parsely_enable_rest_api_support', true ) && $this->parsely->api_key_is_set() ) {
			$this->register_meta();
		}
	}

	/**
	 * Registers the meta field on the appropriate resource types in the REST API.
	 *
	 * @since 3.1.0
	 */
	public function register_meta(): void {
		$options      = $this->parsely->get_options();
		$object_types = array_unique( array_merge( $options['track_post_types'], $options['track_page_types'] ) );

		/**
		 * Filters the list of object types that the Parse.ly REST API is hooked into.
		 *
		 * @since 3.1.0
		 *
		 * @param string[] $object_types Array of strings containing the object types, i.e. `page`,
		 *                 `post`, `term`.
		 */
		$object_types = apply_filters( 'wp_parsely_rest_object_types', $object_types );

		$args = array( 'get_callback' => array( $this, 'get_callback' ) );
		register_rest_field( $object_types, self::FIELD_NAME, $args );
	}

	/**
	 * Function to get hooked into the `get_callback` property of the `parsely`
	 * REST API field. It generates the `parsely` object in the REST API.
	 *
	 * @param array $object         The WordPress object to extract to render the metadata for,
	 *                              usually a post or a page.
	 * @return array<string, mixed> The `parsely` object to be rendered in the REST API. Contains a
	 *                              version number describing the response and the `meta` object
	 *                              containing the actual metadata.
	 */
	public function get_callback( array $object ): array {
		$post_id = $object['ID'] ?? $object['id'] ?? 0;
		$post    = WP_Post::get_instance( $post_id );
		$options = $this->parsely->get_options();

		if ( false === $post ) {
			$metadata = '';
		} else {
			$metadata = ( new Metadata( $this->parsely ) )->construct_metadata( $post );
		}

		$response = array(
			'version' => self::REST_VERSION,
			'meta'    => $metadata,
		);

		/**
		 * Filter whether REST API support in rendered string format is enabled
		 * or not.
		 *
		 * @since 3.1.0
		 *
		 * @param bool $enabled True if enabled, false if not.
		 * @param WP_Post|false $post Current post object.
		 */
		if ( apply_filters( 'wp_parsely_enable_rest_rendered_support', true, $post ) ) {
			$response['rendered'] = $this->get_rendered_meta( $options['meta_type'] );
		}

		/**
		 * Filter whether the REST API returns the tracker URL.
		 *
		 * @since 3.3.0
		 *
		 * @param bool $enabled True if enabled, false if not.
		 * @param WP_Post|false $post Current post object.
		 */
		if ( apply_filters( 'wp_parsely_enable_tracker_url', true, $post ) ) {
			$response['tracker_url'] = $this->parsely->get_tracker_url();
		}

		return $response;
	}
}
