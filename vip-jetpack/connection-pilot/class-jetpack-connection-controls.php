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
		if ( ! self::validate_constants() ) {
			return new WP_Error( 'jp-cxn-pilot-missing-constants', 'This is not a valid VIP Go environment or some required constants are missing.' );
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
		} else {
			// The Jetpack::is_active() method just checks if there are user/blog tokens in the database.
			if ( ! \Jetpack::is_active() || ! \Jetpack_Options::get_option( 'id' ) ) {
				return new WP_Error( 'jp-cxn-pilot-not-active', 'Jetpack is not currently active.' );
			}
		}

		$vip_machine_user = new \WP_User( \Jetpack_Options::get_option( 'master_user' ) );
		if ( ! $vip_machine_user->exists() ) {
			return new WP_Error( 'jp-cxn-pilot-vip-user-missing', sprintf( 'The "%s" VIP user is missing.', WPCOM_VIP_MACHINE_USER_LOGIN ) );
		} elseif ( ! user_can( $vip_machine_user, 'manage_options' ) ) {
			return new WP_Error( 'jp-cxn-pilot-vip-user-caps', sprintf( 'The "%s" VIP user does not have admin capabilities.', WPCOM_VIP_MACHINE_USER_LOGIN ) );
		}

		$is_connected = self::test_jetpack_connection();
		if ( is_wp_error( $is_connected ) ) {
			return $is_connected;
		}

		$connection_owner  = \Jetpack::connection()->get_connection_owner();
		$is_vip_connection = $connection_owner && WPCOM_VIP_MACHINE_USER_LOGIN === $connection_owner->user_login;
		if ( ! $is_vip_connection ) {
			$connection_owner_login = $connection_owner ? $connection_owner->user_login : 'unknown';

			return new WP_Error( 'jp-cxn-pilot-not-vip-owned', sprintf( 'The connection is not owned by "%s". Current connection owner is: "%s"', WPCOM_VIP_MACHINE_USER_LOGIN, $connection_owner_login ) );
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
		if ( ! self::validate_constants() ) {
			return new WP_Error( 'jp-cxn-pilot-missing-constants', 'This is not a valid VIP Go environment or some constants are missing.' );
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

		$user = self::maybe_create_user();
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
	 * @return bool True if connection worked, false otherwise
	 */
	public static function connect_akismet(): bool {
		if ( class_exists( 'Akismet_Admin' ) && method_exists( 'Akismet_Admin', 'connect_jetpack_user' ) ) {

			if ( is_akismet_key_invalid() ) {
				$original_user = wp_get_current_user();
				// Getting wpcomvip user, since it's the owner of the Jetpack connection
				$vip_user = get_user_by( 'login', 'wpcomvip' );
				if ( ! $original_user || ! $vip_user ) {
					return false;
				}

				wp_set_current_user( $vip_user );

				$result = \Akismet_Admin::connect_jetpack_user();
				wp_set_current_user( $original_user );

				return $result;
			}

			return true;
		}

		return false;
	}

	/**
	 * Connect a site to VaultPress.
	 *
	 * Uses VaultPress' function to connect VaultPress using Jetpack. An active Jetpack connection is required on the site.
	 *
	 * @return bool|WP_Error True if site is connected, error otherwise.
	 */
	public static function connect_vaultpress() {
		$vaultpress = \VaultPress::init();
		if ( ! $vaultpress->is_registered() || ! isset( $vaultpress->options['connection'] ) || 'ok' !== $vaultpress->options['connection'] ) {
			// Remove the VaultPress option from the db to prevent site registration from failing
			delete_option( 'vaultpress' );

			return $vaultpress->register_via_jetpack( true );
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
	 * Maybe add our machine user to the site. Also sanity checks the user's permissions.
	 *
	 * @return object \WP_User if successful, WP_Error otherwise.
	 */
	private static function maybe_create_user() {
		$user = get_user_by( 'login', WPCOM_VIP_MACHINE_USER_LOGIN );

		if ( ! $user ) {
			$user_id = wp_insert_user( array(
				'user_login'   => WPCOM_VIP_MACHINE_USER_LOGIN,
				'user_email'   => WPCOM_VIP_MACHINE_USER_EMAIL,
				'display_name' => WPCOM_VIP_MACHINE_USER_NAME,
				'role'         => WPCOM_VIP_MACHINE_USER_ROLE,
				'user_pass'    => wp_generate_password( 36 ), // This account can't be logged into, but just in case.
			) );

			$user = get_userdata( $user_id );
			if ( is_wp_error( $user_id ) || ! $user ) {
				return new WP_Error( 'jp-cxn-pilot-user-create-failed', 'Failed to create new user.' );
			}
		}

		$user_id = $user->ID;

		// Add user to blog if needed, and ensure they are a super admin.
		if ( is_multisite() ) {
			$blog_id = get_current_blog_id();

			if ( ! is_user_member_of_blog( $user_id, $blog_id ) ) {
				$added_to_blog = add_user_to_blog( $blog_id, $user_id, WPCOM_VIP_MACHINE_USER_ROLE );

				if ( is_wp_error( $added_to_blog ) ) {
					return new WP_Error( 'jp-cxn-pilot-user-ms-create-failed', 'Failed to add user to blog.' );
				}
			}

			if ( ! is_super_admin( $user_id ) ) {
				// Will also return false if user already is SA.
				grant_super_admin( $user_id );
			}

			return $user;
		}

		// Ensure the correct role is applied.
		$user_roles = (array) $user->roles;
		if ( ! in_array( WPCOM_VIP_MACHINE_USER_ROLE, $user_roles, true ) ) {
			$user->set_role( WPCOM_VIP_MACHINE_USER_ROLE );
		}

		return $user;
	}

	/**
	 * Refresh the options cache.
	 *
	 * This helps prevent cache issues for times where the database was directly updated.
	 */
	private static function refresh_options_cache() {
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
	 * Ensures we have all the needed constants available.
	 *
	 * @return bool True if we have all the needed constants.
	 */
	private static function validate_constants() {
		$required_constants = [
			defined( 'WPCOM_VIP_MACHINE_USER_LOGIN' ),
			defined( 'WPCOM_VIP_MACHINE_USER_ROLE' ),
			defined( 'WPCOM_VIP_MACHINE_USER_NAME' ),
			defined( 'WPCOM_VIP_MACHINE_USER_EMAIL' ),
			defined( 'VIP_GO_APP_ID' ),
		];

		return ! in_array( false, $required_constants, true );
	}
}
