<?php
/**
 * Page For Posts Metadata Builder class
 *
 * @package Parsely
 * @since 3.4.0
 */

declare(strict_types=1);

namespace Parsely\Metadata;

/**
 * Implements abstract Metadata Builder class to generate the metadata array
 * for a page for posts.
 *
 * @since 3.4.0
 */
class Page_For_Posts_Builder extends Metadata_Builder {
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
		$this->metadata['headline'] = get_the_title( get_option( 'page_for_posts', true ) );
	}
}
