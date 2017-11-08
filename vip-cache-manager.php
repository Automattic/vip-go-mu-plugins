<?php
/*
Plugin name: VIP Cache Manager
Description: Automatically clears the Varnish cache when necessary
Author: Automattic
Author URI: http://automattic.com/
Version: 1.1
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

require_once( __DIR__ . '/vip-cache-manager/vip-cache-manager.php' );
require_once( __DIR__ . '/vip-cache-manager/api.php' );
require_once( __DIR__ . '/vip-cache-manager/ttl-manager.php' );

if ( defined( 'WPCOM_IS_VIP_ENV' ) && WPCOM_IS_VIP_ENV === true ) {
	WPCOM_VIP_Cache_Manager::instance();
	Automattic\VIP\Cache\TTL_Manager\init();
}
