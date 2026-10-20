<?php

namespace Automattic\VIP\Tests;

use WP_Test_REST_TestCase;
use WPCOM_VIP_Cache_Manager;

class Cache_Purge_Term_Test extends WP_Test_REST_TestCase {
	const TEST_TAXONOMY_SLUG = 'my-cool-taxonomy';

	/** @var WPCOM_VIP_Cache_Manager */
	private $cache_manager;

	public function setUp(): void {
		parent::setUp();

		$this->cache_manager = WPCOM_VIP_Cache_Manager::instance();
		$this->cache_manager->init();
		$this->cache_manager->clear_queued_purge_urls();
	}

	public function tearDown(): void {
		$this->cache_manager->clear_queued_purge_urls();
		unregister_taxonomy( self::TEST_TAXONOMY_SLUG );

		parent::tearDown();
	}

	private function register_taxonomy_and_term( $taxonomy_args = [] ) {
		register_taxonomy( self::TEST_TAXONOMY_SLUG, 'post', $taxonomy_args );

		$factory = new \WP_UnitTest_Factory_For_Term( null, self::TEST_TAXONOMY_SLUG );
		$term_id = $factory->create_object( [
			'name' => 'my-cool-term',
		] );
		// Retain production hooks and discard only fixture-creation purges.
		$this->cache_manager->clear_queued_purge_urls();
		return $term_id;
	}

	public function test__invalid_taxonomy() {
		// Don't bother registering taxonomy here

		$this->cache_manager->queue_terms_purges( 1, 'invalid-taxonomy' );

		$queued_purge_urls = $this->cache_manager->get_queued_purge_urls();
		$this->assertEmpty( $queued_purge_urls );
	}

	public function test__non_public_taxonomy() {
		$term_id = $this->register_taxonomy_and_term( [
			'public' => false,
		] );

		$this->cache_manager->queue_terms_purges( $term_id, self::TEST_TAXONOMY_SLUG );

		$queued_purge_urls = $this->cache_manager->get_queued_purge_urls();
		$this->assertEmpty( $queued_purge_urls );
	}

	public function test__invalid_term() {
		$this->register_taxonomy_and_term();
		$this->cache_manager->queue_terms_purges( PHP_INT_MAX, self::TEST_TAXONOMY_SLUG );

		$queued_purge_urls = $this->cache_manager->get_queued_purge_urls();
		$this->assertEmpty( $queued_purge_urls );
	}

	public function get_data_for_valid_term_and_taxonomy_tests() {
		return [
			'public_taxonomy'             => [
				[
					'public' => true,
				],
			],

			'publicly_queryable_taxonomy' => [
				[
					'public'             => false,
					'publicly_queryable' => true,
				],
			],

			'show_in_rest_taxonomy'       => [
				[
					'public'       => false,
					'show_in_rest' => true,
				],
			],
		];
	}

	/**
	 * A WordPress term update must dispatch the production purge registration.
	 *
	 * @dataProvider get_data_for_valid_term_and_taxonomy_tests
	 */
	public function test_wordpress_term_update_queues_purge( array $taxonomy_args ): void {
		$term_id = $this->register_taxonomy_and_term( $taxonomy_args );
		$this->assertEmpty( $this->cache_manager->get_queued_purge_urls() );
		$result = wp_update_term( $term_id, self::TEST_TAXONOMY_SLUG, [ 'name' => 'Updated term' ] );
		$this->assertNotWPError( $result );
		$this->assertContains( get_term_link( $term_id, self::TEST_TAXONOMY_SLUG ), $this->cache_manager->get_queued_purge_urls() );
	}
}
