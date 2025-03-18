<?php

namespace Automattic\VIP\Jetpack\Connection_Pilot;

use WP_Error;

/**
 * These are the re-usable methods for testing JP connections and (re)connecting sites.
 */
class Controls {

	/**
	 * Get the current status of the Jetpack connection.
	 *
	 * There is potential here to replace most of this with Jetpack_Cxn_Test_Base().
	 *
	 * @return mixed bool|WP_Error True if JP is properly connected, WP_Error otherwise.
	 */
	public static function jetpack_is_connected() {
		if ( ! self::validate_environment() ) {
			return new WP_Error( 'jp-cxn-pilot-invalid-environment', 'This is not a valid VIP Go environment.' );
		}

		if ( ( new \Automattic\Jetpack\Status() )->is_offline_mode() ) {
			return new WP_Error( 'jp-cxn-pilot-offline-mode', 'Jetpack is in offline mode.' );
		}

		// Methods only available in Jetpack 9.2 and above
		if ( method_exists( 'Automattic\Jetpack\Connection\Manager', 'is_connected' ) && method_exists( 'Automattic\Jetpack\Connection\Manager', 'has_connected_owner' ) ) {
			// The Jetpack_Connection::is_connected() method checks both the existence of a blog token and id.
			if ( ! \Jetpack::connection()->is_connected() ) {
				return new WP_Error( 'jp-cxn-pilot-not-connected', 'Jetpack is currently not connected.' );
			}

			// The Jetpack_Connection::has_connected_owner() method checks both the existence of a user token and master_user option.
			if ( ! \Jetpack::connection()->has_connected_owner() ) {
				return new WP_Error( 'jp-cxn-pilot-not-connected-owner', 'Jetpack does not have a connected owner.' );
			}
		} elseif ( ! \Jetpack::is_active() || ! \Jetpack_Options::get_option( 'id' ) ) {
			// The Jetpack::is_active() method just checks if there are user/blog tokens in the database.
			return new WP_Error( 'jp-cxn-pilot-not-active', 'Jetpack is not currently active.' );
		}

		$jp_primary_user = new \WP_User( \Jetpack_Options::get_option( 'master_user' ) );
		if ( ! $jp_primary_user->exists() ) {
			return new WP_Error( 'jp-cxn-pilot-primary-user-missing', sprintf( 'Jetpack does not have a valid primary user.' ) );
		} elseif ( ! user_can( $jp_primary_user, 'manage_options' ) ) {
			return new WP_Error( 'jp-cxn-pilot-primary-user-caps', sprintf( 'The Jetpack primary user does not have admin capabilities.' ) );
		}

		$is_connected = self::test_jetpack_connection();
		if ( is_wp_error( $is_connected ) ) {
			return $is_connected;
		}

		$attendant = Attendant::instance();
		switch ( $attendant->check_connection_owner_validity() ) {
			case 'is_legacy_vip':
				return new WP_Error( 'jp-cxn-pilot-has-legacy-vip-owner', 'The connection is owned by the legacy VIP user.' );
			case 'not_vip':
				return new WP_Error( 'jp-cxn-pilot-not-vip-owned', 'The connection is not owned by VIP.' );
		}

		$is_owner_connected = self::test_jetpack_owner_connection();
		if ( ! $is_owner_connected ) {
			return new WP_Error( 'jp-cxn-pilot-owner-not-connected', sprintf( 'The connection owner is not connected to Jetpack.' ) );
		}

		return true;
	}

