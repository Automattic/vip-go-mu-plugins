<?php

/**
 * @psalm-suppress UndefinedClass
*/
class Test_WP_Object_Cache extends WP_UnitTestCase {

	/**
	 * @var WP_Object_Cache
	 */
	private $object_cache;

	private $is_using_memcached_ext = false;

	public function setUp(): void {
		parent::setUp();
		$host1 = getenv( 'MEMCACHED_HOST_1' );
		$host2 = getenv( 'MEMCACHED_HOST_2' );

		$host1 = $host1 ? "{$host1}" : 'localhost:11211';
		$host2 = $host2 ? "{$host2}" : 'localhost:11212';

		$GLOBALS['memcached_servers'] = [ $host1, $host2 ];
		$GLOBALS['wp_object_cache']   = $this->object_cache = new WP_Object_Cache(); // phpcs:ignore

		if ( defined( 'AUTOMATTIC_MEMCACHED_USE_MEMCACHED_EXTENSION' ) && AUTOMATTIC_MEMCACHED_USE_MEMCACHED_EXTENSION ) {
			$this->is_using_memcached_ext = true;
		}
	}

	public function tearDown(): void {
		$this->object_cache->flush();
		$this->object_cache->close();
		parent::tearDown();
	}

	/*
	|--------------------------------------------------------------------------
	| The main methods used by the cache API.
	|--------------------------------------------------------------------------
	*/

	/**
	 * @dataProvider data_cache_inputs
	 */
	public function test_add( $value ) {
		// Add to memcached.
		self::assertTrue( $this->object_cache->add( 'key', $value ) );

		// Check local cache.
		$cache_key = $this->object_cache->key( 'key', 'default' );
		self::assertEquals( $this->object_cache->cache[ $cache_key ], [
			'value' => $value,
			'found' => true,
		] );

		// Fails to add now because it already exists in local cache.
		self::assertFalse( $this->object_cache->add( 'key', $value ) );

		// Still fails after removing from local cache because it exists in memcached.
		unset( $this->object_cache->cache[ $cache_key ] );
		self::assertFalse( $this->object_cache->add( 'key', $value ) );

		// TODO: Test unsetting from local cache after memcached failure.
	}

	/**
	 * @dataProvider data_cache_inputs
	 */
	public function test_add_for_non_persistent_groups( $value ) {
		$group = 'do-not-persist-me';

		$this->object_cache->add_non_persistent_groups( [ $group ] );

		// Add to local cache.
		self::assertTrue( $this->object_cache->add( 'key', $value, $group ) );

		// Check local cache.
		$cache_key = $this->object_cache->key( 'key', $group );
		self::assertEquals( $this->object_cache->cache[ $cache_key ], [
			'value' => $value,
			'found' => false,
		] );

		// Fails to add now because it already exists in local cache.
		self::assertFalse( $this->object_cache->add( 'key', $value, $group ) );

		// Succeeds after removing from local cache because it never existed remotely.
		unset( $this->object_cache->cache[ $cache_key ] );
		self::assertTrue( $this->object_cache->add( 'key', $value, $group ) );
	}

	public function test_add_multiple() {
		$inputs = $this->data_cache_inputs();

		$values          = [];
		$expected_first  = [];
		$expected_second = [];
		foreach ( $inputs as $key => $input_array ) {
			$values[ $key ]          = $input_array[0];
			$expected_first[ $key ]  = true;
			$expected_second[ $key ] = false;
		}

		// Add to memcached.
		self::assertEquals( $this->object_cache->add_multiple( $values ), $expected_first );

		// Check local cache.
		foreach ( $inputs as $key => $input_array ) {
			$cache_key = $this->object_cache->key( $key, 'default' );
			self::assertEquals( $this->object_cache->cache[ $cache_key ], [
				'value' => $input_array[0],
				'found' => true,
			] );
		}

		// Fails to add all but the new one now because they already exists.
		$values['something-new']          = 'test';
		$expected_second['something-new'] = true;
		self::assertEquals( $this->object_cache->add_multiple( $values ), $expected_second );
	}

