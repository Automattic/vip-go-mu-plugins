<?php
/**
 * Plugin Name: Elasticsearch
 * Description: Power your site search and other queries with Elasticsearch
 * Version:     0.1.0
 * Author:      Automattic VIP
 * Author URI:  https://wpvip.com
 * License:     GPLv2 or later
 * Text Domain: elasticsearch
 * Domain Path: /lang/
 *
 * @package  elasticsearch
 */

namespace Automattic\VIP\Elasticsearch;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once __DIR__ . '/includes/classes/class-elasticsearch.php';

// Load ElasticPress
require_once __DIR__ . '/elasticpress/elasticpress.php';
// Load ElasticPress Debug Bar
require_once __DIR__ . '/debug-bar-elasticpress/debug-bar-elasticpress.php';


do_action( 'vip_search_loaded' );
