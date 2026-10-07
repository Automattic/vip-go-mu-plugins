<?php

namespace Automattic\VIP;

use Automattic\VIP\Utils\Jetpack_IP_Manager;
use WP_Error;
use WP_UnitTestCase;

use function Automattic\Test\Utils\http_response;

class Test_Jetpack_IP_Manager extends WP_UnitTestCase {
	private bool $did_remote_request = false;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		require_once __DIR__ . '/../../vip-helpers/class-jetpack-ip-manager.php';
	}

	/**
	 * Record requests to the Jetpack IP endpoint and answer them with $response (null lets the request through).
	 *
	 * @param array|WP_Error|null $response
	 */
	private function mock_jetpack_endpoint( $response = null ): void {
		$this->did_remote_request = false;

		add_filter( 'pre_http_request', function ( $result, $args, $url ) use ( $response ) {
			if ( Jetpack_IP_Manager::ENDPOINT === $url ) {
				$this->did_remote_request = true;
				return $response ?? $result;
			}

			return $result;
		}, 10, 3 );
	}

	public function test_get_jetpack_ips_option_is_fresh(): void {
		$current = [ '10.0.0.0/24' ];

		update_option( Jetpack_IP_Manager::OPTION_NAME, [
			'ips' => $current,
			'exp' => time() + DAY_IN_SECONDS,
		] );

		$this->mock_jetpack_endpoint();

		$actual = Jetpack_IP_Manager::get_jetpack_ips();

		self::assertFalse( $this->did_remote_request );
		self::assertSame( $current, $actual );
	}

	public function data_stale_option(): array {
		return [
			'no option'      => [ null ],
			'expired option' => [
				[
					'ips' => [ '1.1.1.1' ],
					'exp' => time() - DAY_IN_SECONDS,
				],
			],
		];
	}

	/**
	 * @dataProvider data_stale_option
	 */
	public function test_get_jetpack_ips_refreshes_stale_option( ?array $option ): void {
		$expected = [ '10.0.0.0/8' ];

		if ( null === $option ) {
			delete_option( Jetpack_IP_Manager::OPTION_NAME );
		} else {
			update_option( Jetpack_IP_Manager::OPTION_NAME, $option );
		}

		$this->mock_jetpack_endpoint( http_response( 200, wp_json_encode( $expected ) ) );

		$actual = Jetpack_IP_Manager::get_jetpack_ips();

		self::assertTrue( $this->did_remote_request );
		self::assertSame( $expected, $actual );
	}

	/**
	 * @dataProvider data_get_jetpack_ips_transient_error
	 */
	public function test_get_jetpack_ips_retrieval_error( $response ): void {
		delete_option( Jetpack_IP_Manager::OPTION_NAME );

		$this->mock_jetpack_endpoint( $response );

		$actual = Jetpack_IP_Manager::get_jetpack_ips();

		self::assertTrue( $this->did_remote_request );
		self::assertEmpty( $actual );
	}

	public function data_get_jetpack_ips_transient_error(): iterable {
		return [
			'WP_Error'   => [ new WP_Error( 'code_phat_gaya' ) ],
			'HTTP error' => [ http_response( 400, '' ) ],
			'Bad value'  => [ http_response( 200, '' ) ],
		];
	}

	public function test_get_jetpack_ips_expired_error(): void {
		$instance = Jetpack_IP_Manager::instance();
		$expected = [ '1.1.1.1' ];

		update_option( Jetpack_IP_Manager::OPTION_NAME, [
			'ips' => $expected,
			'exp' => time() - DAY_IN_SECONDS,
		] );

		$this->mock_jetpack_endpoint( new WP_Error( 'code_phat_gaya' ) );

		$actual = $instance->get_jetpack_ips();

		self::assertSame( $expected, $actual );
		self::assertTrue( $this->did_remote_request );
	}
}
