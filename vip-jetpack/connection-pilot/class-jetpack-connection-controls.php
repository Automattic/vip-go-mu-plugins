<?php

namespace Automattic\VIP\Jetpack\Connection_Pilot;

/**
 * These are the re-usable methods for testing JP connections and (re)connecting sites.
 */
class Controls {

	/**
	 * Get the current status of the Jetpack connection.
	 *
	 * There is potential here to replace most of this with Jetpack_Cxn_Test_Base().
	 *
	 * @return mixed bool|\WP_Error True if JP is properly connected, \WP_Error otherwise.
	 */
	public static function jetpack_is_connected() {
		if ( ! self::validate_constants() ) {
			return new \WP_Error( 'jp-cxn-pilot-missing-constants', 'This is not a valid VIP Go environment or some required constants are missing.' );
		}

		if ( (new \Automattic\Jetpack\Status())->is_offline_mode() ) {
			return new \WP_Error( 'jp-cxn-pilot-offline-mode', 'Jetpack is in offline mode.' );
		}

		// The Jetpack::is_active() method just checks if there are user/blog tokens in the database.
		if ( ! \Jetpack::is_active() || ! \Jetpack_Options::get_option( 'id' ) ) {
			return new \WP_Error( 'jp-cxn-pilot-not-active', 'Jetpack is not currently active.' );
		}

		$is_vip_connection = WPCOM_VIP_MACHINE_USER_EMAIL === \Jetpack::get_master_user_email();
		if ( ! $is_vip_connection ) {
			return new \WP_Error( 'jp-cxn-pilot-not-vip-owned', sprintf( 'The connection is not owned by "%s".', WPCOM_VIP_MACHINE_USER_LOGIN ) );
		}

		$vip_machine_user = new \WP_User( \Jetpack_Options::get_option( 'master_user' ) );
		if ( ! $vip_machine_user->exists() ) {
			return new \WP_Error( 'jp-cxn-pilot-vip-user-missing', sprintf( 'The "%s" VIP user is missing.', WPCOM_VIP_MACHINE_USER_LOGIN ) );
		} elseif ( ! user_can( $vip_machine_user, 'manage_options' ) ) {
			return new \WP_Error( 'jp-cxn-pilot-vip-user-caps', sprintf( 'The "%s" VIP user does not have admin capabilities.', WPCOM_VIP_MACHINE_USER_LOGIN ) );
		}

		$is_connected = self::test_jetpack_connection();
		if ( is_wp_error( $is_connected ) ) {
			return $is_connected;
		}

		return true;
	}

	/**
	 * Tests the active connection.
	 *
	 * Does a two-way test to verify that the local site can communicate with remote Jetpack/WP.com servers and that Jetpack/WP.com servers can talk to the local site.
	 * Modified version of https://github.com/Automattic/jetpack/blob/cdfe559613c989875050642189664f2cdafbd651/class.jetpack-cli.php#L120
	 *
	 * @return mixed bool|\WP_Error True if test connection succeeded, \WP_Error otherwise.
	 */
	private static function test_jetpack_connection() {
		$response = \Automattic\Jetpack\Connection\Client::wpcom_json_api_request_as_blog(
			sprintf( '/jetpack-blogs/%d/test-connection', \Jetpack_Options::get_option( 'id' ) ),
			\Automattic\Jetpack\Connection\Client::WPCOM_JSON_API_VERSION
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'jp-cxn-pilot-test-fail', sprintf( 'Failed to test connection (#%s: %s)', $response->get_error_code(), $response->get_error_message() ) );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( ! $body ) {
			return new \WP_Error( 'jp-cxn-pilot-empty-body', 'Failed to test connection (empty response body).' );
		}

		$result       = json_decode( $body );
		$is_connected = isset( $result->connected ) ? (bool) $result->connected : false;
		if ( ! $is_connected ) {
			return new \WP_Error( 'jp-cxn-pilot-not-connected', 'Connection test failed (WP.com does not think this site is connected or there are authentication or other issues).' );
		}

		return true;
	}

