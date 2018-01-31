<?php
/*
Plugin name: Cache Manager
Description: Automatically clears the Varnish cache when necessary
Author: Automattic
Author URI: http://automattic.com/
Version: 1.1
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

// Load basics needed to instantiate plugin.
require __DIR__ . '/includes/class-singleton.php';

// Load API functions
require __DIR__ . '/api.php';

// Instantiate main plugin class, which checks environment and loads remaining classes when appropriate.
require __DIR__ . '/includes/class-main.php';
WPCOM_VIP_Cache_Manager::instance();
