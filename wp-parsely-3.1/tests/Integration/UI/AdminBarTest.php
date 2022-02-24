<?php
/**
 * Admin bar tests.
 *
 * @package Parsely
 */

declare(strict_types=1);

namespace Parsely\Tests\Integration\UI;

use Parsely\Parsely;
use Parsely\Tests\Integration\TestCase;
use Parsely\UI\Admin_Bar;

/**
 * Admin bar modifications tests.
 *
 * @since 3.1.0
 */
final class AdminBarTest extends TestCase {
	/**
	 * Internal variable.
	 *
	 * @var Admin_Bar $admin_bar Holds the Admin_Bar object
	 */
	private static $admin_bar;

	/**
	 * The setUp run before each test
	 */
	public function set_up(): void {
		parent::set_up();

		self::$admin_bar = new Admin_Bar( new Parsely() );
	}

	/**
	 * Check that the function to render the stats button is enqueued on the admin menu.
	 *
	 * @covers \Parsely\UI\Admin_Bar::run
	 */
	public function test_admin_bar_enqueued(): void {
		self::$admin_bar->run();

		self::assertEquals( 201, has_filter( 'admin_bar_menu', array( self::$admin_bar, 'admin_bar_parsely_stats_button' ) ) );
	}

	/**
	 * Check that the function to render the stats button is not enqueued on the admin menu when there's the filter.
	 *
	 * @covers \Parsely\UI\Admin_Bar::run
	 */
	public function test_admin_bar_not_enqueued_with_filter(): void {
		add_filter( 'wp_parsely_enable_admin_bar', '__return_false' );

		self::$admin_bar->run();

		self::assertFalse( has_filter( 'admin_bar_menu', array( self::$admin_bar, 'admin_bar_parsely_stats_button' ) ) );
	}
}
