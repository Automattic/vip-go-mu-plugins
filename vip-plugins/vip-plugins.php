<?php

/**
 * Returns a filtered list of code activated plugins similar to core plugins option
 *
 * @return array list of filtered plugins
 */
function wpcom_vip_get_filtered_loaded_plugins() {
	$code_plugins = wpcom_vip_get_loaded_plugins();
	foreach ( $code_plugins as $key => $plugin ) {
		if ( substr( $plugin, 0, 8 ) === 'plugins/' ) {
			// /plugins removed from each $plugin to match core active_plugins option
			$code_plugins[ $key ] = preg_replace( '/^(plugins\/)/i', '', $plugin );
		} else {
			unset( $code_plugins[ $key ] );
		}
	}

	return $code_plugins;
}

/**
 * Returns a filtered list of code activated plugins similar to network plugins option
 *
 * @return array list of filtered, active plugins
 */
function wpcom_vip_get_network_filtered_loaded_plugins() {
	$code_plugins = wpcom_vip_get_filtered_loaded_plugins();
	foreach ( $code_plugins as $key => $plugin ) {
		unset( $code_plugins[ $key ] );
		// added stable timestamp, ensures this returns a similar array to the site option: active_sitewide_plugins
		$code_plugins[ $plugin ] = filemtime( __FILE__ );
	}

	return $code_plugins;
}

/**
 * Ensure code activated plugins are shown as such on core plugins screens
 *
 * @param  array $actions
 * @param  string $plugin_file
 * @param  array $plugin_data
 * @param  string $context
 * @return array
 */
function wpcom_vip_plugin_action_links( $actions, $plugin_file ) {
	$screen = get_current_screen();
	if ( in_array( $plugin_file, wpcom_vip_get_filtered_loaded_plugins(), true ) ) {
		if ( array_key_exists( 'activate', $actions ) ) {
			unset( $actions['activate'] );
		}
		if ( array_key_exists( 'deactivate', $actions ) ) {
			unset( $actions['deactivate'] );
		}
		$actions['vip-code-activated-plugin'] = __( 'Enabled via code', 'vip-plugins-dashboard' );

		if ( is_a( $screen, 'WP_Screen' ) && 'plugins' === $screen->id ) {
			unset( $actions['network_active'] );
		}
	}

	return $actions;
}
add_filter( 'plugin_action_links', 'wpcom_vip_plugin_action_links', 10, 2 );
add_filter( 'network_admin_plugin_action_links', 'wpcom_vip_plugin_action_links', 10, 2 );

/**
 * Merge code activated plugins with database option for better UI experience
 *
 * @param  array $value
 * @param  string $option
 * @return array
 */
function wpcom_vip_option_active_plugins( $value ) {
	$code_plugins = wpcom_vip_get_filtered_loaded_plugins();

	if ( false === is_array( $value ) ) {
		$value = array();
	}

	$value = array_unique( array_merge( $value, $code_plugins ) );

	sort( $value );

	return $value;
}
add_filter( 'option_active_plugins', 'wpcom_vip_option_active_plugins' );

/**
 * Merge code activated plugins with network database option for better UI experience
 *
 * @param  array $value
 * @param  string $option
 * @return array
 */
function wpcom_vip_site_option_active_sitewide_plugins( $value ) {
	$code_plugins = wpcom_vip_get_network_filtered_loaded_plugins();

	if ( false === is_array( $value ) ) {
		$value = array();
	}

	$value = array_merge( $value, $code_plugins );

	ksort( $value );

	return $value;
}
add_filter( 'site_option_active_sitewide_plugins', 'wpcom_vip_site_option_active_sitewide_plugins' );

/**
 * Unmerge code activated plugins from active plugins option (reverse of the above)
 *
 * @param  array $value
 * @param  array $old_value
 * @param  string $option
 * @return array
 */
function wpcom_vip_pre_update_option_active_plugins( $value ) {
	$code_plugins = wpcom_vip_get_filtered_loaded_plugins();

	if ( false === is_array( $value ) ) {
		$value = array();
	}

	$value = array_diff( $value, $code_plugins );

	sort( $value );

	return $value;
}
add_filter( 'pre_update_option_active_plugins', 'wpcom_vip_pre_update_option_active_plugins' );

/**
 * Unmerge code activated plugins from network active plugins option (reverse of the above)
 *
 * @param  array $value
 * @param  array $old_value
 * @param  string $option
 * @param  int $network_id
 * @return array
 */
function wpcom_vip_pre_update_site_option_active_sitewide_plugins( $value ) {
	$code_plugins = wpcom_vip_get_network_filtered_loaded_plugins();

	if ( false === is_array( $value ) ) {
		$value = array();
	}

	$value = array_diff( $value, $code_plugins );

	ksort( $value );

	return $value;
}
add_filter( 'pre_update_site_option_active_sitewide_plugins', 'wpcom_vip_pre_update_site_option_active_sitewide_plugins' );

/**
 * Custom JavaScript for the plugins UIs
 *
 * @return null
 */
function wpcom_vip_plugins_ui_admin_enqueue_scripts() {
	$screen = get_current_screen();
	if ( 'plugins' === $screen->id || 'plugins-network' === $screen->id ) {
		wp_enqueue_script( 'vip-plugins-script', plugins_url( '/js/plugins-ui.js', __FILE__ ), array( 'jquery' ), '3.0', true );
	}
}
add_action( 'admin_enqueue_scripts', 'wpcom_vip_plugins_ui_admin_enqueue_scripts' );

/**
 * Restore shared plugin loading - this function was brought over from vip-dashboard
 * Until our protected plugins are moved/retired we will need to keep this in place
 * See wpcom_vip_load_plugin / wpcom_vip_can_use_shared_plugin for more context
 */
function wpcom_vip_include_active_plugins() {
	$retired_plugins_option = get_option( 'wpcom_vip_active_plugins', array() );

	if ( ! is_array( $retired_plugins_option ) ) {
		return;
	}

	foreach ( $retired_plugins_option as $plugin ) {
		wpcom_vip_load_plugin( $plugin );
	}
}
add_action( 'plugins_loaded', 'wpcom_vip_include_active_plugins', 5 );
