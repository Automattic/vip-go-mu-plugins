<?php

namespace Automattic\VIP\Admin_Notice;

use PHPUnit\Framework\MockObject\MockObject;
use WP_HTML_Tag_Processor;
use WP_UnitTest_Factory;
use WP_UnitTestCase;

require_once __DIR__ . '/../../admin-notice/class-admin-notice.php';
require_once __DIR__ . '/../../admin-notice/conditions/interface-condition.php';

class Admin_Notice_Class_Test extends WP_UnitTestCase {
	public static $super_admin_id;
	public static $user_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$super_admin_id = $factory->user->create([
			'user_login' => 'test_user',
			'user_pass'  => 'test_password',
			'user_email' => 'test@test.com',
			'role'       => 'admin',
		]);

		$super_admin = get_user_by( 'id', self::$super_admin_id );
		grant_super_admin( self::$super_admin_id );
		$super_admin->add_cap( 'delete_users' ); // Fake super admin

		self::$user_id = $factory->user->create([
			'user_login' => 'foo',
			'user_pass'  => 'bar',
			'user_email' => 'foo@bar.com',
			'role'       => 'subscriber',
		]);
	}

	public static function tearDownAfterClass(): void {
		revoke_super_admin( self::$super_admin_id );
		parent::tearDownAfterClass();
	}

	/**
	 * Preserve allowed anchor markup while removing forbidden tags and handlers.
	 */
	public function test__display(): void {
		$message = '<a href="https://example.org/" title="Allowed" target="_blank" onclick="bad()">Allowed link</a><script>bad-script-text</script><img src="x" onerror="bad()">';
		$notice  = new Admin_Notice( $message );

		ob_start();
		$notice->display();
		$output = ob_get_clean();

		// WordPress 7.2 reimplements wp_kses() with the HTML API, which reorders attributes and drops
		// <script> contents, so check what the notice must allow and strip rather than the exact markup.
		$this->assertStringStartsWith( '<div data-vip-admin-notice="" class="notice notice-info vip-notice"><p>', $output );
		$this->assertStringEndsWith( '</p></div>', $output );
		$this->assertStringContainsString( '>Allowed link</a>', $output );
		$this->assertStringNotContainsString( '<script', $output );
		$this->assertStringNotContainsString( '<img', $output );

		$processor = new WP_HTML_Tag_Processor( $output );
		$this->assertTrue( $processor->next_tag( 'a' ) );
		$attributes = $processor->get_attribute_names_with_prefix( '' );
		sort( $attributes );
		$this->assertSame( [ 'href', 'target', 'title' ], $attributes );
		$this->assertSame( 'https://example.org/', $processor->get_attribute( 'href' ) );
		$this->assertSame( 'Allowed', $processor->get_attribute( 'title' ) );
		$this->assertSame( '_blank', $processor->get_attribute( 'target' ) );
	}

	/**
	 * Escape quotes and angle brackets in the dismissible notice identifier.
	 */
	public function test__display_dismissible(): void {
		$message    = 'Test Message';
		$dismiss_id = 'dismiss_id" onmouseover="bad()"><script>';
		$notice     = new Admin_Notice( $message, [], $dismiss_id );
		$this->expectOutputString( '<div data-vip-admin-notice="dismiss_id&quot; onmouseover=&quot;bad()&quot;&gt;&lt;script&gt;" class="notice notice-info vip-notice is-dismissible"><p>Test Message</p></div>' );
		$notice->display();
	}

	public function should_render_conditions_data() {
		return [
			[ [], true ],
			[ [ false ], false ],
			[ [ true ], true ],
			[ [ true, true ], true ],
			[ [ true, false ], false ],
			[ [ false, true ], false ],
			[ [ false, false ], false ],
		];
	}

	/**
	 * @dataProvider should_render_conditions_data
	 */
	public function test__should_render_conditions( $condition_results, $expected_result ) {
		wp_set_current_user( self::$super_admin_id );

		$conditions = array_map( function ( $result_to_return ) {
			/** @var Condition&MockObject */
			$condition_stub = $this->createMock( Condition::class );
			$condition_stub->method( 'evaluate' )->willReturn( $result_to_return );
			return $condition_stub;
		}, $condition_results);

		$notice = new Admin_Notice( 'foo', $conditions );

		$result = $notice->should_render();

		$this->assertEquals( $expected_result, $result );

		wp_set_current_user( self::$user_id );

		$result = $notice->should_render();

		$this->assertFalse( $result );
	}

	/**
	 * @dataProvider data_cap_condition_exist
	 */
	public function test_cap_condition_exist( array $conditions, bool $xpected ): void {
		$notice = new Admin_Notice( 'notice', $conditions );
		$actual = $notice->cap_condition_exist();
		self::assertSame( $xpected, $actual );
	}

	public function data_cap_condition_exist(): iterable {
		return [
			'no conditions'        => [
				[],
				false,
			],
			'capability condition' => [
				[ new Capability_Condition( 'delete_users' ) ],
				true,
			],
		];
	}
}
