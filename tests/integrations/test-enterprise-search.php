<?php
/**
 * Test: Enterprise Search Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use PHPUnit\Framework\MockObject\MockObject;
use WP_UnitTestCase;

use function Automattic\Test\Utils\get_class_property_as_public;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing

class VIP_EnterpriseSearch_Integration_Test extends WP_UnitTestCase {
	private string $slug = 'enterprise-search';

	public function test_is_loaded_returns_true_if_es_exist(): void {
		require_once __DIR__ . '/../../search/search.php';

		$es_integration = new EnterpriseSearchIntegration( $this->slug );
		$this->assertTrue( $es_integration->is_loaded() );
	}

	public function test__load_call_returns_without_requiring_class_if_es_is_already_loaded(): void {
		/**
		 * Integration mock.
		 *
		 * @var MockObject|EnterpriseSearchIntegration
		 */
		$es_integration_mock = $this->getMockBuilder( EnterpriseSearchIntegration::class )->setConstructorArgs( [ 'enterprise-search' ] )->onlyMethods( [ 'is_loaded' ] )->getMock();
		$es_integration_mock->expects( $this->once() )->method( 'is_loaded' )->willReturn( true );
		$preload_state = class_exists( '\Automattic\VIP\Search\Search' );

		$es_integration_mock->load();

		$this->assertEquals( $preload_state, class_exists( '\Automattic\VIP\Search\Search' ) );
	}

	public function test__load_call_if_class_not_exist(): void {
		/**
		 * Integration mock.
		 *
		 * @var MockObject|EnterpriseSearchIntegration
		 */
		$es_integration_mock = $this->getMockBuilder( EnterpriseSearchIntegration::class )->setConstructorArgs( [ 'enterprise-search' ] )->onlyMethods( [ 'is_loaded' ] )->getMock();
		$es_integration_mock->expects( $this->once() )->method( 'is_loaded' )->willReturn( false );
		$existing_value = class_exists( '\Automattic\VIP\Search\Search' );

		$es_integration_mock->load();

		if ( ! $existing_value ) {
			$this->assertTrue( class_exists( '\Automattic\VIP\Search\Search' ) );
		}
	}

	public function test__configure_action(): void {
		$credentials    = [
			'username' => 'test-username',
			'password' => 'foo-bar',
		];
		$es_integration = new EnterpriseSearchIntegration( $this->slug );
		$es_integration->configure();

		get_class_property_as_public( Integration::class, 'options' )->setValue( $es_integration, [
			'config' => $credentials,
		] );

		do_action( 'vip_search_loaded' );

		$this->assertEquals( 10, has_action( 'vip_search_loaded', [ $es_integration, 'vip_set_es_credentials' ] ) );
		$this->assertEquals( constant( 'VIP_ELASTICSEARCH_USERNAME' ), $credentials['username'] );
		$this->assertEquals( constant( 'VIP_ELASTICSEARCH_PASSWORD' ), $credentials['password'] );
	}
}