	/**
	 * (Re)connect a site to Jetpack.
	 *
	 * Creates the VIP user if needed, provisions the plan with WP.com, and re-runs the connection checks to ensure it all worked.
	 *
	 * @param bool $skip_connection_tests Skip if we've already run the checks before this point.
	 * @param bool $disconnect Set to true if it should disconnect Jetpack at the start.
	 *
	 * @return mixed bool|\WP_Error True if JP was (re)connected, \WP_Error otherwise.
	 */
	public static function connect_site( $skip_connection_tests = false, $disconnect = false ) {
		if ( ! self::validate_constants() ) {
			return new \WP_Error( 'jp-cxn-pilot-missing-constants', 'This is not a valid VIP Go environment or some constants are missing.' );
		}

		if ( ! $skip_connection_tests && ! $disconnect ) {
			$connection_test = self::jetpack_is_connected();

			if ( true === $connection_test ) {
				// Abort since the site is already connected to JP, and we aren't okay with disconnecting.
				return new \WP_Error( 'jp-cxn-pilot-already-connected', 'Jetpack is already properly connected.' );
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

	public static function connect_vaultpress() {
		if ( class_exists( 'VaultPress' ) ) {
			return \VaultPress::init()->register_via_jetpack( true );
		}

		return false;
	}

	/**
	 * Provision the site with WP.com.
	 *
	 * @see https://github.com/Automattic/host-partner-documentation/blob/master/jetpack/plan-provisioning.md
	 *
	 * @param int $user_id The VIP machine user ID.
	 *
	 * @return mixed bool|\WP_Error True if provisioning worked, \WP_Error otherwise.
	 */
	private static function provision_site( $user_id ) {
		// TODO: This is a high-risk possible problem on the CLI/CRON containers. Needs testing.
		$script_path = __DIR__ . '/bin/partner-provision.sh';

		// Can use JP core's script once the changes here are made: https://github.com/Automattic/vip-go-mu-plugins/pull/986.
		// $script_path = WPMU_PLUGIN_DIR . '/jetpack/bin/partner-provision.sh';

		$cmd = sprintf(
			'%s --partner_id=%s --partner_secret=%s --url=%s --user=%s --wpcom_user_id=%s --partner-tracking-id=%s --plan="professional" --force_connect=1 --allow-root',
			$script_path,
			escapeshellarg( WPCOM_VIP_JP_START_API_CLIENT_ID ),
			escapeshellarg( WPCOM_VIP_JP_START_API_CLIENT_SECRET ),
			escapeshellarg( get_site_url() ),
			escapeshellarg( $user_id ),
			escapeshellarg( WPCOM_VIP_JP_START_WPCOM_USER_ID ),
			escapeshellarg( VIP_GO_APP_ID )
		);

		exec( $cmd, $script_output );
		$script_output_json = json_decode( end( $script_output ) );

		if ( ! $script_output_json ) {
			return new \WP_Error( 'jp-cxn-pilot-provision-invalid-output', 'Could not parse script output. - ' . $script_output );
		} elseif ( isset( $script_output_json->error_code ) ) {
			return new \WP_Error( 'jp-cxn-pilot-provision-error', sprintf( 'Failed to provision site. Error (%s): %s', $script_output_json->error_code, $script_output_json->error_message ) );
		} elseif ( ! isset( $script_output_json->success ) || true !== $script_output_json->success ) {
			return new \WP_Error( 'jp-cxn-pilot-provision-error-unknown', 'Failed to provision site. Unknown Error.' );
		}

		return true;
	}

	/**
	 * Maybe add our machine user to the site. Also sanity checks the user's permissions.
	 *
	 * @return object \WP_User if successful, \WP_Error otherwise.
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
				return new \WP_Error( 'jp-cxn-pilot-user-create-failed', 'Failed to create new user.' );
			}
		}

		$user_id = $user->ID;

		// Add user to blog if needed, and ensure they are a super admin.
		if ( is_multisite() ) {
			$blog_id = get_current_blog_id();

			if ( ! is_user_member_of_blog( $user_id, $blog_id ) ) {
				$added_to_blog = add_user_to_blog( $blog_id, $user_id, WPCOM_VIP_MACHINE_USER_ROLE );

				if ( is_wp_error( $added_to_blog ) ) {
					return new \WP_Error( 'jp-cxn-pilot-user-ms-create-failed', 'Failed to add user to blog.' );
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
		if ( ! defined( 'WPCOM_VIP_MACHINE_USER_LOGIN' ) ||
			! defined( 'WPCOM_VIP_MACHINE_USER_ROLE' ) ||
			! defined( 'WPCOM_VIP_MACHINE_USER_NAME' ) ||
			! defined( 'WPCOM_VIP_MACHINE_USER_EMAIL' ) ) {
			return false;
		}

		if ( ! defined( 'VIP_GO_APP_ID' ) ||
			! defined( 'WPCOM_VIP_JP_START_API_CLIENT_ID' ) ||
			! defined( 'WPCOM_VIP_JP_START_API_CLIENT_SECRET' ) ||
			! defined( 'WPCOM_VIP_JP_START_WPCOM_USER_ID' ) ) {
			return false;
		}

		return true;
	}
}
