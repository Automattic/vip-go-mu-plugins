<?php

namespace Automattic\VIP\WP_Parsely_Integration;

use WP_UnitTestCase;

/**
 * Tests for helpers in wp-parsely.php that don't load the wp-parsely plugin, so unlike
 * MU_Parsely_Integration_Test they don't need to run in separate processes.
 */
class MU_Parsely_Integration_Helpers_Test extends WP_UnitTestCase {
	public function test_is_queued_for_activation_get() {
		set_current_screen( 'plugins' );

		$_GET['plugin'] = 'wp-parsely/wp-parsely.php';
		$_GET['action'] = 'activate';

		$this->assertTrue( is_queued_for_activation() );
	}

	public function test_is_queued_for_activation_post() {
		set_current_screen( 'plugins' );

		$_POST['checked'] = [ 'wp-parsely/wp-parsely.php' ];
		$_POST['action']  = 'activate-selected';

		$this->assertTrue( is_queued_for_activation() );
	}

	public function test_is_not_queued_for_activation() {
		set_current_screen( 'plugins' );

		$this->assertFalse( is_queued_for_activation() );
	}

	public function test_alter_option_use_repeated_metas() {
		$options = alter_option_use_repeated_metas();
		$this->assertSame( array( 'meta_type' => 'repeated_metas' ), $options );

		$options = alter_option_use_repeated_metas( array( 'some_option' => 'value' ) );
		$this->assertSame( array(
			'some_option' => 'value',
			'meta_type'   => 'repeated_metas',
		), $options );

		$options = alter_option_use_repeated_metas( array( 'meta_type' => 'json_ld' ) );
		$this->assertSame( array( 'meta_type' => 'repeated_metas' ), $options );
	}
}
