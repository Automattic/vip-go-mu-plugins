<?php
/**
 * Plugin Name: Elasticsearch Wrapper for WP_Query
 * Plugin URI: https://github.com/alleyinteractive/es-wp-query
 * Description: A drop-in replacement for WP_Query to leverage Elasticsearch for complex queries.
 * Version: 0.4
 * Author: Matthew Boynes
 * Author URI: http://www.alleyinteractive.com/
 *
 * @package ES_WP_Query
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

// phpcs:disable WordPressVIPMinimum.Files.IncludingFile.IncludingFile

define( 'ES_WP_QUERY_PATH', dirname( __FILE__ ) );

require_once ES_WP_QUERY_PATH . '/class-es-wp-query-wrapper.php';
require_once ES_WP_QUERY_PATH . '/class-es-wp-tax-query.php';
require_once ES_WP_QUERY_PATH . '/class-es-wp-date-query.php';
require_once ES_WP_QUERY_PATH . '/class-es-wp-meta-query.php';
require_once ES_WP_QUERY_PATH . '/class-es-wp-query-shoehorn.php';
require_once ES_WP_QUERY_PATH . '/functions.php';
