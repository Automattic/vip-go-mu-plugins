<?php

namespace Automattic\VIP;

use Automattic\VIP\Utils\Jetpack_IP_Manager;
use WP_Error;
use WP_UnitTestCase;

class Test_Jetpack_IP_Manager extends WP_UnitTestCase {
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		require_once __DIR__ . '/../../vip-helpers/class-jetpack-ip-manager.php';
	}

	public function test_init(): void {
		Jetpack_IP_Manager::instance()->init();
		self::assertNotFalse( wp_next_scheduled( Jetpack_IP_Manager::CRON_EVENT_NAME ) );
		self::assertTrue( has_action( Jetpack_IP_Manager::CRON_EVENT_NAME ) );
	}

	public function test_get_jetpack_ips_transient_available(): void {
		$did_remote_request = false;
		$expected           = [ '10.0.0.0/24' ];

		set_transient( Jetpack_IP_Manager::TRANSIENT_NAME, $expected );

		add_filter( 'pre_http_request', function( $result, $args, $url ) use ( &$did_remote_request ) {
			if ( Jetpack_IP_Manager::ENDPOINT === $url ) {
				$did_remote_request = true;
			}

			return $result;
		}, 10, 3 );

		$actual = Jetpack_IP_Manager::get_jetpack_ips();

		self::assertFalse( $did_remote_request );
		self::assertSame( $expected, $actual );
	}

	public function test_get_jetpack_ips_transient_unavailable(): void {
		$did_remote_request = false;
		$expected           = [ '10.0.0.0/8' ];

		delete_transient( Jetpack_IP_Manager::TRANSIENT_NAME );

		add_filter( 'pre_http_request', function( $result, $args, $url ) use ( &$did_remote_request, $expected ) {
			if ( Jetpack_IP_Manager::ENDPOINT === $url ) {
				$did_remote_request = true;
				return [
					'headers'  => [],
					'body'     => wp_json_encode( $expected ),
					'response' => [
						'code'    => 200,
						'message' => get_status_header_desc( 200 ),
					],
					'cookies'  => [],
					'filename' => null,
				];
			}

			return $result;
		}, 10, 3 );

		$actual = Jetpack_IP_Manager::get_jetpack_ips();

		self::assertTrue( $did_remote_request );
		self::assertSame( $expected, $actual );
	}

	/**
	 * @dataProvider data_get_jetpack_ips_transient_error
	 */
	public function test_get_jetpack_ips_transient_error( $response ): void {
		$did_remote_request = false;

		delete_transient( Jetpack_IP_Manager::TRANSIENT_NAME );

		add_filter( 'pre_http_request', function( $result, $args, $url ) use ( &$did_remote_request, $response ) {
			if ( Jetpack_IP_Manager::ENDPOINT === $url ) {
				$did_remote_request = true;
				return $response;
			}

			return $result;
		}, 10, 3 );

		$actual = Jetpack_IP_Manager::get_jetpack_ips();

		self::assertTrue( $did_remote_request );
		self::assertEmpty( $actual );
	}

	public function data_get_jetpack_ips_transient_error(): iterable {
		return [
			'WP_Error'   => [ new WP_Error( 'code_phat_gaya' ) ],
			'HTTP error' => [
				[
					'headers'  => [],
					'body'     => '',
					'response' => [
						'code'    => 400,
						'message' => get_status_header_desc( 400 ),
					],
					'cookies'  => [],
					'filename' => null,
				],
			],
			'Bad value'  => [
				[
					'headers'  => [],
					'body'     => '',
					'response' => [
						'code'    => 200,
						'message' => get_status_header_desc( 200 ),
					],
					'cookies'  => [],
					'filename' => null,
				],
			],
		];
	}
}
