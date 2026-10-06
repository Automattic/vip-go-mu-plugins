<?php
/**
 * Test: Content for Agents Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use Automattic\Test\Constant_Mocker;
use WP_UnitTestCase;

class Content_For_Agents_Integration_Test extends WP_UnitTestCase {
	public function tearDown(): void {
		Constant_Mocker::clear();

		parent::tearDown();
	}

	public function test_load_returns_early_if_plugin_already_loaded(): void {
		$integration = $this->getMockBuilder( ContentForAgentsIntegration::class )
			->setConstructorArgs( [ 'content-for-agents' ] )
			->onlyMethods( [ 'get_latest_version' ] )
			->getMock();
		$integration->expects( $this->never() )->method( 'get_latest_version' );
		$integration->activate();
		$integration->load();

		Constant_Mocker::define( 'CONTENT_FOR_AGENTS_LOADED', true );
		do_action( 'plugins_loaded' );
	}

	/**
	 * @dataProvider unavailable_plugin_provider
	 */
	public function test_load_sets_inactive_when_plugin_is_unavailable( ?string $directory ): void {
		$integration = $this->getMockBuilder( ContentForAgentsIntegration::class )
			->setConstructorArgs( [ 'content-for-agents' ] )
			->onlyMethods( [ 'get_latest_version' ] )
			->getMock();
		$integration->expects( $this->once() )->method( 'get_latest_version' )->willReturn( $directory );
		$integration->activate();
		$integration->load();

		do_action( 'plugins_loaded' );

		$this->assertFalse( $integration->is_active() );
	}

	public function unavailable_plugin_provider(): array {
		return [
			'no versions'        => [ null ],
			'missing entry file' => [ 'content-for-agents-missing-test-plugin' ],
		];
	}
}
