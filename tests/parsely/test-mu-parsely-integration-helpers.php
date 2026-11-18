<?php

namespace Automattic\VIP\WP_Parsely_Integration;

use WP_UnitTestCase;

/**
 * Tests for helpers in wp-parsely.php that don't load the wp-parsely plugin, so unlike
 * MU_Parsely_Integration_Test they don't need to run in separate processes.
 */
class MU_Parsely_Integration_Helpers_Test extends WP_UnitTestCase {
	/**
	 * @dataProvider data_is_queued_for_activation
	 */
	public function test_is_queued_for_activation( array $get, array $post, bool $expected ) {
		set_current_screen( 'plugins' );
		$_GET  = $get;
		$_POST = $post;

		$this->assertSame( $expected, is_queued_for_activation() );
	}

	public function data_is_queued_for_activation(): array {
		return [
			'single activation link' => [
				[
					'plugin' => 'wp-parsely/wp-parsely.php',
					'action' => 'activate',
				],
				[],
				true,
			],
			'bulk activation form'   => [
				[],
				[
					'checked' => [ 'wp-parsely/wp-parsely.php' ],
					'action'  => 'activate-selected',
				],
				true,
			],
			'no activation request'  => [ [], [], false ],
		];
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
