<?php

namespace Automattic\VIP\Files\Acl\Restrict_All_Files;

use WP_UnitTestCase;

require_once __DIR__ . '/../../../files/acl/acl.php';
require_once __DIR__ . '/../../../files/acl/restrict-all-files.php';

class VIP_Files_Acl_Restrict_All_Files_Test extends WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();

		$this->original_current_user_id = get_current_user_id();
	}

	public function tearDown(): void {
		wp_set_current_user( $this->original_current_user_id );

		parent::tearDown();
	}

	public function test__check_file_visibility__not_logged_in() {
		$expected_file_visibility = \Automattic\VIP\Files\Acl\FILE_IS_PRIVATE_AND_DENIED;

		$file_visibility = \Automattic\VIP\Files\Acl\FILE_IS_PUBLIC;
		$file_path       = '2021/01/kittens.jpg';

		$actual_file_visibility = check_file_visibility( $file_visibility, $file_path );

		$this->assertEquals( $expected_file_visibility, $actual_file_visibility );
	}

	public function test__check_file_visibility__logged_in_without_permissions() {
		$expected_file_visibility = \Automattic\VIP\Files\Acl\FILE_IS_PRIVATE_AND_DENIED;

		$file_visibility = \Automattic\VIP\Files\Acl\FILE_IS_PUBLIC;
		$file_path       = '2021/01/kittens.jpg';

		$test_user_id = $this->factory->user->create();
		$user         = new \WP_User( $test_user_id );
		$user->remove_role( 'subscriber' );
		wp_set_current_user( $test_user_id );

		$actual_file_visibility = check_file_visibility( $file_visibility, $file_path );

		$this->assertEquals( $expected_file_visibility, $actual_file_visibility );
	}

	public function test__check_file_visibility__logged_in_with_permissions() {
		$expected_file_visibility = \Automattic\VIP\Files\Acl\FILE_IS_PRIVATE_AND_ALLOWED;

		$file_visibility = \Automattic\VIP\Files\Acl\FILE_IS_PUBLIC;
		$file_path       = '2021/01/kittens.jpg';

		$test_user_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $test_user_id );

		$actual_file_visibility = check_file_visibility( $file_visibility, $file_path );

		$this->assertEquals( $expected_file_visibility, $actual_file_visibility );
	}
}

