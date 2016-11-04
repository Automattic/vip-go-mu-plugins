<?php
/*
 Plugin Name: WP-Cron Control Revisited
 Plugin URI:
 Description: Take control of wp-cron execution.
 Author: Erick Hitter, Automattic
 Version: 0.1
 Text Domain: wp-cron-control-revisited
 */

namespace WP_Cron_Control_Revisited;

// Load basics needed to instantiate plugin
require __DIR__ . '/includes/constants.php';
require __DIR__ . '/includes/abstract-class-singleton.php';
require __DIR__ . '/includes/class-cron-options-cpt.php';

// Instantiate main plugin class, which checks environment and loads remaining classes when appropriate
require __DIR__ . '/includes/class-main.php';
Main::instance();
