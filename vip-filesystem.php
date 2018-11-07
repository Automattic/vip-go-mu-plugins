<?php
/**
 * Plugin Name:       VIP Filesystem Plugin
 * Description:       Provides a custom stream wrapper for handling file uploads to VIP/A8C Files API.
 * Version:           1.0.0
 * Author:            Automattic
 * Author URI:        http://automattic.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 */
define( 'VIP_FILESYSTEM_VERSION', '1.0.0' );

/**
 * The core plugin class
 */
require __DIR__ . '/vip-filesystem/class.vip-filesystem.php';

use Automattic\VIP\Filesystem\Vip_Filesystem;

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_vip_filesystem() {
	$plugin = new Vip_Filesystem();
	$plugin->run();
}

run_vip_filesystem();