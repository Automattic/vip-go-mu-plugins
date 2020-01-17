<?php

/**
 * Plugin Name: VIP Back-compat
 * Description: Adds relevant backwards compatibility code.
 * Author: Automattic
 * Version: 1.0
 * License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

/*
 * WordPress 5.3 fixes a bug in mediaelement which has been leaking the $ to window.
 * Some plugins, and VIPs, might not be following the best practices and are using the $ instead of jQuery.
 * Let's preserve the functionality for them (for now).
 *
 * @TODO: this will be removed as soon as we've ensured that plugins and sites have stopped using $ in their code.
 */
function wpcom_core_compat_leak_jquery_in_non_compat_mode() {
	wp_add_inline_script( 'mediaelement-core', 'if ( window.jQuery && ! window.$ ) { console.log( "mediaelement-core: Loading VIP back-compat shim to map window.$ to window.jQuery (see mu-plugins/back-compat.php)." ); window.$ = window.jQuery; }', 'after' );
}
add_action( 'admin_enqueue_scripts', 'wpcom_core_compat_leak_jquery_in_non_compat_mode' );
