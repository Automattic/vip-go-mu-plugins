<?php
/**
 * Structured Data Tests for posts.
 *
 * @package Parsely\Tests
 */

namespace Parsely\Tests;

/**
 * Structured Data Tests for posts.
 *
 * @see https://www.parse.ly/help/integration/jsonld
 */
final class Single_Post_Test extends TestCase {
	/**
	 * Create a single post, and test the structured data.
	 *
	 * @covers \Parsely::construct_parsely_metadata
	 * @uses \Parsely::__construct
	 * @uses \Parsely::get_author_name
	 * @uses \Parsely::get_author_names
	 * @uses \Parsely::get_bottom_level_term
	 * @uses \Parsely::get_category_name
	 * @uses \Parsely::get_clean_parsely_page_value
	 * @uses \Parsely::get_coauthor_names
	 * @uses \Parsely::get_current_url
	 * @uses \Parsely::get_first_image
	 * @uses \Parsely::get_options
	 * @uses \Parsely::get_tags
	 * @uses \Parsely::post_has_trackable_status
	 * @uses \Parsely::update_metadata_endpoint
	 * @group metadata
	 */
	public function test_single_post() {
		// Setup Parsley object.
		$parsely         = new \Parsely();
		$parsely_options = get_option( \Parsely::OPTIONS_KEY );

		// Insert a single post and set as global post.
		$post_id = self::factory()->post->create();
		$post    = get_post( $post_id );

		// Make a request to the root of the site to set the global $wp_query object.
		$this->go_to( get_permalink( $post ) );

		// Create the structured data for that post.
		$structured_data = $parsely->construct_parsely_metadata( $parsely_options, $post );

		// Check the required properties exist.
		$this->assert_data_has_required_properties( $structured_data );

		// Add further checks for this context.
	}

	public function assert_data_has_required_properties( $structured_data ) {
		$required_properties = $this->get_required_properties();

		array_walk(
			$required_properties,
			static function( $property, $index ) use ( $structured_data ) {
				self::assertArrayHasKey( $property, $structured_data, 'Data does not have required property: ' . $property );
			}
		);
	}

	private function get_required_properties() {
		return array(
			'@context',
			'@type',
			'headline',
			'url',
			'thumbnailUrl',
			'datePublished',
			'articleSection',
			'creator',
			'keywords',
		);
	}
}
