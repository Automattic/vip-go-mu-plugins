<?php
/**
 * Test: VIP Integrations
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use stdClass;
use WP_UnitTestCase;

use function Automattic\Test\Utils\get_class_property_as_public;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.FunctionComment.MissingParamComment

class VIP_Integrations_Plugin_Test extends WP_UnitTestCase {
	/** @var Integrations|null */
	private $original_integrations;

	public function setUp(): void {
		parent::setUp();

		$this->original_integrations = get_class_property_as_public( IntegrationsSingleton::class, 'instance' )->getValue();
	}

	public function tearDown(): void {
		// Don't leak the mocked singleton into other tests.
		get_class_property_as_public( IntegrationsSingleton::class, 'instance' )->setValue( null, $this->original_integrations );

		parent::tearDown();
	}

	public function test_activate_function_is_calling_the_activate_method_from_integrations_class(): void {
		$integrations_mock = $this->getMockBuilder( Integrations::class )->onlyMethods( [ 'activate' ] )->getMock();
		$integrations_mock->expects( $this->once() )->method( 'activate' )->with( $this->equalTo( 'test-slug' ), $this->equalTo( [ 'test-key' => 'test-value' ] ) );

		$this->set_integrations( $integrations_mock );

		activate( 'test-slug', [ 'test-key' => 'test-value' ] );
	}

	public function test_integrations_are_activated_and_loaded_on_muplugins_loaded_hook(): void {
		$integrations_mock = $this->getMockBuilder( Integrations::class )->getMock();
		$integrations_mock->expects( $this->once() )->method( 'activate_platform_integrations' )->with();
		$integrations_mock->expects( $this->once() )->method( 'load_active' )->with();

		$this->set_integrations( $integrations_mock );

		do_action( 'muplugins_loaded' );
		ob_clean();
	}

	/**
	 * Set integrations mock.
	 *
	 * @param MockObject&Integrations $mock
	 */
	private function set_integrations( $mock ): void {
		$instance = IntegrationsSingleton::instance();
		get_class_property_as_public( IntegrationsSingleton::class, 'instance' )->setValue( $instance, $mock );
	}

	/**
	 * @dataProvider data_public_api_functions
	 */
	public function test_public_api_function_delegates_to_integrations_instance( string $function_name, string $method, array $args, $expected ): void {
		$integrations_mock = $this->getMockBuilder( Integrations::class )->onlyMethods( [ $method ] )->getMock();
		$integrations_mock->expects( $this->once() )->method( $method )->with( ...$args )->willReturn( $expected );

		$this->set_integrations( $integrations_mock );

		$this->assertSame( $expected, call_user_func_array( __NAMESPACE__ . '\\' . $function_name, $args ) );
	}

	public static function data_public_api_functions(): array {
		return [
			'wpvip_is_integration_enabled'   => [ 'wpvip_is_integration_enabled', 'is_integration_enabled', [ 'test-slug' ], true ],
			'wpvip_get_integration'          => [ 'wpvip_get_integration', 'get_integration', [ 'test-slug' ], new ParselyIntegration( 'test-slug' ) ],
			'wpvip_get_integration_info'     => [
				'wpvip_get_integration_info',
				'get_integration_info',
				[ 'test-slug' ],
				[
					'slug'      => 'test-slug',
					'is_active' => true,
				],
			],
			'wpvip_get_enabled_integrations' => [ 'wpvip_get_enabled_integrations', 'get_enabled_integrations', [], [ 'test-slug' => new stdClass() ] ],
			'wpvip_get_all_integrations'     => [ 'wpvip_get_all_integrations', 'get_all_integrations', [], [ 'test-slug' => new stdClass() ] ],
			'wpvip_get_integrations_summary' => [ 'wpvip_get_integrations_summary', 'get_integrations_summary', [], [ 'test-slug' => [ 'is_active' => true ] ] ],
		];
	}
}
