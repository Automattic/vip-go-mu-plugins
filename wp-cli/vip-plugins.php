<?php
/**
 * Explore the VIP Shared Plugins repo
 */

class VIP_Plugin_Command extends WPCOM_VIP_CLI_Command {

	public $fields = array(
			'name',
			'author',
			'vip_version',
			'wporg_version',
			'slug',
		);

	public $vip_plugins;

	public $vip_plugins_wporg_details;

	function __construct() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	/**
	 * List details about all of the plugins in the VIP Shared Plugins repo
	 *
	 * @subcommand list
	 * @synopsis [--filter=<filter>] [--format=<format>]
	 */
	public function _list( $args, $assoc_args ) {

		$defaults = array(
				'filter'          => '',
				'format'          => 'table',
			);
		$assoc_args = array_merge( $defaults, $assoc_args );

		$vip_plugins = $this->get_vip_plugins();

		$plugins_output = array();
		foreach( $vip_plugins as $plugin_path => $vip_plugin ) {
			$plugin_output = new \stdClass;

			$plugin_slug = explode( '/', $plugin_path );
			$plugin_slug = $plugin_slug[0];

			$plugin_output->name = $vip_plugin['Name'];
			$plugin_output->author = $vip_plugin['Author'];
			$plugin_output->vip_version = $vip_plugin['Version'];
			$plugin_output->wporg_version = $this->get_plugin_wporg_version( $plugin_path );
			$plugin_output->slug = $plugin_slug;

			switch ( $assoc_args['filter'] ) {
				case 'outdated':
					if ( $plugin_output->wporg_version && version_compare( $plugin_output->wporg_version, $plugin_output->vip_version ) )
						$plugins_output[] = $plugin_output;
					break;
				default:
					$plugins_output[] = $plugin_output;
					break;
			}
		}

		\WP_CLI\utils\format_items( $assoc_args['format'], $plugins_output, $this->fields );

	}

	/**
	 * List active plugins for a blog
	 *
	 * @subcommand get-active-plugins-for-blog
	 */
	public function get_active_plugins_for_blog( $args, $assoc_args ) {

		WP_CLI::log( 'fetching active plugins ...' );

		foreach ( $this->get_vip_plugins() as $plugin_file => $plugin_data ) {

			$plugin_folder = basename( dirname( $plugin_file ) );

			$plugin_status = WPcom_VIP_Plugins_UI()->is_plugin_active( $plugin_folder ) ? 'active' : 'inactive';

			if ( 'active' === $plugin_status ) {
				WP_CLI::log(  $plugin_folder );
			}
		}

		WP_CLI::log( '... finished fetching active plugins' );
	}


	/**
	 * Deactivate a VIP Plugin
	 *
	 * @subcommand deactivate <plugin>
	 * @synopsis [--force]
	 */
	public function deactivate_vip_plugin( $args, $assoc_args ) {
		$plugin = sanitize_key( $args[0] );

		$this->args = wp_parse_args( $assoc_args, array(
			'force' => false,
		));

		if ( ! isset( $plugin ) || empty( $plugin ) ) {
			WP_CLI::error( sprintf( 'No plugin specified. Please specify a plugin and run the command once again.' ) );
		}

		$plugin_ui = WPcom_VIP_Plugins_UI::instance();
		$deactivated = $plugin_ui->deactivate_plugin( $plugin , $this->args['force'] );

		if ( true === $deactivated ) {
			WP_CLI::line( sprintf( 'Success! The %s plugin was deactivated', $plugin ) );
		} else if ( ! $this->args['force'] ) {
			WP_CLI::line( 'Plugin deactivation failed. Perhaps you meant to use --force?' );
		} else {
			WP_CLI::line( 'Plugin deactivation failed. The plugin is really not stored in the option.' );
		}

	}

	/**
	 * Activate a VIP Plugin
	 *
	 * @subcommand activate <plugin>
	 */
	public function activate_vip_plugin( $args, $assoc_args ) {
		$plugin = sanitize_key( $args[0] );

		$this->args = wp_parse_args( $assoc_args, array() );

		if ( ! isset( $plugin ) || empty( $plugin ) ) {
			WP_CLI::error( sprintf( 'No plugin specified. Please specify a plugin and run the command once again.' ) );
		}

		$plugin_ui = WPcom_VIP_Plugins_UI::instance();
		$activated = $plugin_ui->activate_plugin( $plugin );

		if ( true === $activated ) {
			WP_CLI::line( sprintf( 'The %s plugin was successfully activated', $plugin ) );
		} else {
			WP_CLI::line( sprintf( 'Could not activate the %s plugin. Check the spelling please and try it again.', $plugin ) );
		}
	}

