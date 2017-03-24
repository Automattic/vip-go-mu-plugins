<?php
/**
 * Plugin Name: Install Defaults
 * Description: Set default values during install
 */

/**
 * Generic handler for any install defaults
 */
function wp_install_defaults( $user_id ) {
	do_action( 'wpcom_vip_install_defaults', $user_id );
}

/**
 * Replicate the minimums needed from Core, such as the default category
 */
function wp_install_defaults_from_core( $user_id ) {
	// Default category
}
add_action( 'wpcom_vip_install_defaults', 'wp_install_defaults_from_core' );
