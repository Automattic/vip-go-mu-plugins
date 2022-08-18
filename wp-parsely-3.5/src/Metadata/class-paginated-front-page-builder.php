<?php
/**
 * Paginated Front Page Metadata Builder class
 *
 * @package Parsely
 * @since 3.4.0
 */

declare(strict_types=1);

namespace Parsely\Metadata;

/**
 * Implements abstract Metadata Builder class to generate the metadata array
 * for a paginated front page.
 *
 * @since 3.4.0
 */
class Paginated_Front_Page_Builder extends Front_Page_Builder {
	/**
	 * Populates the `url` field in the metadata object by getting the current page's URL.
	 *
	 * @since 3.4.0
	 */
	protected function build_url(): void {
		$this->metadata['url'] = $this->get_current_url();
	}
}