	/**
	 * Check if a plugin is active on a VIP
	 *
	 * @subcommand is-active <plugin>
	 * @synopsis [--activation_type=<ui|admin|theme>]
	 */
	public function is_vip_plugin_active( $args, $assoc_args ) {
		$plugin = sanitize_key( $args[0] );

		$this->args = wp_parse_args( $assoc_args, array(
			'activation_type' => false,
		));

		$this->args['activation_type'] = mb_strtolower( $this->args['activation_type'] );

		//standardise the naming
		if ( true === in_array( $this->args['activation_type'], array( 'ui', 'admin' ), true ) ) {
			$this->args['activation_type'] = 'option';
		}

		//standardise the naming
		if ( true === in_array( $this->args['type'], array( 'theme' ), true ) ) {
			$this->args['activation_type'] = 'manual';
		}

		if ( ! isset( $plugin ) || empty( $plugin ) ) {
			WP_CLI::error( sprintf( 'No plugin specified. Please specify a plugin and run the command once again.' ) );
		}

		// Get the status of this plugin
		$plugin_ui = WPcom_VIP_Plugins_UI::instance();
		$active = $plugin_ui->is_plugin_active( $plugin );

		// Let's report which site we're on, just in case we're running this on multiple sites at once
		$site = get_site_url();

		if ( ! isset( $this->args['activation_type'] ) ) {
			switch ( $active ) {
				case 'option' :
					WP_CLI::line( sprintf( '%s: Plugin %s is activate via UI', $site, $plugin ) );
					break;
				case 'manual' :
					WP_CLI::line( sprintf( '%s: Plugin %s is active via theme', $site, $plugin ) );
					break;
				default:
					WP_CLI::line( sprintf( '%s: Plugin %s is not active on the site', $site, plugin ) );
			}
		} else {
			if ( $active === $this->args['activation_type'] ) {
				WP_CLI::line( sprintf( '%s: Active', $site ) );
			} else {
				WP_CLI::line( sprintf( '%s: Not active', $site ) );
			}
		}

	}


	/**
	 * Get details on all of the VIP plugins
	 */
	private function get_vip_plugins() {
		return get_plugins( '/../mu-plugins/shared-plugins' );
	}

	/**
	 * Get the current WordPress.org version of a plugin
	 */
	private function get_plugin_wporg_version( $plugin_path ) {
		$wporg_details = $this->get_plugin_wporg_details( $plugin_path );
		if ( isset( $wporg_details->new_version ) )
			return $wporg_details->new_version;
		else
			return false;
	}

	/**
	 * Refresh the local cache of plugin details on WordPress.org
	 * Partially stolen from core's wp_update_plugins()
	 */
	private function get_plugin_wporg_details( $plugin_path ) {

		if ( isset( $this->vip_plugins_wporg_details[$plugin_path] ) )
			return $this->vip_plugins_wporg_details[$plugin_path];

		if ( $wporg_details = wp_cache_get( 'vip_plugins_wporg_details', 'wp-cli' ) ) {
			$this->vip_plugins_wporg_details = $wporg_details;
			return $this->vip_plugins_wporg_details[$plugin_path];
		}

		$to_send = new stdClass;
		$to_send->plugins = $this->get_vip_plugins();

		$options = array(
			'timeout' => '30',
			'body' => array( 'plugins' => serialize( $to_send ) ),
		);

		$raw_response = wp_remote_post( 'http://api.wordpress.org/plugins/update-check/1.0/', $options );

		if ( is_wp_error( $raw_response ) || 200 != wp_remote_retrieve_response_code( $raw_response ) )
			return false;

		$response = maybe_unserialize( wp_remote_retrieve_body( $raw_response ) );

		if ( ! is_array( $response ) )
			$response = array();

		$this->vip_plugins_wporg_details = $response;
		wp_cache_set( 'vip_plugins_wporg_details', $this->vip_plugins_wporg_details, 'wp-cli', 900 );
		return $this->vip_plugins_wporg_details[$plugin_path];
	}

}

WP_CLI::add_command( 'vip plugin', 'VIP_Plugin_Command' );
