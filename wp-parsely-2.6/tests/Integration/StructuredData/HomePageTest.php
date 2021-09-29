<?php
/**
 * Structured Data Tests for the home page.
 *
 * @package Parsely\Tests
 */

namespace Parsely\Tests\Integration\StructuredData;

/**
 * Structured Data Tests for the home page.
 *
 * @see https://www.parse.ly/help/integration/jsonld
 * @covers \Parsely::construct_parsely_metadata
 */
final class HomePageTest extends NonPostTestCase {
	/**
	 * Runs the routine before each test is executed.
	 */
	public function set_up() {
		parent::set_up();

		update_option( 'show_on_front', 'posts' );
		delete_option( 'page_for_posts' );
		delete_option( 'page_on_front' );
	}

	/**
	 * Create a single page, set as homepage (blog archive), and test the structured data.
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
	public function test_home_page_for_posts() {
		// Setup Parsley object.
		$parsely         = new \Parsely();
		$parsely_options = get_option( \Parsely::OPTIONS_KEY );

		// Insert a single page.
		$page_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Page for Posts',
			)
		);
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
	 * Create 2 posts, set posts per page to 1, navigate to page 2 and test the structured data.
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
	public function test_home_page_for_posts_paged() {
		// Setup Parsley object.
		$parsely         = new \Parsely();
		$parsely_options = get_option( \Parsely::OPTIONS_KEY );

		// Insert 2 posts.
		$page_id = self::factory()->post->create();
		self::factory()->post->create();
		$page = get_post( $page_id );

		// Set permalinks, as Parsely currently strips ?page_id=... from the URL property.
		// See https://github.com/Parsely/wp-parsely/issues/151.
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );

		// Set the homepage to show 1 post per page.
		update_option( 'posts_per_page', 1 );

		// Go to Page 2 of posts.
		$this->go_to( home_url( '/page/2' ) );

		// Create the structured data for that post.
		$structured_data = $parsely->construct_parsely_metadata( $parsely_options, $page );

		// Check the required properties exist.
		$this->assert_data_has_required_properties( $structured_data );

		// The headline should be the name of the site, not the post_title of the latest post.
		self::assertEquals( 'Test Blog', $structured_data['headline'] );
		// The URL should be the current page, not the home url.
		self::assertEquals( home_url( '/page/2' ), $structured_data['url'] );
	}

	/**
	 * Create a single page, set as homepage (page on front), and test the structured data.
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
	public function test_home_page_on_front() {
		// Setup Parsley object.
		$parsely         = new \Parsely();
		$parsely_options = get_option( \Parsely::OPTIONS_KEY );

		// Insert a single page.
		$page_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Home',
			)
		);
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
		// The metadata '@type' for the context should be 'WebPage' for the homepage.
		self::assertSame( 'WebPage', $structured_data['@type'] );
	}

	/**
	 * Check for the case when the show_on_front setting is Page, but no Page has been selected.
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
	public function test_home_for_misconfigured_settings() {
		// Setup Parsley object.
		$parsely         = new \Parsely();
		$parsely_options = get_option( \Parsely::OPTIONS_KEY );

		// Insert a single page.
		$page_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Home',
			)
		);
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
}
