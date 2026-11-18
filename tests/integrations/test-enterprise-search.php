<?php
/**
 * Test: Enterprise Search Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use Automattic\VIP\Search\Search;
use PHPUnit\Framework\MockObject\MockObject;
use WP_UnitTestCase;
use Automattic\Test\Constant_Mocker;

use function Automattic\Test\Utils\get_class_property_as_public;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing, Squiz.Commenting.FunctionComment.MissingParamComment

class VIP_EnterpriseSearch_Integration_Test extends WP_UnitTestCase {
	private string $slug = 'enterprise-search';

	public function test__load_call_returns_without_requiring_class_if_es_is_already_loaded(): void {
		/**
		 * Integration mock.
		 *
		 * @var MockObject|EnterpriseSearchIntegration
		 */
		$es_integration_mock = $this->getMockBuilder( EnterpriseSearchIntegration::class )->setConstructorArgs( [ 'enterprise-search' ] )->onlyMethods( [ 'is_loaded' ] )->getMock();
		$es_integration_mock->expects( $this->once() )->method( 'is_loaded' )->willReturn( true );

		$es_integration_mock->load();

		$this->assertFalse( Constant_Mocker::defined( 'VIP_SEARCH_ENABLED_BY' ) );
	}

	/**
	 * Load Search in a fresh process, so the class is provably absent before load().
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__load_requires_search_and_marks_it_enabled_by_the_integration(): void {
		$this->assertFalse( class_exists( Search::class, false ) );

		$integration = new EnterpriseSearchIntegration( $this->slug );
		$integration->load();

		$this->assertTrue( class_exists( Search::class, false ) );
		$this->assertTrue( $integration->is_loaded() );
		$this->assertSame( 'integration', Constant_Mocker::constant( 'VIP_SEARCH_ENABLED_BY' ) );
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

	public function test__should_not_configure_if_es_constants_are_already_present(): void {
		Constant_Mocker::define( 'VIP_ELASTICSEARCH_USERNAME', 'baz' );
		Constant_Mocker::define( 'VIP_ELASTICSEARCH_PASSWORD', '123' );

		$credentials    = [
			'username' => 'test-username',
			'password' => 'foo-bar',
		];
		$es_integration = new EnterpriseSearchIntegration( $this->slug );
		get_class_property_as_public( Integration::class, 'options' )->setValue( $es_integration, [
			'config' => $credentials,
		] );
		$es_integration->configure();

		$this->assertEquals( false, has_action( 'vip_search_loaded', [ $es_integration, 'vip_set_es_credentials' ] ) );
		$this->assertEquals( Constant_Mocker::constant( 'VIP_ELASTICSEARCH_USERNAME' ), 'baz' );
		$this->assertEquals( Constant_Mocker::constant( 'VIP_ELASTICSEARCH_PASSWORD' ), '123' );
	}

	/**
	 * @dataProvider data_offload_search
	 */
	public function test_configure_sets_query_integration_from_offload_search( array $config, bool $query_integration_constant, ?bool $expected ): void {
		require_once __DIR__ . '/../../search/search.php';

		if ( $query_integration_constant ) {
			Constant_Mocker::define( 'VIP_ENABLE_VIP_SEARCH_QUERY_INTEGRATION', true );
		}

		$es_integration = new EnterpriseSearchIntegration( $this->slug );
		get_class_property_as_public( Integration::class, 'options' )->setValue( $es_integration, [
			'config' => $config,
		] );

		$es_integration->configure();
		do_action( 'vip_search_loaded' );

		$true_filter_priority  = true === $expected ? PHP_INT_MAX : false;
		$false_filter_priority = false === $expected ? PHP_INT_MAX : false;
		$this->assertSame( $true_filter_priority, has_filter( 'vip_search_query_integration_enabled', '__return_true' ) );
		$this->assertSame( $false_filter_priority, has_filter( 'vip_search_query_integration_enabled', '__return_false' ) );
		$this->assertSame( $expected, apply_filters( 'vip_search_query_integration_enabled', null ) );
		$this->assertSame( true === $expected, Search::is_query_integration_enabled() );
	}

	public static function data_offload_search(): array {
		return [
			'offload enabled'                         => [ [ 'offload_search' => 'true' ], false, true ],
			// The query integration constant has no effect once offloading is explicitly disabled.
			'offload disabled overrides the constant' => [ [ 'offload_search' => 'false' ], true, false ],
			'offload not configured'                  => [ [], false, null ],
		];
	}
}
