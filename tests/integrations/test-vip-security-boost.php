<?php

/**
 * Test: VIP Security Boost Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use WP_UnitTestCase;
use Automattic\Test\Constant_Mocker;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing

class Vip_Security_Boost_Integration_Test extends WP_UnitTestCase {
	private string $slug = 'vip-security-boost';
	private static string $original_wp_version;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		self::$original_wp_version = get_bloginfo( 'version' );
	}

	public function tearDown(): void {
		parent::tearDown();

		Constant_Mocker::clear();

		// Reset our global flag if it was set
		if ( isset( $GLOBALS['_vip_security_boost_fired_action'] ) ) {
			$GLOBALS['_vip_security_boost_fired_action'] = false;
		}

		// Reset our mock state
		global $mock_filesystem_state;
		$mock_filesystem_state = null;

		// Reset the WordPress version
		global $wp_version;
		$wp_version = self::$original_wp_version; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	public function test_is_loaded_returns_false_when_not_loaded(): void {
		$security_boost_integration = new SecurityBoostIntegration( $this->slug );
		$this->assertFalse( $security_boost_integration->is_loaded() );
	}

	public function test_is_loaded_returns_true_when_constant_defined(): void {
		Constant_Mocker::define( 'VIP_SECURITY_BOOST__LOADED', true );
		$security_boost_integration = new SecurityBoostIntegration( $this->slug );
		$this->assertTrue( $security_boost_integration->is_loaded() );
	}

	public function test_configure_defines_config_constant(): void {
		$security_boost_integration = new SecurityBoostIntegration( $this->slug );
		$security_boost_integration->configure();

		$this->assertTrue( defined( 'VIP_SECURITY_BOOST_CONFIGS' ) );
		$this->assertEquals( [], constant( 'VIP_SECURITY_BOOST_CONFIGS' ) );
	}

	public function test_configure_does_not_redefine_constant(): void {
		Constant_Mocker::define( 'VIP_SECURITY_BOOST_CONFIGS', [ 'test' => 'value' ] );

		$security_boost_integration = new SecurityBoostIntegration( $this->slug );
		$security_boost_integration->configure();

		$this->assertEquals( [ 'test' => 'value' ], constant( 'VIP_SECURITY_BOOST_CONFIGS' ) );
	}

	public function test_get_selected_version_folder_returns_latest_version_when_version_is_latest(): void {
		$security_boost_integration          = new SecurityBoostIntegration( $this->slug );
		$security_boost_integration->version = 'latest';
		$versions                            = array(
			'vip-security-boost-2.5'  => '2.5',
			'vip-security-boost-1.11' => '1.11',
			'vip-security-boost-1.2'  => '1.2',
		);
		$this->assertEquals( 'vip-security-boost-2.5', $security_boost_integration->get_selected_version_folder( $versions ) );
	}

	public function test_get_selected_version_folder_returns_desired_version_when_version_is_specified(): void {
		$security_boost_integration          = new SecurityBoostIntegration( $this->slug );
		$security_boost_integration->version = '1.2';
		$versions                            = array(
			'vip-security-boost-2.5'  => '2.5',
			'vip-security-boost-1.11' => '1.11',
			'vip-security-boost-1.2'  => '1.2',
		);
		$this->assertEquals( 'vip-security-boost-1.2', $security_boost_integration->get_selected_version_folder( $versions ) );
	}

	public function test_get_selected_version_folder_returns_latest_version_when_version_is_not_specified(): void {
		$security_boost_integration          = new SecurityBoostIntegration( $this->slug );
		$security_boost_integration->version = '';
		$versions                            = array(
			'vip-security-boost-2.5'  => '2.5',
			'vip-security-boost-1.11' => '1.11',
			'vip-security-boost-1.2'  => '1.2',
		);
		$this->assertEquals( 'vip-security-boost-2.5', $security_boost_integration->get_selected_version_folder( $versions ) );
	}
}
