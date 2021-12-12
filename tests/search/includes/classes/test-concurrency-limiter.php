<?php

namespace Automattic\VIP\Search;

use ElasticPress\Elasticsearch;
use WP_Error;
use WP_UnitTestCase;

require_once __DIR__ . '/class-concurrency-limiter-helper.php';
require_once __DIR__ . '/../../../../search/elasticpress/includes/classes/Elasticsearch.php';
require_once __DIR__ . '/../../../../search/elasticpress/includes/utils.php';

/**
 * @requires extension apcu
 */
class Test_Concurrency_Limiter extends WP_UnitTestCase {
	public function test_concurrency_limiting(): void {
		if ( ! apcu_enabled() ) {
			self::markTestSkipped( 'APCu is not enabled' );
		}

		add_filter( 'ep_intercept_remote_request', '__return_true' );
		add_filter( 'vip_es_max_concurrent_requests', function() {
			return 1;
		} );

		$interceptor = function( $request ) {
			if ( is_wp_error( $request ) && 503 === $request->get_error_code() ) {
				return $request;
			}

			return [
				'response' => [
					'code' => 200,
				],
			];
		};

		add_filter( 'ep_do_intercept_request', $interceptor, 50 );
		
		$es      = new Elasticsearch();
		$client1 = new Concurrency_Limiter();

		$this->check_apcu_oom();

		$response2 = null;

		// This is how we simulate a concurrent request
		// We need to inject into `ep_remote_request` as early as possible, before `Concurrency_Limiter` has a chance to mark the first request as completed.
		// The first thing we need to do is to remove ourselves from the hook list to avoid infinite loops.
		$send_request = function() use ( $es, &$response2, &$send_request ) {
			remove_action( 'ep_remote_request', $send_request, 0 );
			$client2   = new Concurrency_Limiter();
			$response2 = $es->remote_request( '' );
			$client2->cleanup();
		};

		add_action( 'ep_remote_request', $send_request, 0 );

		$response1 = $es->remote_request( '' );
		$client1->cleanup();

		self::assertIsArray( $response1 );
		self::assertInstanceOf( WP_Error::class, $response2 );
		/** @var WP_Error $response2 */
		self::assertSame( 503, $response2->get_error_code() );
	}

	public function test_destruction(): void {
		if ( ! apcu_enabled() ) {
			self::markTestSkipped( 'APCu is not enabled' );
		}

		$client   = new Concurrency_Limiter_Helper();
		$verifier = new Concurrency_Limiter_Helper();

		$this->check_apcu_oom();

		self::assertSame( 0, $client->get_key() );
		self::assertSame( $verifier->get_key(), $client->get_key() );
		$client->ep_do_intercept_request( new WP_Error( 400 ) );

		self::assertSame( 1, $client->get_key() );
		self::assertSame( $verifier->get_key(), $client->get_key() );
		// The destructor is called when there are no references to the class.
		// We, therefore, need to clean up all hooks we installed. This is what PHP does during the shutdown sequence.
		$client->cleanup();
		
		self::assertSame( 1, $client->get_key() );
		self::assertSame( $verifier->get_key(), $client->get_key() );

		unset( $client );
		self::assertSame( 0, $verifier->get_key() );
	}

	public function test_get_key_fixes_value_type(): void {
		if ( ! apcu_enabled() ) {
			self::markTestSkipped( 'APCu is not enabled' );
		}

		apcu_delete( Concurrency_Limiter::KEY_NAME );
		apcu_store( Concurrency_Limiter::KEY_NAME, 'something wrong', 15 );

		$this->check_apcu_oom();

		$verifier = new Concurrency_Limiter_Helper();
		self::assertSame( 0, $verifier->get_key() );
	}

	private function check_apcu_oom(): void {
		if ( ! apcu_exists( Concurrency_Limiter::KEY_NAME ) ) {
			self::markTestSkipped( 'APCu is out of memory' );
		}
	}
}
