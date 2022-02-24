<?php
/**
 * UI Tests for the plugin actions
 *
 * @package Parsely\Tests\UI
 */

declare(strict_types=1);

namespace Parsely\Tests\Integration\UI;

use Parsely\Tests\Integration\TestCase;
use Parsely\UI\Plugins_Actions;

use const Parsely\PARSELY_FILE;

/**
 * UI Tests for the plugin screen.
 */
final class PluginsActionsTest extends TestCase {
	/**
	 * Check that plugins screen will add a hook to change the plugin action links.
	 *
	 * @covers \Parsely\UI\Plugins_Actions::run
	 * @group ui
	 */
	public function test_plugins_screen_has_filter_to_add_a_settings_action_link(): void {
		$plugins_screen = new Plugins_Actions();
		$plugins_screen->run();

		self::assertNotFalse( has_filter( 'plugin_action_links_' . plugin_basename( PARSELY_FILE ), array( $plugins_screen, 'add_plugin_meta_links' ) ) );
	}

	/**
	 * Check that plugins screen will add a hook to change the plugin action links.
	 *
	 * @covers \Parsely\UI\Plugins_Actions::run
	 * @covers \Parsely\UI\Plugins_Actions::add_plugin_meta_links
	 * @uses \Parsely\Parsely::get_settings_url
	 * @group ui
	 */
	public function test_plugins_screen_adds_a_settings_action_link(): void {
		$actions = array();
		$actions = ( new Plugins_Actions() )->add_plugin_meta_links( $actions );

		self::assertCount( 2, $actions );
	}
}
