<?php

namespace Automattic\VIP\Jetpack;

use DateTime;

require_once __DIR__ . '/class-jetpack-connection-controls.php';

/**
 * The Pilot is in control of setting up the cron job for monitoring JP connections and sending out alerts if anything is wrong.
 * Will only run if the `VIP_JETPACK_AUTO_MANAGE_CONNECTION` constant is defined and set to true.
 */
class Connection_Pilot {
	/**
	 * The option name used for keeping track of successful connection checks.
	 */
	const HEARTBEAT_OPTION_NAME = 'vip_jetpack_connection_pilot_heartbeat';

	/**
	 * Cron action that runs the connection pilot checks.
	 */
	const CRON_ACTION = 'wpcom_vip_run_jetpack_connection_pilot';

	/**
	 * The schedule the cron job runs on. Update in 000-vip-init.php as well.
	 *
	 * Schedule changes can take up to 24 hours to take effect.
	 * See the a8c_cron_control_clean_legacy_data event for more details.
	 */
	const CRON_SCHEDULE = 'hourly';

	/**
	 * The healtcheck option's current data.
	 *
	 * Example: [ 'site_url' => 'https://example.go-vip.co', 'hashed_site_url' => '371a92eb7d5d63007db216dbd3b49187', 'cache_site_id' => 1234, 'timestamp' => 1555124370 ]
	 *
	 * @var mixed False if doesn't exist, else an array with the data shown above.
	 */
	private $last_heartbeat;

	/**
	 * Singleton
	 *
	 * @var Connection_Pilot Singleton instance
	 */
	private static $instance = null;

	private function __construct() {
		if ( ! self::should_run_connection_pilot() ) {
			return;
		}

		$this->init_actions();

		$this->last_heartbeat = get_option( self::HEARTBEAT_OPTION_NAME );
	}