	/**
	 * @dataProvider data_cache_inputs
	 */
	public function test_replace( $value, $replace_value ) {
		// Add to memcached first.
		self::assertTrue( $this->object_cache->add( 'key', $value ) );

		// Replace with new value.
		self::assertTrue( $this->object_cache->replace( 'key', $replace_value ) );

		// Check local cache.
		$cache_key = $this->object_cache->key( 'key', 'default' );
		self::assertEquals( $this->object_cache->cache[ $cache_key ], [
			'value' => $replace_value,
			'found' => true,
		] );

		// Can't replace a value that isn't set yet.
		self::assertFalse( $this->object_cache->replace( 'new_key', $replace_value ) );

		// TODO: Test unsetting from local cache after memcached failure.
	}

	/**
	 * @dataProvider data_cache_inputs
	 */
	public function test_replace_for_non_persistent_groups( $value, $replace_value ) {
		$group = 'do-not-persist-me';

		$this->object_cache->add_non_persistent_groups( [ $group ] );

		// Add to memcached first.
		self::assertTrue( $this->object_cache->add( 'key', $value, $group ) );

		// Replace with new value.
		self::assertTrue( $this->object_cache->replace( 'key', $replace_value, $group ) );

		// Check local cache.
		$cache_key = $this->object_cache->key( 'key', $group );
		self::assertEquals( $this->object_cache->cache[ $cache_key ], [
			'value' => $replace_value,
			'found' => false,
		] );

		// Can't replace a value that isn't set yet.
		self::assertFalse( $this->object_cache->replace( 'new_key', $replace_value, $group ) );

		// Never made it's way to the remote cache.
		unset( $this->object_cache->cache[ $cache_key ] );
		$this->object_cache->no_mc_groups = [];
		self::assertFalse( $this->object_cache->get( 'key', $group ) );
	}

	/**
	 * @dataProvider data_cache_inputs
	 */
	public function test_set( $value, $update_value ) {
		$cache_key = $this->object_cache->key( 'key', 'default' );

		// Set to memcached.
		self::assertTrue( $this->object_cache->set( 'key', $value ) );

		// Check local cache.
		self::assertEquals( $this->object_cache->cache[ $cache_key ], [
			'value' => $value,
			'found' => true,
		] );

		// Update with new value.
		self::assertTrue( $this->object_cache->set( 'key', $update_value ) );

		// Check local cache again.
		self::assertEquals( $this->object_cache->cache[ $cache_key ], [
			'value' => $update_value,
			'found' => true,
		] );

		// TODO: Test local cache result after a failed memcached set
	}

	/**
	 * @dataProvider data_cache_inputs
	 */
	public function test_set_for_non_persistent_groups( $value ) {
		$group = 'do-not-persist-me';

		$this->object_cache->add_non_persistent_groups( [ $group ] );

		// Set in local cache.
		self::assertTrue( $this->object_cache->set( 'key', $value, $group ) );

		// Check local cache.
		$cache_key = $this->object_cache->key( 'key', $group );
		self::assertEquals( $this->object_cache->cache[ $cache_key ], [
			'value' => $value,
			'found' => false,
		] );

		// Never made it's way to the remote cache.
		unset( $this->object_cache->cache[ $cache_key ] );
		$this->object_cache->no_mc_groups = [];
		self::assertFalse( $this->object_cache->get( 'key', $group ) );
	}

	public function test_set_multiple() {
		$inputs = $this->data_cache_inputs();

		$values   = [];
		$expected = [];
		foreach ( $inputs as $key => $input_array ) {
			$values[ $key ]   = $input_array[0];
			$expected[ $key ] = true;
		}

		// Set to memcached.
		self::assertEquals( $this->object_cache->set_multiple( $values ), $expected );

		// Check local cache.
		foreach ( $inputs as $key => $input_array ) {
			$cache_key = $this->object_cache->key( $key, 'default' );
			self::assertEquals( $this->object_cache->cache[ $cache_key ], [
				'value' => $input_array[0],
				'found' => true,
			] );
		}
	}

