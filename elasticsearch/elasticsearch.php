<?php
/**
 * Plugin Name: Elasticsearch
 * Description: VIP Elasticsearch plugin
 * Version:     0.1
 * Author:      Automattic VIP
 * Author URI:  http://wpvip.com
 * License:     GPLv2 or later
 * Text Domain: vip-search
 * Domain Path: /lang/
 *
 * @package  elasticsearch
 */

namespace Automattic\VIP\Elasticsearch;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
/**
* PSR-4-ish autoloading
*
* @since 1.0
*/
spl_autoload_register(
	function( $class ) {
		// project-specific namespace prefix.
		$prefix = 'Automattic\\VIP\\Elasticsearch\\';

		// base directory for the namespace prefix.
		$base_dir = __DIR__ . '/includes/classes/';

		// does the class use the namespace prefix?
		$len = strlen( $prefix );

		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, $len );

		$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		// if the file exists, require it.
		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

do_action( 'vip_search_loaded' );