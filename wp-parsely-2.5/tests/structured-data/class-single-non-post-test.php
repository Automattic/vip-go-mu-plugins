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
final class Single_Non_Post_Test extends TestCase {
	public function setUp() {
		parent::setUp();

		update_option( 'show_on_front', 'posts' );
		delete_option( 'page_for_posts' );
		delete_option( 'page_on_front' );
	}

	/**
	 * Create a single page, and test the structured data.
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
	public function test_single_page() {
		// Setup Parsley object.
		$parsely         = new \Parsely();
		$parsely_options = get_option( \Parsely::OPTIONS_KEY );

		// Insert a single page.
		$page_id = self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'Single Page', 'post_name' => 'foo' ] );
		$page    = get_post( $page_id );

		// Set permalinks, as Parsely currently strips ?page_id=... from the URL property.
		// See https://github.com/Parsely/wp-parsely/issues/151
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure('/%postname%/');

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
		$wp_rewrite->set_permalink_structure('');
	}

	/**
	 * Create a single page, set as homepage (blog archive), and test the structured data.
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
	public function test_home_page_for_posts() {
		// Setup Parsley object.
		$parsely         = new \Parsely();
		$parsely_options = get_option( \Parsely::OPTIONS_KEY );

		// Insert a single page.
		$page_id = self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'Page for Posts' ] );
		$page    = get_post( $page_id );

		// Make a request to the root of the site to set the global $wp_query object.
		$this->go_to( '/' );

		// Create the structured data for that post.
		$structured_data = $parsely->construct_parsely_metadata( $parsely_options, $page );

		// Check the required properties exist.
		$this->assert_data_has_required_properties( $structured_data );

		// The headline should be the name of the site, not the post_title of the Page.
		self::assertEquals( 'Test Blog', $structured_data['headline'] );
		self::assertEquals( home_url(), $structured_data['url'] );
	}

	/**
	 * Create a single page, set as the posts page (blog archive) but not the home page, and test the structured data.
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
	public function test_blog_page_for_posts() {
		// Setup Parsley object.
		$parsely         = new \Parsely();
		$parsely_options = get_option( \Parsely::OPTIONS_KEY );

		// Insert a page for blog posts and insert another post.
		$page_id = self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'Page for Posts', 'post_name' => 'page-for-posts' ] );
		self::factory()->post->create();
		$page    = get_post( $page_id );

		// Set permalinks, as Parsely currently strips ?page_id=... from the URL property.
		// See https://github.com/Parsely/wp-parsely/issues/151
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure('/%postname%/');

		// Set a static page to the homepage, set the newly created page to show the posts
		update_option( 'show_on_front', 'page' );
		update_option('page_on_front', 1 );
		update_option( 'page_for_posts', $page_id );

		// Make a request to the root of the site to set the global $wp_query object.
		$this->go_to( get_permalink( $page_id ) );

		// Create the structured data for that post.
		$structured_data = $parsely->construct_parsely_metadata( $parsely_options, $page );

		// Check the required properties exist.
		$this->assert_data_has_required_properties( $structured_data );

		// The headline should be the title of the post, not the name of the Site.
		self::assertEquals( 'Page for Posts', $structured_data['headline'] );
		self::assertEquals( get_permalink( $page_id ), $structured_data['url'] );
	}

	/**
	 * Create a single page, set as homepage (page on front), and test the structured data.
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
	public function test_home_page_on_front() {
		// Setup Parsley object.
		$parsely         = new \Parsely();
		$parsely_options = get_option( \Parsely::OPTIONS_KEY );

		// Insert a single page.
		$page_id = self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'Home' ] );
		$page    = get_post( $page_id );

		// Set that page as the homepage Page.
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $page_id );

		// Make a request to the root of the site to set the global $wp_query object.
		$this->go_to( '/' );

		// Create the structured data for that post.
		$structured_data = $parsely->construct_parsely_metadata( $parsely_options, $page );

		// Check the required properties exist.
		$this->assert_data_has_required_properties( $structured_data );

		// The headline should be the name of the site, not the post_title of the Page.
		self::assertEquals( 'Test Blog', $structured_data['headline'] );
		self::assertEquals( home_url(), $structured_data['url'] );
	}

	/**
	 * Check for the case when the show_on_front setting is Page, but no Page has been selected.
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
	public function test_home_for_misconfigured_settings() {
		// Setup Parsley object.
		$parsely         = new \Parsely();
		$parsely_options = get_option( \Parsely::OPTIONS_KEY );

		// Insert a single page.
		$page_id = self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'Home' ] );
		$page    = get_post( $page_id );

		// Set that page as the homepage Page.
		update_option( 'show_on_front', 'page' );
		delete_option( 'page_on_front' );

		// Make a request to the root of the site to set the global $wp_query object.
		$this->go_to( '/' );

		// Create the structured data for that post.
		$structured_data = $parsely->construct_parsely_metadata( $parsely_options, $page );

		// Check the required properties exist.
		$this->assert_data_has_required_properties( $structured_data );

		// The headline should be the name of the site, not the post_title of the Page.
		self::assertEquals( 'Test Blog', $structured_data['headline'] );
		self::assertEquals( home_url(), $structured_data['url'] );
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
