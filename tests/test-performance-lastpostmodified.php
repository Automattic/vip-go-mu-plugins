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

	public function test__transition_post_status__ignore_when_locked() {
		$before = did_action( 'wpcom_vip_bump_lastpostmodified' );
		// The first update bumps and sets the lock, so the action should only fire once when updating twice
		\wp_transition_post_status( 'publish', 'publish', $this->post );
		\wp_transition_post_status( 'publish', 'publish', $this->post );
		$after = did_action( 'wpcom_vip_bump_lastpostmodified' );

		$this->assertEquals( 1, $after - $before );
	}

	public function get_data__bump_lastpostmodified() {
		return [
			'any' => [ 'post', 'any', '1989-12-13 01:00:00', '1989-12-13 06:00:00' ],
			'cpt' => [ 'book', 'book', '2003-05-27 00:00:00', '2003-05-27 05:00:00' ],
		];
	}

	/**
	 * @dataProvider get_data__bump_lastpostmodified
	 */
	public function test__bump_lastpostmodified( $post_type, $lastpostmodified_post_type, $post_modified, $post_modified_gmt ) {
		$this->post->post_type         = $post_type;
		$this->post->post_modified     = $post_modified;
		$this->post->post_modified_gmt = $post_modified_gmt;

		Last_Post_Modified::bump_lastpostmodified( $this->post );

		$blog_actual = Last_Post_Modified::get_lastpostmodified( 'blog', $lastpostmodified_post_type );
		$this->assertEquals( $post_modified, $blog_actual );
		$gmt_actual = Last_Post_Modified::get_lastpostmodified( 'gmt', $lastpostmodified_post_type );
		$this->assertEquals( $post_modified_gmt, $gmt_actual );
		$server_actual = Last_Post_Modified::get_lastpostmodified( 'server', $lastpostmodified_post_type );
		$this->assertEquals( $post_modified_gmt, $server_actual );
	}

	public function get_data__override_lastpostmodified() {
		return [
			'is set any'      => [ [ '1989-12-13', 'gmt' ], [ 'gmt' ], '1989-12-13' ],
			'is set post'     => [ [ '2003-05-27', 'gmt', 'post' ], [ 'gmt', 'post' ], '2003-05-27' ],
			'is not set post' => [ null, [ 'gmt', 'post' ], false ],
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
