<?php
/**
 * Parsely REST API tests.
 *
 * @package Parsely\Tests
 */

declare(strict_types=1);

namespace Parsely\Tests\Integration;

use Parsely\Parsely;
use Parsely\Rest;


/**
 * Parsely REST API tests.
 */
final class RestTest extends TestCase {
	/**
	 * Internal variable
	 *
	 * @var Rest $rest Holds the Rest object
	 */
	private static $rest;

	/**
	 * Internal Parsely variable
	 *
	 * @var Parsely $parsely Holds the Parsely object
	 */
	private static $parsely;

	/**
	 * The setUp run before each test
	 */
	public function set_up(): void {
		parent::set_up();

		self::$parsely = new Parsely();
		self::$rest    = new Rest( self::$parsely );
	}

	/**
	 * Test whether the logic has been enqueued in the `rest_api_init` hook.
	 *
	 * @covers \Parsely\Rest::run
	 */
	public function test_register_enqueued_rest_init(): void {
		self::$rest->run();
		self::assertSame(
			10,
			has_action( 'rest_api_init', array( self::$rest, 'register_meta' ) )
		);
	}

	/**
	 * Test whether the logic has been enqueued in the `rest_api_init` hook with a filter that disables it.
	 *
	 * @covers \Parsely\Rest::run
	 */
	public function test_register_enqueued_rest_init_filter(): void {
		add_filter( 'wp_parsely_enable_rest_api_support', '__return_false' );
		self::$rest->run();
		self::assertFalse( has_action( 'rest_api_init', array( self::$rest, 'register_meta' ) ) );
	}

	/**
	 * Test that the REST fields are registered to WordPress REST API.
	 *
	 * @covers \Parsely\Rest::register_meta
	 */
	public function test_register_meta_registers_fields(): void {
		global $wp_rest_additional_fields;

		self::$rest->register_meta();

		$this->assertParselyRestFieldIsConstructedCorrectly( 'page', $wp_rest_additional_fields );

		// Cleaning up the registered fields.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
		$wp_rest_additional_fields = array();
	}

	/**
	 * Test that the REST fields are can be modified using the `wp_parsely_rest_object_types` filter.
	 *
	 * @covers \Parsely\Rest::register_meta
	 */
	public function test_register_meta_with_filter(): void {
		global $wp_rest_additional_fields;

		add_filter(
			'wp_parsely_rest_object_types',
			function() {
				return array( 'term' );
			}
		);

		self::$rest->register_meta();

		// Should only be 1, including term. Post and page should be left out by the filter.
		self::assertCount( 1, $wp_rest_additional_fields );

		$this->assertParselyRestFieldIsConstructedCorrectly( 'term', $wp_rest_additional_fields );

		// Cleaning up the registered fields.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
		$wp_rest_additional_fields = array();
	}

	/**
	 * Test that the get_rest_callback method is able to generate the `parsely` object for the REST API.
	 *
	 * @covers \Parsely\Rest::get_callback
	 */
	public function test_get_callback(): void {
		self::set_options( array( 'apikey' => 'testkey' ) );
		$post_id = self::factory()->post->create();

		$meta_object = self::$rest->get_callback( get_post( $post_id, 'ARRAY_A' ) );
		$expected    = array(
			'version'  => '1.0.0',
			'meta'     => self::$parsely->construct_parsely_metadata( self::$parsely->get_options(), get_post( $post_id ) ),
			'rendered' => self::$rest->get_rendered_meta(),
		);

		self::assertEquals( $expected, $meta_object );
	}

	/**
	 * Test that the get_rest_callback method does not generate metadata when there is no API key.
	 *
	 * @covers \Parsely\Rest::get_callback
	 */
	public function test_get_callback_no_api_key(): void {
		$post_id = self::factory()->post->create();

		$meta_object = self::$rest->get_callback( get_post( $post_id, 'ARRAY_A' ) );
		$expected    = array(
			'version'  => '1.0.0',
			'meta'     => '',
			'rendered' => '',
		);

		self::assertEquals( $expected, $meta_object );
	}

