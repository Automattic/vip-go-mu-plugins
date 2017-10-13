<?php 

/**
 * Retrieve featured plugins from the vip.wordpress.com API
 *
 * @return array an array of plugins
 */
function wpcom_vip_fetch_vip_featured_plugins() {
	$plugins = wp_cache_get( 'wpcom_vip_featured_plugins' );

	if ( false === $plugins ) {
		$plugins = array();
		$url_for_featured_plugins = 'https://vip.wordpress.com/wp-json/vip/v1/plugins?type=technology';
		$response = vip_safe_wp_remote_get( $url_for_featured_plugins, false, 3, 5 );

		if ( ! $response || is_wp_error( $response ) ) {
			trigger_error( 'The API on vip.wordpress.com is not responding (' . esc_url( $url_for_featured_plugins ) . ')' );
			return false;
		}

		$plugins = json_decode( $response['body'] );

		if ( empty( $plugins ) ) {
			return false;
		}

		wp_cache_set( 'wpcom_vip_featured_plugins', $plugins, '', HOUR_IN_SECONDS * 4 );
	}

	return $plugins;
}

/**
 * Render the featured partner plugins to the plugins screenReaderText
 * Uses the notice hooks as that is all we have on these pages
 *
 * @return null
 */
function wpcom_vip_render_vip_featured_plugins() {
	$screen = get_current_screen();

	if ( ! ( 'plugins' === $screen->id || 'plugins-network' === $screen->id ) ) {
		return;
	}

	$plugins = wpcom_vip_fetch_vip_featured_plugins();

	if ( ! $plugins ) {
		return;
	}

	?>
	<div class="featured-plugins notice">
		<h3><?php _e( 'VIP Featured Plugins', 'vip-dashboard' ); ?></h3>
		<?php
		foreach ( $plugins as $key => $plugin ) {
			?>
			<div class="plugin">
				<a class="fp-content" href="<?php echo esc_attr( $plugin->meta->plugin_url ); ?>" target="_blank">
					<img src="<?php echo esc_attr( $plugin->meta->listing_logo ); ?>" alt="<?php echo esc_attr( $plugin->post_title ); ?>" />
					<h4><?php echo esc_html( $plugin->post_title ); ?></h4>
					<p><?php echo esc_html( $plugin->meta->listing_description ); ?></p>
				</a>
				<a class="fp-overlay" href="<?php echo esc_attr( $plugin->meta->plugin_url ); ?>" target="_blank">
					<div class="fp-overlay-inner">
						<div class="fp-overlay-cell">
							<span>	
								<?php _e( 'More Information', 'vip-dashboard' ); ?>
							</span>
						</div>
					</div>
				</a>
			</div>
			<?php
		}
		?>
	</div>
	<?php
}
add_action( 'admin_notices', 'wpcom_vip_render_vip_featured_plugins', 99 );
add_action( 'network_admin_notices', 'wpcom_vip_render_vip_featured_plugins', 99 );

/**
 * Returns a filtered list of code activated plugins similar to core plugins Option
 *
 * @return array list of filtered plugins
 */
function wpcom_vip_get_filtered_loaded_plugins() {
	$code_plugins = wpcom_vip_get_loaded_plugins();
	foreach ( $code_plugins as $key => $plugin ) {
		if ( substr( $plugin, 0, 8 ) === 'plugins/' ) {
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
function wpcom_vip_plugin_action_links( $actions, $plugin_file, $plugin_data, $context ) {
	$screen = get_current_screen();
	if ( in_array( $plugin_file, wpcom_vip_get_filtered_loaded_plugins(), true ) ) {
		if ( array_key_exists( 'activate', $actions ) ) {
			unset( $actions['activate'] );
		}
		if ( array_key_exists( 'deactivate', $actions ) ) {
			unset( $actions['deactivate'] );
		}
		$actions['vip-code-activated-plugin'] = __( 'Enabled via code', 'vip-dashboard' );

		if ( 'plugins' === $screen->id ) {
			unset( $actions['network_active'] );
		}
	}

	return $actions;
}
add_filter( 'plugin_action_links', 'wpcom_vip_plugin_action_links', 10, 4 );
add_filter( 'network_admin_plugin_action_links', 'wpcom_vip_plugin_action_links', 10, 4 );

/**
 * Merge code activated plugins with database option for better UI experience
 *
 * @param  array $value
 * @param  string $option
 * @return array
 */
function wpcom_vip_option_active_plugins( $value, $option ) {
	$code_plugins = wpcom_vip_get_filtered_loaded_plugins();
	$value = array_merge( $code_plugins, $value );

	return $value;
}
add_filter( 'option_active_plugins', 'wpcom_vip_option_active_plugins', 10, 2 );

/**
 * Merge code activated plugins with network database option for better UI experience
 *
 * @param  array $value
 * @param  string $option
 * @return array
 */
function wpcom_vip_site_option_active_sitewide_plugins( $value, $option ) {
	$code_plugins = wpcom_vip_get_network_filtered_loaded_plugins();
	$value = array_merge( $code_plugins, $value );

	return $value;

}
add_filter( 'site_option_active_sitewide_plugins', 'wpcom_vip_site_option_active_sitewide_plugins', 10, 2 );

/**
 * Unmerge code activated plugins from active plugins option (reverse of the above)
 *
 * @param  array $value
 * @param  array $old_value
 * @param  string $option
 * @return array
 */
function wpcom_vip_pre_update_option_active_plugins( $value, $old_value, $option ) {
	$code_plugins = wpcom_vip_get_filtered_loaded_plugins();
	$value = array_diff( $value, $code_plugins );

	return $value;
}
add_filter( 'pre_update_option_active_plugins', 'wpcom_vip_pre_update_option_active_plugins', 10, 3 );

/**
 * Unmerge code activated plugins from network active plugins option (reverse of the above)
 *
 * @param  array $value
 * @param  array $old_value
 * @param  string $option
 * @param  int $network_id
 * @return array
 */
function wpcom_vip_pre_update_site_option_active_sitewide_plugins( $value, $old_value, $option, $network_id ) {
	$code_plugins = wpcom_vip_get_network_filtered_loaded_plugins();
	$value = array_diff( $value, $code_plugins );

	return $value;
}
add_filter( 'pre_update_site_option_active_sitewide_plugins', 'wpcom_vip_pre_update_site_option_active_sitewide_plugins', 10, 4 );

/**
 * Custom CSS and JS for the plugins UIs
 *
 * @return null
 */
function wpcom_vip_plugins_ui_admin_enqueue_scripts() {
	$screen = get_current_screen();
	if ( 'plugins' === $screen->id || 'plugins-network' === $screen->id ) {
		wp_enqueue_style( 'vip-plugins-style', WP_CONTENT_URL . '/mu-plugins/' . basename( __DIR__ ) . '/css/plugins-ui.css' , '3.0' );
		wp_enqueue_script( 'vip-plugins-script', WP_CONTENT_URL . '/mu-plugins/' . basename( __DIR__ ) . '/js/plugins-ui.js', array( 'jquery' ), '3.0', true );
	}
}
add_action( 'admin_enqueue_scripts', 'wpcom_vip_plugins_ui_admin_enqueue_scripts' );
