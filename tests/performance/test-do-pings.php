<?php

namespace Automattic\VIP\Performance;

class Do_Pings_Test extends \WP_UnitTestCase {
	public function setUp() {
		parent::setUp();
	}

	public function tearDown() {
		parent::tearDown();
	}

	public function test__block_encloseme_metadata_filter_should_respect_update_value_if_not_encloseme() {
		$should_update = true;
		$object_id = 1;
		$meta_key = 'test';
		$meta_value = 'random value';
		$unique = true;

		$result = block_encloseme_metadata_filter( $should_update, $object_id, $meta_key, $meta_value, $unique );
		
		$this->assertTrue( $result, 'block_encloseme_metadata_filter should return true' );
		$this->assertTrue( \apply_filters( 'add_post_metadata', $should_update, $object_id, $meta_key, $meta_value, $unique ), 'add_post_metadata should return true' );

		$should_update = false;

		$result = block_encloseme_metadata_filter( $should_update, $object_id, $meta_key, $meta_value, $unique );

		$this->assertFalse( $result, 'block_encloseme_metadata_filter should return false' );
		$this->assertFalse( \apply_filters( 'add_post_metadata', $should_update, $object_id, $meta_key, $meta_value, $unique ), 'add_post_metadata should return false' );
	}

	public function test__block_encloseme_metadata_filter_should_be_false_if_encloseme() {
		$should_update = true;
		$object_id = 1;
		$meta_key = '_encloseme';
		$meta_value = 'random value';
		$unique = true;

		$result = block_encloseme_metadata_filter( $should_update, $object_id, $meta_key, $meta_value, $unique );
		
		$this->assertFalse( $result, 'block_encloseme_metadata_filter should return false since the meta key is _encloseme' );
		$this->assertFalse( \apply_filters( 'add_post_metadata', $should_update, $object_id, $meta_key, $meta_value, $unique ), 'add_post_metadata should return false since the meta key is _encloseme' );

		$should_update = false;

		$result = block_encloseme_metadata_filter( $should_update, $object_id, $meta_key, $meta_value, $unique );

		$this->assertFalse( $result, 'block_encloseme_metadata_filter should return false' );
		$this->assertFalse( \apply_filters( 'add_post_metadata', $should_update, $object_id, $meta_key, $meta_value, $unique ), 'add_post_metadata should return false' );

	}
}

