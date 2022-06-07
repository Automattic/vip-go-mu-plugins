<?php

namespace Automattic\VIP\CodebaseManager;

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Remote objects use camelCase.

class PluginsManager {
	private $all_plugins;
	private $update_data;
	private $codebase_info;
	private $column_count;

	public function init() {
		if ( ! $this->user_can_manage_plugins() ) {
			// Currently nothing to do here for users without necessary permissions.
			return;
		}

		$this->all_plugins   = get_plugins();
		$this->update_data   = $this->fetch_plugins_with_updates();
		$this->codebase_info = $this->fetch_codebase_info();

		// Check how many columns exist on the plugin's page.
		$wp_list_table      = _get_list_table( 'WP_Plugins_List_Table', [ 'screen' => 'plugins' ] );
		$this->column_count = method_exists( $wp_list_table, 'get_column_count' ) ? $wp_list_table->get_column_count() : 3;

		add_action( 'after_plugin_row', [ $this, 'output_plugin_row_information' ], 10, 2 );
		add_action( 'admin_init', [ $this, 'display_update_count_bubble_in_menu' ], 15 ); // Hook in early, but after PluginsManager is initialized.
		add_action( 'admin_enqueue_scripts', [ $this, 'print_admin_styles' ] );
	}

	/**
	 * Display additional information underneath the plugin rows, such as available version updates.
	 *
	 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
	 * @param array  $plugin_data An array of plugin data. See `get_plugin_data()` & `plugin_row_meta` filter in WP core for the full list.
	 */
	public function output_plugin_row_information( $plugin_file, $plugin_data ) {
		$update_data = $this->update_data[ $plugin_file ] ?? new \stdClass();

		$plugin_folder   = explode( '/', $plugin_file )[0];
		$vulnerabilities = $this->codebase_info['plugins_vulns'][ "plugins/{$plugin_folder}" ] ?? [];

		$plugin = new Plugin( $plugin_file, $plugin_data, $update_data, $vulnerabilities );
		$plugin->display_version_update_information( $this->column_count );
		$plugin->display_vulnerability_information( $this->column_count );
	}

