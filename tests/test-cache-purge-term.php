<?php

namespace Automattic\VIP\Tests;

use WP_Test_REST_TestCase;
use WPCOM_VIP_Cache_Manager;

class Cache_Purge_Term_Test extends WP_Test_REST_TestCase {
	const TEST_TAXONOMY_SLUG = 'my-cool-taxonomy';

	public function setUp(): void {
		parent::setUp();

		$this->cache_manager = WPCOM_VIP_Cache_Manager::instance();
		$this->cache_manager->clear_queued_purge_urls();

		// When we create our test term, these fire and pollute our tests :)
		remove_all_actions( 'clean_term_cache' );
		remove_all_actions( 'clean_post_cache' );
	}

	public function tearDown(): void {
		unregister_taxonomy( self::TEST_TAXONOMY_SLUG );

		parent::tearDown();
	}

	private function register_taxonomy_and_term( $taxonomy_args = [] ) {
		register_taxonomy( self::TEST_TAXONOMY_SLUG, 'post', $taxonomy_args );

		$factory = new \WP_UnitTest_Factory_For_Term( null, self::TEST_TAXONOMY_SLUG );
		return $factory->create_object( [
			'name' => 'my-cool-term',
		] );
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
	 * @dataProvider get_data_for_valid_term_and_taxonomy_tests
	 */
	public function test_valid_term_and_taxonomy( $taxonomy_args ) {
		$term_id = $this->register_taxonomy_and_term( $taxonomy_args );

		$this->cache_manager->queue_terms_purges( $term_id, self::TEST_TAXONOMY_SLUG );

		$expected_term_link = get_term_link( $term_id, self::TEST_TAXONOMY_SLUG );
		$queued_purge_urls  = $this->cache_manager->get_queued_purge_urls();
		$this->assertContains( $expected_term_link, $queued_purge_urls );
	}
}
