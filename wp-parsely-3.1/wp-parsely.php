<?php
/**
 * Parse.ly
 *
 * @package      Parsely\wp-parsely
 * @author       Parse.ly
 * @copyright    2012 Parse.ly
 * @license      GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Parse.ly
 * Plugin URI:        https://www.parse.ly/help/integration/wordpress
 * Description:       This plugin makes it a snap to add Parse.ly tracking code to your WordPress blog.
 * Version:           3.1.3
 * Author:            Parse.ly
 * Author URI:        https://www.parse.ly
 * Text Domain:       wp-parsely
 * License:           GPL-2.0-or-later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * GitHub Plugin URI: https://github.com/Parsely/wp-parsely
 * Requires PHP:      7.1
 * Requires WP:       5.0.0
 */

declare(strict_types=1);

namespace Parsely;

use Parsely\Integrations\Amp;
use Parsely\Integrations\Facebook_Instant_Articles;
use Parsely\Integrations\Integrations;
use Parsely\UI\Admin_Bar;
use Parsely\UI\Admin_Warning;
use Parsely\UI\Plugins_Actions;
use Parsely\UI\Recommended_Widget;
use Parsely\UI\Row_Actions;
use Parsely\UI\Settings_Page;

if ( class_exists( Parsely::class ) ) {
	return;
}

const PARSELY_VERSION = '3.1.3';
const PARSELY_FILE    = __FILE__;

require __DIR__ . '/src/class-parsely.php';
require __DIR__ . '/src/class-rest.php';
require __DIR__ . '/src/class-scripts.php';
require __DIR__ . '/src/class-dashboard-link.php';
require __DIR__ . '/src/UI/class-admin-bar.php';

add_action( 'plugins_loaded', __NAMESPACE__ . '\\parsely_initialize_plugin' );
/**
 * Register the basic classes to initialize the plugin.
 *
 * @return void
 */
function parsely_initialize_plugin(): void {
	$GLOBALS['parsely'] = new Parsely();
	$GLOBALS['parsely']->run();

	$rest = new Rest( $GLOBALS['parsely'] );
	$rest->run();

	$scripts = new Scripts( $GLOBALS['parsely'] );
	$scripts->run();

	$admin_bar = new Admin_Bar( $GLOBALS['parsely'] );
	$admin_bar->run();
}

require __DIR__ . '/src/UI/class-admin-warning.php';
require __DIR__ . '/src/UI/class-plugins-actions.php';
require __DIR__ . '/src/UI/class-row-actions.php';

add_action( 'admin_init', __NAMESPACE__ . '\\parsely_admin_init_register' );
/**
 * Register the Parse.ly wp-admin warnings, plugin actions and row actions.
 *
 * @return void
 */
function parsely_admin_init_register(): void {
	$admin_warning = new Admin_Warning( $GLOBALS['parsely'] );
	$admin_warning->run();

	$GLOBALS['parsely_ui_plugins_actions'] = new Plugins_Actions();
	$GLOBALS['parsely_ui_plugins_actions']->run();

	$row_actions = new Row_Actions( $GLOBALS['parsely'] );
	$row_actions->run();
}

require __DIR__ . '/src/UI/class-settings-page.php';

add_action( '_admin_menu', __NAMESPACE__ . '\\parsely_admin_menu_register' );
/**
 * Register the Parse.ly wp-admin settings page.
 *
 * @return void
 */
function parsely_admin_menu_register(): void {
	$settings_page = new Settings_Page( $GLOBALS['parsely'] );
	$settings_page->run();
}

require __DIR__ . '/src/UI/class-recommended-widget.php';

add_action( 'widgets_init', __NAMESPACE__ . '\\parsely_recommended_widget_register' );
/**
 * Register the Parse.ly Recommended widget.
 *
 * @return void
 */
function parsely_recommended_widget_register(): void {
	register_widget( Recommended_Widget::class );
}

require __DIR__ . '/src/Integrations/class-integration.php';
require __DIR__ . '/src/Integrations/class-integrations.php';
require __DIR__ . '/src/Integrations/class-amp.php';
require __DIR__ . '/src/Integrations/class-facebook-instant-articles.php';

add_action( 'init', __NAMESPACE__ . '\\parsely_integrations' );
/**
 * Instantiate Integrations collection and register built-in integrations.
 *
 * @since 2.6.0
 *
 * @return Integrations
 */
function parsely_integrations(): Integrations {
	$parsely_integrations = new Integrations();
	$parsely_integrations->register( 'amp', Amp::class );
	$parsely_integrations->register( 'fbia', Facebook_Instant_Articles::class );
	$parsely_integrations = apply_filters( 'wp_parsely_add_integration', $parsely_integrations );
	$parsely_integrations->integrate();

	return $parsely_integrations;
}
