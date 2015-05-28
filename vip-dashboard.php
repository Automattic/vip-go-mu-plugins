<?php
/*
Plugin Name: VIP Dashboard
Plugin URI: http://vip.wordpress.com
Description: WordPress VIP Dashboard
Author: <a href="http://automattic.com">Automattic</a>
Version: 1.0
Author URI: http://vip.wordpress.com
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: vip-dashboard
Domain Path: /languages/
*/

if ( is_admin() ) {
  require __DIR__ . '/vip-dashboard/vip-dashboard.php';
}