	/**
	 * @dataProvider data_cache_inputs
	 */
	public function test_get( $value ) {
		$cache_key = $this->object_cache->key( 'key', 'default' );

		// Not found intially.
		$found = null;
		self::assertFalse( $this->object_cache->get( 'key', 'default', false, $found ) );
		self::assertFalse( $found );

		// Local cache stored the "not found" state.
		self::assertEquals( $this->object_cache->cache[ $cache_key ], [
			'value' => false,
			'found' => false,
		] );

		// Will return from local cache if present.
		$this->object_cache->cache[ $cache_key ] = [
			'value' => $value,
			'found' => true,
		];
		$found                                   = null;
		self::assertEquals( $this->object_cache->get( 'key', 'default', false, $found ), $value );
		self::assertTrue( $found );

		// But will skip local cache if forced.
		$found = null;
		self::assertFalse( $this->object_cache->get( 'key', 'default', true, $found ) );
		self::assertFalse( $found );

		// Actually set the value remotely now.
		self::assertTrue( $this->object_cache->set( 'key', $value ) );

		$found = null;
		self::assertEquals( $this->object_cache->get( 'key', 'default', false, $found ), $value );
		self::assertTrue( $found );

		// Check that the local cache was saved.
		self::assertEquals( $this->object_cache->cache[ $cache_key ], [
			'value' => $value,
			'found' => true,
		] );
	}

	/**
	 * @dataProvider data_cache_inputs
	 */
	public function test_get_for_non_persistent_groups( $value ) {
		$group     = 'do-not-persist-me';
		$cache_key = $this->object_cache->key( 'key', $group );

		// Before we start, let's put a value in the remote cache then remove from local.
		self::assertTrue( $this->object_cache->set( 'key', $value, $group ) );
		unset( $this->object_cache->cache[ $cache_key ] );

		$this->object_cache->add_non_persistent_groups( [ $group ] );

		// Fetch from local cache.
		$found = null;
		self::assertFalse( $this->object_cache->get( 'key', $group, true, $found ) );
		self::assertFalse( $found );

		// Set in local cache and check again. $found is still false by design.
		self::assertTrue( $this->object_cache->set( 'key', $value, $group ) );
		$found = null;
		self::assertEquals( $this->object_cache->get( 'key', $group, true, $found ), $value );
		self::assertFalse( $found );
	}

	public function test_get_multiple() {
		$inputs = $this->data_cache_inputs();
		$keys   = array_keys( $inputs );

		$values          = [];
		$expected_first  = [];
		$expected_second = [];
		foreach ( $inputs as $key => $input_array ) {
			$values[ $key ]          = $input_array[0];
			$expected_first[ $key ]  = false;
			$expected_second[ $key ] = $input_array[0];
		}

		// Each is not found intially.
		self::assertEquals( $this->object_cache->get_multiple( $keys ), $expected_first );

		// Will return from local cache if present.
		foreach ( $inputs as $key => $input_array ) {
			$cache_key = $this->object_cache->key( $key, 'default' );

			$this->object_cache->cache[ $cache_key ] = [
				'value' => $input_array[0],
				'found' => true,
			];
		}
		self::assertEquals( $this->object_cache->get_multiple( $keys ), $expected_second );

		// But will skip local cache if forced.
		self::assertEquals( $this->object_cache->get_multiple( $keys, 'default', true ), $expected_first );

		// Set values in remote memcached now, but clear out local cache before fetching.
		$this->object_cache->set_multiple( $values );
		$this->object_cache->flush_runtime();
		$fetch_keys                          = array_merge( $keys, [ 'non-existant-key' ] );
		$expected_second['non-existant-key'] = false;

		self::assertEquals( $this->object_cache->get_multiple( $fetch_keys ), $expected_second );

		// Ensure local cache was saved.
		foreach ( $inputs as $key => $input_array ) {
			$cache_key = $this->object_cache->key( $key, 'default' );
			self::assertEquals( $this->object_cache->cache[ $cache_key ], [
				'value' => $input_array[0],
				'found' => true,
			] );
		}

		// As well as saved for the non-existant key.
		$non_existant_cache_key = $this->object_cache->key( 'non-existant-key', 'default' );
		self::assertEquals( $this->object_cache->cache[ $non_existant_cache_key ], [
			'value' => false,
			'found' => false,
		] );

		// Ensure we still get an array if no keys are found.
		self::assertEquals( $this->object_cache->get_multiple( [ 'non-existant-key2', 'non-existant-key3' ] ), [
			'non-existant-key2' => false,
			'non-existant-key3' => false,
		] );

		// Test super large multiGets that should be broken up into multiple batches.
		$large_mget_values = [
			'mget_1' => '1',
			'mget_500' => '500',
			'mget_999' => '999',
			'mget_1001' => '1001',
			'mget_1500' => '1500',
			'mget_1999' => '1999',
			'mget_2001' => '2001',
			'mget_2500' => '2500',
			'mget_2999' => '2999',
			'mget_3001' => '3001',
			'mget_3500' => '3500',
			'mget_3999' => '3999',
		];
		$this->object_cache->set_multiple( $large_mget_values );
		$this->object_cache->flush_runtime();

		$expected_large_mget_values = [];
		for ( $i = 0; $i < 4000; $i++ ) {
			$key = 'mget_' . $i;
			$expected_large_mget_values[ $key ] = isset( $large_mget_values[ $key ] ) ? $large_mget_values[ $key ] : false;
		}

		self::assertEquals( $this->object_cache->get_multiple( array_keys( $expected_large_mget_values ) ), $expected_large_mget_values );
	}

