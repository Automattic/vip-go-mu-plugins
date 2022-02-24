<?php
/**
 * Base unit test case
 *
 * @package Parsely\Tests
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Parsely\Tests\Integration;

use Parsely\Parsely;
use WP_Error;
use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Abstract base class for all test case implementations.
 *
 * @package Parsely\Tests
 */
abstract class TestCase extends WPIntegrationTestCase {
	public const DEFAULT_OPTIONS = array(
		'apikey'                    => 'blog.parsely.com',
		'content_id_prefix'         => '',
		'use_top_level_cats'        => false,
		'cats_as_tags'              => false,
		'track_authenticated_users' => true,
		'custom_taxonomy_section'   => 'category',
		'lowercase_tags'            => true,
		'track_post_types'          => array( 'post' ),
		'track_page_types'          => array( 'page' ),
		'logo'                      => '',
	);

	public const EMPTY_DEFAULT_OPTIONS = array(
		'apikey'                      => '',
		'content_id_prefix'           => '',
		'api_secret'                  => '',
		'use_top_level_cats'          => false,
		'custom_taxonomy_section'     => 'category',
		'cats_as_tags'                => false,
		'track_authenticated_users'   => true,
		'lowercase_tags'              => true,
		'force_https_canonicals'      => false,
		'track_post_types'            => array( 'post' ),
		'track_page_types'            => array( 'page' ),
		'disable_javascript'          => false,
		'disable_amp'                 => false,
		'meta_type'                   => 'json_ld',
		'logo'                        => '',
		'metadata_secret'             => '',
		'parsely_wipe_metadata_cache' => false,
	);

	/**
	 * Utility function to update Parse.ly options with a merge of default values and custom values.
	 *
	 * @param array $custom_options Associative array of option keys and values that should be saved.
	 */
	public static function set_options( array $custom_options = array() ): void {
		update_option( Parsely::OPTIONS_KEY, array_merge( self::DEFAULT_OPTIONS, $custom_options ) );
	}

	/**
	 * Create a test post.
	 *
	 * @param string $post_type Optional. Post type. Default is 'post'.
	 *
	 * @return array An array of WP_Post fields.
	 */
	public function create_test_post_array( string $post_type = 'post' ): array {
		return array(
			'post_title'   => 'Sample Parsely Post',
			'post_author'  => 1,
			'post_content' => 'Some sample content just to have here',
			'post_status'  => 'publish',
			'post_type'    => $post_type,
		);
	}

	/**
	 * Create a test category.
	 *
	 * @param string $name Category name.
	 * @return array|WP_Error An array containing the term_id and term_taxonomy_id, WP_Error otherwise.
	 */
	public function create_test_category( string $name ) {
		return $this->factory->category->create(
			array(
				'name'                 => $name,
				'category_description' => $name,
				'category_nicename'    => 'category-' . $name,
				'taxonomy'             => 'category',
			)
		);
	}

	/**
	 * Create a test user.
	 *
	 * @param string $user_login The user's login username.
	 * @return int|WP_Error The newly created user's ID or a WP_Error object if the user could not be created.
	 */
	public function create_test_user( string $user_login ) {
		return $this->factory->user->create( array( 'user_login' => $user_login ) );
	}

	/**
	 * Create a test blog.
	 *
	 * @param string $domain  Site second-level domain without a .com TLD e.g. 'example' will
	 *                        result in a new subsite of 'http://example.com'.
	 * @param int    $user_id User ID for the site administrator.
	 * @return int|WP_Error The site ID on success, WP_Error object on failure.
	 */
	public function create_test_blog( string $domain, int $user_id ) {
		return $this->factory->blog->create(
			array(
				'domain'  => 'http://' . $domain . 'com',
				'user_id' => $user_id,
			)
		);
	}

	/**
	 * Create a test taxonomy with a single term.
	 *
	 * @param string $taxonomy_key Taxonomy key, must not exceed 32 characters.
	 * @param string $term_name    The term name to add.
	 * @return array|WP_Error An array containing the term_id and term_taxonomy_id, WP_Error otherwise.
	 */
	public function create_test_taxonomy( string $taxonomy_key, string $term_name ) {
		register_taxonomy(
			$taxonomy_key,
			'post',
			array(
				'label'        => $taxonomy_key,
				'hierarchical' => true,
			)
		);

		return $this->factory->term->create(
			array(
				'name'     => $term_name,
				'taxonomy' => $taxonomy_key,
			)
		);
	}

	/**
	 * Get a method from the Parsely class. This should be used when trying to access a private method for testing.
	 *
	 * @param string $method_name Name of the method to get.
	 * @param string $class_name  Name of the class the method is in. Can be passed as Foo::class.
	 *
	 * @return \ReflectionMethod
	 * @throws \ReflectionException The method does not exist in the class.
	 */
	public static function getMethod( string $method_name, string $class_name = Parsely::class ): \ReflectionMethod {
		$class  = new \ReflectionClass( $class_name );
		$method = $class->getMethod( $method_name );
		$method->setAccessible( true );
		return $method;
	}

	/**
	 * Override the value of a private property on a given object. This is useful when mocking the internals of class.
	 * Note that the property will no longer be private after setAccessible is called.
	 *
	 * @param string $class_name The fully qualified class name (including namespace).
	 * @param object $object The object instance on which to set the value.
	 * @param string $property_name The name of the private property to override.
	 * @param mixed  $value The value to set.
	 */
	public static function setPrivateProperty( string $class_name, $object, string $property_name, $value ): void {
		$class = new \ReflectionClass( $class_name );
		$prop  = $class->getProperty( $property_name );
		$prop->setAccessible( true );
		$prop->setValue( $object, $value );
	}

	/**
	 * Create a new post and go to it.
	 *
	 * @return int The new post's ID.
	 */
	public function go_to_new_post(): int {
		$post_data = $this->create_test_post_array();
		$post_id   = $this->factory->post->create( $post_data );
		$this->go_to( '/?p=' . $post_id );

		return $post_id;
	}
}
