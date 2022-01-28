<?php
/**
 * Structured Data Tests for the blog page (archive).
 *
 * @package Parsely\Tests
 */

declare(strict_types=1);

namespace Parsely\Tests\Integration\StructuredData;

use Parsely\Parsely;

/**
 * Structured Data Tests for the custom taxonomy term (archive).
 *
 * @see https://www.parse.ly/help/integration/jsonld
 * @covers \Parsely\Parsely::construct_parsely_metadata
 */
class CustomTaxonomyTermArchiveTest extends NonPostTestCase {
	/**
	 * Check metadata for custom post type term archive.
	 *
	 * @covers \Parsely\Parsely::construct_parsely_metadata
	 * @uses \Parsely\Parsely::__construct
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
	public function test_metadata_is_correctly_constructed_for_custom_taxonomy_term_archive(): void {
		// Set permalinks, as Parsely currently strips ?page_id=... from the URL property.
		// See https://github.com/Parsely/wp-parsely/issues/151.
		$this->set_permalink_structure( '/%postname%/' );

		// Setup Parsely object.
		$parsely         = new Parsely();
		$parsely_options = get_option( Parsely::OPTIONS_KEY );

		// Register custom taxonomy.
		register_taxonomy( 'custom_tax', array( 'post' ) );

		// Insert a single term, and a post with the custom term.
		$term    = self::factory()->term->create(
			array(
				'taxonomy' => 'custom_tax',
				'slug'     => 'term',
				'name'     => 'Custom Taxonomy Term',
			)
		);
		$post_id = self::factory()->post->create();

		wp_set_post_terms( $post_id, $term, 'custom_tax' );

		$term_link = get_term_link( $term );

		// Flush rewrite rules after creating new taxonomy type.
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.flush_rewrite_rules_flush_rewrite_rules
		flush_rewrite_rules();

		// Go to the term archive page.
		$this->go_to( $term_link );

		// The query should be for a taxonomy archive.
		self::assertQueryTrue( 'is_archive', 'is_tax' );

		// Create the structured data for that term archive.
		// The term archive metadata doesn't use the post data, but the construction method requires it for now.
		$structured_data = $parsely->construct_parsely_metadata( $parsely_options, get_post( $post_id ) );

		// Check the required properties exist.
		$this->assert_data_has_required_properties( $structured_data );

		// The headline should be the term name.
		self::assertEquals( 'Custom Taxonomy Term', $structured_data['headline'] );
		self::assertEquals( $term_link, $structured_data['url'] );
	}
}
