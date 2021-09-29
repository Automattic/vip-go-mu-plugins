<?php
/**
 * Base unit test case
 *
 * @package Parsely\Tests
 * @license GPL-2.0-or-later
 */

namespace Parsely\Tests\Integration;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Abstract base class for all test case implementations.
 *
 * @package Parsely\Tests
 */
abstract class TestCase extends WPIntegrationTestCase {
	const DEFAULT_OPTIONS = array(
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

	/**
	 * Utility function to update Parse.ly options with a merge of default values and custom values.
	 *
	 * @param array $custom_options Associative array of option keys and values that should be saved.
	 */
	public static function set_options( $custom_options = array() ) {
		update_option( \Parsely::OPTIONS_KEY, array_merge( self::DEFAULT_OPTIONS, $custom_options ) );
	}

	/**
	 * Create a test post.
	 *
	 * @param string $post_type Optional. Post type. Default is 'post'.
	 * @return array An array of WP_Post fields.
	 */
	public function create_test_post_array( $post_type = 'post' ) {
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
	public function create_test_category( $name ) {
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
	public function create_test_user( $user_login ) {
		return $this->factory->user->create( array( 'user_login' => $user_login ) );
	}

	/**
	 * Create a test blog.
	 *
	 * @param string $domain  Site second-level domain without a .com TLD e.g. 'example' will
	 *                        result in a new subsite of 'http://example.com'.
	 * @param string $user_id User ID for the site administrator.
	 * @return int|WP_Error The site ID on success, WP_Error object on failure.
	 */
	public function create_test_blog( $domain, $user_id ) {
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
	public function create_test_taxonomy( $taxonomy_key, $term_name ) {
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
	public static function getMethod( $method_name, $class_name = 'Parsely' ) {
		$class  = new \ReflectionClass( $class_name );
		$method = $class->getMethod( $method_name );
		$method->setAccessible( true );
		return $method;
	}
}
