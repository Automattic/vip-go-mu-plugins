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
	 * Check that default tracking values get saved.
	 *
	 * @since 3.1.0
	 *
	 * @covers \Parsely\UI\Settings_Page::validate_options
	 * @group ui
	 */
	public function test_validate_unique_tracking_values_succeeds(): void {
		// Initializations.
		$expected = self::$parsely->get_options();
		$options  = self::$parsely->get_options();

		// Default tracking values.
		$options['track_post_types'] = array( 'post' );
		$options['track_page_types'] = array( 'page' );

		$actual = self::$settings_page->validate_options( $options );
		self::assertSame( $expected, $actual );
	}

	/**
	 * Check that validate_options() method will not allow duplicate tracking
	 * in post types array.
	 *
	 * @since 3.1.0
	 *
	 * @covers \Parsely\UI\Settings_Page::validate_options
	 * @group ui
	 */
	public function test_validate_duplicate_tracking_in_post_types(): void {
		// Initializations.
		$expected = self::$parsely->get_options();
		$options  = self::$parsely->get_options();

		// Duplicate selection in Post Types.
		$options['track_post_types'] = array( 'post', 'page' );
		$options['track_page_types'] = array( 'page' );

		$actual = self::$settings_page->validate_options( $options );
		self::assertSame( $expected, $actual );
	}

	/**
	 * Check that validate_options() method will not allow duplicate tracking
	 * in page types array.
	 *
	 * @since 3.1.0
	 *
	 * @covers \Parsely\UI\Settings_Page::validate_options
	 * @group ui
	 */
	public function test_validate_duplicate_tracking_in_page_types(): void {
		// Initializations.
		$expected = self::$parsely->get_options();
		$options  = self::$parsely->get_options();

		// Duplicate selection in Page Types.
		$options['track_post_types'] = array( 'post' );
		$options['track_page_types'] = array( 'post', 'page' );

		$actual = self::$settings_page->validate_options( $options );
		self::assertSame( $expected, $actual );
	}

	/**
	 * Check that validate_options() method will not allow duplicate tracking
	 * when the array order is different than the default.
	 *
	 * @since 3.1.0
	 *
	 * @covers \Parsely\UI\Settings_Page::validate_options
	 * @group ui
	 */
	public function test_validate_duplicate_tracking_with_unexpected_array_order(): void {
		// Initializations.
		$expected = self::$parsely->get_options();
		$options  = self::$parsely->get_options();

		// Duplicate selection in Page Types (different order of array items).
		$options['track_post_types'] = array( 'post' );
		$options['track_page_types'] = array( 'page', 'post' );

		$actual = self::$settings_page->validate_options( $options );
		self::assertSame( $expected, $actual );
	}
}
