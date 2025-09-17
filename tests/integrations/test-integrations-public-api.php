<?php
/**
 * Test: Integrations Public API
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use WP_UnitTestCase;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.FunctionComment.MissingParamComment

require_once __DIR__ . '/fake-integration.php';

class VIP_Integrations_Public_API_Test extends WP_UnitTestCase {

	private Integrations $integrations;

	public function setUp(): void {
		parent::setUp();
		$this->integrations = new Integrations();
	}

	public function test__get_integration_is_now_public(): void {
		$integration = new FakeIntegration( 'test-integration' );
		$this->integrations->register( $integration );

		$result = $this->integrations->get_integration( 'test-integration' );

		$this->assertSame( $integration, $result );
	}

	public function test__get_integration_returns_null_for_non_existent(): void {
		$result = $this->integrations->get_integration( 'non-existent' );

		$this->assertNull( $result );
	}

	public function test__is_integration_enabled_returns_true_for_active_integration(): void {
		$integration = new FakeIntegration( 'active-integration' );
		$this->integrations->register( $integration );
		$this->integrations->activate( 'active-integration' );

		$result = $this->integrations->is_integration_enabled( 'active-integration' );

		$this->assertTrue( $result );
	}

	public function test__is_integration_enabled_returns_false_for_inactive_integration(): void {
		$integration = new FakeIntegration( 'inactive-integration' );
		$this->integrations->register( $integration );
		// Not activating the integration

		$result = $this->integrations->is_integration_enabled( 'inactive-integration' );

		$this->assertFalse( $result );
	}

	public function test__is_integration_enabled_returns_false_for_non_existent_integration(): void {
		$result = $this->integrations->is_integration_enabled( 'non-existent' );

		$this->assertFalse( $result );
	}

	public function test__get_enabled_integrations_returns_only_active_integrations(): void {
		$integration1 = new FakeIntegration( 'active-1' );
		$integration2 = new FakeIntegration( 'inactive-1' );
		$integration3 = new FakeIntegration( 'active-2' );

		$this->integrations->register( $integration1 );
		$this->integrations->register( $integration2 );
		$this->integrations->register( $integration3 );

		$this->integrations->activate( 'active-1' );
		// Not activating inactive-1
		$this->integrations->activate( 'active-2' );

		$result = $this->integrations->get_enabled_integrations();

		$this->assertCount( 2, $result );
		$this->assertArrayHasKey( 'active-1', $result );
		$this->assertArrayHasKey( 'active-2', $result );
		$this->assertArrayNotHasKey( 'inactive-1', $result );
		$this->assertSame( $integration1, $result['active-1'] );
		$this->assertSame( $integration3, $result['active-2'] );
	}

	public function test__get_enabled_integrations_returns_empty_array_when_none_active(): void {
		$integration = new FakeIntegration( 'inactive' );
		$this->integrations->register( $integration );
		// Not activating

		$result = $this->integrations->get_enabled_integrations();

		$this->assertEmpty( $result );
	}

	public function test__get_all_integrations_returns_all_registered_integrations(): void {
		$integration1 = new FakeIntegration( 'active' );
		$integration2 = new FakeIntegration( 'inactive' );

		$this->integrations->register( $integration1 );
		$this->integrations->register( $integration2 );

		$this->integrations->activate( 'active' );
		// Not activating inactive

		$result = $this->integrations->get_all_integrations();

		$this->assertCount( 2, $result );
		$this->assertArrayHasKey( 'active', $result );
		$this->assertArrayHasKey( 'inactive', $result );
		$this->assertSame( $integration1, $result['active'] );
		$this->assertSame( $integration2, $result['inactive'] );
	}

	public function test__get_integration_info_returns_correct_data_for_active_integration(): void {
		$integration = new FakeIntegration( 'test-integration' );
		$this->integrations->register( $integration );
		$this->integrations->activate( 'test-integration', [
			'config' => [ 'api_key' => 'test-key' ],
		] );

		$result = $this->integrations->get_integration_info( 'test-integration' );

		$this->assertIsArray( $result );
		$this->assertEquals( 'test-integration', $result['slug'] );
		$this->assertTrue( $result['is_active'] );
		$this->assertFalse( $result['is_loaded'] ); // FakeIntegration returns false
		$this->assertEquals( FakeIntegration::class, $result['class'] );
		$this->assertEquals( [ 'api_key' => 'test-key' ], $result['env_config'] );
		$this->assertEquals( [], $result['site_config'] );
		$this->assertEquals( [], $result['child_configs'] );
	}

	public function test__get_integration_info_returns_correct_data_for_inactive_integration(): void {
		$integration = new FakeIntegration( 'inactive-integration' );
		$this->integrations->register( $integration );
		// Not activating

		$result = $this->integrations->get_integration_info( 'inactive-integration' );

		$this->assertIsArray( $result );
		$this->assertEquals( 'inactive-integration', $result['slug'] );
		$this->assertFalse( $result['is_active'] );
		$this->assertFalse( $result['is_loaded'] );
		$this->assertEquals( FakeIntegration::class, $result['class'] );
		$this->assertEquals( [], $result['env_config'] );
	}

	public function test__get_integration_info_returns_null_for_non_existent_integration(): void {
		$result = $this->integrations->get_integration_info( 'non-existent' );

		$this->assertNull( $result );
	}

	public function test__get_integrations_summary_returns_correct_format(): void {
		$integration1 = new FakeIntegration( 'active-with-config' );
		$integration2 = new FakeIntegration( 'active-no-config' );
		$integration3 = new FakeIntegration( 'inactive' );

		$this->integrations->register( $integration1 );
		$this->integrations->register( $integration2 );
		$this->integrations->register( $integration3 );

		$this->integrations->activate( 'active-with-config', [
			'config' => [ 'setting' => 'value' ],
		] );
		$this->integrations->activate( 'active-no-config' );
		// Not activating inactive

		$result = $this->integrations->get_integrations_summary();

		$this->assertCount( 3, $result );

		// Check active with config
		$this->assertEquals( 'active-with-config', $result['active-with-config']['slug'] );
		$this->assertTrue( $result['active-with-config']['is_active'] );
		$this->assertFalse( $result['active-with-config']['is_loaded'] );
		$this->assertEquals( FakeIntegration::class, $result['active-with-config']['class'] );
		$this->assertTrue( $result['active-with-config']['has_config'] );

		// Check active without config
		$this->assertEquals( 'active-no-config', $result['active-no-config']['slug'] );
		$this->assertTrue( $result['active-no-config']['is_active'] );
		$this->assertFalse( $result['active-no-config']['has_config'] );

		// Check inactive
		$this->assertEquals( 'inactive', $result['inactive']['slug'] );
		$this->assertFalse( $result['inactive']['is_active'] );
		$this->assertFalse( $result['inactive']['has_config'] );
	}

	public function test__get_integrations_summary_returns_empty_array_when_no_integrations(): void {
		$result = $this->integrations->get_integrations_summary();

		$this->assertEmpty( $result );
	}
}
