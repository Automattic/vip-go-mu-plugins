<?php
/**
 * Structured Data Tests for non-posts.
 *
 * @package Parsely\Tests
 */

namespace Parsely\Tests;

/**
 * Structured Data Tests for non-posts.
 *
 * @see https://www.parse.ly/help/integration/jsonld
 * @covers \Parsely::construct_parsely_metadata
 */
final class Archive_Post_Test extends TestCase {
	public function setUp() {
		parent::setUp();

		update_option( 'show_on_front', 'posts' );
		delete_option( 'page_for_posts' );
		delete_option( 'page_on_front' );
	}

	/**
	 * Create 2 posts, set posts per page to 1, navigate to page 2 and test the structured data.
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
	public function test_home_page_for_posts_paged() {
		// Setup Parsley object.
		$parsely         = new \Parsely();
		$parsely_options = get_option( \Parsely::OPTIONS_KEY );

		// Insert 2 posts.
		$page_id = self::factory()->post->create();
		self::factory()->post->create();
		$page = get_post( $page_id );

		// Set permalinks, as Parsely currently strips ?page_id=... from the URL property.
		// See https://github.com/Parsely/wp-parsely/issues/151
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure('/%postname%/');

		// Set the homepage to show 1 post per page.
		update_option( 'posts_per_page', 1 );

		// Go to Page 2 of posts.
		$this->go_to( home_url('/page/2' ) );

		// Create the structured data for that post.
		$structured_data = $parsely->construct_parsely_metadata( $parsely_options, $page );

		// Check the required properties exist.
		$this->assert_data_has_required_properties( $structured_data );

		// The headline should be the name of the site, not the post_title of the latest post.
		self::assertEquals( 'Test Blog', $structured_data['headline'] );
		// The URL should be the current page, not the home url.
		self::assertEquals( home_url('/page/2'), $structured_data['url'] );
	}

	/**
	 * Create a single page, set as the posts page (blog archive) but not the home page, go to Page 2, and test the structured data.
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
	public function test_blog_page_for_posts_paged() {
		// Setup Parsley object.
		$parsely         = new \Parsely();
		$parsely_options = get_option( \Parsely::OPTIONS_KEY );

		// Insert a page for the blog posts.
		$page_id = self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'Page for Posts', 'post_name' => 'page-for-posts' ] );

		// Create 2 posts so that posts page has pagination
		self::factory()->post->create();
		self::factory()->post->create();
		$page    = get_post( $page_id );

		// Set permalinks, as Parsely currently strips ?page_id=... from the URL property.
		// See https://github.com/Parsely/wp-parsely/issues/151
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure('/%postname%/');

		// Set a static page to the homepage, set the newly created page to show the posts, add pagination to posts page
		update_option( 'show_on_front', 'page' );
		update_option('page_on_front', 1 );
		update_option( 'page_for_posts', $page_id );
		update_option( 'posts_per_page', 1 );

		// Make a request to the root of the site to set the global $wp_query object.
		$this->go_to( get_permalink( $page_id ) . 'page/2');

		// Create the structured data for that post.
		$structured_data = $parsely->construct_parsely_metadata( $parsely_options, $page );

		// Check the required properties exist.
		$this->assert_data_has_required_properties( $structured_data );

		// The headline should be the title of the post, not the name of the Site.
		self::assertEquals( 'Page for Posts', $structured_data['headline'] );
		self::assertEquals( get_permalink( $page_id ) . 'page/2', $structured_data['url'] );
	}

	/**
	 * Check metadata for author archive.
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
	public function test_author_archive() {
		// Set permalinks, as Parsely currently strips ?page_id=... from the URL property.
		// See https://github.com/Parsely/wp-parsely/issues/151
		$this->set_permalink_structure( '/%postname%/' );

		// Setup Parsley object.
		$parsely         = new \Parsely();
		$parsely_options = get_option( \Parsely::OPTIONS_KEY );

		// Insert a single user, and a Post assigned to them.
		$user = self::factory()->user->create( [ 'user_login' => 'parsely' ] );
		self::factory()->post->create( [ 'post_author' => $user ] );

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

	/**
	 * Check metadata for term archive.
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
	public function test_term_archive() {
		// Set permalinks, as Parsely currently strips ?page_id=... from the URL property.
		// See https://github.com/Parsely/wp-parsely/issues/151
		$this->set_permalink_structure( '/%postname%/' );

		// Setup Parsley object.
		$parsely         = new \Parsely();
		$parsely_options = get_option( \Parsely::OPTIONS_KEY );

		// Insert a single category term, and a Post with that category.
		$category = self::factory()->category->create( [ 'name' => 'Test Category' ] );
		self::factory()->post->create( [ 'post_category' => [ $category ] ] );

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
		);
	}
}
