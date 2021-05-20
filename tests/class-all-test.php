<?php
/**
 * Class SampleTest
 *
 * @package WordPress
 */

namespace Parsely\Tests;

use Parsely\Tests\TestCase as ParselyTestCase;
use PHPUnit\Framework\Error\Warning as PHPUnit_Warning;

/**
 * Catch-all class for testing.
 * TODO: Break this into multiple targeted files
 *
 * @category   Class
 * @package    All_Test
 */
class All_Test extends ParselyTestCase {

	/**
	 * Internal variables
	 *
	 * @var string $parsely Holds the Parsely object.
	 */
	protected static $parsely;

	/**
	 * The setUp run before each test
	 */
	public function setUp() {
		global $wp_scripts;

		parent::setUp();

		$wp_scripts = new \WP_Scripts();
		self::$parsely   = new \Parsely();

		// Set the default options prior to each test
		ParselyTestCase::set_options();
	}

	/**
	 * Make sure the version is semver-compliant
	 *
	 * @see https://semver.org/#is-there-a-suggested-regular-expression-regex-to-check-a-semver-string
	 * @see https://regex101.com/r/Ly7O1x/3/
	 */
	public function test_constant_version() {
		self::assertSame(
			1,
			preg_match(
				'/^(?P<major>0|[1-9]\d*)\.(?P<minor>0|[1-9]\d*)\.(?P<patch>0|[1-9]\d*)(?:-(?P<prerelease>(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?(?:\+(?P<buildmetadata>[0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$/',
				PARSELY_VERSION
			)
		);
	}

	public function test_class_version() {
		self::assertSame( PARSELY_VERSION, \Parsely::VERSION );
	}

	public function test_cache_buster() {
		self::assertSame( PARSELY_VERSION, \Parsely::get_asset_cache_buster() );
	}

	public function test_plugin_url_constant() {
		self::assertTrue( defined( 'PARSELY_PLUGIN_URL' ) && is_string( PARSELY_PLUGIN_URL ) && strlen( PARSELY_PLUGIN_URL ) > 0 );
	}

	/**
	 * Test JavaScript registrations.
	 *
	 * @covers \Parsely::register_js
	 * @group insert-js
	 */
	public function test_parsely_register_js() {
		ob_start();
		$post_array = $this->create_test_post_array();
		$post       = $this->factory->post->create( $post_array );
		$this->go_to( '/?p=' . $post );
		self::$parsely->register_js();
		$output = ob_get_clean();

		self::assertSame(
			'',
			$output,
			'Failed to confirm nothing was printed by register_js()'
		);

		self::assertTrue(
			wp_script_is( 'wp-parsely-api', 'registered' ),
			'Failed to confirm API script was registered'
		);

		self::assertFalse(
			wp_script_is( 'wp-parsely-api', 'enqueued' ),
			'Failed to confirm API script was not enqueued'
		);

		self::assertTrue(
			wp_script_is( 'wp-parsely-tracker', 'registered' ),
			'Failed to confirm API script was registered'
		);

		self::assertFalse(
			wp_script_is( 'wp-parsely-tracker', 'enqueued' ),
			'Failed to confirm API script was not enqueued'
		);
	}

	/**
	 * Test the tracker script enqueue.
	 *
	 * @covers \Parsely::load_js_tracker
	 * @group insert-js
	 */
	public function test_load_js_tracker() {
		ob_start();
		$post_array = $this->create_test_post_array();
		$post       = $this->factory->post->create( $post_array );
		$this->go_to( '/?p=' . $post );
		self::$parsely->register_js();
		echo self::$parsely->load_js_tracker();
		$intermediate_output = ob_get_contents();
		self::assertSame(
			'',
			$intermediate_output,
			'Failed to confirm scripts were not printed by load_js_tracker()'
		);

		self::assertTrue(
			wp_script_is( 'wp-parsely-tracker', 'enqueued' ),
			'Failed to confirm tracker script was enqueued'
		);

		wp_print_scripts();
		$output = ob_get_clean();

		self::assertSame(
			"<script data-cfasync=\"false\" type='text/javascript' data-parsely-site=\"blog.parsely.com\" src='https://cdn.parsely.com/keys/blog.parsely.com/p.js?ver=" . PARSELY_VERSION . "' id=\"parsely-cfg\"></script>\n",
			$output,
			'Failed to confirm script tag was printed correctly'
		);
	}

