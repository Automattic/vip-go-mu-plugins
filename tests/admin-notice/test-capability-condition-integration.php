<?php

namespace Automattic\VIP\Admin_Notice;

use WP_UnitTestCase;

require_once __DIR__ . '/../../admin-notice/class-admin-notice.php';
require_once __DIR__ . '/../../admin-notice/conditions/class-capability-condition.php';

/**
 * Compose actual WordPress permissions with notice rendering decisions.
 */
class Capability_Condition_Integration_Test extends WP_UnitTestCase {
	/**
	 * Supply an allowed role and a role that has only one of the capabilities.
	 */
	public function permission_cases(): array {
		return [
			'administrator' => [ 'administrator', true ],
			'editor'        => [ 'editor', false ],
		];
	}

	/**
	 * Use the real condition and should_render with real users.
	 *
	 * @dataProvider permission_cases
	 */
	public function test_notice_visibility( string $role, bool $allowed ): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => $role ] ) );
		$condition = new Capability_Condition( 'manage_options', 'edit_others_posts' );
		$notice    = new Admin_Notice( 'Capability restricted notice', [ $condition ] );
		$this->assertSame( $allowed, $condition->evaluate() );
		$this->assertSame( $allowed, $notice->should_render() );
	}
}
