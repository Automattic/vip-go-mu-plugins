<?php
/**
 * Plugin Name: Cron Control
 * Plugin URI: https://vip.wordpress.com/
 * Description: Execute WordPress cron events in parallel, using a custom post type for event storage.
 * Author: Erick Hitter, Automattic
 * Version: 3.1
 * Requires at least: 5.1
 * Requires PHP: 7.4
 * Text Domain: automattic-cron-control
 * Domain Path: /languages
 *
 * @package a8c_Cron_Control
 */

namespace Automattic\WP\Cron_Control;

// Load basics needed to instantiate plugin.
require __DIR__ . '/includes/class-singleton.php';

// Instantiate main plugin class, which checks environment and loads remaining classes when appropriate.
require __DIR__ . '/includes/class-main.php';
Main::instance();
