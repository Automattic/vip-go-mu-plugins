<?php

/**
 * This class forces certain Jetpack modules to be on by default and
 * does not allow these to be deactivated.
 *
 * @TODO: Remove the deactivate links for forced modules for users with JS disabled
 * @TODO: Remove the "this module has been deactivated" notice when the module is forced (and therefore hasn't been deactivated at all)
 *
 * @package WPCOM_VIP_Jetpack_Mandatory
 **/
class WPCOM_VIP_Jetpack_Mandatory {

	/**
	 * An array of mandatory module names
	 *
	 * @var array
	 */
	protected $mandatory_modules = array(
		'manage',
		'monitor',
		// 'sso', // Disabled while we roll out force-2fa
		'stats',
		'vaultpress',
	);

	/**
	 * Initiate an instance of this class if one doesn't
	 * exist already. Return the WPCOM_VIP_Jetpack_Mandatory instance.
	 *
	 * @access @static
	 *
	 * @return WPCOM_VIP_Jetpack_Mandatory object The instance of WPCOM_VIP_Jetpack_Mandatory
	 */
	static public function init() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new WPCOM_VIP_Jetpack_Mandatory;
		}

		return $instance;

	}

	/**
	 * Class constructor. Handles hooking actions and filters.
	 */
	public function __construct() {
		add_action( 'admin_footer',                      array( $this, 'action_admin_footer_early' ), 5 );
		add_action( 'admin_footer',                      array( $this, 'action_admin_footer' ), 8 );
		add_action( 'load-jetpack_page_jetpack_modules', array( $this, 'action_load_jetpack_modules' ) );

		// @TODO: Add VIP scanner check to watch for people unhooking this
		add_filter( 'jetpack_get_default_modules',              array( $this, 'filter_jetpack_get_default_modules' ), 99 );
		// @TODO: Add VIP scanner check to watch for people unhooking this
		add_filter( 'pre_update_option_jetpack_active_modules', array( $this, 'filter_pre_update_option_jetpack_active_modules' ), 99, 2 );
	}

	// HOOKS
	// =====

	public function action_load_jetpack_modules() {
		$mandatory_css_url = WP_CONTENT_URL . '/mu-plugins/' . basename( __DIR__ ) . '/css/mandatory-settings.css';
		$mandatory_css_file = WP_CONTENT_DIR . '/mu-plugins/' . basename( __DIR__ ) . '/css/mandatory-settings.css';
		$mtime = filemtime( $mandatory_css_file );
		wp_enqueue_style( 'vip-jetpack-mandatory-settings', $mandatory_css_url, array(), $mtime );
	}

	/**
	 * Hooks the WP admin_footer action to add our list table JS template.
	 */
	public function action_admin_footer() {
		// We're adding our own JS template in, and this will override Jetpack's
		// @TODO: When this Jetpack issue is resolved, refactor our code accordingly: https://github.com/Automattic/jetpack/issues/2189
		$this->js_templates();
	}

	/**
	 * Hooks the WP admin_footer action early to output JSON encoded
	 * JS data listing the mandatory modules, this is used by our
	 * JS template.
	 */
	public function action_admin_footer_early() {
		$forced_modules = $this->mandatory_modules;
		// Code cribbed from WP_Scripts::localize()
		foreach ( (array) $forced_modules as $key => $value ) {
			if ( ! is_scalar($value) ) {
				$forced_modules[$key] = $value;
			} else {
				$forced_modules[$key] = html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8' );
			}
		}

		$output = "var wpcom_vip_jetpack_forced = " . wp_json_encode( $forced_modules ) . ';';
		echo "<script type='text/javascript'>" . PHP_EOL; // CDATA and type='text/javascript' is not needed for HTML 5
		echo "/* <![CDATA[ */" . PHP_EOL;
		echo $output . PHP_EOL;
		echo "/* ]]> */" . PHP_EOL;
		echo "</script>" . PHP_EOL;
	}

	/**
	 * Hooks the WP pre_update filter on the jetpack_active_modules option to
	 * add in our mandatory modules to the array.
	 *
	 * @FIXME: Non-JS users see the deactivate button, and if they click it they see a message saying the module has been deactivated even if it's not
	 *
	 * @param array $modules An array of Jetpack module slugs
	 *
	 * @return array An array of Jetpack module slugs
	 */
	public function filter_pre_update_option_jetpack_active_modules( $modules ) {
		return $this->add_mandatory_modules( $modules );
	}

	/**
	 * Hooks the JP jetpack_get_default_modules filter to add
	 * in our mandatory modules to the array.
	 *
	 * @param array $modules An array of Jetpack module slugs
	 *
	 * @return array An array of Jetpack module slugs
	 */
	public function filter_jetpack_get_default_modules( $modules ) {
		return $this->add_mandatory_modules( $modules );
	}

	// UTILITIES
	// =========

	/**
	 * A getter for the mandatory_modules property.
	 *
	 * @return array An array of mandatory Jetpack module slugs
	 */
	public function get_mandatory_modules() {
		return $this->mandatory_modules;
	}

	/**
	 * Provides a JS template for the JP module listing template. This overrides
	 * the JP template of the same purpose.
	 *
	 * This template is copied from the Jetpack_Modules_List_Table::js_templates
	 * with minor tweaks to not show the deactivate button if the module
	 * is mandatory.
	 */
	public function js_templates() {
		?>
		<script type="text/html" id="tmpl-Jetpack_Modules_List_Table_Template">
			<# var i = 0;
				if ( data.items.length ) {
				_.each( data.items, function( item, key, list ) {
				if ( item === undefined ) return;
				#>
				<tr class="jetpack-module <# if ( ++i % 2 ) { #> alternate<# } #><# if ( item.activated ) { #> active<# } #><# if ( ! item.available ) { #> unavailable<# } #>" id="{{{ item.module }}}">
					<th scope="row" class="check-column">
						<# if ( wpcom_vip_jetpack_forced.indexOf(item.module) === -1 ) { #>
							<input type="checkbox" name="modules[]" value="{{{ item.module }}}" />
						<# } #>
					</th>
					<td class='name column-name'>
						<span class='info'><a href="#">{{{ item.name }}}</a></span>
						<div class="row-actions">
							<# if ( item.configurable ) { #>
								<span class='configure'>{{{ item.configurable }}}</span>
							<# } #>
							<# if ( wpcom_vip_jetpack_forced.indexOf(item.module) !== -1 ) { #>
								<span class='wpcom-vip-no-delete' id="wpcom-vip-no-delete-{{ item.module }}"><?php _e( 'This module is required for WordPress.com VIP', 'wpcom-vip-jetpack' ); ?></span>
							<# } else if ( item.activated && 'vaultpress' !== item.module ) { #>
								<# if ( 'omnisearch' !== item.module ) { #>
									<span class='delete'><a href="<?php echo admin_url( 'admin.php' ); ?>?page=jetpack&#038;action=deactivate&#038;module={{{ item.module }}}&#038;_wpnonce={{{ item.deactivate_nonce }}}"><?php _e( 'Deactivate', 'jetpack' ); ?></a></span>
								<# } #>
							<# } else if ( item.available ) { #>
								<span class='activate'><a href="<?php echo admin_url( 'admin.php' ); ?>?page=jetpack&#038;action=activate&#038;module={{{ item.module }}}&#038;_wpnonce={{{ item.activate_nonce }}}"><?php _e( 'Activate', 'jetpack' ); ?></a></span>
							<# } #>
						</div>
					</td>
				</tr>
				<# }); } else { #>
					<tr class="no-modules-found">
						<td colspan="2"><?php esc_html_e( 'No Modules Found' , 'jetpack' ); ?></td>
					</tr>
				<# } #>
		</script>
		<?php
	}

	/**
	 * Takes an array of module slugs and adds our mandatory modules
	 * if they are not already present.
	 *
	 * @param array $modules An array of Jetpack module slugs
	 *
	 * @return array An array of Jetpack module slugs
	 */
	public function add_mandatory_modules( $modules ) {
		$modules = array_merge( $modules, $this->mandatory_modules );
		$modules = array_unique( $modules );
		$modules = array_values( $modules );
		return $modules;
	}

}

WPCOM_VIP_Jetpack_Mandatory::init();