	/**
	 * Test the API init script enqueue.
	 *
	 * @covers \Parsely::load_js_api
	 * @group insert-js
	 */
	public function test_load_js_api_no_secret() {
		ob_start();
		$post_array = $this->create_test_post_array();
		$post       = $this->factory->post->create( $post_array );
		$this->go_to( '/?p=' . $post );
		self::$parsely->register_js();
		echo self::$parsely->load_js_api();
		$intermediate_output = ob_get_contents();
		self::assertSame(
			'',
			$intermediate_output,
			'Failed to confirm scripts were not printed by load_js_api()'
		);

		self::assertFalse(
			wp_script_is( 'wp-parsely-api', 'enqueued' ),
			'Failed to confirm api script was not enqueued when an API secret is not set'
		);

		wp_print_scripts();
		$output = ob_get_clean();

		self::assertSame(
			'',
			$output,
			'Failed to confirm script was not printed'
		);
	}

	/**
	 * Test the API init script enqueue.
	 *
	 * @covers \Parsely::load_js_api
	 * @group insert-js
	 */
	public function test_load_js_api_with_secret() {
		ob_start();
		$post_array = $this->create_test_post_array();
		$post       = $this->factory->post->create( $post_array );
		$this->go_to( '/?p=' . $post );
		self::$parsely->register_js();

		self::set_options( array( 'api_secret' => 'hunter2' ) );

		echo self::$parsely->load_js_api();
		$intermediate_output = ob_get_contents();
		self::assertSame(
			'',
			$intermediate_output,
			'Failed to confirm scripts were not printed by load_js_api()'
		);

		self::assertTrue(
			wp_script_is( 'wp-parsely-api', 'enqueued' ),
			'Failed to confirm api script was enqueued when an API secret is set'
		);

		wp_print_scripts();
		$output = ob_get_clean();


		self::assertContains(
			"<script type='text/javascript' id='wp-parsely-api-js-extra'>
/* <![CDATA[ */
var wpParsely = {\"apikey\":\"blog.parsely.com\"};
/* ]]> */
</script>",
			$output,
			'Failed to confirm "localized" data were embedded'
		);

		self::assertContains(
			"<script data-cfasync=\"false\" type='text/javascript' src='" . esc_url( PARSELY_PLUGIN_URL ) . "build/init-api.js?ver=" . PARSELY_VERSION . "' id='wp-parsely-api-js'></script>",
			$output,
			'Failed to confirm script tag was printed correctly'
		);
	}

	/**
	 * Check the context `@type` field for a Post and the Homepage.
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
	public function test_parsely_metadata_context_output() {
		// Setup Parsley object.
		$parsely         = new \Parsely();
		$parsely_options = get_option( \Parsely::OPTIONS_KEY );

		// Create a single post
		$post_id = self::factory()->post->create(
			array(
				'post_type'  => 'post',
				'post_title' => 'Home',
			)
		);
		$post    = get_post( $post_id );

		// Go to the homepage
		$this->go_to( '/' );
		$structured_data = $parsely->construct_parsely_metadata( $parsely_options, $post );

		// The metadata '@type' for the context should be 'WebPage' for the homepage.
		self::assertSame( 'WebPage', $structured_data['@type'] );

		// Go to a single post page
		$this->go_to( '/?p=' . $post_id );

		// Create the structured data for that post.
		$structured_data = $parsely->construct_parsely_metadata( $parsely_options, $post );

		// The metadata '@type' for the context should be 'NewsArticle' for a single post page.
		self::assertSame( 'NewsArticle', $structured_data['@type'] );
	}

	/**
	 *  Check the category
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
	public function test_parsely_categories() {
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
	 * Check that the tags are lowercase
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
	 * @group settings
	 */
	public function test_parsely_tags_lowercase() {
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
	 * @uses \Parsely::__construct
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
	public function test_parsely_categories_as_tags() {
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
	 * @uses \Parsely::__construct
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
	public function test_custom_taxonomy_tags() {
		// Setup Parsley object.
		$parsely         = new \Parsely();
		$parsely_options = get_option( \Parsely::OPTIONS_KEY );

		// Set up the options to force lowercase tags.
		$parsely_options['cats_as_tags'] = true;
		update_option( 'parsely', $parsely_options );

		// Create a custom taxonomy and add a tag for it.
		register_taxonomy( 'hockey', 'post' );
		$custom_tax_tag = self::factory()->tag->create(
			array(
				'name'     => 'Gretzky',
				'taxonomy' => 'hockey',
			)
		);

		// Create a tag and a category and a signle post and assign the category to the post.
		$tag     = self::factory()->tag->create( array( 'name' => 'Tag' ) );
		$cat     = self::factory()->category->create( array( 'name' => 'Category' ) );
		$post_id = self::factory()->post->create( array( 'post_category' => array( $cat ) ) );

		$post = get_post( $post_id );

		// Assign the tag and custom taxonomy to the post.
		wp_set_object_terms( $post_id, array( $custom_tax_tag ), 'hockey' );
		wp_set_object_terms( $post_id, array( $tag ), 'post_tag' );

		// Create the structured data for that post.
		$structured_data = $parsely->construct_parsely_metadata( $parsely_options, $post );

		// The structrued data should contain the category, the post tag, and the custom taxonomy term.
		self::assertContains( 'category', $structured_data['keywords'] );
		self::assertContains( 'tag', $structured_data['keywords'] );
		self::assertContains( 'gretzky', $structured_data['keywords'] );
	}


	/**
	 * Are the top level categories what we expect?
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
	 * @uses \Parsely::get_top_level_term
	 * @uses \Parsely::post_has_trackable_status
	 * @uses \Parsely::update_metadata_endpoint
	 * @group metadata
	 * @group settings
	 */
	public function test_use_top_level_cats() {
		// Setup Parsley object.
		$parsely         = new \Parsely();
		$parsely_options = get_option( \Parsely::OPTIONS_KEY );

		// Set Parsely to use top-level categories.
		$parsely_options['use_top_level_cats'] = true;
		update_option( 'parsely', $parsely_options );

		// Create 3 categories and a single post with those categories.
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

		// The structrued data should contain the parent category.
		self::assertSame( 'Parent Category', $structured_data['articleSection'] );
	}

	/**
	 * Check out the custom taxonomy as section.
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
	 * @uses \Parsely::get_top_level_term
	 * @uses \Parsely::post_has_trackable_status
	 * @uses \Parsely::update_metadata_endpoint
	 * @group metadata
	 * @group settings
	 */
	public function test_custom_taxonomy_as_section() {
		// Setup Parsley object.
		$parsely         = new \Parsely();
		$parsely_options = get_option( \Parsely::OPTIONS_KEY );

		// Set Parsely to use 'sports' as custom taxonomy for section.
		$parsely_options['custom_taxonomy_section'] = 'sports';

		// Make sure top-level categories are not set to be used.
		$parsely_options['use_top_level_cats'] = false;
		update_option( 'parsely', $parsely_options );

		// Create a custom taxonomy, add a term and child term to it, and add them to a post.
		register_taxonomy( 'sports', 'post' );
		$custom_tax_tag       = self::factory()->term->create(
			array(
				'name'     => 'football',
				'taxonomy' => 'sports',
			)
		);
		$custom_tax_tag_child = self::factory()->term->create(
			array(
				'name'     => 'premiere league',
				'taxonomy' => 'sports',
				'parent'   => $custom_tax_tag,
			)
		);
		$post_id              = self::factory()->post->create();
		$post                 = get_post( $post_id );

		// Set the custom taxonomy terms to the post.
		wp_set_object_terms( $post_id, array( $custom_tax_tag, $custom_tax_tag_child ), 'sports' );

		// Create the structured data for that post.
		$structured_data = $parsely->construct_parsely_metadata( $parsely_options, $post );
		self::assertSame( 'premiere league', $structured_data['articleSection'] );
	}

	/**
	 * Check out the top level taxonomy as a section.
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
	 * @uses \Parsely::get_top_level_term
	 * @uses \Parsely::post_has_trackable_status
	 * @uses \Parsely::update_metadata_endpoint
	 * @group metadata
	 * @group settings
	 */
	public function test_top_level_taxonomy_as_section() {
		// Setup Parsley object.
		$parsely         = new \Parsely();
		$parsely_options = get_option( \Parsely::OPTIONS_KEY );

		// Set Parsely to use 'sports' as custom taxonomy for section.
		$parsely_options['custom_taxonomy_section'] = 'sports';

		// Make sure top-level categories are not set to be used.
		$parsely_options['use_top_level_cats'] = true;
		update_option( 'parsely', $parsely_options );

		// Create a custom taxonomy, add a term and child term to it, and add them to a post.
		register_taxonomy( 'sports', 'post' );
		$custom_tax_tag       = self::factory()->term->create(
			array(
				'name'     => 'football',
				'taxonomy' => 'sports',
			)
		);
		$custom_tax_tag_child = self::factory()->term->create(
			array(
				'name'     => 'premiere league',
				'taxonomy' => 'sports',
				'parent'   => $custom_tax_tag,
			)
		);
		$post_id              = self::factory()->post->create();
		$post                 = get_post( $post_id );

		// Set the custom taxonomy terms to the post.
		wp_set_object_terms( $post_id, array( $custom_tax_tag, $custom_tax_tag_child ), 'sports' );

		// Create the structured data for that post.
		$structured_data = $parsely->construct_parsely_metadata( $parsely_options, $post );
		self::assertSame( 'football', $structured_data['articleSection'] );
	}

	/**
	 * Check the canonicals.
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
	 * @group settings
	 */
	public function test_http_canonicals() {
		// Setup Parsley object.
		$parsely         = new \Parsely();
		$parsely_options = get_option( \Parsely::OPTIONS_KEY );

		// Set Parsely to not force https canonicals.
		$parsely_options['force_https_canonicals'] = false;
		update_option( 'parsely', $parsely_options );

		// Create a single post.
		$post_id = self::factory()->post->create();
		$post    = get_post( $post_id );

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
	 * Check the facebook instant articles integration.
	 *
	 * @covers \Parsely::insert_parsely_tracking_fbia
	 * @uses \Parsely::__construct
	 * @uses \Parsely::get_options()
	 * @group fbia
	 */
	public function test_fbia_integration() {
		$options = get_option( 'parsely' );
		$output  = self::$parsely->insert_parsely_tracking_fbia( $registry );
		self::assertTrue( strpos( $output, 'facebook.com/instantarticles' ) > 0 );
		self::assertTrue( strpos( $output, 'blog.parsely.com' ) > 0 );
	}


	/**
	 * Check the AMP integration.
	 *
	 * @covers \Parsely::parsely_add_amp_actions
	 * @covers \Parsely::parsely_add_amp_analytics
	 * @uses \Parsely::__construct
	 * @uses \Parsely::get_options()
	 * @group amp
	 * @group settings
	 */
	public function test_amp_integration() {
		$options   = get_option( 'parsely' );
		$analytics = array();
		$filter    = self::$parsely->parsely_add_amp_actions();
		$output    = self::$parsely->parsely_add_amp_analytics( $analytics );
		self::assertEmpty( $filter );
		self::assertSame( 'parsely', $output['parsely']['type'] );
		self::assertSame( 'blog.parsely.com', $output['parsely']['config_data']['vars']['apikey'] );
		$options['disable_amp'] = true;
		update_option( 'parsely', $options );
		$filter = self::$parsely->parsely_add_amp_actions();
		self::assertSame( '', $filter );
	}

	/**
	 * Check out page filtering.
	 *
	 * @covers \Parsely::construct_parsely_metadata
	 * @expectedDeprecated after_set_parsely_page
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
	 * @group filters
	 */
	public function test_parsely_page_filter() {
		// Setup Parsley object.
		$parsely         = new \Parsely();
		$parsely_options = get_option( \Parsely::OPTIONS_KEY );

		// Create a single post.
		$post_id = $this->factory->post->create();
		$post    = get_post( $post_id );

		// Apply page filtering.
		$headline = 'Completely New And Original Filtered Headline';
		add_filter(
			'after_set_parsely_page',
			function( $args ) use ( $headline ) {
				$args['headline'] = $headline;

				return $args;
			},
			10,
			3
		);

		$thumbnail_url = 'http://example.com/image.png';
		add_filter(
			'wp_parsely_metadata',
			function( $args ) use ( $thumbnail_url ) {
				$args['thumbnailUrl'] = $thumbnail_url;
				return $args;
			},
			10,
			3
		);

		// Create the structured data for that post.
		$structured_data = $parsely->construct_parsely_metadata( $parsely_options, $post );

		// The structured data should contain the headline from the deprecated filter.
		self::assertSame( $structured_data['headline'], $headline );

		// The structured data should contain the thumbnailUrl from the filter.
		self::assertSame( $structured_data['thumbnailUrl'], $thumbnail_url );
	}

	/**
	 * Make sure users can log in.
	 *
	 * @covers \Parsely::load_js_tracker
	 * @group insert-js
	 * @group settings
	 */
	public function test_user_logged_in() {
		ParselyTestCase::set_options( array( 'track_authenticated_users' => false ) );
		$new_user = $this->create_test_user( 'bill_brasky' );
		wp_set_current_user( $new_user );

		ob_start();
		echo self::$parsely->load_js_tracker();

		$intermediate_output = ob_get_contents();
		self::assertSame(
			'',
			$intermediate_output,
			'Failed to confirm scripts were not printed by load_js_tracker()'
		);

		self::assertFalse(
			wp_script_is( 'wp-parsely-api', 'registered' ),
			'Failed to confirm API script was not registered'
		);

		self::assertFalse(
			wp_script_is( 'wp-parsely-api', 'enqueued' ),
			'Failed to confirm API script was not enqueued'
		);

		self::assertFalse(
			wp_script_is( 'wp-parsely-tracker', 'registered' ),
			'Failed to confirm tracker script was not registered'
		);

		self::assertFalse(
			wp_script_is( 'wp-parsely-tracker', 'enqueued' ),
			'Failed to confirm tracker script was not enqueued'
		);

		wp_print_scripts();
		$output = ob_get_clean();

		self::assertSame(
			'',
			$output,
			'Failed to confirm script tags were not printed'
		);
	}

	/**
	 * Make sure users can log in to more than one site.
	 *
	 * @covers \Parsely::load_js_tracker
	 * @group insert-js
	 * @group settings
	 */
	public function test_user_logged_in_multisite() {
		if ( ! is_multisite() ) {
			self::markTestSkipped( "this test can't run without multisite" );
		}

		$new_user    = $this->create_test_user( 'optimus_prime' );
		$second_user = $this->create_test_user( 'megatron' );
		$first_blog  = $this->create_test_blog( 'autobots', $new_user );
		$second_blog = $this->create_test_blog( 'decepticons', $second_user );

		wp_set_current_user( $new_user );
		switch_to_blog( $first_blog );

		// These custom options will be used for both blog_ids.
		$custom_options = array(
			'track_authenticated_users' => false,
			'apikey' => 'blog.parsely.com',
		);
		ParselyTestCase::set_options( $custom_options );

		$post_array = $this->create_test_post_array();
		$post       = $this->factory->post->create( $post_array );
		$this->go_to( '/?p=' . $post );

		self::assertEquals( get_current_blog_id(), $first_blog );
		self::assertTrue( is_user_member_of_blog( $new_user, $first_blog ) );
		self::assertFalse( is_user_member_of_blog( $new_user, $second_blog ) );

		ob_start();
		self::$parsely->register_js();
		echo self::$parsely->load_js_tracker();

		$intermediate_output = ob_get_contents();
		self::assertSame(
			'',
			$intermediate_output,
			'Failed to confirm scripts were not printed by load_js_tracker()'
		);

		self::assertFalse(
			wp_script_is( 'wp-parsely-tracker', 'enqueued' ),
			'Failed to confirm tracker script was not enqueued'
		);

		wp_print_scripts();
		$output = ob_get_clean();

		self::assertSame(
			'',
			$output,
			'Failed to confirm script tags were not printed'
		);

		switch_to_blog( $second_blog );
		ParselyTestCase::set_options( $custom_options );

		self::assertEquals( get_current_blog_id(), $second_blog );
		self::assertFalse( is_user_member_of_blog( $new_user, get_current_blog_id() ) );

		ob_start();
		self::$parsely->register_js();
		echo self::$parsely->load_js_tracker();

		$intermediate_output = ob_get_contents();
		self::assertSame(
			'',
			$intermediate_output,
			'Failed to confirm scripts were not printed by load_js_tracker()'
		);

		self::assertTrue(
			wp_script_is( 'wp-parsely-tracker', 'enqueued' ),
			'Failed to confirm tracker script was enqueued'
		);

		wp_print_scripts();
		$output = ob_get_clean();

		self::assertSame(
			"<script data-cfasync=\"false\" type='text/javascript' data-parsely-site=\"blog.parsely.com\" src='https://cdn.parsely.com/keys/blog.parsely.com/p.js?ver=" . PARSELY_VERSION . "' id=\"parsely-cfg\"></script>\n",
			$output,
			'Failed to confirm script tags were printed correctly'
		);
	}

	/**
	 * Data provider for test_get_current_url
	 *
	 * @return array[]
	 */
	public function data_for_test_get_current_url() {
		return array(
			// Start cases with 'force_https_canonicals' = true
			array(
				true,
				'http://example.com',
				'https://example.com',
			),
			array(
				true,
				'https://example.com',
				'https://example.com',
			),
			array(
				true,
				'http://example.com:1234',
				'https://example.com:1234',
			),
			array(
				true,
				'https://example.com:1234',
				'https://example.com:1234',
			),
			array(
				true,
				'http://example.com:1234/foo/bar',
				'https://example.com:1234/foo/bar',
			),
			array(
				true,
				'https://example.com:1234/foo/bar',
				'https://example.com:1234/foo/bar',
			),
			// Start cases with 'force_https_canonicals' = false
			array(
				false,
				'http://example.com',
				'http://example.com',
			),
			array(
				false,
				'https://example.com',
				'http://example.com',
			),
			array(
				false,
				'http://example.com:1234',
				'http://example.com:1234',
			),
			array(
				false,
				'https://example.com:1234',
				'http://example.com:1234',
			),
			array(
				false,
				'http://example.com:1234/foo/bar',
				'http://example.com:1234/foo/bar',
			),
			array(
				false,
				'https://example.com:1234/foo/bar',
				'http://example.com:1234/foo/bar',
			),
		);
	}

	/**
	 * Test the get_current_url() method.
	 *
	 * @dataProvider data_for_test_get_current_url
	 * @covers \Parsely::get_current_url
	 * @uses \Parsely::__construct()
	 * @uses \Parsely::get_options()
	 * @uses \Parsely::update_metadata_endpoint()
	 * @group get-current-url
	 */
	public function test_get_current_url( $force_https, $url, $expected ) {
		$options                           = get_option( \Parsely::OPTIONS_KEY );
		$options['force_https_canonicals'] = $force_https;
		update_option( \Parsely::OPTIONS_KEY, $options );

		update_option( 'home', $url );

		// Test homepage
		$this->go_to( '/' );
		$res = self::$parsely->get_current_url();
		self::assertStringStartsWith( $expected, $res );

		// Test a specific post
		$post_array = $this->create_test_post_array();
		$post_id    = $this->factory->post->create( $post_array );
		$this->go_to( '/?p=' . $post_id );
		$res = self::$parsely->get_current_url( 'post', $post_id );
		self::assertStringStartsWith( $expected, $res );

		// Test a random URL
		$this->go_to( '/random-url' );
		$res = self::$parsely->get_current_url();
		self::assertStringStartsWith( $expected, $res );
	}

	/**
	 * Test the wp_parsely_load_js_tracker filter
	 * When it returns false, the tracking script should not be enqueued.
	 *
	 * @covers \Parsely::load_js_tracker
	 */
	public function test_load_js_tracker_filter() {
		add_filter( 'wp_parsely_load_js_tracker', '__return_false' );

		ob_start();
		$post_array = $this->create_test_post_array();
		$post = $this->factory->post->create( $post_array );
		$this->go_to( '/?p=' . $post );
		echo self::$parsely->load_js_tracker();
		$intermediate_output = ob_get_contents();

		self::assertSame(
			'',
			$intermediate_output,
			'Failed to confirm scripts were not printed by load_js_tracker()'
		);

		wp_print_scripts();

		$output = ob_get_clean();
		self::assertSame(
			'',
			$output,
			'Failed to confirm filter prevented enqueued scripts'
		);
	}

	/**
	 * Test the parsely_filter_insert_javascript filter
	 * When it returns false, the tracking script should not be enqueued.
	 *
	 * @deprecated deprecated since 2.5.0. This test can be removed when the filter is removed.
	 *
	 * @expectedDeprecated parsely_filter_insert_javascript
	 */
	public function test_deprecated_insert_javascript_filter() {
		add_filter( 'parsely_filter_insert_javascript', '__return_false' );

		ob_start();
		$post_array = $this->create_test_post_array();
		$post = $this->factory->post->create( $post_array );
		$this->go_to( '/?p=' . $post );
		echo self::$parsely->load_js_tracker();
		$intermediate_output = ob_get_contents();

		self::assertSame(
			'',
			$intermediate_output,
			'Failed to confirm scripts were not printed by load_js_tracker()'
		);

		wp_print_scripts();

		$output = ob_get_clean();
		self::assertSame(
			'',
			$output,
			'Failed to confirm filter prevented enqueued scripts'
		);
	}

	/*
	 * Test the wp_parsely_post_type filter
	 *
	 * @uses \Parsely::get_options()
	 * @uses \Parsely::construct_parsely_metadata()
	 */
	public function test_filter_wp_parsely_post_type() {
		$options = get_option( \Parsely::OPTIONS_KEY );

		$post_array = $this->create_test_post_array();
		$post_id    = $this->factory->post->create( $post_array );
		$post_obj   = get_post( $post_id );
		$this->go_to( '/?p=' . $post_id );

		// Try to change the post type to a supported value - BlogPosting.
		add_filter(
			'wp_parsely_post_type',
			function() {
				return 'BlogPosting';
			}
		);

		$metadata = self::$parsely->construct_parsely_metadata( $options, $post_obj );
		self::assertSame( 'BlogPosting', $metadata['@type'] );

		// Do not run the following assertion for PHP 5.6.
		if ( version_compare( PHP_VERSION, '7.0.0', '<' ) ) {
			return;
		}

		// Try to change the post type to a non-supported value - Not_Supported.
		add_filter(
			'wp_parsely_post_type',
			function() {
				return 'Not_Supported_Type';
			}
		);

		/**
		 * Ideally we use two methods expectWarning and expectWarningMessageMatches to test this error
		 * But they're not available until PHPUnit 8.4 while here we're still running PHPUnit 7.x
		 *
		 * @see 7.5 https://phpunit.readthedocs.io/en/7.5/writing-tests-for-phpunit.html#testing-php-errors
		 * @see 8.4 https://phpunit.readthedocs.io/en/8.4/writing-tests-for-phpunit.html#testing-php-errors-warnings-and-notices
		 */
		self::expectException( PHPUnit_Warning::class );
		self::$parsely->construct_parsely_metadata( $options, $post_obj );
	}
}
