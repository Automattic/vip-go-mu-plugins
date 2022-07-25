<?php
/**
 * Plugin Name: VIP Prometheus integration
 * Description: Integration with Prometheus
 * Author: Automattic
 */

use Automattic\VIP\Prometheus\Plugin;

// @codeCoverageIgnoreStart -- this file is loaded before tests start
if ( defined( 'ABSPATH' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';

	Plugin::get_instance();
}
// @codeCoverageIgnoreEnd
