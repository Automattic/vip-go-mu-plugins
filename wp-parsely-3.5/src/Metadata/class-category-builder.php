<?php
/**
 * Category Page Metadata Builder class
 *
 * @package Parsely
 * @since 3.4.0
 */

declare(strict_types=1);

namespace Parsely\Metadata;

/**
 * Implements abstract Metadata Builder class to generate the metadata array
 * for a category page.
 *
 * @since 3.4.0
 */
class Category_Builder extends Metadata_Builder {
	/**
	 * Generates the metadata object by calling the build_* methods and
	 * returns the value.
	 *
	 * @since 3.4.0
	 *
	 * @return array<string, mixed>
	 */
	public function get_metadata(): array {
		$this->build_basic();
		$this->build_headline();
		$this->build_url();

		return $this->metadata;
	}

	/**
	 * Populates the `headline` field in the metadata object.
	 *
	 * @since 3.4.0
	 */
	private function build_headline(): void {
		$category                   = get_queried_object();
		$this->metadata['headline'] = $this->clean_value( $category->name );
	}
}
