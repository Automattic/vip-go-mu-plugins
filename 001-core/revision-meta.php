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

add_filter( 'rest_prepare_revision', __NAMESPACE__ . '\filter_revision_meta', 10, 2 );

/**
 * Omit defaults for metadata that is not stored on revisions.
 *
 * @param \WP_REST_Response $response Prepared REST response.
 * @param \WP_Post          $post     Revision or parent post (for own-draft autosaves).
 * @return \WP_REST_Response Response containing only revision-enabled registered meta.
 */
function filter_revision_meta( \WP_REST_Response $response, \WP_Post $post ): \WP_REST_Response {
	if ( 'revision' !== $post->post_type || ! function_exists( 'wp_post_revision_meta_keys' ) ) {
		return $response;
	}

	$data = $response->get_data();
	if ( ! is_array( $data['meta'] ?? null ) ) {
		return $response;
	}

	$post_type = get_post_type( $post->post_parent );
	if ( ! $post_type ) {
		return $response;
	}

	$revisioned_keys = (array) wp_post_revision_meta_keys( $post_type );
	$registered      = array_merge( get_registered_meta_keys( 'post' ), get_registered_meta_keys( 'post', $post_type ) );
	foreach ( $registered as $meta_key => $args ) {
		if ( empty( $args['show_in_rest'] ) || in_array( $meta_key, $revisioned_keys, true ) ) {
			continue;
		}

		// REST names can differ from storage keys; subtype registrations override global ones.
		$rest_name = is_array( $args['show_in_rest'] ) ? ( $args['show_in_rest']['name'] ?? $meta_key ) : $meta_key;
		if ( array_key_exists( $rest_name, $data['meta'] ) ) {
			unset( $data['meta'][ $rest_name ] );
		}
	}

	$response->set_data( $data );
	return $response;
}