	public function test_get_multiple_for_non_persistent_groups() {
		$inputs = $this->data_cache_inputs();
		$keys   = array_keys( $inputs );
		$group  = 'do-not-persist-me';

		$local_values    = [];
		$remote_values   = [];
		$expected_first  = [];
		$expected_second = [];
		foreach ( $inputs as $key => $input_array ) {
			$local_values[ $key ]    = $input_array[0];
			$remote_values[ $key ]   = $input_array[1];
			$expected_first[ $key ]  = false;
			$expected_second[ $key ] = $input_array[0];
		}

		// Before we start, let's put value in the remote cache then remove from local.
		$this->object_cache->set_multiple( $remote_values, $group );
		$this->object_cache->flush_runtime();

		$this->object_cache->add_non_persistent_groups( [ $group ] );

		// Fetch from local cache, should be empty.
		self::assertEquals( $this->object_cache->get_multiple( $keys, $group ), $expected_first );

		// Set in local cache and check again.
		$this->object_cache->set_multiple( $local_values, $group );
		self::assertEquals( $this->object_cache->get_multiple( $keys, $group ), $expected_second );
	}

	public function test_get_multi() {
		$inputs = $this->data_cache_inputs();
		$keys   = array_keys( $inputs );

		$values   = [];
		$expected = [];
		foreach ( $inputs as $key => $input_array ) {
			$cache_key = $this->object_cache->key( $key, 'default' );

			$values[ $key ]         = $input_array[0];
			$expected[ $cache_key ] = $input_array[0];
		}

		$non_existant_cache_key              = $this->object_cache->key( 'non-existant-key', 'default' );
		$keys                                = array_merge( $keys, [ 'non-existant-key' ] );
		$expected[ $non_existant_cache_key ] = false;

		// Populate in memcached but flush local cache after.
		$this->object_cache->set_multiple( $values );
		$this->object_cache->flush_runtime();

		self::assertEquals( $this->object_cache->get_multi( [ 'default' => $keys ] ), $expected );
	}

	/**
	 * @dataProvider data_cache_inputs
	 */
	public function test_delete( $value ) {
		$cache_key = $this->object_cache->key( 'key', 'default' );

		// Nothing to delete yet.
		self::assertFalse( $this->object_cache->delete( 'key' ) );

		// Now it can delete.
		self::assertTrue( $this->object_cache->set( 'key', $value ) );
		self::assertTrue( $this->object_cache->delete( 'key' ) );

		// Also removed from local cache.
		self::assertFalse( isset( $this->object_cache->cache[ $cache_key ] ) );
	}

	/**
	 * @dataProvider data_cache_inputs
	 */
	public function test_delete_for_non_persistent_groups( $value ) {
		$group     = 'do-not-persist-me';
		$cache_key = $this->object_cache->key( 'key', $group );

		// Set in remote cache first, then add to non-persistent groups.
		self::assertTrue( $this->object_cache->set( 'key', $value, $group ) );
		$this->object_cache->flush_runtime();
		$this->object_cache->add_non_persistent_groups( [ $group ] );

		// Nothing to delete yet.
		self::assertFalse( $this->object_cache->delete( 'key', $group ) );

		// Now it can locally delete.
		self::assertTrue( $this->object_cache->set( 'key', $value, $group ) );
		self::assertTrue( $this->object_cache->delete( 'key', $group ) );
		self::assertFalse( isset( $this->object_cache->cache[ $cache_key ] ) );

		// But never made it's way to actually deleting from the remote cache.
		$this->object_cache->no_mc_groups = [];
		self::assertEquals( $this->object_cache->get( 'key', $group ), $value );
	}

