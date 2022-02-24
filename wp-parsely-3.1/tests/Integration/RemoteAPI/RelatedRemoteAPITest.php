<?php
/**
 * Parsely `/related` Remote API Integration tests.
 *
 * @package Parsely\Tests
 */

declare(strict_types=1);

namespace Parsely\Tests\Integration;

use Parsely\Parsely;
use Parsely\RemoteAPI\Cache;
use Parsely\RemoteAPI\Cached_Proxy;
use Parsely\RemoteAPI\Related_Proxy;
use WP_Error;

/**
 * Parsely `/related` Remote API tests.
 */
final class RelatedRemoteAPITest extends TestCase {
	/**
	 * Internal Parsely variable
	 *
	 * @var Parsely $parsely Holds the Parsely object
	 */
	private static $parsely;

	/**
	 * The setUp run before each test
	 */
	public function set_up(): void {
		parent::set_up();

		self::$parsely = new Parsely();
	}

	/**
	 * Data provider for test_related_api_url().
	 *
	 * @return iterable
	 */
	public function data_related_api_url(): iterable {
		yield 'Basic (Expected data)' => array(
			array(
				'apikey'         => 'my-key',
				'pub_date_start' => '7d',
				'sort'           => 'score',
				'boost'          => 'views',
				'limit'          => 5,
			),
			'https://api.parsely.com/v2/related?apikey=my-key&boost=views&limit=5&pub_date_start=7d&sort=score',
		);

		yield 'published_within value of 0' => array(
			array(
				'apikey' => 'my-key',
				'sort'   => 'score',
				'boost'  => 'views',
				'limit'  => 5,
			),
			'https://api.parsely.com/v2/related?apikey=my-key&boost=views&limit=5&sort=score',
		);

		yield 'Sort on publish date (no boost param)' => array(
			array(
				'apikey' => 'my-key',
				'sort'   => 'pub_date',
				'limit'  => 5,
			),
			'https://api.parsely.com/v2/related?apikey=my-key&limit=5&sort=pub_date',
		);

		yield 'Rank by relevance only (no boost param)' => array(
			array(
				'apikey' => 'my-key',
				'sort'   => 'score',
				'limit'  => 5,
			),
			'https://api.parsely.com/v2/related?apikey=my-key&limit=5&sort=score',
		);
	}

	/**
	 * Test the basic generation of the API URL.
	 *
	 * @dataProvider data_related_api_url
	 * @covers \Parsely\RemoteAPI\Related_Proxy:get_api_url
	 *
	 * @param array  $query Test query arguments.
	 * @param string $url Expected generated URL.
	 */
	public function test_related_api_url( array $query, string $url ): void {
		self::set_options( array( 'apikey' => 'my-key' ) );
		$proxy = new Related_Proxy( self::$parsely );
		self::assertEquals( $url, $proxy->get_api_url( $query ) );
	}

	/**
	 * Test that the cache is used instead of the proxy when there's a cache hit.
	 *
	 * @covers \Parsely\RemoteAPI\Cached_Proxy::get_items
	 */
	public function test_related_cached_proxy_returns_cached_value(): void {
		$proxy = $this->getMockBuilder( Related_Proxy::class )
			->disableOriginalConstructor()
			->getMock();

		// If this method is called, that means our cache did not hit as expected.
		$proxy->expects( self::never() )->method( 'get_items' );

		$cache_key = 'parsely_api_' . wp_hash( wp_json_encode( $proxy ) ) . '_' . wp_hash( wp_json_encode( array() ) );

		$object_cache = $this->createMock( Cache::class );
		$object_cache->method( 'get' )
			->willReturn( (object) array( 'cache_hit' => true ) );

		$object_cache->expects( self::once() )
			->method( 'get' )
			->with(
				self::equalTo( $cache_key ),
				self::equalTo( 'wp-parsely' ),
				self::equalTo( false ),
				self::isNull()
			);

		$cached_proxy = $this->getMockBuilder( Cached_Proxy::class )
			->setConstructorArgs( array( $proxy, $object_cache ) )
			->setMethodsExcept( array( 'get_items' ) )
			->getMock();

		self::assertEquals( (object) array( 'cache_hit' => true ), $cached_proxy->get_items( array() ) );
	}

	/**
	 * Test that when the cache misses, the proxy is used instead and the resultant value is cached.
	 *
	 * @covers \Parsely\RemoteAPI\Cached_Proxy::get_items
	 */
	public function test_related_caching_decorator_returns_uncached_value(): void {
		$proxy = $this->getMockBuilder( Related_Proxy::class )
			->disableOriginalConstructor()
			->getMock();

		$proxy->method( 'get_items' )
			->willReturn( (object) array( 'cache_hit' => false ) );

		// If this method is _NOT_ called, that means our cache did not miss as expected.
		$proxy->expects( self::once() )->method( 'get_items' );

		$cache_key = 'parsely_api_' . wp_hash( wp_json_encode( $proxy ) ) . '_' . wp_hash( wp_json_encode( array() ) );

		$object_cache = $this->createMock( Cache::class );
		$object_cache->method( 'get' )
			->willReturn( false );

		$object_cache->expects( self::once() )
			->method( 'get' )
			->with(
				self::equalTo( $cache_key ),
				self::equalTo( 'wp-parsely' ),
				self::equalTo( false ),
				self::isNull()
			);

		$cached_proxy = $this->getMockBuilder( Cached_Proxy::class )
			->setConstructorArgs( array( $proxy, $object_cache ) )
			->setMethodsExcept( array( 'get_items' ) )
			->getMock();

		self::assertEquals( (object) array( 'cache_hit' => false ), $cached_proxy->get_items( array() ) );
	}
}
