<?php
/**
 * Abstract class for Structured Data Tests for non-posts.
 *
 * @package Parsely\Tests
 */

namespace Parsely\Tests\StructuredData;

use Parsely\Tests\TestCase;

/**
 * Create a base class that all Structured Data Tests for non-posts should extend.
 */
abstract class NonPostTestCase extends TestCase {
	/**
	 * Utility method to check metadata properties correctly set.
	 *
	 * @param array $structured_data Array of metadata to check.
	 */
	public function assert_data_has_required_properties( $structured_data ) {
		$required_properties = $this->get_required_properties();

		array_walk(
			$required_properties,
			static function( $property, $index ) use ( $structured_data ) {
				self::assertArrayHasKey( $property, $structured_data, 'Data does not have required property: ' . $property );
			}
		);
	}

	/**
	 * These are the required properties for non-posts.
	 *
	 * @return string[]
	 */
	private function get_required_properties() {
		return array(
			'@context',
			'@type',
			'headline',
			'url',
		);
	}
}
