<?php
/**
 * REST integration regressions for the temporary Core #66329 metadata workaround.
 *
 * @package Automattic\VIP\Core\RevisionMeta
 */

namespace Automattic\VIP\Core\RevisionMeta;

use WP_REST_Request;
use WP_REST_Response;
use WP_UnitTestCase;

/**
 * Exercise the editor's revision restore against real WordPress REST controllers.
 */
class VIP_Revision_Meta_Test extends WP_UnitTestCase {
	/**
	 * Y.encodeStateAsUpdate() for a Y.Doc with clientID 66329 and
	 * getText('content').insert(0, 'Shared RTC content'), encoded as base64.
	 */
	private const CRDT_DOCUMENT = 'AQGZhgQABAEHY29udGVudBJTaGFyZWQgUlRDIGNvbnRlbnQA';

	/**
	 * Register revisioned and non-revisioned metadata, including Gutenberg's CRDT contract.
	 */
	public function setUp(): void {
		parent::setUp();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );

		// Match lib/compat/wordpress-7.1/collaboration.php in the managed Gutenberg build.
		register_meta( 'post', '_crdt_document', [
			'auth_callback'     => static function ( bool $allowed, string $meta_key, int $object_id, int $user_id ): bool {
				return user_can( $user_id, 'edit_post', $object_id );
			},
			'revisions_enabled' => false,
			'show_in_rest'      => [
				'schema' => [
					'type'    => 'string',
					'context' => [ 'edit' ],
				],
			],
			'single'            => true,
			'type'              => 'string',
		] );
		register_post_meta( 'post', 'rtc_revisioned', [
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => [ 'name' => 'revisioned_alias' ],
			'revisions_enabled' => true,
		] );
		register_post_meta( 'post', 'rtc_unrelated', [
			'type'         => 'string',
			'single'       => true,
			'show_in_rest' => true,
		] );
	}

	/**
	 * Clear the REST server so controller schemas never leak between tests.
	 */
	public function tearDown(): void {
		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tearDown();
	}

	/**
	 * An earlier response filter's error must pass through without a type error.
	 */
	public function test_earlier_revision_filter_error_passes_through(): void {
		$post_id     = $this->create_document();
		$revision_id = array_key_first( wp_get_post_revisions( $post_id ) );
		$error       = new \WP_Error( 'revision_unavailable', 'Revision unavailable.', [ 'status' => 403 ] );
		$filter      = static function () use ( $error ) {
			return $error;
		};
		add_filter( 'rest_prepare_revision', $filter, 9 );

		try {
			$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id . '/revisions/' . $revision_id );
			$this->assertSame( $error, apply_filters( 'rest_prepare_revision', new WP_REST_Response(), get_post( $revision_id ), $request ) );
			$response = $this->request( 'GET', $request->get_route() );
			$this->assertSame( 403, $response->get_status() );
			$this->assertSame( 'revision_unavailable', $response->get_data()['code'] );
		} finally {
			remove_filter( 'rest_prepare_revision', $filter, 9 );
		}
	}

	/**
	 * Unexpected earlier filter values are not response objects to modify.
	 */
	public function test_earlier_revision_filter_unexpected_values_pass_through(): void {
		$post    = self::factory()->post->create_and_get( [ 'post_type' => 'revision' ] );
		$request = new WP_REST_Request( 'GET' );
		foreach ( [ null, false, [], new \stdClass() ] as $value ) {
			$filter = static function () use ( $value ) {
				return $value;
			};
			add_filter( 'rest_prepare_revision', $filter, 9 );

			try {
				$this->assertSame( $value, apply_filters( 'rest_prepare_revision', new WP_REST_Response(), $post, $request ) );
			} finally {
				remove_filter( 'rest_prepare_revision', $filter, 9 );
			}
		}
	}

	/**
	 * Dispatch a JSON REST request with edit context.
	 *
	 * @param string $method HTTP method.
	 * @param string $route REST route.
	 * @param array  $body JSON request body.
	 * @return WP_REST_Response Response from the real REST server.
	 */
	private function request( string $method, string $route, array $body = [] ): WP_REST_Response {
		$request = new WP_REST_Request( $method, $route );
		$request->set_param( 'context', 'edit' );
		if ( $body ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( wp_json_encode( $body ) );
		}

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Create a post and save a real CRDT document through the post REST endpoint.
	 *
	 * @param string $status Post status.
	 * @return int Post ID.
	 */
	private function create_document( string $status = 'publish' ): int {
		$post_id  = self::factory()->post->create( [
			'post_author'  => get_current_user_id(),
			'post_content' => 'Original content.',
			'post_status'  => $status,
		] );
		$response = $this->request( 'POST', '/wp/v2/posts/' . $post_id, [
			'content' => 'Snapshot content.',
			'meta'    => [
				'_crdt_document'   => self::CRDT_DOCUMENT,
				'revisioned_alias' => 'snapshot meta',
				'rtc_unrelated'    => 'customer value',
			],
		] );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( self::CRDT_DOCUMENT, $response->get_data()['meta']['_crdt_document'] );
		$this->assertSame( self::CRDT_DOCUMENT, get_post_meta( $post_id, '_crdt_document', true ) );

		return $post_id;
	}

	/**
	 * Restoring the revision's content + meta must not erase the shared document.
	 */
	public function test_editor_restore_preserves_live_crdt_document(): void {
		$post_id     = $this->create_document();
		$revision_id = array_key_first( wp_get_post_revisions( $post_id ) );
		$this->assertNotEmpty( $revision_id );
		$this->assertFalse( metadata_exists( 'post', $revision_id, '_crdt_document' ) );
		$this->assertSame( 'snapshot meta', get_post_meta( $revision_id, 'rtc_revisioned', true ) );

		$this->assertSame( 200, $this->request( 'POST', '/wp/v2/posts/' . $post_id, [
			'content' => 'Latest content.',
			'meta'    => [ 'revisioned_alias' => 'latest meta' ],
		] )->get_status() );

		$revision = $this->request( 'GET', '/wp/v2/posts/' . $post_id . '/revisions/' . $revision_id );
		$this->assertSame( 200, $revision->get_status() );
		$data = $revision->get_data();

		// Match the editor: GET revision in edit context, then save its content and meta.
		$restore = $this->request( 'POST', '/wp/v2/posts/' . $post_id, [
			'content' => $data['content']['raw'],
			'meta'    => $data['meta'],
		] );
		$this->assertSame( 200, $restore->get_status() );
		$this->assertSame( self::CRDT_DOCUMENT, get_post_meta( $post_id, '_crdt_document', true ), 'A revision restore must not reset persisted CRDT state.' );
		$this->assertSame( 'customer value', get_post_meta( $post_id, 'rtc_unrelated', true ) );
		$this->assertSame( 'Snapshot content.', get_post( $post_id )->post_content );
		$this->assertSame( 'snapshot meta', get_post_meta( $post_id, 'rtc_revisioned', true ) );
		$this->assertArrayNotHasKey( '_crdt_document', $data['meta'] );

		$collection = $this->request( 'GET', '/wp/v2/posts/' . $post_id . '/revisions' );
		$this->assertSame( 200, $collection->get_status() );
		foreach ( $collection->get_data() as $item ) {
			$this->assertArrayNotHasKey( '_crdt_document', $item['meta'] );
			$this->assertArrayHasKey( 'revisioned_alias', $item['meta'] );
			// Non-revisioned metadata is omitted even without the RTC integration.
			$this->assertArrayNotHasKey( 'rtc_unrelated', $item['meta'] );
		}
	}

	/**
	 * Autosaves that are revisions must not expose an empty CRDT default either.
	 *
	 * Core defines the native DOING_AUTOSAVE constant; isolate that request state.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_autosave_revision_omits_crdt_but_retains_revisioned_meta(): void {
		$post_id  = $this->create_document();
		$response = $this->request( 'POST', '/wp/v2/posts/' . $post_id . '/autosaves', [
			'content' => 'Autosaved content.',
		] );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'revision', get_post_type( $data['id'] ) );
		$this->assertArrayNotHasKey( '_crdt_document', $data['meta'] );
		$this->assertSame( 'snapshot meta', $data['meta']['revisioned_alias'] );
		$this->assertSame( self::CRDT_DOCUMENT, get_post_meta( $post_id, '_crdt_document', true ) );

		foreach ( [ '/autosaves', '/autosaves/' . $data['id'] ] as $suffix ) {
			$response = $this->request( 'GET', '/wp/v2/posts/' . $post_id . $suffix );
			$this->assertSame( 200, $response->get_status() );
			$items = '/autosaves' === $suffix ? $response->get_data() : [ $response->get_data() ];
			foreach ( $items as $item ) {
				$this->assertArrayNotHasKey( '_crdt_document', $item['meta'] );
			}
		}
	}

	/**
	 * An author's draft autosave returns the parent post, not a revision.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_own_draft_autosave_and_normal_post_reads_keep_live_meta(): void {
		$post_id = $this->create_document( 'draft' );
		foreach ( [ 'GET', 'POST' ] as $method ) {
			$route    = '/wp/v2/posts/' . $post_id . ( 'POST' === $method ? '/autosaves' : '' );
			$response = $this->request( $method, $route, 'POST' === $method ? [ 'content' => 'Draft autosave.' ] : [] );
			$this->assertSame( 200, $response->get_status() );
			$this->assertSame( $post_id, $response->get_data()['id'] );
			$this->assertSame( self::CRDT_DOCUMENT, $response->get_data()['meta']['_crdt_document'] );
			$this->assertSame( 'customer value', $response->get_data()['meta']['rtc_unrelated'] );
		}
	}

	/**
	 * Explicit revision opt-in remains authoritative, even for the CRDT key.
	 */
	public function test_explicitly_revisioned_crdt_meta_is_unchanged(): void {
		$args                      = get_registered_meta_keys( 'post' )['_crdt_document'];
		$args['revisions_enabled'] = true;
		register_meta( 'post', '_crdt_document', $args );
		$post_id     = $this->create_document();
		$revision_id = array_key_first( wp_get_post_revisions( $post_id ) );
		$response    = $this->request( 'GET', '/wp/v2/posts/' . $post_id . '/revisions/' . $revision_id );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( self::CRDT_DOCUMENT, $response->get_data()['meta']['_crdt_document'] );
	}

	/**
	 * Field projections without meta must not gain a meta property.
	 */
	public function test_revision_field_projection_does_not_gain_meta(): void {
		$post_id     = $this->create_document();
		$revision_id = array_key_first( wp_get_post_revisions( $post_id ) );
		$request     = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id . '/revisions/' . $revision_id );
		$request->set_query_params( [
			'context' => 'edit',
			'_fields' => 'id',
		] );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [ 'id' => $revision_id ], $response->get_data() );
	}

	/**
	 * A subtype can opt in to revisions without changing the global registration.
	 */
	public function test_subtype_revision_opt_in_overrides_global_crdt_registration(): void {
		$args                      = get_registered_meta_keys( 'post' )['_crdt_document'];
		$args['revisions_enabled'] = true;
		register_post_meta( 'post', '_crdt_document', $args );
		$this->assertFalse( get_registered_meta_keys( 'post' )['_crdt_document']['revisions_enabled'] );
		$post_id     = $this->create_document();
		$revision_id = array_key_first( wp_get_post_revisions( $post_id ) );
		$this->assertSame( self::CRDT_DOCUMENT, get_post_meta( $revision_id, '_crdt_document', true ) );
		$response = $this->request( 'GET', '/wp/v2/posts/' . $post_id . '/revisions/' . $revision_id );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( self::CRDT_DOCUMENT, $response->get_data()['meta']['_crdt_document'] );
	}

	/**
	 * Including meta in a projection must still omit all non-revisioned fields.
	 */
	public function test_revision_meta_projection_keeps_only_revisioned_fields(): void {
		$post_id     = $this->create_document();
		$revision_id = array_key_first( wp_get_post_revisions( $post_id ) );
		$request     = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id . '/revisions/' . $revision_id );
		$request->set_query_params( [
			'context' => 'edit',
			'_fields' => 'id,meta',
		] );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [
			'id'   => $revision_id,
			'meta' => [
				'revisioned_alias' => 'snapshot meta',
			],
		], $response->get_data() );
	}

	/**
	 * HEAD must retain the controller's response semantics without exposing CRDT.
	 */
	public function test_revision_head_responses_preserve_controller_behavior(): void {
		$post_id     = $this->create_document();
		$revision_id = array_key_first( wp_get_post_revisions( $post_id ) );
		foreach ( [ '/revisions', '/revisions/' . $revision_id ] as $suffix ) {
			$response = $this->request( 'HEAD', '/wp/v2/posts/' . $post_id . $suffix );
			$this->assertSame( 200, $response->get_status() );
			remove_filter( 'rest_prepare_revision', __NAMESPACE__ . '\filter_revision_meta', 10 );
			$unfiltered = $this->request( 'HEAD', '/wp/v2/posts/' . $post_id . $suffix );
			add_filter( 'rest_prepare_revision', __NAMESPACE__ . '\filter_revision_meta', 10, 3 );
			$this->assertSame( $unfiltered->get_status(), $response->get_status() );
			$this->assertSame( $unfiltered->get_headers(), $response->get_headers() );
			// Older cores prepare GET-equivalent data for HEAD; newer cores return no data.
			$expected = empty( $unfiltered->get_data() ) ? $unfiltered->get_data() : $this->request( 'GET', '/wp/v2/posts/' . $post_id . $suffix )->get_data();
			$this->assertSame( $expected, $response->get_data() );
		}
		$this->assertSame( self::CRDT_DOCUMENT, get_post_meta( $post_id, '_crdt_document', true ) );
	}

	/**
	 * Global and subtype metadata use REST aliases, not their storage keys.
	 */
	public function test_nonrevisioned_global_and_subtype_aliases_are_not_restored(): void {
		foreach ( [ 'global_value', 'subtype_value' ] as $key ) {
			$args = [
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => [ 'name' => $key . '_alias' ],
			];
			if ( 'global_value' === $key ) {
				register_meta( 'post', $key, $args );
			} else {
				register_post_meta( 'post', $key, $args );
			}
		}
		$post_id = $this->create_document();
		foreach ( [ 'global_value', 'subtype_value' ] as $key ) {
			update_post_meta( $post_id, $key, 'keep ' . $key );
		}
		$revision_id = array_key_first( wp_get_post_revisions( $post_id ) );
		$response    = $this->request( 'GET', '/wp/v2/posts/' . $post_id . '/revisions/' . $revision_id );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		foreach ( [ 'global_value', 'subtype_value' ] as $key ) {
			$this->assertArrayNotHasKey( $key . '_alias', $data['meta'] );
		}
		$restore = $this->request( 'POST', '/wp/v2/posts/' . $post_id, [
			'content' => $data['content']['raw'],
			'meta'    => $data['meta'],
		] );
		$this->assertSame( 200, $restore->get_status() );
		foreach ( [ 'global_value', 'subtype_value' ] as $key ) {
			$this->assertSame( 'keep ' . $key, get_post_meta( $post_id, $key, true ) );
		}
	}

	/**
	 * Registration order must not let a non-revisioned field replace a shared alias.
	 *
	 * @dataProvider shared_alias_registrations
	 * @param bool $revisioned_first Whether the revisioned field is registered first.
	 * @param bool $global Whether both fields are registered globally.
	 */
	public function test_shared_alias_keeps_revisioned_value( bool $revisioned_first, bool $global ): void {
		$keys = $revisioned_first ? [ 'shared_revisioned', 'shared_live' ] : [ 'shared_live', 'shared_revisioned' ];
		foreach ( $keys as $key ) {
			$args = [
				'type'              => 'string',
				'single'            => true,
				'default'           => 'shared_revisioned' === $key ? 'revision default' : 'live default',
				'revisions_enabled' => 'shared_revisioned' === $key,
				'show_in_rest'      => [ 'name' => 'shared_alias' ],
			];
			if ( $global ) {
				register_meta( 'post', $key, $args );
			} else {
				register_post_meta( 'post', $key, $args );
			}
		}

		$post_id     = $this->create_document();
		$revision_id = array_key_first( wp_get_post_revisions( $post_id ) );
		update_metadata( 'post', $revision_id, 'shared_revisioned', 'revision snapshot' );
		update_post_meta( $post_id, 'shared_revisioned', 'latest value' );
		update_post_meta( $post_id, 'shared_live', 'live value' );

		$response = $this->request( 'GET', '/wp/v2/posts/' . $post_id . '/revisions/' . $revision_id );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'revision snapshot', $response->get_data()['meta']['shared_alias'] ?? null );
		$this->assertSame( 'snapshot meta', $response->get_data()['meta']['revisioned_alias'] );
		$this->assertArrayNotHasKey( 'rtc_unrelated', $response->get_data()['meta'] );

		delete_metadata( 'post', $revision_id, 'shared_revisioned' );
		$response = $this->request( 'GET', '/wp/v2/posts/' . $post_id . '/revisions/' . $revision_id );
		$this->assertSame( 'revision default', $response->get_data()['meta']['shared_alias'] ?? null );
		$this->assertSame( 'latest value', get_post_meta( $post_id, 'shared_revisioned', true ) );
		$this->assertSame( 'live value', get_post_meta( $post_id, 'shared_live', true ) );
	}

	/**
	 * Both registration orders, in both metadata registration buckets.
	 *
	 * @return array Test cases.
	 */
	public static function shared_alias_registrations(): array {
		return [
			'subtype revisioned first' => [ true, false ],
			'subtype revisioned last'  => [ false, false ],
			'global revisioned first'  => [ true, true ],
			'global revisioned last'   => [ false, true ],
		];
	}

	/**
	 * A collision must use the revisioned field's preparation and context schema.
	 */
	public function test_shared_alias_uses_revisioned_preparation_and_context(): void {
		register_meta( 'post', 'shared_revisioned', [
			'type'              => 'object',
			'single'            => true,
			'revisions_enabled' => true,
			'show_in_rest'      => [
				'name'             => 'shared_alias',
				'prepare_callback' => static function ( $value, $request ) {
					$value['public'] = $request['context'] . ': ' . ( $value['public'] ?? '' );
					return $value;
				},
				'schema'           => [
					'context'    => [ 'edit', 'view' ],
					'properties' => [
						'public'  => [
							'type'    => 'string',
							'context' => [ 'edit', 'view' ],
						],
						'private' => [
							'type'    => 'string',
							'context' => [ 'edit' ],
						],
					],
				],
			],
		] );
		register_post_meta( 'post', 'shared_live', [
			'type'         => 'string',
			'single'       => true,
			'show_in_rest' => [
				'name'   => 'shared_alias',
				'schema' => [ 'context' => [ 'edit' ] ],
			],
		] );
		$post_id     = $this->create_document();
		$revision_id = array_key_first( wp_get_post_revisions( $post_id ) );
		update_metadata( 'post', $revision_id, 'shared_revisioned', [
			'public'  => 'snapshot',
			'private' => 'secret',
		] );

		foreach ( [ 'view', 'edit' ] as $context ) {
			$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id . '/revisions/' . $revision_id );
			$request->set_param( 'context', $context );
			$response = rest_get_server()->dispatch( $request );
			$this->assertSame( 200, $response->get_status() );
			$expected = [ 'public' => $context . ': snapshot' ];
			if ( 'edit' === $context ) {
				$expected['private'] = 'secret';
			}
			$this->assertSame( $expected, $response->get_data()['meta']['shared_alias'] ?? null );
			$this->assertArrayNotHasKey( 'rtc_unrelated', $response->get_data()['meta'] );
		}

		$request->set_param( '_fields', 'id,meta.shared_alias.public' );
		$response = rest_get_server()->dispatch( $request );
		$response = rest_filter_response_fields( $response, rest_get_server(), $request );
		$this->assertSame( [
			'id'   => $revision_id,
			'meta' => [ 'shared_alias' => [ 'public' => 'edit: snapshot' ] ],
		], $response->get_data() );
	}

	/**
	 * Rebuilding registered metadata must preserve custom response-only fields.
	 */
	public function test_unregistered_response_meta_is_preserved(): void {
		add_filter( 'rest_prepare_revision', static function ( $response ) {
			$data                   = $response->get_data();
			$data['meta']['custom'] = 'response-only value';
			$response->set_data( $data );
			return $response;
		}, 9 );
		$post_id     = $this->create_document();
		$revision_id = array_key_first( wp_get_post_revisions( $post_id ) );
		$response    = $this->request( 'GET', '/wp/v2/posts/' . $post_id . '/revisions/' . $revision_id );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'response-only value', $response->get_data()['meta']['custom'] );
		$this->assertSame( 'snapshot meta', $response->get_data()['meta']['revisioned_alias'] );
		$this->assertArrayNotHasKey( 'rtc_unrelated', $response->get_data()['meta'] );
	}

	/**
	 * The effective revision key filter governs both opt-ins and exclusions.
	 */
	public function test_filtered_revision_keys_are_authoritative(): void {
		$post_id     = $this->create_document();
		$revision_id = array_key_first( wp_get_post_revisions( $post_id ) );
		add_metadata( 'post', $revision_id, 'rtc_unrelated', 'filtered snapshot' );
		add_filter( 'wp_post_revision_meta_keys', static function () {
			return [ 'rtc_unrelated', null, 123 ];
		} );
		$response = $this->request( 'GET', '/wp/v2/posts/' . $post_id . '/revisions/' . $revision_id );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [ 'rtc_unrelated' => 'filtered snapshot' ], $response->get_data()['meta'] );
	}

	/**
	 * Invalid revision-key filter results must not cause a PHP error.
	 */
	public function test_nonarray_revision_keys_filter_omits_registered_meta(): void {
		$post_id     = $this->create_document();
		$revision_id = array_key_first( wp_get_post_revisions( $post_id ) );
		add_filter( 'wp_post_revision_meta_keys', '__return_false' );
		$response = $this->request( 'GET', '/wp/v2/posts/' . $post_id . '/revisions/' . $revision_id );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [], $response->get_data()['meta'] );
	}

	/**
	 * A subtype can disable revisioning enabled by a global registration.
	 */
	public function test_subtype_revision_opt_out_overrides_global_registration(): void {
		$args = [
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => [ 'name' => 'global_alias' ],
			'revisions_enabled' => true,
		];
		register_meta( 'post', 'global_setting', $args );
		$args['revisions_enabled'] = false;
		$args['show_in_rest']      = [ 'name' => 'subtype_alias' ];
		register_post_meta( 'post', 'global_setting', $args );
		$post_id     = $this->create_document();
		$revision_id = array_key_first( wp_get_post_revisions( $post_id ) );
		$response    = $this->request( 'GET', '/wp/v2/posts/' . $post_id . '/revisions/' . $revision_id );
		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayNotHasKey( 'subtype_alias', $response->get_data()['meta'] );
		$this->assertArrayNotHasKey( 'global_alias', $response->get_data()['meta'] );
	}

	/**
	 * Pattern revisions must not reset a non-revisioned sync status to its default.
	 */
	public function test_pattern_revision_restore_preserves_sync_status(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		wp_create_initial_post_meta();
		$post_id = self::factory()->post->create( [
			'post_type'    => 'wp_block',
			'post_content' => 'Original pattern.',
		] );
		update_post_meta( $post_id, 'wp_pattern_sync_status', 'unsynced' );
		wp_update_post( [
			'ID'           => $post_id,
			'post_content' => 'Snapshot pattern.',
		] );
		$revision_id = array_key_first( wp_get_post_revisions( $post_id ) );
		wp_update_post( [
			'ID'           => $post_id,
			'post_content' => 'Latest pattern.',
		] );
		$response = $this->request( 'GET', '/wp/v2/blocks/' . $post_id . '/revisions/' . $revision_id );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayNotHasKey( 'wp_pattern_sync_status', $data['meta'] );
		$restore = $this->request( 'POST', '/wp/v2/blocks/' . $post_id, [
			'content' => $data['content']['raw'],
			'meta'    => $data['meta'],
		] );
		$this->assertSame( 200, $restore->get_status() );
		$this->assertSame( 'Snapshot pattern.', get_post( $post_id )->post_content );
		$this->assertSame( 'unsynced', get_post_meta( $post_id, 'wp_pattern_sync_status', true ) );
	}

	/**
	 * The response filter must not weaken REST read or write permissions.
	 */
	public function test_unauthorized_reader_cannot_read_revisions_or_write_crdt(): void {
		$post_id     = $this->create_document();
		$revision_id = array_key_first( wp_get_post_revisions( $post_id ) );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );

		$this->assertSame( 403, $this->request( 'GET', '/wp/v2/posts/' . $post_id . '/revisions/' . $revision_id )->get_status() );
		$this->assertSame( 403, $this->request( 'POST', '/wp/v2/posts/' . $post_id, [
			'meta' => [ '_crdt_document' => '' ],
		] )->get_status() );
		$this->assertSame( self::CRDT_DOCUMENT, get_post_meta( $post_id, '_crdt_document', true ) );
	}
}
