<?php

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

require __DIR__ . '/wp-cli/vip-fixers.php';
require __DIR__ . '/wp-cli/vip-utf8-to-utf8mb4.php';
