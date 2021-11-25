<?php
/**
 * Abstract class for Structured Data Tests for non-posts.
 *
 * @package Parsely\Tests
 */

declare(strict_types=1);

namespace Parsely\Tests\Integration\StructuredData;

use Parsely\Tests\Integration\TestCase;

/**
 * Create a base class that all Structured Data Tests for non-posts should extend.
 */
abstract class NonPostTestCase extends TestCase {
	/**
	 * The setUp run before each test
	 */
	public function set_up(): void {
		parent::set_up();

		// Set the default options prior to each test.
		TestCase::set_options();
	}

	/**
	 * Utility method to check metadata properties correctly set.
	 *
	 * @param array $structured_data Array of metadata to check.
	 */
	public function assert_data_has_required_properties( $structured_data ): void {
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
	private function get_required_properties(): array {
		return array(
			'@context',
			'@type',
			'headline',
			'url',
		);
	}
}
