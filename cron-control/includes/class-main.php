<?php
/**
 * Plugin's central class, responsible for loading its functionality when appropriate
 *
 * @package a8c_Cron_Control
 */

namespace Automattic\WP\Cron_Control;

use Automattic\WP\Cron_Control\CLI;
use WP_CLI;

class Main extends Singleton {

	protected function class_init() {
		$missing_requirements = $this->check_requirements();
		if ( ! empty( $missing_requirements ) ) {
			$this->alert_for_missing_requirements( $missing_requirements );

			// Avoid loading any of the rest of the plugin.
			return;
		}

		$this->load_plugin_classes();
		$this->block_normal_cron_execution();
	}

	private function check_requirements() {
		global $wp_version;

		$missing_reqs = [];

		if ( ! defined( '\WP_CRON_CONTROL_SECRET' ) ) {
			/* translators: 1: Constant name */
			$missing_reqs[] = sprintf( __( 'Must define the constant %1$s.', 'automattic-cron-control' ), '<code>WP_CRON_CONTROL_SECRET</code>' );
		}

		$required_php_version = '7.4';
		if ( version_compare( phpversion(), $required_php_version, '<' ) ) {
			/* translators: 1: PHP version */
			$missing_reqs[] = sprintf( __( 'The PHP version must be %1$s or above.', 'automattic-cron-control' ), $required_php_version );
		}

		$required_wp_version = '5.1';
		if ( version_compare( $wp_version, $required_wp_version, '<' ) ) {
			/* translators: 1: WP version */
			$missing_reqs[] = sprintf( __( 'The WP version must be %1$s or above.', 'automattic-cron-control' ), $required_wp_version );
		}

		return $missing_reqs;
	}

	private function alert_for_missing_requirements( $missing_requirements ) {
		foreach ( $missing_requirements as $requirement_message ) {
			trigger_error( 'Cron-Control: ' . $requirement_message, E_USER_WARNING );
		}

		$admin_message = '<strong>Cron Control</strong>: ' . implode( ' ', $missing_requirements );
		add_action( 'admin_notices', function () use ( $admin_message ) {
			?>
			<div class="notice notice-error">
				<p><?php echo wp_kses( $admin_message, [ 'strong' => [], 'code' => [] ] ); ?></p>
			</div>
			<?php
		} );
	}

	/**
	 * Load remaining classes
	 *
	 * Order here is somewhat important, as most classes depend on the Event Store,
	 * but we don't want to load it prematurely.
	 */
	private function load_plugin_classes() {
		require __DIR__ . '/constants.php';
		require __DIR__ . '/utils.php';
		require __DIR__ . '/class-events-store.php';
		require __DIR__ . '/class-lock.php';
		require __DIR__ . '/class-event.php';
		require __DIR__ . '/class-events.php';
		require __DIR__ . '/class-internal-events.php';
		require __DIR__ . '/class-rest-api.php';
		require __DIR__ . '/functions.php';
		require __DIR__ . '/wp-adapter.php';

		Events_Store::instance();
		Events::instance();
		Internal_Events::instance();
		REST_API::instance();

		if ( Events_Store::is_installed() ) {
			// Once we've confirmed that the table is installed, start taking over the core cron APIs.
			register_adapter_hooks();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require __DIR__ . '/wp-cli.php';
			CLI\prepare_environment();
		}
	}

	private function block_normal_cron_execution() {
		$block_action = did_action( 'muplugins_loaded' ) ? 'plugins_loaded' : 'muplugins_loaded';
		add_action( $block_action, array( $this, 'block_direct_cron' ) );

		add_filter( 'cron_request', array( $this, 'block_spawn_cron' ) );
		remove_action( 'init', 'wp_cron' );

		$this->set_disable_cron_constants();
	}

	/**
	 * Block direct cron execution as early as possible
	 *
	 * NOTE: We cannot influence the response if php-fpm is in use, as WP core calls fastcgi_finish_request() very early on
	 */
	public function block_direct_cron() {
		if ( false !== stripos( $_SERVER['REQUEST_URI'], '/wp-cron.php' ) || false !== stripos( $_SERVER['SCRIPT_NAME'], '/wp-cron.php' ) ) {
			$wp_error = new \WP_Error( 'forbidden', __( 'Normal cron execution is blocked when the Cron Control plugin is active.', 'automattic-cron-control' ) );
			wp_send_json_error( $wp_error, 403 );
		}
	}

	/**
	 * Block the `spawn_cron()` function
	 *
	 * @param array $spawn_cron_args Arguments used to trigger a wp-cron.php request.
	 * @return array
	 */
	public function block_spawn_cron( $spawn_cron_args ) {
		delete_transient( 'doing_cron' );

		$spawn_cron_args['url']  = '';
		$spawn_cron_args['key']  = '';
		$spawn_cron_args['args'] = array();

		return $spawn_cron_args;
	}

	/**
	 * Define constants that block Core's cron
	 *
	 * If a constant is already defined and isn't what we expect, log it
	 */
	private function set_disable_cron_constants() {
		$constants = array(
			'DISABLE_WP_CRON'   => true,
			'ALTERNATE_WP_CRON' => false,
		);

		foreach ( $constants as $constant => $expected_value ) {
			if ( defined( $constant ) ) {
				if ( constant( $constant ) !== $expected_value ) {
					/* translators: 1: Constant name */
					trigger_error( 'Cron-Control: ' . sprintf( __( '%1$s set to unexpected value; must be corrected for proper behaviour.', 'automattic-cron-control' ), $constant ), E_USER_WARNING );
				}
			} else {
				define( $constant, $expected_value );
			}
		}
	}
}