	public function test_delete_multiple() {
		$inputs = $this->data_cache_inputs();
		$keys   = array_keys( $inputs );

		$values          = [];
		$expected_first  = [];
		$expected_second = [];
		foreach ( $inputs as $key => $input_array ) {
			$values[ $key ]          = $input_array[0];
			$expected_first[ $key ]  = false;
			$expected_second[ $key ] = true;
		}

		// Nothing to delete yet.
		self::assertEquals( $this->object_cache->delete_multiple( $keys ), $expected_first );

		// Now it can delete.
		$keys                                = array_merge( $keys, [ 'non-existant-key' ] );
		$expected_second['non-existant-key'] = false;
		$this->object_cache->set_multiple( $values );
		self::assertEquals( $this->object_cache->delete_multiple( $keys ), $expected_second );

		// Also removed from local cache.
		foreach ( $inputs as $key => $input_array ) {
			$cache_key = $this->object_cache->key( $key, 'default' );
			self::assertFalse( isset( $this->object_cache->cache[ $cache_key ] ) );
		}
	}

	public function test_delete_multiple_for_non_persistent_groups() {
		$inputs = $this->data_cache_inputs();
		$keys   = array_keys( $inputs );
		$group  = 'do-not-persist-me';

		$values          = [];
		$expected_first  = [];
		$expected_second = [];
		$expected_third  = [];
		foreach ( $inputs as $key => $input_array ) {
			$values[ $key ]          = $input_array[0];
			$expected_first[ $key ]  = false;
			$expected_second[ $key ] = true;
			$expected_third[ $key ]  = $input_array[0];
		}

		// Set in remote cache first, then add to non-persistent groups.
		$this->object_cache->set_multiple( $values, $group );
		$this->object_cache->flush_runtime();
		$this->object_cache->add_non_persistent_groups( [ $group ] );

		// Nothing to delete yet.
		self::assertEquals( $this->object_cache->delete_multiple( $keys, $group ), $expected_first );

		// Now it can delete.
		$keys                                = array_merge( $keys, [ 'non-existant-key' ] );
		$expected_second['non-existant-key'] = false;
		$this->object_cache->set_multiple( $values, $group );
		self::assertEquals( $this->object_cache->delete_multiple( $keys, $group ), $expected_second );

		// Also removed from local cache.
		foreach ( $inputs as $key => $input_array ) {
			$cache_key = $this->object_cache->key( $key, $group );
			self::assertFalse( isset( $this->object_cache->cache[ $cache_key ] ) );
		}

		// But not from remote cache.
		$this->object_cache->no_mc_groups = [];
		self::assertEquals( $this->object_cache->get_multiple( array_keys( $inputs ), $group ), $expected_third );
	}

	public function test_incr() {
		// Increments by 1 by default.
		$this->object_cache->add( 'key', 1 );
		self::assertEquals( $this->object_cache->incr( 'key' ), 2 );

		// Can increment by a specified amount
		$this->object_cache->add( 'key2', 1 );
		self::assertEquals( $this->object_cache->incr( 'key2', 5 ), 6 );

		// Returns false if key doesn't exist yet.
		self::assertFalse( $this->object_cache->incr( 'key3' ) );

		// Memcache extension throws notices for the following tests, memcached does not (despite what the docs say).
		if ( ! $this->is_using_memcached_ext ) {
			self::expectNotice();
		}

		// Fails if value is non-int.
		$this->object_cache->add( 'key4', 'non-numeric' );
		self::assertFalse( $this->object_cache->incr( 'key4' ) );

		$this->object_cache->add( 'key5', [ 'non-numeric' ] );
		self::assertFalse( $this->object_cache->incr( 'key5' ) );

		$this->object_cache->add( 'key6', 1.234 );
		self::assertFalse( $this->object_cache->incr( 'key6' ) );
	}

