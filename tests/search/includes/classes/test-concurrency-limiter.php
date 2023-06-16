<?php

namespace Automattic\VIP\Search;

use Automattic\VIP\Search\ConcurrencyLimiter\APCu_Backend;
use Automattic\VIP\Search\ConcurrencyLimiter\Object_Cache_Backend;
use ElasticPress\Elasticsearch;
use WP_Error;
use WP_UnitTestCase;

require_once __DIR__ . '/../../../../search/includes/classes/class-concurrency-limiter.php';
require_once __DIR__ . '/../../../../search/includes/classes/concurrency-limiter/class-apcu-backend.php';
require_once __DIR__ . '/../../../../search/elasticpress/includes/classes/Elasticsearch.php';
require_once __DIR__ . '/../../../../search/elasticpress/includes/utils.php';

class Test_Concurrency_Limiter extends WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();

		remove_all_filters( 'ep_do_intercept_request' );
		remove_all_actions( 'ep_remote_request' );

		add_filter( 'vip_search_should_fail_excessive_request', [ $this, 'vip_search_should_fail_excessive_request' ], PHP_INT_MAX, 2 );
	}

	public function vip_search_should_fail_excessive_request( bool $result, bool $orig_should_fail ): bool {
		return $orig_should_fail;
	}

	/**
	 * @dataProvider data_concurrency_limiting
	 * @param string $backend
	 * @psalm-param class-string<\Automattic\VIP\Search\ConcurrencyLimiter\BackendInterface> $backend
	 */
	public function test_concurrency_limiting( $backend ): void {
		if ( ! $backend::is_supported() ) {
			self::markTestSkipped( sprintf( 'Backend "%s" is not supported', $backend ) );
		}

		add_filter( 'vip_search_concurrency_limit_backend', function() use ( $backend ) {
			return $backend;
		} );

		add_filter( 'ep_intercept_remote_request', '__return_true' );
		add_filter( 'vip_search_max_concurrent_requests', function() {
			return 1;
		} );

		add_filter( 'ep_do_intercept_request', [ __CLASS__, 'request_interceptor' ], 50 );

		$es      = new Elasticsearch();
		$client1 = new Concurrency_Limiter();

		self::assertInstanceOf( $backend, $client1->get_backend() );

		$response2 = null;

		// This is how we simulate a concurrent request
		// We need to inject into `ep_remote_request` as early as possible, before `Concurrency_Limiter` has a chance to mark the first request as completed.
		// The first thing we need to do is to remove ourselves from the hook list to avoid infinite loops.
		$send_request = function() use ( $es, &$response2, &$send_request, $backend ) {
			remove_action( 'ep_remote_request', $send_request, 0 );
			$client2   = new Concurrency_Limiter();
			$response2 = $es->remote_request( '/_search' );
			self::assertInstanceOf( $backend, $client2->get_backend() );
			$client2->cleanup();
		};

		add_action( 'ep_remote_request', $send_request, 0 );

		$response1 = $es->remote_request( '/_search' );
		$client1->cleanup();

		self::assertIsArray( $response1 );
		self::assertInstanceOf( WP_Error::class, $response2 );
		/** @var WP_Error $response2 */
		self::assertSame( 429, $response2->get_error_code() );
	}

	/**
	 * @dataProvider data_concurrency_limiting
	 * @param string $backend
	 * @psalm-param class-string<\Automattic\VIP\Search\ConcurrencyLimiter\BackendInterface> $backend
	 */
	public function test__get_value( $backend ) {
		if ( ! $backend::is_supported() ) {
			self::markTestSkipped( sprintf( 'Backend "%s" is not supported', $backend ) );
		}

		add_filter( 'vip_search_concurrency_limit_backend', fn() => $backend );
		$client1 = new Concurrency_Limiter();
		$backend = $client1->get_backend();
		$backend->inc_value();
		self::assertSame( 1, $backend->get_value() );
	}

	/**
	 * @dataProvider data_concurrency_limiting
	 * @param string $backend
	 * @psalm-param class-string<\Automattic\VIP\Search\ConcurrencyLimiter\BackendInterface> $backend
	 */
	public function test__index_is_not_limited( $backend ): void {
		if ( ! $backend::is_supported() ) {
			self::markTestSkipped( sprintf( 'Backend "%s" is not supported', $backend ) );
		}

		add_filter( 'vip_search_concurrency_limit_backend', fn() => $backend );
		add_filter( 'ep_intercept_remote_request', '__return_true' );
		add_filter( 'vip_search_max_concurrent_requests', function() {
			return 1;
		} );

		add_filter( 'ep_do_intercept_request', [ __CLASS__, 'request_interceptor' ], 50 );

		$es      = new Elasticsearch();
		$client1 = new Concurrency_Limiter();

		self::assertInstanceOf( $backend, $client1->get_backend() );

		$response2 = null;

		// This is how we simulate a concurrent request
		// We need to inject into `ep_remote_request` as early as possible, before `Concurrency_Limiter` has a chance to mark the first request as completed.
		// The first thing we need to do is to remove ourselves from the hook list to avoid infinite loops.
		$send_request = function() use ( $es, &$response2, &$send_request, $backend ) {
			remove_action( 'ep_remote_request', $send_request, 0 );
			$client2   = new Concurrency_Limiter();
			$response2 = $es->remote_request( '/_index' );
			self::assertInstanceOf( $backend, $client2->get_backend() );
			$client2->cleanup();
		};

		add_action( 'ep_remote_request', $send_request, 0 );

		$response1 = $es->remote_request( '/_index' );
		$client1->cleanup();

		self::assertIsArray( $response1 );
		self::assertIsArray( $response2 );
	}

	/**
	 * @psalm-return iterable<string, array{class-string<\Automattic\VIP\Search\ConcurrencyLimiter\BackendInterface>}>
	 * @return iterable
	 */
	public function data_concurrency_limiting(): iterable {
		return [
			'APCu'        => [ APCu_Backend::class ],
			'ObjectCache' => [ Object_Cache_Backend::class ],
		];
	}

	/**
	 * @param mixed $request
	 * @return array|WP_Error
	 */
	public static function request_interceptor( $request ) {
		if ( is_wp_error( $request ) && ( 429 === $request->get_error_code() || 503 === $request->get_error_code() ) ) {
			return $request;
		}

		return [
			'response' => [
				'code' => 200,
			],
		];
	}
}