	/**
	 * Initiate an instance of this class if one doesn't exist already.
	 */
	public static function instance() {
		if ( ! ( self::$instance instanceof self ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Hook any relevant actions
	 */
	public function init_actions() {
		// Ensure the internal cron job has been added. Should already exist as an internal Cron Control job.
		if ( defined( 'WP_CLI' ) && \WP_CLI ) {
			add_action( 'wp_loaded', array( $this, 'schedule_cron' ) );
		} else {
			add_action( 'admin_init', array( $this, 'schedule_cron' ) );
		}

		add_action( 'wp_initialize_site', array( $this, 'schedule_immediate_cron' ) );
		add_action( 'wp_update_site', array( $this, 'schedule_immediate_cron' ) );
		add_action( self::CRON_ACTION, array( '\Automattic\VIP\Jetpack\Connection_Pilot', 'do_cron' ) );

		add_filter( 'vip_jetpack_connection_pilot_should_reconnect', array( $this, 'filter_vip_jetpack_connection_pilot_should_reconnect' ), 10, 2 );
	}

	public function schedule_cron() {
		if ( ! wp_next_scheduled( self::CRON_ACTION ) ) {
			wp_schedule_event( strtotime( sprintf( '+%d minutes', mt_rand( 2, 30 ) ) ), self::CRON_SCHEDULE, self::CRON_ACTION );
		}
	}

	public function schedule_immediate_cron() {
		wp_schedule_single_event( time(), self::CRON_ACTION );
	}

	public static function do_cron() {
		if ( ! self::should_run_connection_pilot() ) {
			return;
		}

		$instance = self::instance();

		$instance->run_connection_pilot();
	}

	/**
	 * The main cron job callback.
	 * Checks the JP connection and alerts/auto-resolves when there are problems.
	 *
	 * Needs to be static due to how it is added to cron control.
	 */
	public function run_connection_pilot() {
		$is_connected = Connection_Pilot\Controls::jetpack_is_connected();

		if ( true === $is_connected ) {
			// Everything checks out. Update the heartbeat option and move on.
			$this->update_heartbeat();

			// TODO: Remove check after general rollout
			if ( self::should_attempt_reconnection() ) {
				// Attempting Akismet connection given that Jetpack is connected
				$akismet_connection_attempt = Connection_Pilot\Controls::connect_akismet();
				if ( ! $akismet_connection_attempt ) {
					$this->send_alert( 'Alert: Could not connect Akismet automatically.' );
				}

				// Attempting VaultPress connection given that Jetpack is connected
				if ( ! defined( 'VIP_VAULTPRESS_SKIP_LOAD' )  || ! VIP_VAULTPRESS_SKIP_LOAD ) {
					$vaultpress_connection_attempt = Connection_Pilot\Controls::connect_vaultpress();
					if ( is_wp_error( $vaultpress_connection_attempt ) ) {
						$message = sprintf( 'VaultPress connection error: [%s] %s', $vaultpress_connection_attempt->get_error_code(), $vaultpress_connection_attempt->get_error_message() );
						$this->send_alert( $message );
					}
				}
			}

			return;
		}

		// Not connected, maybe reconnect
		if ( ! self::should_attempt_reconnection( $is_connected ) ) {
			return;
		}

		// Got here, so we _should_ attempt a reconnection for this site
		$this->reconnect();
	}

	/**
	 * Perform a JP reconnection
	 */
	public function reconnect() {
		// Attempt a reconnect
		$connection_attempt = Connection_Pilot\Controls::connect_site( 'skip_connection_tests' );

		if ( true === $connection_attempt ) {
			if ( ! empty( $this->last_heartbeat['cache_site_id'] ) && (int) \Jetpack_Options::get_option( 'id' ) !== (int) $this->last_heartbeat['cache_site_id'] ) {
				$this->send_alert( 'Alert: Jetpack was automatically reconnected, but the connection may have changed cache sites. Needs manual inspection.' );
				return;
			}

			$this->send_alert( 'Jetpack was successfully (re)connected!' );
			return;
		}

		// Reconnection failed
		$this->send_alert( 'Jetpack (re)connection attempt failed.', $connection_attempt );
	}

	public function update_heartbeat() {
		return update_option( self::HEARTBEAT_OPTION_NAME, array(
			'site_url'         => get_site_url(),
			'hashed_site_url'  => md5( get_site_url() ), // used to protect against S&Rs/imports/syncs
			'cache_site_id'    => (int) \Jetpack_Options::get_option( 'id' ),
			'timestamp' => time(),
		), false );
	}

	public function filter_vip_jetpack_connection_pilot_should_reconnect( $should, $error = null ) {
		$error_code = null;

		if ( $error && is_wp_error( $error ) ) {
			$error_code = $error->get_error_code();
		}

		// 1) Had an error
		switch( $error_code ) {
			case 'jp-cxn-pilot-missing-constants':
			case 'jp-cxn-pilot-development-mode':
				$this->send_alert( 'Jetpack cannot currently be connected on this site due to the environment. JP may be in development mode.', $error );

				return false;

			// It is connected but not under the right account.
			case 'jp-cxn-pilot-not-vip-owned':
				$this->send_alert( 'Jetpack is connected to a non-VIP account.', $error );

				return false;
		}

		// 2) Check the last heartbeat to see if the URLs match.
		if ( ! empty( $this->last_heartbeat['hashed_site_url'] ) ) {
			if ( $this->last_heartbeat['hashed_site_url'] === md5( get_site_url() ) ) {
				// Not connected, but current url matches previous url, attempt a reconnect

				return true;
			}

			// Not connected and current url doesn't match previous url, don't attempt reconnection
			$this->notify_pilot( 'Jetpack is disconnected, and it appears the domain has changed.' );

			return false;
		}

		return $should;
	}

	/**
	 * Send an alert to IRC/Slack, and add to logs.
	 *
	 * Example message:
	 * Jetpack is disconnected, but was previously connected under the same domain.
	 * Site: example.go-vip.co (ID 123). The last known connection was on August 25, 12:11:14 UTC to Cache ID 65432 (example.go-vip.co).
	 * Jetpack connection error: [jp-cxn-pilot-not-active] Jetpack is not currently active.
	 *
	 * @param string   $message optional.
	 * @param \WP_Error $wp_error optional.
	 *
	 * @return mixed True if the message was sent to IRC, false if it failed. If silenced, will just return the message string.
	 */
	protected function send_alert( $message = '', $wp_error = null ) {
		$message .= sprintf( ' Site: %s (ID %d).', get_site_url(), defined( 'VIP_GO_APP_ID' ) ? VIP_GO_APP_ID : 0 );

		$last_heartbeat = $this->last_heartbeat;
		if ( isset( $last_heartbeat['site_url'], $last_heartbeat['cache_site_id'], $last_heartbeat['timestamp'] ) ) {
			$message .= sprintf(
				' The last known connection was on %s UTC to Cache Site ID %d (%s).',
				date( 'F j, H:i', $last_heartbeat['timestamp'] ), $last_heartbeat['cache_site_id'], $last_heartbeat['site_url']
			);
		}

		if ( is_wp_error( $wp_error ) ) {
			$message .= sprintf( ' Jetpack connection error: [%s] %s', $wp_error->get_error_code(), $wp_error->get_error_message() );
		}

		\Automattic\VIP\Logstash\log2logstash( [
			'severity' => 'error',
			'feature' => 'jetpack-connection-pilot',
			'message' => $message,
		] );

		$should_silence_alerts = defined( 'VIP_JETPACK_CONNECTION_PILOT_SILENCE_ALERTS' ) && VIP_JETPACK_CONNECTION_PILOT_SILENCE_ALERTS;
		return $should_silence_alerts ? $message : wpcom_vip_irc( '#vip-jp-cxn-monitoring', $message );
	}

	/**
	 * Checks if the connection pilot should run.
	 *
	 * @return bool True if the connection pilot should run.
	 */
	public static function should_run_connection_pilot(): bool {
		if ( defined( 'VIP_JETPACK_AUTO_MANAGE_CONNECTION' ) ) {
			return VIP_JETPACK_AUTO_MANAGE_CONNECTION;
		}

		return apply_filters( 'vip_jetpack_connection_pilot_should_run', false );
	}

	/**
	 * Checks if a reconnection should be attempted
	 *
	 * @param $error \WP_Error|null Optional error thrown by the connection check
	 *
	 * @return bool True if a reconnect should be attempted
	 */
	public static function should_attempt_reconnection( \WP_Error $error = null ): bool {
		// TODO: Only attempting to reconnect on new sites. We can remove this code after ramp-up
		$is_multisite = is_multisite();
		if ( ! $is_multisite && defined( 'VIP_GO_APP_ID' ) && VIP_GO_APP_ID < 3000 ) {
			return false;
		} else if ( $is_multisite ) {
			if ( ! function_exists( 'get_site' ) ) {
				return false;
			}

			try {
				$site_registered = new DateTime( get_site()->registered );
				$threshold = new DateTime( "2021-06-01" );
				if ( $site_registered < $threshold ) {
					return false;
				}
			} catch ( \Exception $e ) {
				return false;
			}
		}

		// TODO: The constant is deprecated and should be removed. Keeping this check during the ramp-up
		if ( defined( 'VIP_JETPACK_CONNECTION_PILOT_SHOULD_RECONNECT' ) ) {
			return VIP_JETPACK_CONNECTION_PILOT_SHOULD_RECONNECT;
		}

		return apply_filters( 'vip_jetpack_connection_pilot_should_reconnect', false, $error );
	}
}

add_action( 'init', function() {
	Connection_Pilot::instance();
}, 25);
