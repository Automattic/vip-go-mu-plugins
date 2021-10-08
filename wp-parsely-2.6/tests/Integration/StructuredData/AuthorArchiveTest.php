<?php
/**
 * Structured Data Tests for author archives.
 *
 * @package Parsely\Tests
 */

namespace Parsely\Tests\Integration\StructuredData;

/**
 * Structured Data Tests for author archives.
 *
 * @see https://www.parse.ly/help/integration/jsonld
 * @covers \Parsely::construct_parsely_metadata
 */
final class AuthorArchiveTest extends NonPostTestCase {
	/**
	 * Check metadata for author archive.
	 *
	 * @covers \Parsely::construct_parsely_metadata
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
	public function test_author_archive() {
		// Set permalinks, as Parsely currently strips ?page_id=... from the URL property.
		// See https://github.com/Parsely/wp-parsely/issues/151.
		$this->set_permalink_structure( '/%postname%/' );

		// Setup Parsley object.
		$parsely         = new \Parsely();
		$parsely_options = get_option( \Parsely::OPTIONS_KEY );

		// Insert a single user, and a Post assigned to them.
		$user = self::factory()->user->create( array( 'user_login' => 'parsely' ) );
		self::factory()->post->create( array( 'post_author' => $user ) );

		// Make a request to that page to set the global $wp_query object.
		$author_posts_url = get_author_posts_url( $user );
		$this->go_to( $author_posts_url );

		// Reset permalinks to default.
		$this->set_permalink_structure( '' );

		// Create the structured data for that category.
		// The author archive metadata doesn't use the post data, but the construction method requires it for now.
		$structured_data = $parsely->construct_parsely_metadata( $parsely_options, get_post() );

		// Check the required properties exist.
		$this->assert_data_has_required_properties( $structured_data );

		// The headline should be the category name.
		self::assertEquals( 'Author - parsely', $structured_data['headline'] );
		self::assertEquals( $author_posts_url, $structured_data['url'] );
	}
}
