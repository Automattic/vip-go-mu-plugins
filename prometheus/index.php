<?php
/**
 * Plugin Name: VIP Prometheus integration
 * Description: Integration with Prometheus
 * Author: Automattic
 */

use Automattic\VIP\Prometheus\Plugin;

if ( defined( 'ABSPATH' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';

	Plugin::get_instance();
}