	/**
	 * Test that the get_rest_callback method is able to generate the `parsely` object for the REST API.
	 *
	 * @covers \Parsely\Rest::get_callback
	 */
	public function test_get_callback_with_filter(): void {
		add_filter( 'wp_parsely_enable_rest_rendered_support', '__return_false' );
		self::set_options( array( 'apikey' => 'testkey' ) );
		$post_id = self::factory()->post->create();

		$meta_object = self::$rest->get_callback( get_post( $post_id, 'ARRAY_A' ) );
		$expected    = array(
			'version' => '1.0.0',
			'meta'    => self::$parsely->construct_parsely_metadata( self::$parsely->get_options(), get_post( $post_id ) ),
		);

		self::assertEquals( $expected, $meta_object );
	}

	/**
	 * Test that the get_rest_callback method doesn't crash when the post does not exist.
	 *
	 * @covers \Parsely\Rest::get_callback
	 */
	public function test_get_callback_with_non_existent_post(): void {
		$meta_object = self::$rest->get_callback( array() );
		$expected    = array(
			'version'  => '1.0.0',
			'meta'     => '',
			'rendered' => '',
		);

		self::assertEquals( $expected, $meta_object );
	}

	/**
	 * Test that the rendered meta function returns the meta HTML string with json ld.
	 *
	 * @covers \Parsely\Rest::get_rendered_meta
	 */
	public function test_get_rendered_meta_json_ld(): void {
		// Set the default options prior to each test.
		TestCase::set_options();

		global $post;
		$post_id = self::factory()->post->create(
			array(
				'post_title' => 'My test_get_rendered_meta_json_ld title',
			)
		);

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$post = get_post( $post_id );
		$date = gmdate( 'Y-m-d\TH:i:s\Z', get_post_time( 'U', true, $post ) );

		$meta_string = self::$rest->get_rendered_meta();
		$expected    = '
<script type="application/ld+json">
{"@context":"http:\/\/schema.org","@type":"NewsArticle","mainEntityOfPage":{"@type":"WebPage","@id":"http:\/\/example.org\/?p=' . $post_id . '"},"headline":"My test_get_rendered_meta_json_ld title","url":"http:\/\/example.org\/?p=' . $post_id . '","thumbnailUrl":"","image":{"@type":"ImageObject","url":""},"dateCreated":"' . $date . '","datePublished":"' . $date . '","dateModified":"' . $date . '","articleSection":"Uncategorized","author":[],"creator":[],"publisher":{"@type":"Organization","name":"Test Blog","logo":""},"keywords":[]}
</script>

';
		self::assertEquals( $expected, $meta_string );
	}

	/**
	 * Test that the rendered meta function returns the meta HTML string with json ld.
	 *
	 * @covers \Parsely\Rest::get_rendered_meta
	 */
	public function test_get_rendered_repeated_metas(): void {
		// Set the default options prior to each test.
		TestCase::set_options(
			array( 'meta_type' => 'repeated_metas' )
		);

		global $post;
		$post_id = self::factory()->post->create(
			array(
				'post_title' => 'My test_get_rendered_repeated_metas title',
			)
		);

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$post = get_post( $post_id );
		$date = gmdate( 'Y-m-d\TH:i:s\Z', get_post_time( 'U', true, $post ) );

		$meta_string = self::$rest->get_rendered_meta();
		$expected    = '
<meta name="parsely-title" content="My test_get_rendered_repeated_metas title" />
<meta name="parsely-link" content="http://example.org/?p=' . $post_id . '" />
<meta name="parsely-type" content="post" />
<meta name="parsely-pub-date" content="' . $date . '" />
<meta name="parsely-section" content="Uncategorized" />

';
		self::assertEquals( $expected, $meta_string );
	}

	/**
	 * Assert that the Parsely REST field is constructed correctly.
	 * This is a helper function for the tests above.
	 *
	 * @param string $post_type                 Post type.
	 * @param array  $wp_rest_additional_fields Global variable.
	 * @return void
	 */
	private function assertParselyRestFieldIsConstructedCorrectly( string $post_type, array $wp_rest_additional_fields ): void {
		self::assertArrayHasKey( $post_type, $wp_rest_additional_fields );
		self::assertArrayHasKey( 'parsely', $wp_rest_additional_fields[ $post_type ] );
		self::assertIsArray( $wp_rest_additional_fields[ $post_type ]['parsely'] );
		self::assertNull( $wp_rest_additional_fields[ $post_type ]['parsely']['update_callback'] );
		self::assertNull( $wp_rest_additional_fields[ $post_type ]['parsely']['schema'] );
	}
}
