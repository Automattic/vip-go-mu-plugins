<?php
/**
 * Structured Data Tests for posts.
 *
 * @package Parsely\Tests
 */

namespace Parsely\Tests\Integration\StructuredData;

use Parsely\Tests\Integration\TestCase;

/**
 * Structured Data Tests for posts.
 *
 * @see https://www.parse.ly/help/integration/jsonld
 */
final class SinglePostTest extends TestCase {
	/**
	 * The setUp run before each test
	 */
	public function set_up() {
		parent::set_up();

		// Set the default options prior to each test.
		TestCase::set_options();
	}

	/**
	 * Create a single post, and test the structured data.
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
		// The metadata '@type' for the context should be 'NewsArticle' for a single post page.
		self::assertSame( 'NewsArticle', $structured_data['@type'] );
	}

	/**
	 * Check the category.
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
	public function test_category_data_for_single_post() {
		// Setup Parsley object.
		$parsely         = new \Parsely();
		$parsely_options = get_option( \Parsely::OPTIONS_KEY );

		// Insert a single category term, and a Post with that category.
		$category = self::factory()->category->create( array( 'name' => 'Test Category' ) );
		$post_id  = self::factory()->post->create( array( 'post_category' => array( $category ) ) );
		$post     = get_post( $post_id );

		// Create the structured data for that post.
		$structured_data = $parsely->construct_parsely_metadata( $parsely_options, $post );

		// The category in the structured data should match the category of the post.
		self::assertSame( 'Test Category', $structured_data['articleSection'] );
	}

	/**
	 * Check that the tags assigned to a post are lowercase.
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
	 * @group settings
	 */
	public function test_tag_data_assigned_to_a_post_are_lowercase() {
		// Setup Parsley object.
		$parsely         = new \Parsely();
		$parsely_options = get_option( \Parsely::OPTIONS_KEY );

		// Create two tags with uppercase names and a single post.
		$tag1    = self::factory()->tag->create( array( 'name' => 'Sample' ) );
		$tag2    = self::factory()->tag->create( array( 'name' => 'Tag' ) );
		$post_id = self::factory()->post->create();
		$post    = get_post( $post_id );

		// Assign the Tags to the Post.
		wp_set_object_terms( $post_id, array( $tag1, $tag2 ), 'post_tag' );

		// Set the Parsely plugin to use Lowercase tags.
		$parsely_options['lowercase_tags'] = true;
		update_option( 'parsely', $parsely_options );

		// Create the structured data for that post.
		$structured_data = $parsely->construct_parsely_metadata( $parsely_options, $post );

		// The structured data should contain both tags in lowercase form.
		self::assertContains( 'sample', $structured_data['keywords'] );
		self::assertContains( 'tag', $structured_data['keywords'] );
	}

	/**
	 * Check the categories as tags.
	 *
	 * @covers \Parsely::construct_parsely_metadata
	 * @uses \Parsely::get_author_name
	 * @uses \Parsely::get_author_names
	 * @uses \Parsely::get_bottom_level_term
	 * @uses \Parsely::get_categories
	 * @uses \Parsely::get_category_name
	 * @uses \Parsely::get_clean_parsely_page_value
	 * @uses \Parsely::get_coauthor_names
	 * @uses \Parsely::get_current_url
	 * @uses \Parsely::get_custom_taxonomy_values
	 * @uses \Parsely::get_first_image
	 * @uses \Parsely::get_options
	 * @uses \Parsely::get_tags
	 * @uses \Parsely::post_has_trackable_status
	 * @uses \Parsely::update_metadata_endpoint
	 * @group metadata
	 * @group settings
	 */
	public function test_parsely_categories_as_tags_in_single_post() {
		// Setup Parsley object.
		$parsely         = new \Parsely();
		$parsely_options = get_option( \Parsely::OPTIONS_KEY );

		// Set the Categories as Tags option to true.
		$parsely_options['cats_as_tags']   = true;
		$parsely_options['lowercase_tags'] = false;
		update_option( 'parsely', $parsely_options );

		// Create 3 categories and a single post with those categories.
		$cat1    = self::factory()->category->create( array( 'name' => 'Test Category' ) );
		$cat2    = self::factory()->category->create( array( 'name' => 'Test Category 2' ) );
		$cat3    = self::factory()->category->create( array( 'name' => 'Test Category 3' ) );
		$post_id = self::factory()->post->create( array( 'post_category' => array( $cat1, $cat2, $cat3 ) ) );
		$post    = get_post( $post_id );

		// Create the structured data for that post.
		$structured_data = $parsely->construct_parsely_metadata( $parsely_options, $post );

		// The structured data should contain all three categories as keywords.
		self::assertContains( 'Test Category', $structured_data['keywords'] );
		self::assertContains( 'Test Category 2', $structured_data['keywords'] );
		self::assertContains( 'Test Category 3', $structured_data['keywords'] );
	}

