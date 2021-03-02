<?php

namespace Automattic\VIP\Core\Updates;

class Updates_Test extends \WP_UnitTestCase {
	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		require_once( __DIR__ . '/../../001-core/updates.php' );
	}

	public function setUp() {
		parent::setUp();

		$this->original_current_user_id = get_current_user_id();

		// Grabbed via `get_site_transient( 'update_plugins' )`
		set_site_transient( 'update_plugins', (object) array(
			'last_checked' => 1614718985,
			'checked' => [
				'hello.php' => '1.6',
			],
			'response' => [
				'hello.php' => (object) [
					'id' => 'w.org/plugins/hello-dolly',
					'slug' => 'hello-dolly',
					'plugin' => 'hello.php',
					'new_version' => '1.7.2',
					'url' => 'https://wordpress.org/plugins/hello-dolly/',
					'package' => 'https://downloads.wordpress.org/plugin/hello-dolly.1.7.2.zip',
					'icons' => [
						'2x' => 'https://ps.w.org/hello-dolly/assets/icon-256x256.jpg?rev=2052855',
						'1x' => 'https://ps.w.org/hello-dolly/assets/icon-128x128.jpg?rev=2052855',
					],
					'banners' => [
						'1x' => 'https://ps.w.org/hello-dolly/assets/banner-772x250.jpg?rev=2052855',
					],
					'banners_rtl' => [],
					'tested' => '5.5.3',
					'requires_php' => false,
					'compatibility' => [],
				],
			],
			'translations' => [],
			'no_update' => [],
		) );
	}

	public function tearDown() {
		delete_site_transient( 'update_plugins' );

		wp_set_current_user( $this->original_current_user_id );

		parent::tearDown();
	}

	function test__show_plugin_update_notices__is_hooked() {
		$expected_has_action = true;

		$actual_has_action = has_action( 'load-plugins.php', __NAMESPACE__ . '\show_plugin_update_notices' );

		$this->assertTrue( $expected_has_action, $actual_has_action, 'VIP plugin updates notice workound is not correctly hooked' );
	}

	public function test__show_plugin_update_notices__user_without_activate_plugins_cap() {
		$expected_has_action = false;

		$test_user_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $test_user_id );

		show_plugin_update_notices();

		$actual_has_action = has_action( 'after_plugin_row_hello.php', 'wp_plugin_update_row', 10, 2 );

		$this->assertEquals( $expected_has_action, $actual_has_action, 'Plugin should not have update notice hook added due to incorrect permissions' );
	}

	public function test__show_plugin_update_notices__user_with_activate_plugins_cap() {
		$expected_has_action_valid_plugin = true;
		$expected_has_action_invalid_plugin = false;
		
		$test_user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $test_user_id );

		show_plugin_update_notices();

		$actual_has_action_valid_plugin = has_action( 'after_plugin_row_hello.php', 'wp_plugin_update_row', 10, 2 );
		$actual_has_action_invalid_plugin = has_action( 'after_plugin_row_nope.php', 'wp_plugin_update_row', 10, 2 );

		$this->assertEquals( $expected_has_action_valid_plugin, $actual_has_action_valid_plugin, 'Valid, existing plugin did not have update row hook added, but should have' );
		$this->assertEquals( $expected_has_action_invalid_plugin, $actual_has_action_invalid_plugin, 'Invalid, non-existent plugin had update row hook added but should not have' );
	}
}
