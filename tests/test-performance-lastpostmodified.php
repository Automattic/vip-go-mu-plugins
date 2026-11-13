<?php

namespace Automattic\VIP\Performance;

use WP_Post;
use WP_UnitTest_Factory;
use WP_UnitTestCase;

// phpcs:ignore PEAR.NamingConventions.ValidClassName.StartWithCapital
class lastpostmodified_Test extends WP_UnitTestCase {
	/** @var WP_Post Shared draft post; tests get a copy because they modify it. */
	private static $draft_post;

	/** @var WP_Post */
	protected $post;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$draft_post = $factory->post->create_and_get( [ 'post_status' => 'draft' ] );
	}

	public function setUp(): void {
		/** @var wpdb $wpdb */
		global $wpdb;
		parent::setUp();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", Last_Post_Modified::OPTION_PREFIX . '%' ) );

		$this->post = clone self::$draft_post;
	}

	public function test__transition_post_status__ignore_non_publish_status() {
		$before = did_action( 'wpcom_vip_bump_lastpostmodified' );
		\wp_transition_post_status( 'draft', 'future', $this->post );
		$after = did_action( 'wpcom_vip_bump_lastpostmodified' );

		$this->assertEquals( 0, $after - $before );
	}

	/**
	 * Publishing a registered private post type must preserve persisted timestamps.
	 */
	public function test__transition_post_status__ignore_non_public_post_type(): void {
		register_post_type( 'private_book', [ 'public' => false ] );
		try {
			$post_id  = self::factory()->post->create( [
				'post_type'   => 'private_book',
				'post_status' => 'draft',
			] );
			$previous = '2000-01-02 03:04:05';
			foreach ( [ 'any', 'private_book' ] as $post_type ) {
				foreach ( [ 'gmt', 'server', 'blog' ] as $timezone ) {
					Last_Post_Modified::update_lastpostmodified( $previous, $timezone, $post_type );
				}
			}
			$before = did_action( 'wpcom_vip_bump_lastpostmodified' );
			$this->assertSame( $post_id, wp_update_post( [
				'ID'          => $post_id,
				'post_status' => 'publish',
			] ) );
			$after = did_action( 'wpcom_vip_bump_lastpostmodified' );
			$this->assertSame( 0, $after - $before );
			foreach ( [ 'any', 'private_book' ] as $post_type ) {
				foreach ( [ 'gmt', 'server', 'blog' ] as $timezone ) {
					$this->assertSame( $previous, get_option( Last_Post_Modified::OPTION_PREFIX . '_' . $timezone . '_' . $post_type ) );
				}
			}
		} finally {
			unregister_post_type( 'private_book' );
		}
	}

	/**
	 * A second change right after the first must still move the stored value forward.
	 */
	public function test__transition_post_status__records_consecutive_changes(): void {
		\wp_transition_post_status( 'publish', 'publish', $this->post );
		// Backdate the first bump so the second one is visible within the same second.
		Last_Post_Modified::update_lastpostmodified( '2000-01-01 00:00:00', 'gmt' );

		$before = current_time( 'mysql', true );
		\wp_transition_post_status( 'publish', 'publish', $this->post );

		$this->assertGreaterThanOrEqual( $before, Last_Post_Modified::get_lastpostmodified( 'gmt', 'any' ) );
	}

	/**
	 * A scheduled post keeps the modified date from when it was scheduled, so publishing it must not move the stored value backwards.
	 */
	public function test__transition_post_status__scheduled_publish_moves_forward(): void {
		$this->post->post_modified_gmt = '2000-01-01 00:00:00';

		$before = current_time( 'mysql', true );
		\wp_transition_post_status( 'publish', 'future', $this->post );

		$this->assertGreaterThanOrEqual( $before, Last_Post_Modified::get_lastpostmodified( 'gmt', 'any' ) );
	}

	public function get_data__bump_lastpostmodified() {
		return [
			'any, zero modified date' => [ 'post', 'any', '0000-00-00 00:00:00' ],
			'cpt, old modified date'  => [ 'book', 'book', '2003-05-27 00:00:00' ],
		];
	}

	/**
	 * The stored value is the time of the change, whatever the post's own modified date.
	 *
	 * @dataProvider get_data__bump_lastpostmodified
	 */
	public function test__bump_lastpostmodified( $post_type, $lastpostmodified_post_type, $post_modified ) {
		$this->post->post_type         = $post_type;
		$this->post->post_modified     = $post_modified;
		$this->post->post_modified_gmt = $post_modified;

		$before = [
			'gmt'    => current_time( 'mysql', true ),
			'server' => current_time( 'mysql', true ),
			'blog'   => current_time( 'mysql' ),
		];
		Last_Post_Modified::bump_lastpostmodified( $this->post );

		foreach ( $before as $timezone => $time ) {
			$this->assertGreaterThanOrEqual( $time, Last_Post_Modified::get_lastpostmodified( $timezone, $lastpostmodified_post_type ) );
		}
	}

	public function get_data__override_lastpostmodified() {
		return [
			'is set any'      => [ [ '1989-12-13', 'gmt' ], [ 'gmt' ], '1989-12-13' ],
			'is set post'     => [ [ '2003-05-27', 'gmt', 'post' ], [ 'gmt', 'post' ], '2003-05-27' ],
			'is not set post' => [ null, [ 'gmt', 'post' ], false ],
			'is zero post'    => [ [ '0000-00-00 00:00:00', 'gmt', 'post' ], [ 'gmt', 'post' ], false ],
		];
	}

	/**
	 * @dataProvider get_data__override_lastpostmodified
	 */
	public function test__override_lastpostmodified( $stored, $get_args, $expected ) {
		if ( $stored ) {
			Last_Post_Modified::update_lastpostmodified( ...$stored );
		}

		$actual = get_lastpostmodified( ...$get_args );

		$this->assertSame( $expected, $actual );
	}
}
