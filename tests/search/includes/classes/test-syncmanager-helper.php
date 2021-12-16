<?php

namespace Automattic\VIP\Search;

use WP_UnitTest_Factory;
use WP_UnitTestCase;

require_once __DIR__ . '/../../../../search/includes/classes/class-syncmanager-helper.php';

class Test_SyncManager_Helper extends WP_UnitTestCase {
	/** @var int */
	private static $tag_id;

	/** @var bool|null */
	private $outcome;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		$tag_id = $factory->tag->create( [
			'name' => 'Test Tag',
		] );

		self::assertIsInt( $tag_id );
		self::$tag_id = $tag_id;
	}

	public function setUp(): void {
		parent::setUp();

		// Make sure nobody disturbs us
		remove_all_actions( 'edited_term' );
		remove_all_filters( 'ep_skip_action_edited_term' );

		$this->outcome = null;
		add_action( 'edited_term', [ $this, 'edited_term' ], 10, 3 );

		// Reinitialize, as WPTL cleans up all hooks after the test
		SyncManager_Helper::instance()->init();
	}

	public function edited_term( int $term_id, int $tt_id, string $taxonomy ): void {
		$this->outcome = apply_filters( 'ep_skip_action_edited_term', false, $term_id, $tt_id, $taxonomy );
	}

	public function test_real_update(): void {
		$data = wp_update_term( self::$tag_id, 'post_tag', [ 'slug' => 'the-tag' ] );

		self::assertIsArray( $data );
		self::assertFalse( $this->outcome );
	}

	public function test_fake_update(): void {
		$data = wp_update_term( self::$tag_id, 'post_tag' );

		self::assertIsArray( $data );
		self::assertTrue( $this->outcome );
	}
}
