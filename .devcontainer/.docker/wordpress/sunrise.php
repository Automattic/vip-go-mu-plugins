<?php

namespace Automattic\VIP\Sunrise;

if ( is_multisite() ) {
	$sunrise = ABSPATH . 'wp-content/mu-plugins/lib/sunrise/sunrise.php';
	if ( file_exists( $sunrise ) ) {
		require_once $sunrise;
	}
}
