<?php

namespace Automattic\VIP\Performance;

use WP_UnitTestCase;

class Do_Pings_Test extends WP_UnitTestCase {
	public function get_data__block_encloseme_metadata_filter() {
		return [
			'other meta key keeps the earlier value' => [ 'test', true, true ],
			'_encloseme is blocked'                  => [ '_encloseme', true, false ],
		];
	}

	/**
	 * The filter only blocks `_encloseme` metas and otherwise respects the value from earlier filters.
	 *
	 * @dataProvider get_data__block_encloseme_metadata_filter
	 */
	public function test__block_encloseme_metadata_filter( $meta_key, $should_update, $expected ) {
		$object_id  = 1;
		$meta_value = 'random value';
		$unique     = true;

		$this->assertSame( $expected, block_encloseme_metadata_filter( $should_update, $object_id, $meta_key, $meta_value, $unique ), 'Unexpected block_encloseme_metadata_filter result' );
		$this->assertSame( $expected, \apply_filters( 'add_post_metadata', $should_update, $object_id, $meta_key, $meta_value, $unique ), 'Unexpected add_post_metadata result' );
	}

	public function test__add_post_meta_integration() {
		$post = $this->factory()->post->create_and_get();
		
		$object_id  = $post->ID;
		$meta_key   = 'test';
		$meta_value = 'random value';
		$unique     = true;

		// Result should be a meta ID
		$result = \add_post_meta( $object_id, $meta_key, $meta_value, $unique );

		$this->assertTrue( is_numeric( $result ), 'result of add_post_meta should be numeric' );

		$meta_key = '_encloseme';

		// Result should be false because _encloseme is the meta_key
		$result = \add_post_meta( $object_id, $meta_key, $meta_value, $unique );

		$this->assertFalse( $result, 'result of add_post_meta should be false since the meta key is _encloseme' );
	}
}
