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

	public function test_get_jetpack_ips_option_is_fresh(): void {
		$did_remote_request = false;
		$current            = [ '10.0.0.0/24' ];

		update_option( Jetpack_IP_Manager::OPTION_NAME, [
			'ips' => $current,
			'exp' => time() + DAY_IN_SECONDS,
		] );

		add_filter( 'pre_http_request', function( $result, $args, $url ) use ( &$did_remote_request ) {
			if ( Jetpack_IP_Manager::ENDPOINT === $url ) {
				$did_remote_request = true;
			}

			return $result;
		}, 10, 3 );

		$actual = Jetpack_IP_Manager::get_jetpack_ips();

		self::assertFalse( $did_remote_request );
		self::assertSame( $current, $actual );
	}

	public function test_get_jetpack_ips_no_option(): void {
		$did_remote_request = false;
		$current            = [ '10.0.0.0/8' ];

		delete_option( Jetpack_IP_Manager::OPTION_NAME );

		add_filter( 'pre_http_request', function( $result, $args, $url ) use ( &$did_remote_request, $current ) {
			if ( Jetpack_IP_Manager::ENDPOINT === $url ) {
				$did_remote_request = true;
				return [
					'headers'  => [],
					'body'     => wp_json_encode( $current ),
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
		self::assertSame( $current, $actual );
	}

	/**
	 * @dataProvider data_get_jetpack_ips_transient_error
	 */
	public function test_get_jetpack_ips_retrieval_error( $response ): void {
		$did_remote_request = false;

		delete_option( Jetpack_IP_Manager::OPTION_NAME );

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

	public function test_get_jetpack_ips_expired(): void {
		$instance           = Jetpack_IP_Manager::instance();
		$current            = [ '1.1.1.1' ];
		$expected           = [ '1.1.1.2' ];
		$did_remote_request = false;

		update_option( Jetpack_IP_Manager::OPTION_NAME, [
			'ips' => $current,
			'exp' => time() - DAY_IN_SECONDS,
		] );

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

		$actual = $instance->get_jetpack_ips();

		self::assertSame( $expected, $actual );
		self::assertTrue( $did_remote_request );
	}

	public function test_get_jetpack_ips_expired_error(): void {
		$instance           = Jetpack_IP_Manager::instance();
		$expected           = [ '1.1.1.1' ];
		$did_remote_request = false;

		update_option( Jetpack_IP_Manager::OPTION_NAME, [
			'ips' => $expected,
			'exp' => time() - DAY_IN_SECONDS,
		] );

		add_filter( 'pre_http_request', function( $result, $args, $url ) use ( &$did_remote_request ) {
			if ( Jetpack_IP_Manager::ENDPOINT === $url ) {
				$did_remote_request = true;
				return new WP_Error( 'code_phat_gaya' );
			}

			return $result;
		}, 10, 3 );

		$actual = $instance->get_jetpack_ips();

		self::assertSame( $expected, $actual );
		self::assertTrue( $did_remote_request );
	}
}