	/**
	 * Hijack the "Plugins" menu item and insert a notification count of available updates.
	 */
	public function display_update_count_bubble_in_menu(): void {
		global $menu;

		foreach ( $menu as $menu_key => $menu_data ) {
			if ( 'plugins.php' === $menu_data[2] ) {
				$count         = count( $this->update_data );
				$update_bubble = sprintf( '<span class="vip-update-plugins count-%s"><span class="vip-plugin-count">%s</span></span>', $count, number_format_i18n( $count ) );

				/* translators: Number of available plugin updates */
				$menu[ $menu_key ][0] = sprintf( __( 'Plugins %s' ), $update_bubble ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			}
		}
	}

	/**
	 * Get a list of plugins that have an available update.
	 *
	 * @return array An array of objects for plugins that have an available update.
	 */
	private function fetch_plugins_with_updates(): array {
		$update_cache = get_site_transient( 'update_plugins' );

		// Maybe refresh.
		$last_refreshed = isset( $update_cache->last_checked ) ? ( time() - $update_cache->last_checked ) : null;
		if ( ! $last_refreshed || 6 * HOUR_IN_SECONDS > $last_refreshed ) {
			wp_update_plugins();
			$update_cache = get_site_transient( 'update_plugins' );
		}

		$plugins_with_available_update = isset( $update_cache->response ) && is_array( $update_cache->response ) ? $update_cache->response : [];
		return $plugins_with_available_update;
	}

	/**
	 * Get aggregated information about the codebase (plugin vulnerabilities, open update PRs, etc).
	 *
	 * @return array An array of various objects.
	 */
	private function fetch_codebase_info(): array {
		$cached_info = wp_cache_get( 'codebase_info', 'vip_plugins_manager' );

		if ( is_array( $cached_info ) ) {
			return $cached_info;
		}

		$info = [ 'plugins_vulns' => [] ];
		if ( defined( 'SERVICES_API_URL' ) && defined( 'SERVICES_AUTH_TOKEN' ) ) {
			$site_id = defined( 'FILES_CLIENT_SITE_ID' ) && FILES_CLIENT_SITE_ID ? FILES_CLIENT_SITE_ID : 0;

			$url      = rtrim( SERVICES_API_URL, '/' ) . "/codebase-manager/v1/sites/{$site_id}/info";
			$response = vip_safe_wp_remote_request( $url, new \WP_Error( 'remote_request_failed' ), 3, 3, 20, [
				'method'  => 'GET',
				'headers' => [ 'Authorization' => 'Bearer ' . SERVICES_AUTH_TOKEN ],
			] );

			$response_code = (int) wp_remote_retrieve_response_code( $response );
			if ( 200 !== $response_code ) {
				trigger_error( sprintf( 'VIP Codebase Manager: Failed to retrieve codebase info, received status code %s.', esc_html( $response_code ) ), E_USER_WARNING );
				return $info;
			}

			$response_body = json_decode( wp_remote_retrieve_body( $response ) );

			$vulnerabilities = $response_body->plugins->vulnerabilities ?? [];
			foreach ( $vulnerabilities as $vuln ) {
				if ( ! isset( $info['plugins_vulns'][ $vuln->modulePath ] ) ) {
					$info['plugins_vulns'][ $vuln->modulePath ] = [];
				}

				// Indexing by the plugin path for easier usage later.
				array_push( $info['plugins_vulns'][ $vuln->modulePath ], $vuln );
			}
		}

		wp_cache_set( 'codebase_info', $info, 'vip_plugins_manager', 5 * MINUTE_IN_SECONDS );
		return $info;
	}

	/**
	 * On VIP environments, users do not have install/update plugins caps.
	 * Instead we'll use `activate_plugins` by default, but leave it open for customization if desired.
	 */
	private function user_can_manage_plugins(): bool {
		return apply_filters( 'vip_codebase_manager_user_can_manage_plugins', current_user_can( 'activate_plugins' ) );
	}

	/**
	 * Add needed admin CSS styles.
	 *
	 * - Styles the "update count" bubble. Can't use core styles as they will be overwritten due to some core JS.
	 * - Adjusts some styling on the plugins list table page.
	 *
	 * @param string $hook_suffix The current admin page.
	 * @return void
	 */
	public function print_admin_styles( $hook_suffix ): void {
		?>
		<style>
			#adminmenu .vip-update-plugins {
				display: inline-block;
				vertical-align: top;
				box-sizing: border-box;
				margin: 1px 0 -1px 2px;
				padding: 0 5px;
				min-width: 18px;
				height: 18px;
				border-radius: 9px;
				background-color: #d63638;
				color: #fff;
				font-size: 11px;
				line-height: 1.6;
				text-align: center;
				z-index: 26;
			}

			<?php if ( 'plugins.php' === $hook_suffix ) : ?>
				.plugins .plugin-vuln-tr td.plugin-vuln {
					box-shadow: inset 0 -1px 0 rgb(0 0 0 / 10%);
					overflow: hidden;
					padding: 0;
				}

				.plugins .plugin-vuln-tr.active td.plugin-vuln {
					border-left: 4px solid #72aee6;
				}

				.plugins .plugin-vuln-tr .vuln-message {
					margin: 5px 20px 15px 40px;
				}

				.plugins .plugin-vuln-tr .vuln-message p:before {
					display: inline-block;
					font: normal 20px/1 dashicons;
					-webkit-font-smoothing: antialiased;
					-moz-osx-font-smoothing: grayscale;
					color: #d63638;
					content: "\f194";
					margin-right: 6px;
					vertical-align: bottom;
				}

				.plugins .plugin-vuln-tr .vuln-message p {
					margin: .5em 0;
				}

				.plugins .plugin-vuln-tr .vuln-message li {
					margin-left: 27px;
					list-style-type: circle;
				}

				.plugins .hide-box-shadow {
					box-shadow: none !important;
				}

				<?php $this->print_plugins_page_styles(); ?>
				<?php endif; ?>
		</style>
		<?php
	}

	/**
	 * We need to do some trickery to keep the outline from appearing before our custom update/vuln notices.
	 * WP Core handles this in a way that we can't really intercept, so have to do our own thing.
	 */
	private function print_plugins_page_styles(): void {
		$plugins_needing_updates = array_keys( $this->update_data );
		$plugins_with_vulns      = array_keys( $this->codebase_info['plugins_vulns'] );

		foreach ( array_keys( $this->all_plugins ) as $plugin_file ) {
			$plugin_folder = explode( '/', $plugin_file )[0];

			$has_update = in_array( $plugin_file, $plugins_needing_updates, true );
			$has_vuln   = in_array( 'plugins/' . $plugin_folder, $plugins_with_vulns, true );

			if ( $has_update || $has_vuln ) {
				echo '#the-list tr[data-plugin="' . esc_attr( $plugin_file ) . '"] th { box-shadow: none; }';
				echo '#the-list tr[data-plugin="' . esc_attr( $plugin_file ) . '"] td { box-shadow: none; }';
			}
		}
	}
}
