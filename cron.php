<?php
/*
 Plugin Name: WP-Cron Control Revisited
 Plugin URI:
 Description: Take control of wp-cron execution.
 Author: Erick Hitter, Automattic
 Version: 0.1
 Text Domain: wp-cron-control-revisited
 */

class WP_Cron_Control_Revisited {
	/**
	 * Class instance
	 */
	private static $__instance = null;

	public static function instance() {
		if ( ! is_a( self::$__instance, __CLASS__ ) ) {
			self::$__instance = new self;
		}

		return self::$__instance;
	}

	/**
	 * PLUGIN SETUP
	 */

	/**
	 * Class properties
	 */
	private $namespace = 'wp-cron-control-revisited/v1';
	private $secret    = null;

	/**
	 * Register hooks
	 */
	private function __construct() {
		add_action( 'muplugins_loaded', array( $this, 'block_direct_cron' ) );

		if ( defined( 'WP_CRON_CONTROL_SECRET' ) ) {
			$this->secret = WP_CRON_CONTROL_SECRET;

			add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
		} else {
			add_action( 'admin_notices', array( $this, 'admin_notice' ) );
		}
	}

	/**
	 * Block direct cron execution as early as possible
	 */
	public function block_direct_cron() {
		if ( false !== strpos( $_SERVER['REQUEST_URI'], '/wp-cron.php' ) ) {
			status_header( 403 );
			exit;
		}
	}

	/**
	 * Register API routes
	 */
	public function rest_api_init() {
		register_rest_route( $this->namespace, '/events/', array(
			'methods'   => 'GET',
			'callback' => array( $this, 'get_events' ),
		) );
	}

	/**
	 * Display an error if the plugin's conditions aren't met
	 */
	public function admin_notice() {
		$error = sprintf( __( '<strong>%1$s</strong>: To use this plugin, define the constant %2$s.', 'wp-cron-control-revisited' ), 'WP-Cron Control Revisited', '<code>WP_CRON_CONTROL_SECRET</code>' );

		?>
		<div class="notice notice-error">
			<p><?php echo $error; ?></p>
		</div>
		<?php
	}

	/**
	 * PLUGIN FUNCTIONALITY
	 */

	/**
	 * List events pending for the current period
	 */
	public function get_events() {
		return new WP_REST_Response( '¯\_(ツ)_/¯' );
	}
}

WP_Cron_Control_Revisited::instance();