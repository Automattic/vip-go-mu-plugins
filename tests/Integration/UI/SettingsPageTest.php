<?php
/**
 * Settings page tests.
 *
 * @package Parsely
 */

declare(strict_types=1);

namespace Parsely\Tests\Integration\UI;

use Parsely\Parsely;
use Parsely\Tests\Integration\TestCase;
use Parsely\UI\Settings_Page;

/**
 * Settings page tests.
 *
 * @since 3.1.0
 */
final class SettingsPageTest extends TestCase {
	/**
	 * Internal variable.
	 *
	 * @var Settings_Page $settings_page Holds the Settings_Page object.
	 */
	private static $settings_page;
	/**
	 * Internal variable.
	 *
	 * @var Parsely $parsely Holds the Parsely object.
	 */
	private static $parsely;

	/**
	 * The setup run before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		self::$parsely       = new Parsely();
		self::$settings_page = new Settings_Page( self::$parsely );
	}

	/**
	 * Verify that tracking settings get saved.
	 *
	 * @since 3.2.0
	 *
	 * @covers \Parsely\UI\Settings_Page::validate_options
	 * @group ui
	 */
	public function test_save_tracking_settings(): void {
		$options = self::$parsely->get_options();

		$options['track_post_types_as'] = array(
			'post'       => 'post',
			'page'       => 'page',
			'attachment' => 'post',
		);

		$actual = self::$settings_page->validate_options( $options );
		self::assertSame( array( 'post', 'attachment' ), $actual['track_post_types'] );
		self::assertSame( array( 'page' ), $actual['track_page_types'] );
	}

	/**
	 * Verify that non-existent post types cannot be saved into the database for tracking.
	 *
	 * @since 3.2.0
	 *
	 * @covers \Parsely\UI\Settings_Page::validate_options
	 * @group ui
	 */
	public function test_saving_tracking_settings_for_non_existent_post_type_should_fail(): void {
		$expected = self::$parsely->get_options();
		$options  = self::$parsely->get_options();

		// Inject non-existent post type.
		$options['track_post_types_as'] = array(
			'page'                   => 'page',
			'post'                   => 'post',
			'non_existent_post_type' => 'post',
		);

		$actual = self::$settings_page->validate_options( $options );
		self::assertSame( $expected, $actual );
	}

	/**
	 * Verify that trying to save tracking settings with an unset value fails.
	 *
	 * @since 3.2.0
	 *
	 * @covers \Parsely\UI\Settings_Page::validate_options
	 * @group ui
	 */
	public function test_trying_to_save_unset_tracking_settings_should_fail(): void {
		$expected = self::$parsely->get_options();
		$options  = self::$parsely->get_options();

		unset( $options['track_post_types_as'] );
		$actual = self::$settings_page->validate_options( $options );
		self::assertSame( $expected, $actual );
	}

	/**
	 * Verify that trying to save tracking settings with an empty array value fails.
	 *
	 * @since 3.2.0
	 *
	 * @covers \Parsely\UI\Settings_Page::validate_options
	 * @group ui
	 */
	public function test_trying_to_save_empty_array_tracking_settings_should_fail(): void {
		$expected = self::$parsely->get_options();
		$options  = self::$parsely->get_options();

		$options['track_post_types_as'] = array();
		$actual                         = self::$settings_page->validate_options( $options );
		self::assertSame( $expected, $actual );
	}

	/**
	 * Verify that trying to save tracking settings with an non-array value fails.
	 *
	 * @since 3.2.0
	 *
	 * @covers \Parsely\UI\Settings_Page::validate_options
	 * @group ui
	 */
	public function test_trying_to_save_non_array_tracking_settings_should_fail(): void {
		$expected = self::$parsely->get_options();
		$options  = self::$parsely->get_options();

		$options['track_post_types_as'] = 'string';
		$actual                         = self::$settings_page->validate_options( $options );
		self::assertSame( $expected, $actual );
	}

	/**
	 * Make sure that the settings URL is correctly returned for single sites and multisites with and without a blog_id param.
	 *
	 * @covers \Parsely\Parsely::get_settings_url
	 * @uses \Parsely\UI\Settings_Page::__construct
	 * @return void
	 */
	public function test_get_settings_url_with_and_without_blog_id(): void {
		self::assertSame(
			'http://example.org/wp-admin/options-general.php?page=parsely',
			self::$parsely->get_settings_url(),
			'The URL did not match the expected value without a $blog_id param.'
		);

		self::assertSame(
			'http://example.org/wp-admin/options-general.php?page=parsely',
			self::$parsely->get_settings_url( get_current_blog_id() ),
			'The URL did not match the expected value with a $blog_id param.'
		);

		if ( ! is_multisite() ) {
			return;
		}

		$subsite_blog_id = $this->factory->blog->create(
			array(
				'domain' => 'parselyrocks.example.org',
				'path'   => '/vipvipvip',
			)
		);

		self::assertSame(
			'http://parselyrocks.example.org/vipvipvip/wp-admin/options-general.php?page=parsely',
			self::$parsely->get_settings_url( $subsite_blog_id ),
			'The URL did not match when passing $subsite_blog_id.'
		);

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.switch_to_blog_switch_to_blog
		switch_to_blog( $subsite_blog_id );
		self::assertSame(
			'http://parselyrocks.example.org/vipvipvip/wp-admin/options-general.php?page=parsely',
			self::$parsely->get_settings_url(),
			'The URL did not match the subsite without passing a $blog_id param.'
		);
		restore_current_blog();

		self::assertSame(
			'http://example.org/wp-admin/options-general.php?page=parsely',
			self::$parsely->get_settings_url(),
			'The URL did not match the expected value for the main site with no $blog_id param after switching back.'
		);
	}
}
