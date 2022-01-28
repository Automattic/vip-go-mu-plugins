<?php
/**
 * Structured Data Tests for the term archives.
 *
 * @package Parsely\Tests
 */

declare(strict_types=1);

namespace Parsely\Tests\Integration\StructuredData;

use Parsely\Parsely;

/**
 * Structured Data Tests for the term archives.
 *
 * @see https://www.parse.ly/help/integration/jsonld
 * @covers \Parsely\Parsely::construct_parsely_metadata
 */
final class TermArchiveTest extends NonPostTestCase {
	/**
	 * Check metadata for term archive.
	 *
	 * @covers \Parsely\Parsely::construct_parsely_metadata
	 * @uses \Parsely\Parsely::get_author_name
	 * @uses \Parsely\Parsely::get_author_names
	 * @uses \Parsely\Parsely::get_bottom_level_term
	 * @uses \Parsely\Parsely::get_category_name
	 * @uses \Parsely\Parsely::get_clean_parsely_page_value
	 * @uses \Parsely\Parsely::get_coauthor_names
	 * @uses \Parsely\Parsely::get_current_url
	 * @uses \Parsely\Parsely::get_first_image
	 * @uses \Parsely\Parsely::get_options
	 * @uses \Parsely\Parsely::get_tags
	 * @uses \Parsely\Parsely::post_has_trackable_status
	 * @uses \Parsely\Parsely::update_metadata_endpoint
	 * @group metadata
	 */
	public function test_term_archive(): void {
		// Set permalinks, as Parsely currently strips ?page_id=... from the URL property.
		// See https://github.com/Parsely/wp-parsely/issues/151.
		$this->set_permalink_structure( '/%postname%/' );

		// Setup Parsely object.
		$parsely         = new Parsely();
		$parsely_options = get_option( Parsely::OPTIONS_KEY );

		// Insert a single category term, and a Post with that category.
		$category = self::factory()->category->create( array( 'name' => 'Test Category' ) );
		self::factory()->post->create( array( 'post_category' => array( $category ) ) );

		// Make a request to that page to set the global $wp_query object.
		$cat_link = get_category_link( $category );
		$this->go_to( $cat_link );

		// Reset permalinks to default.
		$this->set_permalink_structure( '' );

		// Create the structured data for that category.
		// The category metadata doesn't use the post data, but the construction method requires it for now.
		$structured_data = $parsely->construct_parsely_metadata( $parsely_options, get_post() );

		// Check the required properties exist.
		$this->assert_data_has_required_properties( $structured_data );

		// The headline should be the category name.
		self::assertEquals( 'Test Category', $structured_data['headline'] );
		self::assertEquals( $cat_link, $structured_data['url'] );
	}
}
