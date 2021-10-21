<?php
// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_trigger_error

/**
 * Retrieve featured plugins from the wpvip.com API
 *
 * @return array an array of plugins
 */
function wpcom_vip_fetch_vip_featured_plugins() {
	$plugins = wp_cache_get( 'wpcom_vip_featured_plugins' );

	if ( false === $plugins ) {
		$plugins                  = array();
		$url_for_featured_plugins = 'https://wpvip.com/wp-json/vip/v0/plugins?type=technology';
		$response                 = vip_safe_wp_remote_get( $url_for_featured_plugins, false, 3, 5 );

		if ( ! $response ) {
			trigger_error( 'The API on wpvip.com is not responding (' . esc_url( $url_for_featured_plugins ) . ')', E_USER_WARNING );
		}

		if ( is_wp_error( $response ) ) {
			trigger_error( 'The API on wpvip.com is not responding (' . esc_url( $url_for_featured_plugins ) . '): ' . esc_html( $response->get_error_message() ), E_USER_WARNING );
		}

		if ( ! $response || is_wp_error( $response ) ) {
			wp_cache_set( 'wpcom_vip_featured_plugins', $plugins, '', MINUTE_IN_SECONDS );
		} else {
			$plugins = json_decode( $response['body'] );

			if ( empty( $plugins ) ) {
				trigger_error( 'The API on wpvip.com returned empty data (' . esc_url( $url_for_featured_plugins ) . ')', E_USER_WARNING );
				wp_cache_set( 'wpcom_vip_featured_plugins', $plugins, '', MINUTE_IN_SECONDS );
			} else {
				wp_cache_set( 'wpcom_vip_featured_plugins', $plugins, '', HOUR_IN_SECONDS * 4 );
			}
		}
	}

	return $plugins;
}

/**
 * Render the featured partner plugins to the plugins screenReaderText
 * Uses the admin_notice hooks as that is all we have on these pages
 *
 * @return null
 */
function wpcom_vip_render_vip_featured_plugins() {
	$screen = get_current_screen();

	if ( 'plugins' !== $screen->id && 'plugins-network' !== $screen->id ) {
		return;
	}

	$plugins = wpcom_vip_fetch_vip_featured_plugins();

	if ( empty( $plugins ) ) {
		?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'Unable to display VIP Featured Technology Partners; try refreshing this page in a few minutes. If this error persists, please contact VIP Support.', 'vip-plugins-dashboard' ); ?></p>
		</div>
		<?php
		return;
	}

	?>
	<div class="featured-plugins notice">
		<h3><?php esc_html_e( 'VIP Featured Technology Partners', 'vip-plugins-dashboard' ); ?></h3>
		<div class="plugins-grid">
		<?php
		foreach ( $plugins as $plugin ) {
			?>
			<div class="plugin">
				<a class="fp-content" href="<?php echo esc_url( $plugin->permalink ?? $plugin->meta->plugin_url ); ?>" target="_blank">
					<img src="<?php echo esc_url( $plugin->meta->listing_logo ); ?>" alt="<?php echo esc_attr( $plugin->post_title ); ?>" />
					<h4><?php echo esc_html( $plugin->post_title ); ?></h4>
					<p><?php echo esc_html( $plugin->meta->listing_description ); ?></p>
				</a>
				<a class="fp-overlay" href="<?php echo esc_url( $plugin->permalink ?? $plugin->meta->plugin_url ); ?>" target="_blank">
					<div class="fp-overlay-inner">
						<div class="fp-overlay-cell">
							<span>
								<?php _e( 'More Information', 'vip-plugins-dashboard' ); ?>
							</span>
						</div>
					</div>
				</a>
			</div>
			<?php
		}
		?>
		</div>
	</div>
	<?php
}
// Priority set to 99 to push further down the page and to the bottom of other notices
add_action( 'admin_notices', 'wpcom_vip_render_vip_featured_plugins', 99 );
add_action( 'network_admin_notices', 'wpcom_vip_render_vip_featured_plugins', 99 );

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
 * Custom CSS and JS for the plugins UIs
 *
 * @return null
 */
function wpcom_vip_plugins_ui_admin_enqueue_scripts() {
	$screen = get_current_screen();
	if ( 'plugins' === $screen->id || 'plugins-network' === $screen->id ) {
		wp_enqueue_style( 'vip-plugins-style', plugins_url( '/css/plugins-ui.css', __FILE__ ), array(), '3.0' );
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
