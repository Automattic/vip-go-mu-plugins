<?php

/**
 * Plugin Name: HyperDB
 * Description: An advanced database class that supports replication, failover, load balancing, and partitioning.
 * Version: 1.9
 * Author: Automattic
 * Plugin URI: https://wordpress.org/plugins/hyperdb/
 * License: GPLv2 or later
 *
 * This file is require'd from wp-content/db.php
 */

if ( file_exists( ABSPATH . '/wp-content/mu-plugins/drop-ins/hyperdb/db.php' ) ) {
	require_once ABSPATH . '/wp-content/mu-plugins/drop-ins/hyperdb/db.php';
} else {
	require_once __DIR__ . '/hyperdb.php';
}