	/**
	 * Test custom taxonomy terms, categories, and tags in the metadata.
	 *
	 * @covers \Parsely::construct_parsely_metadata
	 * @uses \Parsely::get_author_name
	 * @uses \Parsely::get_author_names
	 * @uses \Parsely::get_bottom_level_term
	 * @uses \Parsely::get_categories
	 * @uses \Parsely::get_category_name
	 * @uses \Parsely::get_clean_parsely_page_value
	 * @uses \Parsely::get_coauthor_names
	 * @uses \Parsely::get_current_url
	 * @uses \Parsely::get_custom_taxonomy_values
	 * @uses \Parsely::get_first_image
	 * @uses \Parsely::get_options
	 * @uses \Parsely::get_tags
	 * @uses \Parsely::post_has_trackable_status
	 * @uses \Parsely::update_metadata_endpoint
	 * @group metadata
	 * @group settings
	 */
	public function test_custom_taxonomy_as_tags_in_single_post() {
		// Setup Parsley object.
		$parsely         = new \Parsely();
		$parsely_options = get_option( \Parsely::OPTIONS_KEY );

		// Set up the options to force lowercase tags.
		$parsely_options['cats_as_tags'] = true;
		update_option( 'parsely', $parsely_options );

		// Create a custom taxonomy and add a term for it.
		register_taxonomy( 'hockey', 'post' );
		$custom_tax_tag = self::factory()->tag->create(
			array(
				'name'     => 'Gretzky',
				'taxonomy' => 'hockey',
			)
		);

		// Create a tag and a category and a single post and assign the category to the post.
		$tag     = self::factory()->tag->create( array( 'name' => 'My Tag' ) );
		$cat     = self::factory()->category->create( array( 'name' => 'My Category' ) );
		$post_id = self::factory()->post->create( array( 'post_category' => array( $cat ) ) );

		$post = get_post( $post_id );

		// Assign the custom taxonomy term and tag to the post.
		wp_set_object_terms( $post_id, array( $custom_tax_tag ), 'hockey' );
		wp_set_object_terms( $post_id, array( $tag ), 'post_tag' );

		// Create the structured data for that post.
		$structured_data = $parsely->construct_parsely_metadata( $parsely_options, $post );

		// The structured data should contain the category, the post tag, and the custom taxonomy term.
		self::assertContains( 'my category', $structured_data['keywords'] );
		self::assertContains( 'my tag', $structured_data['keywords'] );
		self::assertContains( 'gretzky', $structured_data['keywords'] );
	}

	/**
	 * Are the top level categories what we expect?
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
	 * @uses \Parsely::get_top_level_term
	 * @uses \Parsely::post_has_trackable_status
	 * @uses \Parsely::update_metadata_endpoint
	 * @group metadata
	 * @group settings
	 */
	public function test_use_top_level_cats_in_single_post() {
		// Setup Parsley object.
		$parsely         = new \Parsely();
		$parsely_options = get_option( \Parsely::OPTIONS_KEY );

		// Set Parsely to use top-level categories.
		$parsely_options['use_top_level_cats'] = true;
		update_option( 'parsely', $parsely_options );

		// Create 2 categories and a single post with those categories.
		$cat1    = self::factory()->category->create( array( 'name' => 'Parent Category' ) );
		$cat2    = self::factory()->category->create(
			array(
				'name'   => 'Child Category',
				'parent' => $cat1,
			)
		);
		$post_id = self::factory()->post->create( array( 'post_category' => array( $cat1, $cat2 ) ) );
		$post    = get_post( $post_id );

		// Create the structured data for that post.
		$structured_data = $parsely->construct_parsely_metadata( $parsely_options, $post );

		// The structured data should contain the parent category.
		self::assertSame( 'Parent Category', $structured_data['articleSection'] );

		// Set Parsely to not use top-level categories.
		$parsely_options['use_top_level_cats'] = false;
		update_option( 'parsely', $parsely_options );

		// Create the structured data for that post.
		$structured_data = $parsely->construct_parsely_metadata( $parsely_options, $post );

		// The structured data should contain the child category.
		self::assertSame( 'Child Category', $structured_data['articleSection'] );
	}

