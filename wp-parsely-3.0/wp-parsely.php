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
 * Version:           3.0.0
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
use Parsely\UI\Admin_Warning;
use Parsely\UI\Plugins_Actions;
use Parsely\UI\Recommended_Widget;
use Parsely\UI\Row_Actions;
use Parsely\UI\Settings_Page;

if ( class_exists( Parsely::class ) ) {
	return;
}

const PARSELY_VERSION = '3.0.0';
const PARSELY_FILE    = __FILE__;

require __DIR__ . '/src/class-parsely.php';
require __DIR__ . '/src/class-scripts.php';
add_action(
	'plugins_loaded',
	function(): void {
		$GLOBALS['parsely'] = new Parsely();
		$GLOBALS['parsely']->run();

		$scripts = new Scripts( $GLOBALS['parsely'] );
		$scripts->run();
	}
);

// Until auto-loading happens, we need to include this file for tests as well.
require __DIR__ . '/src/UI/class-admin-warning.php';
require __DIR__ . '/src/UI/class-plugins-actions.php';
require __DIR__ . '/src/UI/class-row-actions.php';
add_action(
	'admin_init',
	function(): void {
		$admin_warning = new Admin_Warning( $GLOBALS['parsely'] );
		$admin_warning->run();

		$GLOBALS['parsely_ui_plugins_actions'] = new Plugins_Actions();
		$GLOBALS['parsely_ui_plugins_actions']->run();

		$row_actions = new Row_Actions( $GLOBALS['parsely'] );
		$row_actions->run();
	}
);

require __DIR__ . '/src/UI/class-settings-page.php';
add_action(
	'_admin_menu',
	function(): void {
		$settings_page = new Settings_Page( $GLOBALS['parsely'] );
		$settings_page->run();
	}
);

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