	/**
	 * Tests the active connection.
	 *
	 * Does a two-way test to verify that the local site can communicate with remote Jetpack/WP.com servers and that Jetpack/WP.com servers can talk to the local site.
	 * Modified version of https://github.com/Automattic/jetpack/blob/af043612267376851389a30fef3927380355f2b3/projects/plugins/jetpack/class.jetpack-cli.php#L131
	 *
	 * @return mixed bool|WP_Error True if test connection succeeded, WP_Error otherwise.
	 */
	private static function test_jetpack_connection() {
		$response = \Automattic\Jetpack\Connection\Client::wpcom_json_api_request_as_blog(
			sprintf( '/jetpack-blogs/%d/test-connection', \Jetpack_Options::get_option( 'id' ) ),
			\Automattic\Jetpack\Connection\Client::WPCOM_JSON_API_VERSION
		);

		if ( is_wp_error( $response ) ) {
			if ( 'http_request_failed' === $response->get_error_code() && str_contains( $response->get_error_message(), 'Operation timed out' ) ) {
				return new WP_Error( 'jp-cxn-pilot-test-timeout', sprintf( 'Failed to test connection (#%s: %s)', $response->get_error_code(), $response->get_error_message() ) );
			}

			return new WP_Error( 'jp-cxn-pilot-test-fail', sprintf( 'Failed to test connection (#%s: %s)', $response->get_error_code(), $response->get_error_message() ) );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( ! $body ) {
			return new WP_Error( 'jp-cxn-pilot-empty-body', 'Failed to test connection (empty response body).' );
		}

		$result       = json_decode( $body );
		$is_connected = isset( $result->connected ) && (bool) $result->connected;
		if ( ! $is_connected ) {
			return new WP_Error( 'jp-cxn-pilot-not-connected', 'Connection test failed (WP.com does not think this site is connected or there are authentication or other issues).' );
		}

		return true;
	}

	private static function test_jetpack_owner_connection(): bool {
		$user_id = \Jetpack::connection()->get_connection_owner_id();

		$xml = new \Jetpack_IXR_Client( array( 'user_id' => $user_id ) );
		$xml->query( 'jetpack.testConnection' );

		return ! $xml->isError();
	}

	/**
	 * (Re)connect a site to Jetpack.
	 *
	 * Creates the VIP user if needed, provisions the plan with WP.com, and re-runs the connection checks to ensure it all worked.
	 *
	 * @param bool $skip_connection_tests Skip if we've already run the checks before this point.
	 * @param bool $disconnect Set to true if it should disconnect Jetpack at the start.
	 *
	 * @return mixed bool|WP_Error True if JP was (re)connected, WP_Error otherwise.
	 */
	public static function connect_site( bool $skip_connection_tests = false, bool $disconnect = false ) {
		if ( ! self::validate_environment() ) {
			return new WP_Error( 'jp-cxn-pilot-invalid-environment', 'This is not a valid WPVIP environment.' );
		}

		if ( ! $skip_connection_tests && ! $disconnect ) {
			$connection_test = self::jetpack_is_connected();

			if ( true === $connection_test ) {
				// Abort since the site is already connected to JP, and we aren't okay with disconnecting.
				return new WP_Error( 'jp-cxn-pilot-already-connected', 'Jetpack is already properly connected.' );
			}
		}

		if ( $disconnect ) {
			\Jetpack::disconnect();
		}


		$attendant = Attendant::instance();
		$user      = $attendant->ensure_user_exists();
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		// Put the machine user in charge of things now.
		wp_set_current_user( $user->ID );

		// Register/connect the site to WP.com.
		$provision_result = self::provision_site( $user->ID );

		if ( is_wp_error( $provision_result ) ) {
			return $provision_result;
		}

		// Without this, Jetpack can incorrectly think it's still disconnected.
		self::refresh_options_cache();

		// Run the tests again and return the result 🤞.
		return self::jetpack_is_connected();
	}

	/**
	 * Connect a site to Akismet.
	 *
	 * Uses Akismet's function to connect Akismet using the Jetpack. An active Jetpack connection on the site
	 * and the Akismet plugin are required.
	 *
	 * @return mixed bool|WP_Error True if Akisment had a connection or was (re)connected, WP_Error otherwise.
	 */
	public static function connect_akismet() {
		if ( ! class_exists( 'Akismet_Admin' ) || ! method_exists( 'Akismet_Admin', 'connect_jetpack_user' ) || ! function_exists( 'is_akismet_key_invalid' ) ) {
			return new WP_Error( 'jp-cxn-pilot-akismet-dependencies-missing', 'Akismet is missing required functions/methods to perform the connection.' );
		}

		if ( ! is_akismet_key_invalid() ) {
			return true;
		}

		$attendant = Attendant::instance();
		$user      = $attendant->ensure_user_exists();
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		// Put the machine user in charge of things.
		wp_set_current_user( $user->ID );

		$result = \Akismet_Admin::connect_jetpack_user();
		if ( ! $result ) {
			return new WP_Error( 'jp-cxn-pilot-akismet-connection-failed', 'Akismet could not be connected.' );
		}

		return true;
	}

	/**
	 * Provision the site with WP.com.
	 *
	 * @param int $user_id The VIP machine user ID.
	 * @return mixed bool|WP_Error True if provisioning worked, WP_Error otherwise.
	 */
	public static function provision_site( $user_id ) {
		if ( class_exists( 'Automattic\VIP\Jetpack\Connection_Pilot\Provisioner' ) ) {
			return Provisioner::provision_site( $user_id );
		}

		return new WP_Error( 'jp-cxn-pilot-provisioning-error', 'Unable to use Provisioner.' );
	}

	/**
	 * Refresh the options cache.
	 *
	 * This helps prevent cache issues for times where the database was directly updated.
	 */
	private static function refresh_options_cache() {
		wp_cache_flush_runtime();

		$options_to_refresh = array(
			'jetpack_options',
			'jetpack_private_options',
			'alloptions',
			'notoptions',
		);

		foreach ( $options_to_refresh as $option_name ) {
			wp_cache_delete( $option_name, 'options' );
		}
	}

	/**
	 * Ensure we are in the right environment.
	 *
	 * @return bool
	 */
	private static function validate_environment() {
		return defined( 'WPCOM_IS_VIP_ENV' ) && true === constant( 'WPCOM_IS_VIP_ENV' );
	}
}
