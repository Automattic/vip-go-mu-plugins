<?php

/**
 * The Status Check is responsible for checking on the JP connection and handling the various disconnected scenarios.
 * It stores a list notifications/errors that can be retrieved by the calling class and utilized however needed.
 */
class WPCOM_VIP_Jetpack_Connection_Status_Check {

	/**
	 * The option name used for keeping track of successful connection checks.
	 */
	const HEALTHCHECK_OPTION_NAME = 'vip_jetpack_connection_pilot_healthcheck';

	/**
	 * The healtcheck option's current data.
	 *
	 * Example: [ 'site_url' => 'https://example.go-vip.co', 'cache_site_id' => 1234, 'last_healthcheck' => 1555124370 ]
	 *
	 * @var mixed False if doesn't exist, else an array with the data shown above.
	 */
	private $healthcheck_option;

	/**
	 * The current site_url.
	 *
	 * @var string Site url.
	 */
	private $site_url;

	/**
	 * An error object describing the connection issue,
	 * or why a (re)connection attempt failed.
	 *
	 * @var WP_Error A WP_Error object.
	 */
	private $connection_error;

	/**
	 * An list of notifications that need to be sent out by the Pilot.
	 *
	 * @var array Pilot notifications.
	 */
	public $pilot_notifications = array();

	/**
	 * Class constructor.
	 * Populates the class properties and sets a fallback error.
	 */
	public function __construct() {
		$this->healthcheck_option = get_option( self::HEALTHCHECK_OPTION_NAME );
		$this->site_url           = get_site_url();
		$this->connection_error   = new WP_Error( 'jp-cxn-pilot-default-error', 'The Jetpack connection is in an unknown state.' );
	}

	/**
	 * The main method, used to start the status check.
	 *
	 * Runs connection checks and handles any problems.
	 */
	public function launch() {
		$is_connected = WPCOM_VIP_Jetpack_Connection_Controls::jetpack_is_connected();

		if ( true === $is_connected ) {
			// Everything checks out. Update the healthcheck option and move on.
			return update_option( self::HEALTHCHECK_OPTION_NAME, array(
				'site_url'         => $this->site_url,
				'cache_site_id'    => (int) Jetpack_Options::get_option( 'id' ),
				'last_healthcheck' => time(),
			), false );
		}

		if ( is_wp_error( $is_connected ) ) {
			$this->connection_error = $is_connected;
		}

		// The connection check failed, let's handle the problem the best we can.
		$this->handle_connection_issues();
	}

	/**
	 * Handle connection issues. (Re)connect when possible, else add a notification.
	 */
	private function handle_connection_issues() {
		if ( in_array( $this->connection_error->get_error_code(), array( 'jp-cxn-pilot-missing-constants', 'jp-cxn-pilot-development-mode' ), true ) ) {
			return $this->notify_pilot( 'Jetpack cannot currently be connected on this site.' );
		}

		// 1) It is connected but not under the right account.
		if ( 'jp-cxn-pilot-not-vip-owned' === $this->connection_error->get_error_code() ) {
			return $this->notify_pilot( 'Jetpack is connected to a non-VIP account.' );
		}

		// 2) Check the last healthcheck to see if the URLs match.
		if ( ! empty( $this->healthcheck_option['site_url'] ) ) {
			return $this->handle_disconnects_using_last_healthcheck();
		}

		// 3) The healthcheck option doesn’t exist. Either it's a new site, or an unknown connection error.
		if ( $this->is_default_vip_domain() || $this->is_new_multisite_site() ) {
			return $this->handle_new_sites();
		} else {
			return $this->notify_pilot( 'Jetpack is disconnected.' );
		}
	}

	/**
	 * Connection issue handler for sites that have previous healthcheck data.
	 *
	 * Will attempt to automatically reconnect if the URLs are the same.
	 */
	private function handle_disconnects_using_last_healthcheck() {
		if ( $this->healthcheck_option['site_url'] === $this->site_url ) {
			$reconnection_attempt = $this->connect_site();
			return $this->notify_pilot( $reconnection_attempt );
		}

		return $this->notify_pilot( 'Jetpack is disconnected, and it appears the domain has changed.' );
	}

	/**
	 * Connection issue handler for new sites.
	 *
	 * This includes either a default go-vip domain
	 * or a recently created multi-site site.
	 */
	private function handle_new_sites() {
		if ( $this->is_default_vip_domain() ) {
			return $this->notify_pilot( 'Jetpack is disconnected, though it appears this is a new site.' );
		}

		// Must be a new site on a MS.
		return $this->notify_pilot( 'Jetpack is disconnected, though it appears this is a new site on a MS network.' );
	}

	/**
	 * Try to (re)connect the site.
	 *
	 * @return string A message with the result of the (re)connection attempt.
	 */
	private function connect_site() {
		// Skip the JP connection tests since we've already run them.
		$connection_attempt = WPCOM_VIP_Jetpack_Connection_Controls::connect_site( 'skip_connection_tests' );

		if ( true === $connection_attempt ) {
			if ( ! empty( $this->healthcheck_option['cache_site_id'] ) && (int) Jetpack_Options::get_option( 'id' ) !== (int) $this->healthcheck_option['cache_site_id'] ) {
				return 'Alert: Jetpack was automatically reconnected, but the connection may have changed cache sites. Needs manual inspection.';
			}
			return 'Jetpack was successfully (re)connected!';
		}

		if ( is_wp_error( $connection_attempt ) ) {
			$this->connection_error = $connection_attempt;
		}

		return 'Jetpack (re)connection attempt failed.';
	}

	/**
	 * Check if the current site is using a default VIP GO domain name.
	 *
	 * @return bool True if this is a default URL.
	 */
	private function is_default_vip_domain() {
		$site_parsed = wp_parse_url( $this->site_url );
		return wp_endswith( $site_parsed['host'], '.go-vip.co' ) || wp_endswith( $site_parsed['host'], '.go-vip.net' );
	}

	/**
	 * Check if the current site is on a MS install and was recently added.
	 *
	 * @return bool True if this is a newly created site.
	 */
	private function is_new_multisite_site() {
		if ( ! is_multisite() ) {
			return false;
		}

		$current_site = get_blog_details();
		if ( empty( $current_site->registered ) ) {
			return false;
		}

		$time_diff = time() - strtotime( $current_site->registered );
		if ( 2 >= ( $time_diff / 3600 ) ) {
			// The site was created in the last 2 hours.
			return true;
		}

		return false;
	}

	/**
	 * Adds to the array of notifications that need to be sent out by the Pilot.
	 *
	 * @param string $message The first line of the notification that will be sent.
	 */
	private function notify_pilot( $message ) {
		$this->pilot_notifications[] = array( 'message' => $message, 'error' => $this->connection_error, 'healthcheck' => $this->healthcheck_option );
	}
}
