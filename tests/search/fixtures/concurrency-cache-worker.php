<?php

namespace Automattic\VIP\Logstash;

// Pipes coordinate test processes; no site filesystem writes or shell execution.
// phpcs:disable WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fwrite, WordPress.WP.AlternativeFunctions.json_encode_json_encode

/**
 * Keep the external logging transport out of the cache integration test.
 *
 * @param array $data Log event.
 */
function log2logstash( array $data ): void {}

use Automattic\VIP\Search\ConcurrencyLimiter\Object_Cache_Backend;

// This process uses the production drop-in with a shared Memcached service.
require $argv[1] . '/wp-includes/plugin.php';
define( 'WP_CACHE_KEY_SALT', $argv[2] );
define( 'AUTOMATTIC_MEMCACHED_USE_MEMCACHED_EXTENSION', true );
// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
$memcached_servers = [ 'default' => [ getenv( 'VIP_TEST_MEMCACHED_SERVER' ) ] ];
// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
$table_prefix = 'concurrency_test_';
require __DIR__ . '/../../../drop-ins/wp-memcached/object-cache.php';
require __DIR__ . '/../../../search/includes/classes/concurrency-limiter/class-object-cache-backend.php';

/**
 * Pause the workers at the cache boundary without changing any cache operation.
 */
class Barrier_Object_Cache extends \WP_Object_Cache {
	public bool $armed = false;

	/**
	 * Read through the production cache before pausing a non-atomic increment.
	 *
	 * @param int|string $key Cache key.
	 * @param string $group Cache group.
	 * @param bool $force Force a remote read.
	 * @param bool|null $found Whether the entry exists.
	 * @return mixed
	 */
	public function get( $key, $group = 'default', $force = false, &$found = null ) {
		$value = parent::get( $key, $group, $force, $found );
		if ( $this->armed && Object_Cache_Backend::KEY_NAME === $key ) {
			$this->armed = false;
			worker_barrier( 'counter' );
		}
		return $value;
	}

	/**
	 * Pause before the production atomic increment.
	 *
	 * @param int|string $key Cache key.
	 * @param int $offset Increment amount.
	 * @param string $group Cache group.
	 * @return int|false
	 */
	public function incr( $key, $offset = 1, $group = 'default' ) {
		if ( $this->armed && Object_Cache_Backend::KEY_NAME === $key ) {
			$this->armed = false;
			worker_barrier( 'counter' );
		}
		return parent::incr( $key, $offset, $group );
	}
}

/**
 * Announce a barrier and await the controller, with bounded failure time.
 *
 * @param string $phase Barrier phase or admission result.
 */
function worker_barrier( string $phase ): void {
	fwrite( STDOUT, $phase . "\n" );
	fflush( STDOUT );
	stream_set_timeout( STDIN, 10 );
	if ( "continue\n" !== fgets( STDIN ) ) {
		throw new \RuntimeException( 'Worker barrier timed out: ' . $phase );
	}
}

// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
$wp_object_cache = new Barrier_Object_Cache();
if ( 'increment' !== $argv[3] ) {
	if ( 'seed' === $argv[3] ) {
		wp_cache_add( Object_Cache_Backend::KEY_NAME, 0, Object_Cache_Backend::GROUP_NAME, 60 );
	}
	$found = false;
	$count = wp_cache_get( Object_Cache_Backend::KEY_NAME, Object_Cache_Backend::GROUP_NAME, true, $found );
	fwrite( STDOUT, json_encode( [ $found, $count ] ) . "\n" );
	if ( 'count' === $argv[3] ) {
		wp_cache_delete( Object_Cache_Backend::KEY_NAME, Object_Cache_Backend::GROUP_NAME );
	}
} else {
	$backend = new Object_Cache_Backend();
	$backend->initialize( 1, 60 );
	worker_barrier( 'ready' );
	$wp_object_cache->armed = true;
	$admitted               = $backend->inc_value();
	// Neither worker releases its increment until both admission results exist.
	worker_barrier( $admitted ? 'admitted' : 'rejected' );
	$backend->dec_value();
}
