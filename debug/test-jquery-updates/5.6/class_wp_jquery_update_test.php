<?php
/*
 * Test jQuery Updates plugin: WP_Jquery_Update_Test class
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Invalid request.' );
}

if ( ! class_exists( 'WP_Jquery_Update_Test' ) ) :
class WP_Jquery_Update_Test {

	private static $plugin_dir_name;
	private static $is_supported;

	private static $default_settings = array(
		'version'        => 'default',
		'migrate'        => 'default',
		'plugin_version' => '2.0',
	);

	private function __construct() {}

	public static function init_actions() {
		self::$plugin_dir_name = basename( __DIR__ );

		// Support WP version 5.6 and 5.7 alpha/beta/RC.
		self::$is_supported = version_compare( $GLOBALS['wp_version'], '5.7', '<' );

		// Add a link to the plugin's settings in the plugins list table.
		add_filter( 'plugin_action_links', array( __CLASS__, 'add_settings_link' ), 10, 2 );
		add_filter( 'network_admin_plugin_action_links', array( __CLASS__, 'add_settings_link' ), 10, 2 );

		add_action( 'admin_menu', array( __CLASS__, 'add_menu_item' ) );
		add_action( 'network_admin_menu', array( __CLASS__, 'add_menu_item' ) );

		if ( ! self::$is_supported ) {
			return;
		}

		// To be able to replace the src, scripts should not be concatenated.
		if ( ! defined( 'CONCATENATE_SCRIPTS' ) ) {
			define( 'CONCATENATE_SCRIPTS', false );
		}

		$GLOBALS['concatenate_scripts'] = false;

		add_action( 'wp_default_scripts', array( __CLASS__, 'replace_scripts' ), -1 );

		add_action( 'admin_init', array( __CLASS__, 'save_settings' ) );

		// Print version info in the console.
		add_action( 'admin_print_footer_scripts', array( __CLASS__, 'print_versions' ), 100 );
		add_action( 'wp_print_footer_scripts', array( __CLASS__, 'print_versions' ), 100 );
	}

	public static function replace_scripts( $scripts ) {
		$settings = self::parse_settings();

		if ( 'default' === $settings['version'] ) {
			if ( 'disable' === $settings['migrate'] ) {
				// Register jQuery without jquery-migrate.js.
				self::set_script( $scripts, 'jquery', false, array( 'jquery-core' ), '3.5.1' );
			}
		} elseif ( '1.12.4' === $settings['version'] ) {
			$assets_url = plugins_url( 'assets/', __FILE__ );

			// Set 'jquery-core' to 1.12.4-wp.
			self::set_script( $scripts, 'jquery-core', $assets_url . 'jquery-1.12.4-wp.min.js', array(), '1.12.4-wp' );
			// Set 'jquery-migrate' to 1.4.1.
			self::set_script( $scripts, 'jquery-migrate', $assets_url . 'jquery-migrate-1.4.1.js', array(), '1,4,1' );

			$deps = array( 'jquery-core' );

			if ( 'disable' !== $settings['migrate'] ) {
				$deps[] = 'jquery-migrate';
			}

			self::set_script( $scripts, 'jquery', false, $deps, '1.12.4-wp' );
		}
	}

	private static function parse_settings() {
		$settings = get_site_option( 'wp-jquery-test-settings', array() );

		// Reset the settings for v2.0.
		if ( empty( $settings['plugin_version'] ) ) {
			$settings = array();
		}

		return wp_parse_args( $settings, self::$default_settings );
	}

	// Pre-register scripts on 'wp_default_scripts' action, they won't be overwritten by $wp_scripts->add().
	private static function set_script( $scripts, $handle, $src, $deps = array(), $ver = false, $in_footer = false ) {
		$script = $scripts->query( $handle, 'registered' );

		if ( $script ) {
			// If already added
			$script->src  = $src;
			$script->deps = $deps;
			$script->ver  = $ver;
			$script->args = $in_footer;

			unset( $script->extra['group'] );

			if ( $in_footer ) {
				$script->add_data( 'group', 1 );
			}
		} else {
			// Add the script
			if ( $in_footer ) {
				$scripts->add( $handle, $src, $deps, $ver, 1 );
			} else {
				$scripts->add( $handle, $src, $deps, $ver );
			}
		}
	}

	public static function save_settings() {
		if ( ! isset( $_POST['wp-jquery-test-save'] ) ) {
			return;
		}

		if (
			! current_user_can( 'install_plugins' ) ||
			! wp_verify_nonce( $_POST['wp-jquery-test-save'], 'wp-jquery-test-settings' )
		) {
			wp_die( 'Invalid URL.' );
		}

		$settings = array();

		// Possible values across all settings.
		$expected = array(
			'default',
			'enable',
			'disable',
			'1.12.4',
		);

		$names = array(
			'version',
			'migrate',
		);

		foreach( $names as $name ) {
			$key = "jquery-test-{$name}";

			if ( ! empty( $_POST[ $key ] ) && in_array( $_POST[ $key ], $expected, true ) ) {
				$settings[ $name ] = $_POST[ $key ];
			} else {
				$settings[ $name ] = 'default';
			}
		}

		$settings['plugin_version'] = '2.0';

		update_site_option( 'wp-jquery-test-settings', $settings );

		$redirect = self_admin_url( 'plugins.php?page=' . self::$plugin_dir_name . '&jqtest-settings-saved' );
		wp_safe_redirect( $redirect );
		exit;
	}

	// Plugin UI
	public static function settings_ui() {
		$settings = self::parse_settings();

		?>
		<div class="wrap" style="max-width: 42rem;">

		<h1><?php _e( 'Test jQuery Updates', 'wp-jquery-update-test' ); ?></h1>

		<?php if ( ! self::$is_supported ) { ?>
			<div class="notice notice-error">
				<p><strong><?php _e( 'WordPress version not supported.', 'wp-jquery-update-test' ); ?></strong></p>
			</div>
			<p>
				<?php _e( 'This plugin is intended for testing of jQuery and jQuery Migrate in WordPress 5.6.', 'wp-jquery-update-test' ); ?>
				<?php

				printf(
					__( 'However your WordPress version appears to be %s.', 'wp-jquery-update-test' ),
					esc_html( $GLOBALS['wp_version'] )
				);

				?>
			</p>
			<p>
				<?php _e( 'For testing in WordPress 5.5 or earlier please install version 1.0.2 of the plugin.', 'wp-jquery-update-test' ); ?>
				<?php _e( 'WordPress version 5.7 and newer are not supported yet.', 'wp-jquery-update-test' ); ?>
			</p>
		<?php } else { ?>

			<?php if ( isset( $_GET['jqtest-settings-saved'] ) ) { ?>
			<div class="notice notice-success is-dismissible">
				<p><strong><?php _e( 'Settings saved.', 'wp-jquery-update-test' ); ?></strong></p>
			</div>
			<?php } ?>

			<p>
				<?php _e( 'This plugin is intended for testing of jQuery and jQuery Migrate in WordPress 5.6.', 'wp-jquery-update-test' ); ?>
				<strong><?php _e( 'It is not intended for use in production.', 'wp-jquery-update-test' ); ?></strong>
				<?php

				printf(
					__( 'If you have installed Test jQuery Updates on a live website, please uninstall it and use <a href="%s">Enable jQuery Migrate Helper</a> instead.', 'wp-jquery-update-test' ),
					esc_url( 'https://wordpress.org/plugins/enable-jquery-migrate-helper/' )
				);

				?>
			</p>

			<p>
				<?php
					printf(
						__( 'If you find a jQuery related bug <a href="%s">please report it</a>.', 'wp-jquery-update-test' ),
						'https://github.com/WordPress/wp-jquery-update-test'
					);
				?>
				<?php
					printf(
						__( 'If the bug is in a jQuery plugin please also check if there is <a href="%s">a new version on NPM</a>.', 'wp-jquery-update-test' ),
						'https://www.npmjs.com/search?q=keywords:jquery-plugin'
					);
				?>
				<?php _e( 'When reporting an issue please include the versions of jQuery, jQuery Migrate, and jQuery UI.', 'wp-jquery-update-test' ); ?>
				<?php _e( 'This plugin outputs these versions in the browser console.', 'wp-jquery-update-test' ); ?>
			</p>

			<form method="post">
			<?php wp_nonce_field( 'wp-jquery-test-settings', 'wp-jquery-test-save' ); ?>
			<table class="form-table">
				<tr class="classic-editor-user-options">
					<th scope="row"><?php _e( 'jQuery version', 'wp-jquery-update-test' ); ?></th>
					<td>
						<p>
							<input type="radio" name="jquery-test-version" id="version-default" value="default"
								<?php checked( $settings['version'] === 'default' ); ?>
							/>
							<label for="version-default"><?php _e( 'Default', 'wp-jquery-update-test' ); ?></label>
						</p>
						<p>
							<input type="radio" name="jquery-test-version" id="version-1.12.4" value="1.12.4"
								<?php checked( $settings['version'] === '1.12.4' ); ?>
							/>
							<label for="version-1.12.4">1.12.4</label>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php _e( 'jQuery Migrate', 'wp-jquery-update-test' ); ?></th>
					<td>
						<p>
							<input type="radio" name="jquery-test-migrate" id="migrate-enable" value="enable"
								<?php checked( $settings['migrate'] === 'enable' || $settings['migrate'] === 'default' ); ?>
							/>
							<label for="migrate-enable"><?php _e( 'Enable', 'wp-jquery-update-test' ); ?></label>
						</p>
						<p>
							<input type="radio" name="jquery-test-migrate" id="migrate-disable" value="disable"
								<?php checked( $settings['migrate'] === 'disable' ); ?>
							/>
							<label for="migrate-disable"><?php _e( 'Disable', 'wp-jquery-update-test' ); ?></label>
						</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
			</form>
			</div>
		<?php }
	}

	public static function add_menu_item() {
		$menu_title = __( 'Test jQuery Updates', 'wp-jquery-update-test' );
		add_plugins_page( $menu_title, $menu_title, 'install_plugins', self::$plugin_dir_name, array( __CLASS__, 'settings_ui' ) );
	}

	public static function add_settings_link( $links, $file ) {
		$plugin_basename = self::$plugin_dir_name . '/wp-jquery-update-test.php';

		if ( $file === $plugin_basename && current_user_can( 'install_plugins' ) ) {
			// Prevent PHP warnings when a plugin uses this filter incorrectly.
			$links = (array) $links;

			if ( ! self::$is_supported ) {
				$text = __( 'WordPress version not supported.', 'wp-jquery-update-test' );
				$links['wp-jquery-update-test button-link-delete'] = $text;
			} else {
				$url  = self_admin_url( 'plugins.php?page=' . self::$plugin_dir_name );
				$text = sprintf( '<a href="%s">%s</a>', $url, __( 'Settings', 'wp-jquery-update-test' ) );
				$links['wp-jquery-update-test'] = $text;
			}
		}

		return $links;
	}

	/**
	 * Set defaults on activation.
	 */
	public static function activate() {
		register_uninstall_hook( __FILE__, array( __CLASS__, 'uninstall' ) );

		add_site_option( 'wp-jquery-test-settings', self::$default_settings );
	}

	/**
	 * Delete the options on uninstall.
	 */
	public static function uninstall() {
		delete_site_option( 'wp-jquery-test-settings' );
	}

	/**
	 * Print versions info in the console.
	 */
	public static function print_versions() {
		?>
		<script>
		if ( window.console && window.console.log && window.jQuery ) {
			window.jQuery( function( $ ) {
				var jquery = $.fn.jquery || 'unknown';
				var migrate = $.migrateVersion || 'not available';
				var ui = ( $.ui && $.ui.version ) || 'not available';

				window.console.log(
					'WordPress jQuery:', jquery + ',',
					'Migrate:', migrate + ',',
					'UI:', ui
				);
			} );
		}
		</script>
		<?php
	}
}

add_action( 'plugins_loaded', array( 'WP_Jquery_Update_Test', 'init_actions' ) );
endif;