	public function test_incr_for_non_persistent_groups() {
		$group     = 'do-not-persist-me';
		$cache_key = $this->object_cache->key( 'key', $group );

		// Set in remote cache first, then add to non-persistent groups.
		self::assertTrue( $this->object_cache->set( 'key', 100, $group ) );
		$this->object_cache->flush_runtime();
		$this->object_cache->add_non_persistent_groups( [ $group ] );

		// Nothing to increment yet.
		self::assertFalse( $this->object_cache->incr( 'key', 1, $group ) );

		// Now it can locally increment.
		self::assertTrue( $this->object_cache->add( 'key', 0, $group ) );
		self::assertEquals( $this->object_cache->incr( 'key', 2, $group ), 2 );

		// Fails if value is non-int.
		$this->object_cache->add( 'key2', 'non-numeric', $group );
		self::assertFalse( $this->object_cache->incr( 'key2', 1, $group ) );

		$this->object_cache->add( 'key3', [ 'non-numeric' ], $group );
		self::assertFalse( $this->object_cache->incr( 'key3', 1, $group ) );

		$this->object_cache->add( 'key4', 1.234, $group );
		self::assertFalse( $this->object_cache->incr( 'key4', 1, $group ) );

		// But the changes never made their way to the remote cache.
		$this->object_cache->flush_runtime();
		$this->object_cache->no_mc_groups = [];
		self::assertEquals( $this->object_cache->get( 'key', $group ), 100 );
	}

	public function test_decr() {
		// Decrement by 1 by default.
		$this->object_cache->add( 'key', 1 );
		self::assertEquals( $this->object_cache->decr( 'key' ), 0 );

		// Can decrement by a specified amount
		$this->object_cache->add( 'key2', 5 );
		self::assertEquals( $this->object_cache->decr( 'key2', 2 ), 3 );

		// Returns false if key doesn't exist yet.
		self::assertFalse( $this->object_cache->decr( 'key3' ) );

		// Returns zero if decremented value would have been less than 0.
		$this->object_cache->add( 'key4', 2 );
		self::assertEquals( $this->object_cache->decr( 'key4', 3 ), 0 );

		// Memcache extension throws notices for the following tests, memcached does not (despite what the docs say).
		if ( ! $this->is_using_memcached_ext ) {
			self::expectNotice();
		}

		// Fails if value is non-int.
		$this->object_cache->add( 'key5', 'non-numeric' );
		self::assertFalse( $this->object_cache->decr( 'key5' ) );

		$this->object_cache->add( 'key6', [ 'non-numeric' ] );
		self::assertFalse( $this->object_cache->decr( 'key6' ) );

		$this->object_cache->add( 'key7', 1.234 );
		self::assertFalse( $this->object_cache->decr( 'key7' ) );
	}

	public function test_decr_for_non_persistent_groups() {
		$group     = 'do-not-persist-me';
		$cache_key = $this->object_cache->key( 'key', $group );

		// Set in remote cache first, then add to non-persistent groups.
		self::assertTrue( $this->object_cache->set( 'key', 100, $group ) );
		$this->object_cache->flush_runtime();
		$this->object_cache->add_non_persistent_groups( [ $group ] );

		// Nothing to decrement yet.
		self::assertFalse( $this->object_cache->decr( 'key', 1, $group ) );

		// Now it can locally decrement.
		self::assertTrue( $this->object_cache->add( 'key', 4, $group ) );
		self::assertEquals( $this->object_cache->decr( 'key', 2, $group ), 2 );

		// Returns zero if decremented value would have been less than 0.
		$this->object_cache->add( 'key2', 2 );
		self::assertEquals( $this->object_cache->decr( 'key2', 3 ), 0 );

		// Fails if value is non-int.
		$this->object_cache->add( 'key3', 'non-numeric', $group );
		self::assertFalse( $this->object_cache->decr( 'key3', 1, $group ) );

		$this->object_cache->add( 'key4', [ 'non-numeric' ], $group );
		self::assertFalse( $this->object_cache->decr( 'key4', 1, $group ) );

		$this->object_cache->add( 'key5', 1.234, $group );
		self::assertFalse( $this->object_cache->decr( 'key5', 1, $group ) );

		// But the changes never made their way to the remote cache.
		$this->object_cache->flush_runtime();
		$this->object_cache->no_mc_groups = [];
		self::assertEquals( $this->object_cache->get( 'key', $group ), 100 );
	}

