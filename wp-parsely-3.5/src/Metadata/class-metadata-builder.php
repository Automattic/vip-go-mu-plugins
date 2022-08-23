<?php
/**
 * Front Page Metadata Builder class
 *
 * @package Parsely
 * @since 3.4.0
 */

declare(strict_types=1);

namespace Parsely\Metadata;

use Parsely\Parsely;

/**
 * Abstract class that implements modular builders for Metadata.
 *
 * It should be extended in subclasses that implement a particular page view to render
 * metadata for. Specifically, the `get_metadata()` method should be implemented.
 *
 * @since 1.0.0
 * @since 3.3.0 Logic extracted from Parsely\Parsely class to separate file/class.
 */
abstract class Metadata_Builder {
	/**
	 * Instance of Parsely class.
	 *
	 * @var Parsely
	 */
	protected $parsely;

	/**
	 * Array holding the metadata keys and values.
	 *
	 * @var array<string, mixed>
	 */
	protected $metadata = array();

	/**
	 * Constructor.
	 *
	 * @param Parsely $parsely Instance of Parsely class.
	 */
	public function __construct( Parsely $parsely ) {
		$this->parsely = $parsely;
	}

	/**
	 * Generates the metadata object by calling the build_* methods and
	 * returns the value.
	 *
	 * @since 3.4.0
	 *
	 * @return array<string, mixed>
	 */
	abstract public function get_metadata(): array;

	/**
	 * Builds basic metadata present in all pages.
	 *
	 * @since 3.4.0
	 */
	protected function build_basic(): void {
		$this->metadata['@context'] = 'https://schema.org';
		$this->metadata['@type']    = 'WebPage';
	}

	/**
	 * Populates the `url` field in the metadata object by getting the current page's URL.
	 *
	 * @since 3.4.0
	 */
	protected function build_url(): void {
		$this->metadata['url'] = $this->get_current_url();
	}

	/**
	 * Sanitizes string content.
	 *
	 * @since 2.6.0
	 * @since 3.4.0 Moved to class-metadata-builder
	 *
	 * @param string|null $val The content you'd like sanitized.
	 * @return string
	 */
	protected function clean_value( ?string $val ): string {
		if ( null === $val ) {
			return '';
		}

		return trim( wp_strip_all_tags( str_replace( array( "\n", "\r" ), '', $val ) ) );
	}

	/**
	 * Gets the URL of the current PHP script.
	 *
	 * A fall-back implementation to determine permalink.
	 *
	 * @since 3.0.0 $parsely_type Default parameter changed to `non-post`.
	 * @since 3.4.0 Moved to class-metadata-builder
	 *
	 * @param string $parsely_type Optional. Parse.ly post type you're interested in, either 'post'
	 *                             or 'non-post'. Default is 'non-post'.
	 * @param int    $post_id      Optional. ID of the post you want to get the URL for. Default is
	 *                             0, which means the global `$post` is used.
	 * @return string
	 */
	protected function get_current_url( string $parsely_type = 'non-post', int $post_id = 0 ): string {
		if ( 'post' === $parsely_type ) {
			$permalink = (string) get_permalink( $post_id );

			/**
			 * Filters the permalink for a post.
			 *
			 * @since 1.14.0
			 * @since 2.5.0  Added $post_id.
			 *
			 * @param string $permalink    The permalink URL or false if post does not exist.
			 * @param string $parsely_type Parse.ly type ("post" or "non-post").
			 * @param int    $post_id      ID of the post you want to get the URL for. May be 0, so
			 *                             $permalink will be for the global $post.
			 */
			$url = apply_filters( 'wp_parsely_permalink', $permalink, $parsely_type, $post_id );
		} else {
			$request_uri = isset( $_SERVER['REQUEST_URI'] )
				? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
				: '';

			$url = home_url( $request_uri );
		}

		$options = $this->parsely->get_options();
		return $options['force_https_canonicals']
			? str_replace( 'http://', 'https://', $url )
			: str_replace( 'https://', 'http://', $url );
	}
}
