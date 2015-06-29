<?php
/**
 * Creates and populates the menu items for the admin bar status menu.
 * The status menu displays a different icon depending on the status and, on hover,
 * provides status information.
 */

require_once ( LP_PLUGIN_PATH . 'php/livepress-config.php' );

class LivePress_Admin_Bar_Status_Menu {

	/**
	 * @access private
	 * @var array $options Options.
	 */
	private $options = array();

	function __construct() {
		add_action( 'admin_bar_menu',                   array( $this, 'admin_bar_menu' ), 100, 1 ); // priority influences position
		add_action( 'admin_enqueue_scripts',            array( $this, 'enqueue' ) );
		add_action( 'wp_enqueue_scripts',               array( $this, 'enqueue' ) );

		$this->livepress_config = LivePress_Config::get_instance();
		$this->options = get_option( LivePress_Administration::$options_name );
	}

	/**
	 * Enqueue a stylesheets and scripts to handle the LivePress toolbar.
	 */
	function enqueue() {
		if ( ! current_user_can( 'manage_options' ) || ! is_admin_bar_showing() ) {
			return;
		}

		if ( $this->livepress_config->script_debug() ) {
			wp_enqueue_script( 'livepress-toolbar', LP_PLUGIN_URL . 'js/livepress-admin-bar.full.js', array( 'jquery' ) );
		} else {
			wp_enqueue_script( 'livepress-toolbar', LP_PLUGIN_URL . 'js/livepress-admin-bar.min.js', array( 'jquery' ) );
		}

		wp_enqueue_style( 'livepress_main_sheets', LP_PLUGIN_URL . 'css/livepress.css' );
		wp_enqueue_style( 'wp-jquery-ui-dialog' );

	}

	/**
	 * Add status menu to the admin bar.
	 *
	 * Ideally, each status comes with an action to take. The action requires
	 * permission. Also ideally, this just hooks into a LivePress status API.
	 *
	 * @author tddewey
	 *
	 * @param WP_Admin_Bar $wp_admin_bar The WP_Admin_Bar object.
	 *
	 * @return void
	 */
	function admin_bar_menu( $wp_admin_bar ) {

		if ( ! current_user_can( 'manage_options' ) || ! is_admin() ) {
			return;
		}

		$class = 'livepress-status-menu';

		$status = self::get_status();

		$wp_admin_bar->add_menu( array(
			'id'     => 'livepress-status',
			'title'  => '<span class="ab-icon"></span><span class="ab-label">' . esc_html__( 'LivePress', 'livepress' ) . '</span>',
			'href'   => '',
			'sticky' => true,
			'meta'   => array(
				'title' => 'connected' == $status ?
					esc_html__( 'LivePress connected.', 'livepress' ) :
					esc_html__( 'LivePress connection error.', 'livepress' ),
				'class' => $class . ' ' . $status,
			),
		) );
	}

	/**
	 * Check the status of the LivePress Server API.
	 *
	 * Method makes a GET request to check the current blog status.  If the request
	 * returns OK, it's assumed that the server is fine. On error, assume an arror. If
	 * LivePress is disabled, return disabled  .
	 *
	 * @author Eric Mann
	 *
	 * @return string Current API status. connected|disconnected|disabled
	 */
	private function get_status() {

		$status  = 'disconnected';
		if ( false == $this->options || ! array_key_exists( 'api_key', $this->options ) ){
			return $status;
		}
		$api_key = $this->options['api_key'];

		if ( ! class_exists( 'WP_Http' ) ) {
			include_once( ABSPATH . WPINC. '/class-http.php' ); }

			$post_vars = array(
				'address' => get_bloginfo( 'url' ),
				'api_key' => $api_key
			);

			$response = wp_remote_post(
				$this->livepress_config->livepress_service_host() . '/blog/get',
				array( 'reject_unsafe_urls' => false, 'body' => $post_vars )
			);

			if ( ! is_wp_error( $response ) && $response['response']['code'] < 300 ) {
				$status = 'connected';
			}

			return $status;
	}

}

$livepress_admin_bar_status_menu = new LivePress_Admin_Bar_Status_Menu();
