<?php
/**
 * Test: Integrations Configuration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.FunctionComment.MissingParamComment

use Automattic\Test\Constant_Mocker;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use WP_UnitTestCase;

use function Automattic\Test\Utils\get_class_method_as_public;
use function Automattic\Test\Utils\get_class_property_as_public;
use function Automattic\Test\Utils\reset_custom_error_reporting;
use function Automattic\Test\Utils\setup_custom_error_reporting;

require_once __DIR__ . '/fake-integration.php';

class VIP_Integrations_Config_Test extends WP_UnitTestCase {
	/**
	 * Original error reporting.
	 *
	 * @var int
	 */
	private $original_error_reporting;

	public function setUp(): void {
		parent::setUp();

		$this->original_error_reporting = setup_custom_error_reporting();
	}

	public function tearDown(): void {
		reset_custom_error_reporting( $this->original_error_reporting );
		Constant_Mocker::clear();

		parent::tearDown();
	}

	public function test__constructor_does_not_set_config_file_dir_property_if_constant_is_not_defined() {
		$integrations_config   = new VipIntegrationsConfig();
		$config_file_dir_value = get_class_property_as_public( VipIntegrationsConfig::class, 'config_file_dir' )->getValue( $integrations_config );
		
		$this->assertEquals( ABSPATH . 'config/integrations-config', $config_file_dir_value );
	}

	public function test__constructor_set_config_file_dir_property_if_constant_is_defined() {
		Constant_Mocker::define( 'WPVIP_INTEGRATIONS_CONFIG_DIR', '/wp-test/config/integrations-config' );

		$integrations_config   = new VipIntegrationsConfig();
		$config_file_dir_value = get_class_property_as_public( VipIntegrationsConfig::class, 'config_file_dir' )->getValue( $integrations_config );
		
		$this->assertEquals( '/wp-test/config/integrations-config', $config_file_dir_value );
	}

	public function test__get_config_file_names_returns_an_empty_array_if_dir_does_not_exist(): void {
		$obj                   = ( new ReflectionClass( VipIntegrationsConfig::class ) )->newInstanceWithoutConstructor();
		$get_config_file_names = get_class_method_as_public( VipIntegrationsConfig::class, 'get_config_file_names' );

		$this->assertEquals( [], $get_config_file_names->invoke( $obj ) );
	}

	public function test__get_config_file_content_returns_null_if_file_is_not_readable(): void {
		$obj                     = ( new ReflectionClass( VipIntegrationsConfig::class ) )->newInstanceWithoutConstructor();
		$get_config_file_content = get_class_method_as_public( VipIntegrationsConfig::class, 'get_config_file_content' );

		$this->assertNull( $get_config_file_content->invoke( $obj, 'file_name' ) );
	}

	public function test__read_config_files_does_not_set_configs_property_if_vip_config_is_not_of_type_array(): void {
		$vip_configs = [ [ 'invalid-config' ] ];

		$mock    = $this->get_mock_with_configs( $vip_configs );
		$configs = get_class_property_as_public( VipIntegrationsConfig::class, 'configs' )->getValue( $mock );

		$this->assertEquals( [], $configs );
	}

	public function test__read_config_files_does_not_set_configs_property_if_vip_config_does_not_have_type_property(): void {
		$vip_configs = [ 
			[ 'env' => [ 'status' => 'enabled' ] ],
		];

		$mock    = $this->get_mock_with_configs( $vip_configs );
		$configs = get_class_property_as_public( VipIntegrationsConfig::class, 'configs' )->getValue( $mock );

		$this->assertEquals( [], $configs );
	}

	public function test__read_config_files_successfully_read_all_vip_configs(): void {
		$vip_configs = [ 
			[
				'type' => 'type-one',
				'env'  => [ 'status' => 'enabled' ],
			],
			[
				'type' => 'type-one',
				'env'  => [ 'status' => 'disabled' ],
			],
			[
				'type' => 'type-two',
				'env'  => [ 'status' => 'blocked' ],
			],
		];

		$mock    = $this->get_mock_with_configs( $vip_configs );
		$configs = get_class_property_as_public( VipIntegrationsConfig::class, 'configs' )->getValue( $mock );

		$this->assertEquals(
			[
				'type-one' => [
					[ 'env' => [ 'status' => 'enabled' ] ],
					[ 'env' => [ 'status' => 'disabled' ] ],
				],
				'type-two' => [
					[ 'env' => [ 'status' => 'blocked' ] ],
				],
			],
			$configs
		);
	}

	public function test__get_vip_configs_returns_an_empty_array_if_slug_does_not_match(): void {
		$vip_configs = [ 
			[
				'type' => 'slug',
				'env'  => [ 'status' => 'blocked' ],
			],
		];

		$mock = $this->get_mock_with_configs( $vip_configs );

		$this->assertEquals( [], $mock->get_vip_configs( 'invalid-slug' ) );
	}

	public function test__get_vip_configs_returns_configs_if_slug_is_matched(): void {
		$vip_configs = [ 
			[
				'type' => 'slug',
				'env'  => [ 'status' => 'enabled' ],
			],
			[
				'type' => 'slug',
				'env'  => [ 'status' => 'blocked' ],
			],
		];

		$mock = $this->get_mock_with_configs( $vip_configs );

		$this->assertEquals( [
			[ 'env' => [ 'status' => 'enabled' ] ],
			[ 'env' => [ 'status' => 'blocked' ] ],
		], $mock->get_vip_configs( 'slug' ) );
	}

	/**
	 * Mock the class with given configs.
	 *
	 * @param array<mixed> $vip_configs
	 *
	 * @return MockObject&VipIntegrationsConfig
	 */
	private function get_mock_with_configs( $vip_configs ) {
		/**
		 * Config Mock.
		 *
		 * @var MockObject&VipIntegrationsConfig
		 */
		$mock = $this->getMockBuilder( VipIntegrationsConfig::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_config_file_names', 'get_config_file_content' ] )
			->getMock();

		$file_names = [];
		foreach ( $vip_configs as $index => $vip_config ) {
			$type         = $vip_config['type'] ?? null;
			$file_names[] = $type . "-$index-config.php";
		}

		$mock->method( 'get_config_file_names' )->willReturn( $file_names );
		$mock->method( 'get_config_file_content' )->willReturn( ...$vip_configs );
		get_class_method_as_public( VipIntegrationsConfig::class, '__construct' )->invoke( $mock );

		return $mock;
	}
}
