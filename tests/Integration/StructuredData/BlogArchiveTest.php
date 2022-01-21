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
 * Structured Data Tests for the blog page (archive).
 *
 * @see https://www.parse.ly/help/integration/jsonld
 * @covers \Parsely\Parsely::construct_parsely_metadata
 */
final class BlogArchiveTest extends NonPostTestCase {
	/**
	 * Create a single page, set as the posts page (blog archive) but not the home page, go to Page 2, and test the structured data.
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
	public function test_blog_page_for_posts_paged(): void {
		// Setup Parsely object.
		$parsely         = new Parsely();
		$parsely_options = get_option( Parsely::OPTIONS_KEY );

		// Insert a page for the blog posts.
		$page_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Page for Posts',
				'post_name'  => 'page-for-posts',
			)
		);

		// Create 2 posts so that posts page has pagination.
		self::factory()->post->create();
		self::factory()->post->create();
		$page = get_post( $page_id );

		// Set permalinks, as Parsely currently strips ?page_id=... from the URL property.
		// See https://github.com/Parsely/wp-parsely/issues/151.
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );

		// Set a static page to the homepage, set the newly created page to show the posts, add pagination to posts page.
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', 1 );
		update_option( 'page_for_posts', $page_id );
		update_option( 'posts_per_page', 1 );

		// Make a request to the root of the site to set the global $wp_query object.
		$this->go_to( get_permalink( $page_id ) . 'page/2' );

		// Create the structured data for that post.
		$structured_data = $parsely->construct_parsely_metadata( $parsely_options, $page );

		// Check the required properties exist.
		$this->assert_data_has_required_properties( $structured_data );

		// The headline should be the title of the post, not the name of the Site.
		self::assertEquals( 'Page for Posts', $structured_data['headline'] );
		self::assertEquals( get_permalink( $page_id ) . 'page/2', $structured_data['url'] );
	}
}
