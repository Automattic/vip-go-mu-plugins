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
	private function __construct() {}

	public static function init_actions() {
		// To be able to replace the src, scripts should not be concatenated.
		if ( ! defined( 'CONCATENATE_SCRIPTS' ) ) {
			define( 'CONCATENATE_SCRIPTS', false );
		}

		$GLOBALS['concatenate_scripts'] = false;

		self::$plugin_dir_name = basename( __DIR__ );

		add_action( 'wp_default_scripts', array( __CLASS__, 'replace_scripts' ), -1 );

		add_action( 'admin_menu', array( __CLASS__, 'add_menu_item' ) );
		add_action( 'network_admin_menu', array( __CLASS__, 'add_menu_item' ) );

		// Add a link to the plugin's settings in the plugins list table.
		add_filter( 'plugin_action_links', array( __CLASS__, 'add_settings_link' ), 10, 2 );
		add_filter( 'network_admin_plugin_action_links', array( __CLASS__, 'add_settings_link' ), 10, 2 );

		add_action( 'admin_init', array( __CLASS__, 'save_settings' ) );

		// Print version info in the console.
		add_action( 'admin_print_footer_scripts', array( __CLASS__, 'print_versions' ), 100 );
		add_action( 'wp_print_footer_scripts', array( __CLASS__, 'print_versions' ), 100 );
	}

	public static function replace_scripts( $scripts ) {
		$settings = get_site_option( 'wp-jquery-test-settings', array() );
		$defaults = array(
			'version'   => 'default',
			'migrate'   => 'disable',
			'uiversion' => 'default',
		);

		$settings = wp_parse_args( $settings, $defaults );

		if ( 'default' === $settings['version'] ) {
			// If Migrate is disabled
			if ( 'disable' === $settings['migrate'] ) {
				// Register jQuery without jquery-migrate.js. For WordPress 5.4 and 5.5-alpha.
				self::set_script( $scripts, 'jquery', false, array( 'jquery-core' ), '1.12.4-wp' );
			} else {
				// For 5.5-beta1 or newer.
				self::set_script( $scripts, 'jquery', false, array( 'jquery-core', 'jquery-migrate' ), '1.12.4-wp' );
			}
		} elseif ( '3.5.1' === $settings['version'] ) {
			$assets_url = plugins_url( 'assets/', __FILE__ );

			if ( 'disable' === $settings['migrate'] ) {
				// Register jQuery without jquery-migrate.js
				self::set_script( $scripts, 'jquery', false, array( 'jquery-core' ), '3.5.1' );

				// Set 'jquery-core' to 3.5.1
				self::set_script( $scripts, 'jquery-core', $assets_url . 'jquery-3.5.1.min.js', array(), '3.5.1' );

				// Reset/remove 'jquery-migrate'
				// TBD: needed?
				self::set_script( $scripts, 'jquery-migrate', false, array() );
			} else {
				self::set_script( $scripts, 'jquery', false, array( 'jquery-core', 'jquery-migrate' ), '3.5.1' );
				self::set_script( $scripts, 'jquery-core', $assets_url . 'jquery-3.5.1.min.js', array(), '3.5.1' );
				self::set_script( $scripts, 'jquery-migrate', $assets_url . 'jquery-migrate-3.3.0.min.js', array(), '3.3.0' );
			}

			if ( '1.12.1' === $settings['uiversion'] ) {
				self::jquery_ui_1121( $scripts );
			}
		}
	}

	// Replace UI 1.11.4 with 1.12.1
	private static function jquery_ui_1121( $scripts ) {
		$assets_url = plugins_url( 'assets/ui', __FILE__ );
		$dev_suffix = wp_scripts_get_suffix( 'dev' );

		// The core.js in 1.12.1 only defines dependencies.
		// Here it is concatenated using another build task in Grunt.
		// The separate jQuery UI core parts are still present for AMD compatibility (is this needed?),
		// but are not registered in script-loader as all of them are in ui/core.js.
		self::set_script( $scripts, 'jquery-ui-core', "{$assets_url}/core{$dev_suffix}.js", array( 'jquery' ), '1.12.1', true );
		self::set_script( $scripts, 'jquery-effects-core', "{$assets_url}/effect{$dev_suffix}.js", array( 'jquery' ), '1.12.1', true );

		self::set_script( $scripts, 'jquery-effects-blind', "{$assets_url}/effect-blind{$dev_suffix}.js", array( 'jquery-effects-core' ), '1.12.1', true );
		self::set_script( $scripts, 'jquery-effects-bounce', "{$assets_url}/effect-bounce{$dev_suffix}.js", array( 'jquery-effects-core' ), '1.12.1', true );
		self::set_script( $scripts, 'jquery-effects-clip', "{$assets_url}/effect-clip{$dev_suffix}.js", array( 'jquery-effects-core' ), '1.12.1', true );
		self::set_script( $scripts, 'jquery-effects-drop', "{$assets_url}/effect-drop{$dev_suffix}.js", array( 'jquery-effects-core' ), '1.12.1', true );
		self::set_script( $scripts, 'jquery-effects-explode', "{$assets_url}/effect-explode{$dev_suffix}.js", array( 'jquery-effects-core' ), '1.12.1', true );
		self::set_script( $scripts, 'jquery-effects-fade', "{$assets_url}/effect-fade{$dev_suffix}.js", array( 'jquery-effects-core' ), '1.12.1', true );
		self::set_script( $scripts, 'jquery-effects-fold', "{$assets_url}/effect-fold{$dev_suffix}.js", array( 'jquery-effects-core' ), '1.12.1', true );
		self::set_script( $scripts, 'jquery-effects-highlight', "{$assets_url}/effect-highlight{$dev_suffix}.js", array( 'jquery-effects-core' ), '1.12.1', true );
		self::set_script( $scripts, 'jquery-effects-puff', "{$assets_url}/effect-puff{$dev_suffix}.js", array( 'jquery-effects-core', 'jquery-effects-scale' ), '1.12.1', true );
		self::set_script( $scripts, 'jquery-effects-pulsate', "{$assets_url}/effect-pulsate{$dev_suffix}.js", array( 'jquery-effects-core' ), '1.12.1', true );
		self::set_script( $scripts, 'jquery-effects-scale', "{$assets_url}/effect-scale{$dev_suffix}.js", array( 'jquery-effects-core', 'jquery-effects-size' ), '1.12.1', true );
		self::set_script( $scripts, 'jquery-effects-shake', "{$assets_url}/effect-shake{$dev_suffix}.js", array( 'jquery-effects-core' ), '1.12.1', true );
		self::set_script( $scripts, 'jquery-effects-size', "{$assets_url}/effect-size{$dev_suffix}.js", array( 'jquery-effects-core' ), '1.12.1', true );
		self::set_script( $scripts, 'jquery-effects-slide', "{$assets_url}/effect-slide{$dev_suffix}.js", array( 'jquery-effects-core' ), '1.12.1', true );
		self::set_script( $scripts, 'jquery-effects-transfer', "{$assets_url}/effect-transfer{$dev_suffix}.js", array( 'jquery-effects-core' ), '1.12.1', true );

		self::set_script( $scripts, 'jquery-ui-accordion', "{$assets_url}/accordion{$dev_suffix}.js", array( 'jquery-ui-core', 'jquery-ui-widget' ), '1.12.1', true );
		self::set_script( $scripts, 'jquery-ui-autocomplete', "{$assets_url}/autocomplete{$dev_suffix}.js", array( 'jquery-ui-menu', 'wp-a11y' ), '1.12.1', true );
		self::set_script( $scripts, 'jquery-ui-button', "{$assets_url}/button{$dev_suffix}.js", array( 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-controlgroup', 'jquery-ui-checkboxradio' ), '1.12.1', true );
		self::set_script( $scripts, 'jquery-ui-datepicker', "{$assets_url}/datepicker{$dev_suffix}.js", array( 'jquery-ui-core' ), '1.12.1', true );
		self::set_script( $scripts, 'jquery-ui-dialog', "{$assets_url}/dialog{$dev_suffix}.js", array( 'jquery-ui-resizable', 'jquery-ui-draggable', 'jquery-ui-button', 'jquery-ui-position' ), '1.12.1', true );
		self::set_script( $scripts, 'jquery-ui-draggable', "{$assets_url}/draggable{$dev_suffix}.js", array( 'jquery-ui-mouse' ), '1.12.1', true );
		self::set_script( $scripts, 'jquery-ui-droppable', "{$assets_url}/droppable{$dev_suffix}.js", array( 'jquery-ui-draggable' ), '1.12.1', true );
		self::set_script( $scripts, 'jquery-ui-menu', "{$assets_url}/menu{$dev_suffix}.js", array( 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-position' ), '1.12.1', true );
		self::set_script( $scripts, 'jquery-ui-mouse', "{$assets_url}/mouse{$dev_suffix}.js", array( 'jquery-ui-core', 'jquery-ui-widget' ), '1.12.1', true );
		self::set_script( $scripts, 'jquery-ui-position', "{$assets_url}/position{$dev_suffix}.js", array( 'jquery' ), '1.12.1', true );
		self::set_script( $scripts, 'jquery-ui-progressbar', "{$assets_url}/progressbar{$dev_suffix}.js", array( 'jquery-ui-core', 'jquery-ui-widget' ), '1.12.1', true );
		self::set_script( $scripts, 'jquery-ui-resizable', "{$assets_url}/resizable{$dev_suffix}.js", array( 'jquery-ui-mouse' ), '1.12.1', true );
		self::set_script( $scripts, 'jquery-ui-selectable', "{$assets_url}/selectable{$dev_suffix}.js", array( 'jquery-ui-mouse' ), '1.12.1', true );
		self::set_script( $scripts, 'jquery-ui-selectmenu', "{$assets_url}/selectmenu{$dev_suffix}.js", array( 'jquery-ui-menu' ), '1.12.1', true );
		self::set_script( $scripts, 'jquery-ui-slider', "{$assets_url}/slider{$dev_suffix}.js", array( 'jquery-ui-mouse' ), '1.12.1', true );
		self::set_script( $scripts, 'jquery-ui-sortable', "{$assets_url}/sortable{$dev_suffix}.js", array( 'jquery-ui-mouse' ), '1.12.1', true );
		self::set_script( $scripts, 'jquery-ui-spinner', "{$assets_url}/spinner{$dev_suffix}.js", array( 'jquery-ui-button' ), '1.12.1', true );
		self::set_script( $scripts, 'jquery-ui-tabs', "{$assets_url}/tabs{$dev_suffix}.js", array( 'jquery-ui-core', 'jquery-ui-widget' ), '1.12.1', true );
		self::set_script( $scripts, 'jquery-ui-tooltip', "{$assets_url}/tooltip{$dev_suffix}.js", array( 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-position' ), '1.12.1', true );
		self::set_script( $scripts, 'jquery-ui-widget', "{$assets_url}/widget{$dev_suffix}.js", array( 'jquery' ), '1.12.1', true );

		// New in 1.12.1
		self::set_script( $scripts, 'jquery-ui-checkboxradio', "{$assets_url}/checkboxradio{$dev_suffix}.js", array( 'jquery-ui-core', 'jquery-ui-widget' ), '1.12.1', true );
		self::set_script( $scripts, 'jquery-ui-controlgroup', "{$assets_url}/controlgroup{$dev_suffix}.js", array( 'jquery-ui-widget' ), '1.12.1', true );
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
			! current_user_can( 'activate_plugins' ) ||
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
			'3.5.1',
			'1.12.1',
		);

		$names = array(
			'version',
			'migrate',
			'uiversion',
		);

		foreach( $names as $name ) {
			$key = "jquery-test-{$name}";

			if ( ! empty( $_POST[ $key ] ) && in_array( $_POST[ $key ], $expected, true ) ) {
				$settings[ $name ] = $_POST[ $key ];
			} else {
				$settings[ $name ] = 'default';
			}
		}

		update_site_option( 'wp-jquery-test-settings', $settings );

		$redirect = self_admin_url( 'plugins.php?page=' . self::$plugin_dir_name . '&jqtest-settings-saved' );
		wp_safe_redirect( $redirect );
		exit;
	}

	// Plugin UI
	public static function settings_ui() {
		$settings = get_site_option( 'wp-jquery-test-settings', array() );
		$defaults = array(
			'version'   => 'default',
			'migrate'   => 'disable',
			'uiversion' => 'default',
		);

		$settings = wp_parse_args( $settings, $defaults );

		?>
		<div class="wrap" style="max-width: 42rem;">

		<h1><?php _e( 'Test jQuery Updates', 'wp-jquery-update-test' ); ?></h1>

		<?php if ( isset( $_GET['jqtest-settings-saved'] ) ) { ?>
		<div class="notice notice-success is-dismissible">
			<p><strong><?php _e( 'Settings saved.', 'wp-jquery-update-test' ); ?></strong></p>
		</div>
		<?php } ?>

		<p>
			<?php _e( 'This plugin is intended for testing of different versions of jQuery and jQuery UI before updating them in WordPress.', 'wp-jquery-update-test' ); ?>
			<?php _e( 'It is not intended for use in production.', 'wp-jquery-update-test' ); ?>
		</p>

		<p>
			<?php _e( 'It includes jQuery 3.5.1, jQuery Migrate 3.3.0, and jQuery UI 1.12.1.', 'wp-jquery-update-test' ); ?>
			<?php _e( 'jQuery UI has been re-built for full backwards compatibility with WordPress.', 'wp-jquery-update-test' ); ?>
		</p>

		<p>
			<?php _e( 'To test:', 'wp-jquery-update-test' ); ?>
		</p>

		<ol>
			<li>
				<?php _e( 'Use the current version of jQuery in WordPress but disable jQuery Migrate.', 'wp-jquery-update-test' ); ?>
				<?php _e( 'This is planned for WordPress 5.5 and is the default setting.', 'wp-jquery-update-test' ); ?>
			</li>
			<li>
				<?php _e( 'Latest jQuery with the latest jQuery Migrate.', 'wp-jquery-update-test' ); ?>
				<?php _e( 'More information:', 'wp-jquery-update-test' ); ?>
				<?php
					printf(
						__( '<a href="%s">jQuery Core 3.0 Upgrade Guide</a>,', 'wp-jquery-update-test' ),
						'https://jquery.com/upgrade-guide/3.0/'
					);
				?>
				<?php
					printf(
						__( '<a href="%s">jQuery Core 3.5 Upgrade Guide</a>.', 'wp-jquery-update-test' ),
						'https://jquery.com/upgrade-guide/3.5/'
					);
				?>
			</li>
			<li>
				<?php _e( 'Latest jQuery with latest jQuery Migrate and latest jQuery UI.', 'wp-jquery-update-test' ); ?>
				<?php _e( 'This is tentatively planned for WordPress 5.6 depending on test results.', 'wp-jquery-update-test' ); ?>
			</li>
			<li>
				<?php _e( 'Latest jQuery (without jQuery Migrate), and latest jQuery UI.', 'wp-jquery-update-test' ); ?>
				<?php _e( 'This is tentatively planned for WordPress 5.7 or later depending on test results.', 'wp-jquery-update-test' ); ?>
			</li>
		</ol>

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
						<input type="radio" name="jquery-test-version" id="version-351" value="3.5.1"
							<?php checked( $settings['version'] === '3.5.1' ); ?>
						/>
						<label for="version-351">3.5.1</label>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php _e( 'jQuery Migrate', 'wp-jquery-update-test' ); ?></th>
				<td>
					<p>
						<input type="radio" name="jquery-test-migrate" id="migrate-enable" value="enable"
							<?php checked( $settings['migrate'] === 'enable' ); ?>
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

			<tr class="classic-editor-user-options">
				<th scope="row"><?php _e( 'jQuery UI version', 'wp-jquery-update-test' ); ?></th>
				<td>
					<p>
						<input type="radio" name="jquery-test-uiversion" id="uiversion-default" value="default"
							<?php checked( $settings['uiversion'] === 'default' ); ?>
						/>
						<label for="uiversion-default"><?php _e( 'Default', 'wp-jquery-update-test' ); ?></label>
					</p>
					<p>
						<input type="radio" name="jquery-test-uiversion" id="uiversion-1121" value="1.12.1"
							<?php checked( $settings['uiversion'] === '1.12.1' ); ?>
						/>
						<label for="uiversion-1121">1.12.1</label>
					</p>
				</td>
			</tr>
		</table>
		<?php submit_button(); ?>
		</form>
		</div>
		<?php
	}

	public static function add_menu_item() {
		$menu_title = __( 'Test jQuery Updates', 'wp-jquery-update-test' );
		add_plugins_page( $menu_title, $menu_title, 'activate_plugins', self::$plugin_dir_name, array( __CLASS__, 'settings_ui' ) );
	}

	public static function add_settings_link( $links, $file ) {
		$plugin_basename = self::$plugin_dir_name . '/wp-jquery-update-test.php';

		if ( $file === $plugin_basename && current_user_can( 'activate_plugins' ) ) {
			// Prevent PHP warnings when a plugin uses this filter incorrectly.
			$links   = (array) $links;
			$url     = self_admin_url( 'plugins.php?page=' . self::$plugin_dir_name );
			$links[] = sprintf( '<a href="%s">%s</a>', $url, __( 'Settings', 'wp-jquery-update-test' ) );
		}

		return $links;
	}

	/**
	 * Set defaults on activation.
	 */
	public static function activate() {
		register_uninstall_hook( __FILE__, array( __CLASS__, 'uninstall' ) );

		$defaults = array(
			'version'   => 'default',
			'migrate'   => 'disable',
			'uiversion' => 'default',
		);

		add_site_option( 'wp-jquery-test-settings', $defaults );
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
