<?php

/*
 * Plugin Name: Jetpack: VIP Specific Changes
 * Plugin URI: https://github.com/Automattic/vipv2-mu-plugins/blob/master/jetpack-mandatory.php
 * Description: VIP-specific customisations to Jetpack.
 * Author: Automattic
 * Version: 1.0.2
 * License: GPL2+
 */

/**
 * Enable VIP modules required as part of the platform
 */
require_once( __DIR__ . '/jetpack-mandatory.php' );

/**
 * Remove certain modules from the list of those that can be activated
 * Blocks access to certain functionality that isn't compatible with the platform.
 */
add_filter( 'jetpack_get_available_modules', function( $modules ) {
	unset( $modules['photon'] );
	unset( $modules['site-icon'] );
	unset( $modules['protect'] );

	return $modules;
}, 999 );

/**
 * Load Jetpack Force 2fa
 */
add_filter( 'jetpack_force_2fa_dependency_notice', '__return_false' );
require_once( __DIR__ . '/jetpack-force-2fa/jetpack-force-2fa.php' );
