<?php
/**
 * Endpoints: GraphQL metadata endpoint class
 *
 * Adds support for GraphQL.
 * Note: This class will only work if the WPGraphQL plugin is installed.
 *
 * @package Parsely
 * @since   3.2.0
 */

declare(strict_types=1);

namespace Parsely\Endpoints;

use WP_Post;

/**
 * Adds Parse.ly metadata fields to the WP GraphQL server.
 *
 * @since 3.2.0
 */
class GraphQL_Metadata extends Metadata_Endpoint {
	private const GRAPHQL_VERSION        = '1.0.0';
	private const GRAPHQL_CONTAINER_TYPE = 'ParselyMetaContainer';

	/**
	 * Registers fields in WPGraphQL plugin.
	 *
	 * @since 3.2.0
	 */
	public function run(): void {
		/**
		 * Filters whether GraphQL support is enabled or not.
		 *
		 * @since 3.2.0
		 *
		 * @param bool $enabled True if enabled, false if not.
		 */
		if ( apply_filters( 'wp_parsely_enable_graphql_support', true ) && $this->parsely->api_key_is_set() ) {
			add_action( 'graphql_register_types', array( $this, 'register_meta' ) );
		}
	}

	/**
	 * Registers the meta field on the appropriate resource types in the REST
	 * API.
	 *
	 * @since 3.2.0
	 */
	public function register_meta(): void {
		$this->register_object_types();
		$this->register_fields();
	}

	/**
	 * Registers the new custom types for Parse.ly Metadata into the GraphQL
	 * instance.
	 *
	 * @since 3.2.0
	 */
	private function register_object_types(): void {
		$container_type = array(
			'description' => __( 'Parse.ly Metadata root type.', 'wp-parsely' ),
			'fields'      => array(
				'version'       => array(
					'type'        => 'String',
					'description' => __( 'Revision of the metadata format.', 'wp-parsely' ),
				),
				'scriptUrl'     => array(
					'type'        => 'String',
					'description' => __( 'URL of the Parse.ly tracking script, specific to the site.', 'wp-parsely' ),
				),
				'repeatedMetas' => array(
					'type'        => 'String',
					'description' => __(
						'HTML string containing the metadata in JSON-LD. Intended to be rendered in the front-end as is.',
						'wp-parsely'
					),
					'resolve'     => function() {
						return self::get_rendered_meta( 'repeated_metas' );
					},
				),
				'jsonLd'        => array(
					'type'        => 'String',
					'args'        => array(
						'removeWrappingTag' => array(
							'type'        => 'Boolean',
							'description' => __( 'Return rendered tags without the `script` wrapping tags.', 'wp-parsely' ),
						),
					),
					'description' => __(
						'HTML string containing the metadata in JSON-LD. Intended to be rendered in the front-end as is.',
						'wp-parsely'
					),
					'resolve'     => function( array $parsely_meta, array $args ) {
						$json_ld = self::get_rendered_meta( 'json_ld' );

						if ( isset( $args['removeWrappingTag'] ) && true === $args['removeWrappingTag'] ) {
							// phpcs:ignore WordPressVIPMinimum.Functions.StripTags.StripTagsOneParameter
							$json_ld = strip_tags( $json_ld );
							$json_ld = trim( $json_ld );
						}

						return $json_ld;
					},
				),
				'isTracked'     => array(
					'type'        => 'Boolean',
					'description' => __(
						'Boolean indicating whether the current object\'s page type should be tracked according to user\'s settings.',
						'wp-parsely'
					),
				),
			),
		);
		register_graphql_object_type( self::GRAPHQL_CONTAINER_TYPE, $container_type );
	}

	/**
	 * Registers the custom metadata fields, so they can be queried in GraphQL.
	 *
	 * @since 3.2.0
	 */
	private function register_fields(): void {
		$resolve = function ( \WPGraphQL\Model\Post $graphql_post ) {
			$post_id = $graphql_post->ID;
			$post    = WP_Post::get_instance( $post_id );

			if ( false === $post ) {
				return null;
			}

			$options             = $this->parsely->get_options();
			$object_types        = array_unique( array_merge( $options['track_post_types'], $options['track_page_types'] ) );
			$current_object_type = get_post_type( $post );

			return array(
				'version'   => self::GRAPHQL_VERSION,
				'scriptUrl' => $this->parsely->get_tracker_url(),
				'isTracked' => in_array( $current_object_type, $object_types, true ),
			);
		};

		$config = array(
			'type'        => self::GRAPHQL_CONTAINER_TYPE,
			'description' => __(
				'Parse.ly metadata fields, to be rendered in the front-end so they can be parsed by the crawler. See https://www.parse.ly/help/integration/crawler.',
				'wp-parsely'
			),
			'resolve'     => $resolve,
		);
		register_graphql_field( 'ContentNode', self::FIELD_NAME, $config );
	}
}
