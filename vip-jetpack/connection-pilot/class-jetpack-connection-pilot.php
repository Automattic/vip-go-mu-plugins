<?php

namespace Automattic\VIP\Jetpack;

use DateTime;

require_once __DIR__ . '/class-jetpack-connection-controls.php';

if ( defined( 'WP_CLI' ) && \WP_CLI ) {
	require_once __DIR__ . '/class-jetpack-connection-cli.php';
}

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
	 * Maximum number of hours that the system will wait to try to reconnect.
	 */
	const MAX_BACKOFF_FACTOR = 7 * 24;

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
		// The hook always needs to be available so the job can remove itself if it needs to.
		add_action( self::CRON_ACTION, array( '\Automattic\VIP\Jetpack\Connection_Pilot', 'do_cron' ) );

		if ( self::should_run_connection_pilot() ) {
			$this->init_actions();
		}
	}

	/**
	 * Initiate an instance of this class if one doesn't exist already.
	 */
	public static function instance() {
		if ( ! ( self::$instance instanceof self ) ) {
			self::$instance = new self();
		}

		// Making sure each time CP is called it reads the correct heartbeat
		self::$instance->last_heartbeat = get_option( self::HEARTBEAT_OPTION_NAME );

		return self::$instance;
	}

	/**
	 * Hook any relevant actions
	 */
	public function init_actions() {
		// Ensure the cron job has been added.
		if ( wp_doing_cron() || ( defined( 'WP_CLI' ) && \WP_CLI ) ) {
			add_action( 'wp_loaded', array( $this, 'schedule_cron' ) );
		}

		add_action( 'wp_initialize_site', array( $this, 'schedule_immediate_cron' ) );
		add_action( 'wp_update_site', array( $this, 'schedule_immediate_cron' ) );

		add_filter( 'vip_jetpack_connection_pilot_should_reconnect', array( $this, 'filter_vip_jetpack_connection_pilot_should_reconnect' ), 10, 2 );
		add_filter( 'vip_jetpack_connection_pilot_silenced_alerts', array( $this, 'filter_vip_jetpack_connection_pilot_silenced_alerts' ) );
	}

	public function schedule_cron() {
		if ( ! wp_next_scheduled( self::CRON_ACTION ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand -- don't need a CSPRNG, mt_rand() is OK
			wp_schedule_event( strtotime( sprintf( '+%d minutes', mt_rand( 2, 30 ) ) ), 'hourly', self::CRON_ACTION );
		}
	}

	public function schedule_immediate_cron() {
		wp_schedule_single_event( time(), self::CRON_ACTION );
	}

	public static function do_cron() {
		if ( ! self::should_run_connection_pilot() ) {
			wp_clear_scheduled_hook( self::CRON_ACTION );
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

			// Attempting Akismet connection given that Jetpack is connected
			$skip_akismet = defined( 'VIP_AKISMET_SKIP_LOAD' ) && VIP_AKISMET_SKIP_LOAD;
			if ( ! $skip_akismet ) {
				$akismet_connection_attempt = Connection_Pilot\Controls::connect_akismet();
				if ( ! $akismet_connection_attempt ) {
					$this->send_alert( 'Alert: Could not connect Akismet automatically.' );
				}
			}

			// Attempting VaultPress connection given that Jetpack is connected
			$skip_vaultpress = defined( 'VIP_VAULTPRESS_SKIP_LOAD' ) && VIP_VAULTPRESS_SKIP_LOAD;
			if ( ! $skip_vaultpress ) {
				$vaultpress_connection_attempt = Connection_Pilot\Controls::connect_vaultpress();
				if ( is_wp_error( $vaultpress_connection_attempt ) ) {
					$message = sprintf( 'VaultPress connection error: [%s] %s', $vaultpress_connection_attempt->get_error_code(), $vaultpress_connection_attempt->get_error_message() );
					$this->send_alert( $message );
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
		$this->update_backoff_factor();
	}

	/**
	 * Checks for the backoff factor and returns whether Connection Pilot should skip a connection attempt.
	 *
	 * @return bool True if CP should back off, false otherwise.
	 */
	private function should_back_off(): bool {
		if ( ! empty( $this->last_heartbeat['backoff_factor'] ) && ! empty( $this->last_heartbeat['timestamp'] ) ) {
			$backoff_factor = $this->last_heartbeat['backoff_factor'];

			// Ensure we don't go past the max, and support future decreases should they occur.
			if ( $backoff_factor > self::MAX_BACKOFF_FACTOR ) {
				$backoff_factor = self::MAX_BACKOFF_FACTOR;
				$this->update_heartbeat( $backoff_factor );
			}

			if ( $backoff_factor > 0 ) {
				$dt_heartbeat = ( new DateTime() )->setTimestamp( $this->last_heartbeat['timestamp'] );
				$dt_now       = new DateTime();
				$diff         = $dt_now->diff( $dt_heartbeat, true );

				// Checking the difference in hours from the last heartbeat
				if ( $diff && $diff->h < $backoff_factor ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Updates the backoff factor after a connection attempt has failed
	 *
	 * @return void
	 */
	private function update_backoff_factor(): void {
		$backoff_factor = (int) $this->last_heartbeat['backoff_factor'];

		if ( $backoff_factor >= self::MAX_BACKOFF_FACTOR ) {
			return;
		} elseif ( $backoff_factor <= 0 ) {
			$backoff_factor = 1;
		} else {
			$backoff_factor = $backoff_factor * 2;
		}

		$this->update_heartbeat( $backoff_factor );
	}

	/**
	 * @param int $backoff_factor
	 *
	 * @return void
	 */
	private function update_heartbeat( int $backoff_factor = 0 ): void {
		$option = array(
			'site_url'        => get_site_url(),
			'hashed_site_url' => md5( get_site_url() ), // used to protect against S&Rs/imports/syncs
			'cache_site_id'   => (int) \Jetpack_Options::get_option( 'id', -1 ), // if no id can be retrieved, we'll fall back to -1
			'timestamp'       => time(),
			'backoff_factor'  => $backoff_factor,
		);
		$update = update_option( self::HEARTBEAT_OPTION_NAME, $option, false );
		if ( $update ) {
			$this->last_heartbeat = $option;
		}
	}

	public function filter_vip_jetpack_connection_pilot_should_reconnect( $should, $error = null ) {
		$error_code = null;

		if ( $error && is_wp_error( $error ) ) {
			$error_code = $error->get_error_code();
		}

		// 1) Had an error
		switch ( $error_code ) {
			case 'jp-cxn-pilot-missing-constants':
			case 'jp-cxn-pilot-development-mode':
				$this->send_alert( 'Jetpack cannot currently be connected on this site due to the environment. JP may be in development mode.', $error );

				return false;

			// It is connected but not under the right account.
			case 'jp-cxn-pilot-not-vip-owned':
				$this->send_alert( 'Jetpack is connected to a non-VIP account.', $error );

				return false;
		}

		// 2) Check the last heartbeat to see if we should back off
		if ( $this->should_back_off() ) {
			return false;
		}

		// 3) Check the last heartbeat to see if the URLs match.
		if ( ! empty( $this->last_heartbeat['hashed_site_url'] ) ) {
			if ( md5( get_site_url() ) === $this->last_heartbeat['hashed_site_url'] ) {
				// Not connected, but current url matches previous url, attempt a reconnect

				return true;
			}

			// Not connected and current url doesn't match previous url, don't attempt reconnection
			$this->send_alert( 'Jetpack is disconnected, and it appears the domain has changed.' );

			return false;
		}

		return $should;
	}

	public function filter_vip_jetpack_connection_pilot_silenced_alerts( $existing_alerts = [] ) {
		$alerts = array(
			'/VaultPress connection error.*A registration key can only be used on one site/',
		);

		return array_merge( (array) $existing_alerts, $alerts );
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
		if ( isset( $last_heartbeat['site_url'], $last_heartbeat['cache_site_id'], $last_heartbeat['timestamp'] ) && -1 != $last_heartbeat['cache_site_id'] ) {
			$message .= sprintf(
				' The last known connection was on %s UTC to Cache Site ID %d (%s).',
				gmdate( 'F j, H:i', $last_heartbeat['timestamp'] ), $last_heartbeat['cache_site_id'], $last_heartbeat['site_url']
			);
		}

		if ( is_wp_error( $wp_error ) ) {
			$message .= sprintf( ' Jetpack connection error: [%s] %s', $wp_error->get_error_code(), $wp_error->get_error_message() );
		}

		\Automattic\VIP\Logstash\log2logstash( [
			'severity' => 'error',
			'feature'  => 'jetpack-connection-pilot',
			'message'  => $message,
		] );

		$should_silence_alerts = defined( 'VIP_JETPACK_CONNECTION_PILOT_SILENCE_ALERTS' ) && VIP_JETPACK_CONNECTION_PILOT_SILENCE_ALERTS;
		if ( $should_silence_alerts ) {
			return $message;
		}

		// Bypass alerting on specific set messages, that can be false positives
		// Array of regexps to match the message that should be ignored
		$alerts_to_be_silenced = apply_filters( 'vip_jetpack_connection_pilot_silenced_alerts', [] );
		foreach ( $alerts_to_be_silenced as $alert_regex ) {
			if ( preg_match( $alert_regex, $message ) ) {
				return $message;
			}
		}

		return wpcom_vip_irc( '#vip-jp-cxn-monitoring', $message );
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
		return apply_filters( 'vip_jetpack_connection_pilot_should_reconnect', true, $error );
	}
}

add_action( 'init', function() {
	Connection_Pilot::instance();
}, 25);
