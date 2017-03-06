<?php

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

foreach( glob( __DIR__ . '/wp-cli/*.php' ) as $command ) {
	require( $command );
}
