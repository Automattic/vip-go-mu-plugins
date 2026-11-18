<?php
/**
 * Shared secondary network site for multisite integration tests.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use WP_UnitTest_Factory;

/**
 * Creates one secondary site per test class (multisite only) instead of one per test.
 *
 * Blogs created in wpSetUpBeforeClass() get real tables that the test library does not clean up,
 * so the site is deleted again after the class.
 */
trait Secondary_Blog {
	private static int $secondary_blog_id = 0;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- WP_UnitTestCase hook.
		if ( is_multisite() ) {
			self::$secondary_blog_id = $factory->blog->create();
		}
	}

	public static function wpTearDownAfterClass(): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- WP_UnitTestCase hook.
		if ( self::$secondary_blog_id ) {
			wp_delete_site( self::$secondary_blog_id );
			self::$secondary_blog_id = 0;
		}
	}

	/**
	 * Skip outside multisite, otherwise switch to the shared secondary site. The test library restores the blog.
	 *
	 * @return int The secondary site's blog ID.
	 */
	private function switch_to_secondary_blog(): int {
		$this->skipWithoutMultisite();
		switch_to_blog( self::$secondary_blog_id );

		return self::$secondary_blog_id;
	}
}
