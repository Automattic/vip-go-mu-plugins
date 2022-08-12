<?php

namespace Automattic\VIP\CodebaseManager;

class PluginsManager {
	private $update_data;
	private $column_count;

	public function init() {
		if ( ! $this->user_can_manage_plugins() ) {
			// Currently nothing to do here for users without necessary permissions.
			return;
		}

		$this->update_data = $this->fetch_plugins_with_updates();

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

		$plugin = new Plugin( $plugin_file, $plugin_data, $update_data );
		$plugin->display_version_update_information( $this->column_count );
	}

	/**
	 * Hijack the "Plugins" menu item and insert a notification count of available updates.
	 */
	public function display_update_count_bubble_in_menu(): void {
		global $menu;

		if ( isset( $menu ) && is_array( $menu ) ) {
			foreach ( $menu as $menu_key => $menu_data ) {
				if ( 'plugins.php' === $menu_data[2] ) {
					$count         = count( $this->update_data );
					$update_bubble = sprintf( '<span class="vip-update-plugins count-%s"><span class="vip-plugin-count">%s</span></span>', $count, number_format_i18n( $count ) );

					/* translators: Number of available plugin updates */
					$menu[ $menu_key ][0] = sprintf( __( 'Plugins %s' ), $update_bubble ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				}
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

			<?php
			if ( 'plugins.php' === $hook_suffix ) {
				$this->print_plugins_page_styles();
			}
			?>

		</style>
		<?php
	}

	/**
	 * We need to do some trickery to keep the outline from appearing before our custom update notices.
	 * WP Core handles this in a way that we can't really intercept, so have to do our own thing.
	 */
	private function print_plugins_page_styles(): void {
		$plugins_needing_updates = array_keys( $this->update_data );

		if ( empty( $plugins_needing_updates ) ) {
			return;
		}

		foreach ( $plugins_needing_updates as $plugin_file ) {
			echo '#the-list tr[data-plugin="' . esc_attr( $plugin_file ) . '"] th { box-shadow: none; }';
			echo '#the-list tr[data-plugin="' . esc_attr( $plugin_file ) . '"] td { box-shadow: none; }';
		}
	}
}
