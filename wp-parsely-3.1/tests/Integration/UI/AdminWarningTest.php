<?php
/**
 * Admin page warning tests.
 *
 * @package Parsely
 */

declare(strict_types=1);

namespace Parsely\Tests\Integration\UI;

use Parsely\Parsely;
use Parsely\Tests\Integration\TestCase;
use Parsely\UI\Admin_Warning;

/**
 * Admin page warning tests.
 *
 * @since 3.0.0
 */
final class AdminWarningTest extends TestCase {
	/**
	 * Internal variable.
	 *
	 * @var Admin_Warning $admin_warning Holds the Admin_Warning object
	 */
	private static $admin_warning;

	/**
	 * The setUp run before each test
	 */
	public function set_up(): void {
		parent::set_up();

		self::$admin_warning = new Admin_Warning( new Parsely() );
	}

	/**
	 * Test that test_display_admin_warning action returns a warning when there is no key
	 *
	 * @covers \Parsely\UI\Admin_Warning::should_display_admin_warning
	 * @uses \Parsely\UI\Admin_Warning::__construct
	 * @uses \Parsely\Parsely::get_options
	 */
	public function test_display_admin_warning_without_key(): void {
		$should_display_admin_warning = self::getMethod( 'should_display_admin_warning', Admin_Warning::class );
		$this->set_options( array( 'apikey' => '' ) );

		$response = $should_display_admin_warning->invoke( self::$admin_warning );
		self::assertTrue( $response );
	}

	/**
	 * Test that test_display_admin_warning action returns a warning when there is no key
	 *
	 * @covers \Parsely\UI\Admin_Warning::should_display_admin_warning
	 * @uses \Parsely\UI\Admin_Warning::__construct
	 */
	public function test_display_admin_warning_network_admin(): void {
		$should_display_admin_warning = self::getMethod( 'should_display_admin_warning', Admin_Warning::class );
		$this->set_options( array( 'apikey' => '' ) );
		set_current_screen( 'dashboard-network' );

		$response = $should_display_admin_warning->invoke( self::$admin_warning );
		self::assertFalse( $response );
	}

	/**
	 * Test that test_display_admin_warning action doesn't return a warning when there is a key
	 *
	 * @covers \Parsely\UI\Admin_Warning::should_display_admin_warning
	 * @uses \Parsely\UI\Admin_Warning::__construct
	 * @uses \Parsely\Parsely::get_options
	 */
	public function test_display_admin_warning_with_key(): void {
		$should_display_admin_warning = self::getMethod( 'should_display_admin_warning', Admin_Warning::class );
		$this->set_options( array( 'apikey' => 'somekey' ) );

		$response = $should_display_admin_warning->invoke( self::$admin_warning );
		self::assertFalse( $response );
	}
}