	/**
	 * Check out the custom taxonomy as section.
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
	 * @uses \Parsely::get_top_level_term
	 * @uses \Parsely::post_has_trackable_status
	 * @uses \Parsely::update_metadata_endpoint
	 * @group metadata
	 * @group settings
	 */
	public function test_custom_taxonomy_as_section_in_single_post() {
		// Setup Parsley object.
		$parsely         = new \Parsely();
		$parsely_options = get_option( \Parsely::OPTIONS_KEY );

		// Set Parsely to use 'sports' as custom taxonomy for section.
		$parsely_options['custom_taxonomy_section'] = 'sports';

		// Create a custom taxonomy, add a term and child term to it, and create a post.
		register_taxonomy( 'sports', 'post' );
		$custom_tax_tag       = self::factory()->term->create(
			array(
				'name'     => 'football',
				'taxonomy' => 'sports',
			)
		);
		$custom_tax_tag_child = self::factory()->term->create(
			array(
				'name'     => 'Premier League',
				'taxonomy' => 'sports',
				'parent'   => $custom_tax_tag,
			)
		);
		$post_id              = self::factory()->post->create();
		$post                 = get_post( $post_id );

		// Set the custom taxonomy terms to the post.
		wp_set_object_terms( $post_id, array( $custom_tax_tag, $custom_tax_tag_child ), 'sports' );

		// Make sure top-level categories are not set to be used.
		$parsely_options['use_top_level_cats'] = false;
		update_option( 'parsely', $parsely_options );

		// Create the structured data for that post.
		$structured_data = $parsely->construct_parsely_metadata( $parsely_options, $post );

		self::assertSame( 'Premier League', $structured_data['articleSection'] );

		// Now make sure top-level categories are set to be used.
		$parsely_options['use_top_level_cats'] = true;
		update_option( 'parsely', $parsely_options );

		// Create the structured data for that post.
		$structured_data = $parsely->construct_parsely_metadata( $parsely_options, $post );

		self::assertSame( 'football', $structured_data['articleSection'] );
	}

	/**
	 * Check the canonicals.
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
	 * @group settings
	 */
	public function test_http_canonicals_for_single_post() {
		// Setup Parsley object.
		$parsely         = new \Parsely();
		$parsely_options = get_option( \Parsely::OPTIONS_KEY );

		// Create a single post.
		$post_id = self::factory()->post->create();
		$post    = get_post( $post_id );

		// Set Parsely to not force https canonicals.
		$parsely_options['force_https_canonicals'] = false;
		update_option( 'parsely', $parsely_options );

		// Create the structured data for that post.
		$structured_data = $parsely->construct_parsely_metadata( $parsely_options, $post );

		// The url scheme should be 'http'.
		$url = wp_parse_url( $structured_data['url'] );
		self::assertSame( 'http', $url['scheme'] );

		// Set Parsely to force https canonicals.
		$parsely_options['force_https_canonicals'] = true;
		update_option( 'parsely', $parsely_options );

		// Create the structured data for that post.
		$structured_data = $parsely->construct_parsely_metadata( $parsely_options, $post );

		// The url scheme should be 'https'.
		$url = wp_parse_url( $structured_data['url'] );
		self::assertSame( 'https', $url['scheme'] );
	}

	/**
	 * Utility method to check metadata properties correctly set.
	 *
	 * @param array $structured_data Array of metadata to check.
	 */
	public function assert_data_has_required_properties( $structured_data ) {
		$required_properties = $this->get_required_properties();

		array_walk(
			$required_properties,
			static function( $property, $index ) use ( $structured_data ) {
				self::assertArrayHasKey( $property, $structured_data, 'Data does not have required property: ' . $property );
			}
		);
	}

	/**
	 * These are the required properties for posts.
	 *
	 * @return string[]
	 */
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
