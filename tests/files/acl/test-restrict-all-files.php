<?php

namespace Automattic\VIP\Files\Acl\Restrict_All_Files;

use WP_UnitTestCase;

require_once __DIR__ . '/../../../files/acl/acl.php';
require_once __DIR__ . '/../../../files/acl/restrict-all-files.php';

class VIP_Files_Acl_Restrict_All_Files_Test extends WP_UnitTestCase {
	/** @var int */
	private $original_current_user_id;

	public function setUp(): void {
		parent::setUp();

		$this->original_current_user_id = get_current_user_id();
	}

	public function tearDown(): void {
		wp_set_current_user( $this->original_current_user_id );

		parent::tearDown();
	}

	public function get_data__check_file_visibility() {
		return [
			'not logged in'                 => [ null, \Automattic\VIP\Files\Acl\FILE_IS_PRIVATE_AND_DENIED ],
			// A user without any role doesn't have the `read` capability.
			'logged in without permissions' => [ '', \Automattic\VIP\Files\Acl\FILE_IS_PRIVATE_AND_DENIED ],
			'logged in with permissions'    => [ 'editor', \Automattic\VIP\Files\Acl\FILE_IS_PRIVATE_AND_ALLOWED ],
		];
	}

	/**
	 * @dataProvider get_data__check_file_visibility
	 */
	public function test__check_file_visibility( $user_role, $expected_file_visibility ) {
		$file_visibility = \Automattic\VIP\Files\Acl\FILE_IS_PUBLIC;
		$file_path       = '2021/01/kittens.jpg';

		if ( null !== $user_role ) {
			$test_user_id = $this->factory()->user->create();
			( new \WP_User( $test_user_id ) )->set_role( $user_role );
			wp_set_current_user( $test_user_id );
		}

		$actual_file_visibility = check_file_visibility( $file_visibility, $file_path );

		$this->assertEquals( $expected_file_visibility, $actual_file_visibility );
	}
}
