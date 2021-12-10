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
 * Version:           2.6.1
 * Author:            Parse.ly
 * Author URI:        https://www.parse.ly
 * Text Domain:       wp-parsely
 * License:           GPL-2.0-or-later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * GitHub Plugin URI: https://github.com/Parsely/wp-parsely
 * Requires PHP:      5.6
 * Requires WP:       4.0.0
 */

use Parsely\Integrations\Amp;
use Parsely\Integrations\Facebook_Instant_Articles;
use Parsely\Integrations\Integrations;
use Parsely\UI\Row_Actions;

if ( class_exists( 'Parsely' ) ) {
	return;
}

define( 'PARSELY_VERSION', '2.6.1' );
define( 'PARSELY_FILE', __FILE__ );

require __DIR__ . '/src/class-parsely.php';
add_action(
	'plugins_loaded',
	function() {
		$GLOBALS['parsely'] = new Parsely();
		$GLOBALS['parsely']->run();
	}
);

// Until auto-loading happens, we need to include this file for tests as well.
require __DIR__ . '/src/UI/class-plugins-actions.php';
require __DIR__ . '/src/UI/class-row-actions.php';
add_action(
	'admin_init',
	function() {
		$GLOBALS['parsely_ui_plugins_actions'] = new Parsely\UI\Plugins_Actions();
		$GLOBALS['parsely_ui_plugins_actions']->run();

		$row_actions = new Row_Actions( $GLOBALS['parsely'] );
		$row_actions->run();
	}
);

require __DIR__ . '/src/class-parsely-recommended-widget.php';

add_action( 'widgets_init', 'parsely_recommended_widget_register' );
/**
 * Register the Parse.ly Recommended widget.
 */
function parsely_recommended_widget_register() {
	register_widget( 'Parsely_Recommended_Widget' );
}

add_action( 'init', 'parsely_load_textdomain' );
/**
 * Load plugin textdomain.
 *
 * Only look for WP_LANG_DIR . '/plugins/wp-parsely-' . $locale . '.mo'.
 * WP_LANG_DIR is usually WP_CONTENT_DIR . '/languages/'.
 * No other fallback location is supported.
 *
 * This can be removed once minimum supported WordPress is 4.6 or later.
 *
 * @since 2.5.0
 */
function parsely_load_textdomain() {
	load_plugin_textdomain( 'wp-parsely' );
}

require __DIR__ . '/src/Integrations/class-integration.php';
require __DIR__ . '/src/Integrations/class-integrations.php';
require __DIR__ . '/src/Integrations/class-amp.php';
require __DIR__ . '/src/Integrations/class-facebook-instant-articles.php';

add_action( 'init', 'parsely_integrations' );
/**
 * Instantiate Integrations collection and register built-in integrations.
 *
 * @since 2.6.0
 */
function parsely_integrations() {
	$parsely_integrations = new Integrations();
	$parsely_integrations->register( 'amp', Amp::class );
	$parsely_integrations->register( 'fbia', Facebook_Instant_Articles::class );
	$parsely_integrations = apply_filters( 'wp_parsely_add_integration', $parsely_integrations );
	$parsely_integrations->integrate();

	return $parsely_integrations;
}
