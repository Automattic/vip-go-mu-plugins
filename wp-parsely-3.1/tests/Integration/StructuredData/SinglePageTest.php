<?php
/**
 * Structured Data Tests for single Pages.
 *
 * @package Parsely\Tests
 */

declare(strict_types=1);

namespace Parsely\Tests\Integration\StructuredData;

use Parsely\Parsely;

/**
 * Structured Data Tests for single Pages.
 *
 * @see https://www.parse.ly/help/integration/jsonld
 * @covers \Parsely\Parsely::construct_parsely_metadata
 */
final class SinglePageTest extends NonPostTestCase {

	/**
	 * Create a single page, and test the structured data.
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
	public function test_single_page(): void {
		// Setup Parsely object.
		$parsely         = new Parsely();
		$parsely_options = get_option( Parsely::OPTIONS_KEY );

		// Insert a single page.
		$page_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Single Page',
				'post_name'  => 'foo',
			)
		);
		$page    = get_post( $page_id );

		// Set permalinks, as Parsely currently strips ?page_id=... from the URL property.
		// See https://github.com/Parsely/wp-parsely/issues/151.
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );

		// Make a request to that page to set the global $wp_query object.
		$this->go_to( get_permalink( $page_id ) );

		// Create the structured data for that post.
		$structured_data = $parsely->construct_parsely_metadata( $parsely_options, $page );

		// Check the required properties exist.
		$this->assert_data_has_required_properties( $structured_data );

		// The headline should be the post_title of the Page.
		self::assertEquals( 'Single Page', $structured_data['headline'] );
		self::assertEquals( get_permalink( $page_id ), $structured_data['url'] );
		self::assertQueryTrue( 'is_page', 'is_singular' );

		// Reset permalinks to Plain.
		$wp_rewrite->set_permalink_structure( '' );
	}
}
