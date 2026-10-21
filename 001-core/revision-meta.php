<?php
/**
 * Temporary REST revision metadata compatibility fix for Core #66329.
 *
 * Revision responses include defaults for non-revisioned metadata. Restoring
 * those defaults in the block editor can overwrite the live post's values.
 * Filter actual revision responses to retain only revision-enabled registered
 * metadata, respecting post-type overrides and REST aliases. Normal post
 * responses and own-draft autosaves returning the parent post are unchanged.
 *
 * This response-only workaround leaves REST OPTIONS schemas unchanged and
 * cannot recover already-cleared data. Before rollout, verify revision restores
 * on a sandbox, including two-editor RTC restore/reload/convergence behavior.
 * Remove this module and its require once all supported cores include the fix:
 * https://core.trac.wordpress.org/ticket/66329
 * https://github.com/WordPress/wordpress-develop/pull/14359
 *
 * @package Automattic\VIP\Core
 */

namespace Automattic\VIP\Core\RevisionMeta;

add_filter( 'rest_prepare_revision', __NAMESPACE__ . '\filter_revision_meta', 10, 3 );

/**
 * Omit defaults for metadata that is not stored on revisions.
 *
 * @param \WP_REST_Response $response Prepared REST response.
 * @param \WP_Post          $post     Revision or parent post (for own-draft autosaves).
 * @param \WP_REST_Request  $request  Request used to prepare the response.
 * @return \WP_REST_Response Response containing only revision-enabled registered meta.
 */
function filter_revision_meta( \WP_REST_Response $response, \WP_Post $post, \WP_REST_Request $request ): \WP_REST_Response {
	$data = $response->get_data();
	if ( 'revision' !== $post->post_type || ! function_exists( 'wp_post_revision_meta_keys' ) || ! is_array( $data['meta'] ?? null ) ) {
		return $response;
	}

	$post_type = get_post_type( $post->post_parent );
	if ( ! $post_type ) {
		return $response;
	}

	// Re-read only revisioned registrations: a shared REST alias may already hold
	// a non-revisioned field's default, so merely retaining that alias is not enough.
	$meta_fields = new class( $post_type ) extends \WP_REST_Post_Meta_Fields {
		/**
		 * Keep Core's schema/default/type handling, but exclude non-revisioned keys.
		 *
		 * @return array Registered revision-enabled REST fields, keyed by storage key.
		 */
		protected function get_registered_fields() {
			$fields          = parent::get_registered_fields();
			$revisioned_keys = (array) wp_post_revision_meta_keys( $this->post_type );
			foreach ( array_keys( $fields ) as $meta_key ) {
				if ( ! in_array( $meta_key, $revisioned_keys, true ) ) {
					unset( $fields[ $meta_key ] );
				}
			}
			return $fields;
		}
	};

	$registered_schema = ( new \WP_REST_Post_Meta_Fields( $post_type ) )->get_field_schema();
	$revisioned_meta   = rest_filter_response_by_context(
		$meta_fields->get_value( $post->ID, $request ),
		$meta_fields->get_field_schema(),
		! empty( $request['context'] ) ? $request['context'] : 'view'
	);
	// Preserve metadata added by other response filters without a registered field.
	$data['meta'] = array_merge( array_diff_key( $data['meta'], $registered_schema['properties'] ), $revisioned_meta );

	$response->set_data( $data );
	return $response;
}
