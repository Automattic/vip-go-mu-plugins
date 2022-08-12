<?php
/**
 * Page Metadata Builder class
 *
 * @package Parsely
 * @since 3.4.0
 */

declare(strict_types=1);

namespace Parsely\Metadata;

use Parsely\Parsely;
use WP_Post;

/**
 * Implements abstract Metadata Builder class to generate the metadata array
 * for a generic page.
 *
 * @since 3.4.0
 */
class Page_Builder extends Metadata_Builder {
	/**
	 * Post object to generate the metadata for.
	 *
	 * @var WP_Post
	 */
	private $post;

	/**
	 * Constructor.
	 *
	 * @param Parsely $parsely Instance of Parsely class.
	 * @param WP_Post $post Post object to generate the metadata for.
	 */
	public function __construct( Parsely $parsely, WP_Post $post ) {
		parent::__construct( $parsely );
		$this->post = $post;
	}

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
		$this->metadata['headline'] = $this->clean_value( get_the_title( $this->post ) );
	}

	/**
	 * Populates the `url` field in the metadata object by getting the current page's URL.
	 *
	 * @since 3.4.0
	 */
	protected function build_url(): void {
		$this->metadata['url'] = $this->get_current_url( 'post' );
	}
}
