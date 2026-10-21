<?php
/**
 * REST integration regressions for the temporary core #66329 RTC workaround.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use Automattic\Test\Constant_Mocker;
use WP_REST_Request;
use WP_REST_Response;
use WP_UnitTestCase;

/**
 * Exercise the editor's revision restore against real WordPress REST controllers.
 */
class RTC_Revision_Meta_Test extends WP_UnitTestCase {
	/**
	 * Y.encodeStateAsUpdate() for a Y.Doc with clientID 66329 and
	 * getText('content').insert(0, 'Shared RTC content'), encoded as base64.
	 */
	private const CRDT_DOCUMENT = 'AQGZhgQABAEHY29udGVudBJTaGFyZWQgUlRDIGNvbnRlbnQA';

	/**
	 * Managed integration instance whose hooks are exercised by REST requests.
	 *
	 * @var RealTimeCollaborationIntegration
	 */
	private RealTimeCollaborationIntegration $integration;

	/**
	 * Set up the managed integration and Gutenberg's actual CRDT meta contract.
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

		$this->integration = new RealTimeCollaborationIntegration( 'real-time-collaboration' );
		$this->integration->load();
		// Emulate the installed plugin, without requiring its external build or WebSocket service.
		Constant_Mocker::define( 'VIP_REAL_TIME_COLLABORATION__LOADED', true );
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
		$this->assertSame( 'Snapshot content.', get_post( $post_id )->post_content );
		$this->assertSame( 'snapshot meta', get_post_meta( $post_id, 'rtc_revisioned', true ) );
		$this->assertArrayNotHasKey( '_crdt_document', $data['meta'] );

		$collection = $this->request( 'GET', '/wp/v2/posts/' . $post_id . '/revisions' );
		$this->assertSame( 200, $collection->get_status() );
		foreach ( $collection->get_data() as $item ) {
			$this->assertArrayNotHasKey( '_crdt_document', $item['meta'] );
			$this->assertArrayHasKey( 'revisioned_alias', $item['meta'] );
			// This workaround deliberately does not change other non-revisioned fields.
			$this->assertArrayHasKey( 'rtc_unrelated', $item['meta'] );
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
	 * Inactive RTC must not alter revision responses on unrelated sites.
	 */
	public function test_without_loaded_rtc_revision_meta_is_unchanged(): void {
		Constant_Mocker::clear();
		$post_id     = $this->create_document();
		$revision_id = array_key_first( wp_get_post_revisions( $post_id ) );
		$response    = $this->request( 'GET', '/wp/v2/posts/' . $post_id . '/revisions/' . $revision_id );
		$this->assertSame( 200, $response->get_status() );
		remove_filter( 'rest_prepare_revision', [ $this->integration, 'filter_revision_crdt_meta' ], 10 );
		$unfiltered = $this->request( 'GET', '/wp/v2/posts/' . $post_id . '/revisions/' . $revision_id );
		$this->assertSame( $unfiltered->get_status(), $response->get_status() );
		$this->assertSame( $unfiltered->get_data(), $response->get_data() );
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
	 * Including meta in a projection must still omit only the non-revisioned CRDT.
	 */
	public function test_revision_meta_projection_preserves_unrelated_fields(): void {
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
				'rtc_unrelated'    => '',
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
			remove_filter( 'rest_prepare_revision', [ $this->integration, 'filter_revision_crdt_meta' ], 10 );
			$unfiltered = $this->request( 'HEAD', '/wp/v2/posts/' . $post_id . $suffix );
			add_filter( 'rest_prepare_revision', [ $this->integration, 'filter_revision_crdt_meta' ], 10, 2 );
			$this->assertSame( $unfiltered->get_status(), $response->get_status() );
			$this->assertSame( $unfiltered->get_headers(), $response->get_headers() );
			// Older cores prepare GET-equivalent data for HEAD; newer cores return no data.
			$expected = empty( $unfiltered->get_data() ) ? $unfiltered->get_data() : $this->request( 'GET', '/wp/v2/posts/' . $post_id . $suffix )->get_data();
			$this->assertSame( $expected, $response->get_data() );
		}
		$this->assertSame( self::CRDT_DOCUMENT, get_post_meta( $post_id, '_crdt_document', true ) );
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
