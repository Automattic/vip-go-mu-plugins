<?php
/**
 * Plugin Name: VIP Search
 * Description: Power your site search and other queries with Elasticsearch
 * Version:     0.1.0
 * Author:      Automattic VIP
 * Author URI:  https://wpvip.com
 * License:     GPLv2 or later
 * Text Domain: vip-search
 * Domain Path: /lang/
 *
 * @package Automattic\VIP\Search
 */

namespace Automattic\VIP\Search;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once __DIR__ . '/includes/classes/class-search.php';
require_once __DIR__ . '/search-dev-tools/search-dev-tools.php';

do_action( 'vip_search_loaded' );