	public function test_flush() {
		// Add a value to memcached (local and remote)
		self::assertTrue( $this->object_cache->add( 'key', 'data' ) );
		$cache_key = $this->object_cache->key( 'key', 'default' );
		self::assertNotEmpty( $this->object_cache->cache[ $cache_key ] );

		$initial_site_flush_number   = $this->object_cache->flush_number[ $this->object_cache->blog_prefix ];
		$initial_global_flush_number = $this->object_cache->global_flush_number;
		self::assertTrue( $this->object_cache->flush() );
		$new_site_flush_number   = $this->object_cache->flush_number[ $this->object_cache->blog_prefix ];
		$new_global_flush_number = $this->object_cache->global_flush_number;

		// Ensure the local cache was flushed.
		// Note: Local cache won't be completely empty because it stores updated flush number in local cache.
		self::assertArrayNotHasKey( $cache_key, $this->object_cache->cache );

		// Ensure it can't pull from remote cache either.
		self::assertFalse( $this->object_cache->get( 'key' ) );

		// Ensure the flush keys were rotated.
		self::assertNotEmpty( $new_site_flush_number );
		self::assertNotEmpty( $new_global_flush_number );
		self::assertNotEquals( $initial_site_flush_number, $new_site_flush_number );
		self::assertNotEquals( $initial_global_flush_number, $new_global_flush_number );
	}

	public function test_flush_runtime() {
		// Add a value to memcached (local and remote)
		self::assertTrue( $this->object_cache->add( 'key', 'data' ) );
		$cache_key = $this->object_cache->key( 'key', 'default' );
		self::assertNotEmpty( $this->object_cache->cache[ $cache_key ] );
		self::assertNotEmpty( $this->object_cache->group_ops );

		self::assertTrue( $this->object_cache->flush_runtime() );

		// Gone from local cache.
		self::assertArrayNotHasKey( $cache_key, $this->object_cache->cache );
		self::assertEquals( $this->object_cache->group_ops, [] );

		// But exists remotely still
		self::assertEquals( $this->object_cache->get( 'key' ), 'data' );
	}

	public function test_add_global_groups() {
		// Starts out with one element in array already.
		self::assertEquals( $this->object_cache->global_groups, [ $this->object_cache->global_flush_group ] );

		// Accepts a single string.
		$single_group = 'single-group';
		$this->object_cache->add_global_groups( $single_group );
		$this->assertContains( $single_group, $this->object_cache->global_groups );

		// Or an array (but removes duplicates).
		$duplicate_groups = [ $single_group, 'another-group', 'another-group' ];
		$this->object_cache->add_global_groups( $duplicate_groups );
		$this->assertContains( $single_group, $this->object_cache->global_groups );
		$this->assertContains( 'another-group', $this->object_cache->global_groups );
		$this->assertEquals( count( $this->object_cache->global_groups ), 3 );
	}

	public function test_add_non_persistent_groups() {
		// Starts out as an empty array.
		self::assertEmpty( $this->object_cache->no_mc_groups );

		// Accepts a single string.
		$single_group = 'single-group';
		$this->object_cache->add_non_persistent_groups( $single_group );
		$this->assertContains( $single_group, $this->object_cache->no_mc_groups );

		// Or an array (but removes duplicates).
		$duplicate_groups = [ $single_group, 'another-group', 'another-group' ];
		$this->object_cache->add_non_persistent_groups( $duplicate_groups );
		$this->assertContains( $single_group, $this->object_cache->no_mc_groups );
		$this->assertContains( 'another-group', $this->object_cache->no_mc_groups );
		$this->assertEquals( count( $this->object_cache->no_mc_groups ), 2 );
	}

	public function test_switch_to_blog() {
		global $table_prefix;

		$initial_blog_prefix = $this->object_cache->blog_prefix;
		$this->object_cache->switch_to_blog( 2 );

		if ( is_multisite() ) {
			self::assertEquals( $this->object_cache->blog_prefix, 2 );
		} else {
			self::assertEquals( $this->object_cache->blog_prefix, $table_prefix );
		}
	}

	public function test_close() {
		self::assertTrue( $this->object_cache->close() );

		// TODO: Further testing requires being able to be able to inject/mock the adapters.
	}

	/*
	|--------------------------------------------------------------------------
	| Various edge cases.
	|--------------------------------------------------------------------------
	*/

	public function test_large_key_lengths() {
		$large_keys = [
			str_repeat( 'a', 100 )  => 'a value',
			str_repeat( 'b', 250 )  => 'b value',
			str_repeat( 'c', 1000 ) => 'c value',
		];

		foreach ( $large_keys as $key => $_value ) {
			self::assertTrue( $this->object_cache->add( $key, 1 ) );
			self::assertTrue( $this->object_cache->replace( $key, 2 ) );
			self::assertTrue( $this->object_cache->set( $key, 3 ) );
			self::assertEquals( $this->object_cache->incr( $key ), 4 );
			self::assertEquals( $this->object_cache->decr( $key, 3 ), 1 );
			self::assertEquals( $this->object_cache->get( $key ), 1 );
			self::assertTrue( $this->object_cache->delete( $key ) );
		}

		self::assertSame( $this->object_cache->add_multiple( $large_keys ), array_map( fn() => true, $large_keys ) );
		self::assertSame( $this->object_cache->delete_multiple( array_keys( $large_keys ) ), array_map( fn() => true, $large_keys ) );
		self::assertSame( $this->object_cache->set_multiple( $large_keys ), array_map( fn() => true, $large_keys ) );
		self::assertSame( $this->object_cache->get_multiple( array_keys( $large_keys ) ), $large_keys );
	}

	/*
	|--------------------------------------------------------------------------
	| Internal methods, mostly deals with flush numbers, the pseudo-cache-flushing mechanic.
	|--------------------------------------------------------------------------
	*/

	public function test_flush_prefix() {
		// Does not flush the flush groups.
		self::assertEquals( '_:', $this->object_cache->flush_prefix( $this->object_cache->flush_group ) );
		self::assertEquals( '_:', $this->object_cache->flush_prefix( $this->object_cache->global_flush_group ) );

		// TODO: sets global vs sets blog prefix.
		// test_flush_prefix_sets_global_flush_number_for_global_groups
		// test_flush_prefix_sets_flush_number_for_non_global_groups
	}

	public function test_key() {
		// Uses "default" group by default.
		self::assertStringContainsString( 'default:foo', $this->object_cache->key( 'foo', '' ) );

		// Contains global prefix for global groups.
		$this->object_cache->add_global_groups( [ 'global-group' ] );
		$this->object_cache->global_prefix = 'global_prefix'; // Mock for non-multisite tests.
		self::assertStringContainsString( $this->object_cache->global_prefix, $this->object_cache->key( 'foo', 'global-group' ) );

		// Contains blog prefix for non-global groups.
		$this->object_cache->blog_prefix = 'blog_prefix'; // Mock for non-multisite tests.
		$this->assertStringContainsString( $this->object_cache->blog_prefix, $this->object_cache->key( 'foo', 'non-global-group' ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Testing Utils
	|--------------------------------------------------------------------------
	*/

	public function data_cache_inputs() {
		$object           = new stdClass();
		$object->property = 'test';

		// Key => [ first value, updated value ]
		return [
			'empty-string'  => [ '', 'updated' ],
			'empty-array'   => [ [], [ 'updated' ] ],
			'empty-object'  => [ new stdClass(), $object ],
			'zero'          => [ 0, 1 ],
			'one'           => [ 1, 0 ],
			'false'         => [ false, true ],
			'true'          => [ true, false ],
			'null'          => [ null, 'notnull' ],
			'basic-array'   => [ [ 'basic', 'array' ], [] ],
			'complex-array' => [
				[
					'a'           => 'multi',
					'dimensional' => [ 'array', 'example' ],
				],
				[],
			],
			'string'        => [ 'string', '' ],
			'float'         => [ 1.234, 5.678 ],
			'object'        => [ $object, new stdClass() ],
		];
	}
}
