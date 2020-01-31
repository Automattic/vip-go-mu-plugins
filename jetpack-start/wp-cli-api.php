<?php

/**
 * Note: this file requires that WPCOM_VIP_JP_START_API_CLIENT_ID and WPCOM_VIP_JP_START_CLIENT_SECRET and WPCOM_VIP_JP_START_WPCOM_USER_ID are set
 */

class Jetpack_Start_CLI_Command extends WP_CLI_Command {
	/**
	 * Cancel a Jetpack Start subscription for the current site
	 *
	 * ## OPTIONS
	 *
	 * [--confirm]
	 * : Flag to confirm that we really do want to cancel
	 *
	 * ## EXAMPLES
	 *
	 *     wp jetpack-start api cancel
	 *
	 */
	public function cancel( $args, $assoc_args ) {
		$this->validate_constants_or_die();

		WP_CLI::line( '- Cancelling Jetpack plan for site' );

		$confirm = WP_CLI\Utils\get_flag_value( $assoc_args, 'confirm', false );
		if ( ! $confirm ) {
			return WP_CLI::error( 'Are you really sure you want to cancel? This should only be done if you are having issues connecting to Jetpack and need a nuclear option. If you\'re really sure, please re-run this command with the `--confirm` flag.' );
		}

		$data = $this->run_jetpack_bin( 'partner-cancel.sh' );

		if ( is_wp_error( $data ) ) {
			WP_CLI::error( sprintf( '-- Failed to cancel plan: %s', $data->get_error_message() ) );
		}

		WP_CLI::line( sprintf( '-- Cancelled subscription for site (API response: %s)', var_export( $data, true ) ) );

		$this->disconnect_site();
	}

	/**
	 * Connect the site using Jetpack Start
	 *
	 * Creates a machine user (if needed) and then uses the Jetpack Start API to initialize a connection and sets up the necessary local bits.
	 *
	 * ## OPTIONS
	 *
	 * [--network]
	 * : Connect all subsites of this multisite network
	 *
	 * ## EXAMPLES
	 *
	 *     wp jetpack-start api connect
	 *     wp jetpack-start api connect --network
	 *
	 */
	public function connect( $args, $assoc_args ) {
		$this->validate_constants_or_die();

		if ( defined( 'JETPACK_DEV_DEBUG' ) && true === JETPACK_DEV_DEBUG ) {
			WP_CLI::warning( 'JETPACK_DEV_DEBUG mode is enabled. Please remove the constant after connection.' );
		}

		$network = WP_CLI\Utils\get_flag_value( $assoc_args, 'network', false );
		if ( $network && is_multisite() ) {
			$sites = get_sites( [
				'public'   => null,
				'archived' => 0,
				'spam'     => 0,
				'deleted'  => 0,
				'fields'   => 'ids',
			] );

			// Instead of repeatedly calling restore_current_blog() just to switch again, manually switch back at the end
			$starting_blog_id = get_current_blog_id();

			// Track whether there were any failures, to adjust messaging
			$failure_occurred = false;

			foreach ( $sites as $site ) {
				switch_to_blog( $site );

				WP_CLI::line( sprintf( 'Starting %s (site %d)', home_url( '/' ), $site ) );

				$connected = $this->connect_site( $assoc_args );

				if ( false === $connected ) {
					$failure_occurred = true;
				}

				WP_CLI::line( sprintf( 'Done with %s, on to the next site!', home_url( '/' ) ) );
				WP_CLI::line( '' );
			}

			switch_to_blog( $starting_blog_id );
		} else {
			$failure_occurred = ! $this->connect_site( $assoc_args );
		}

		if ( $failure_occurred ) {
			WP_CLI::warning( 'Attempt completed. Please resolve the issues noted above and try again.' );
		} else {
			WP_CLI::success( 'All done! Welcome to Jetpack! ✈️️✈️️✈️️' );
		}
	}

	private function connect_site( $assoc_args = [] ) {
		WP_CLI::line( 'Connecting Jetpack!' );

		if ( Jetpack::is_active() ) {
			WP_CLI::line( '- Jetpack is already connected' );

			$is_vip_connection = $this->is_vip_connection();
			if ( ! $is_vip_connection ) {
				WP_CLI::line( sprintf( '-- The connection is not owned by `%s`; fixing.', WPCOM_VIP_MACHINE_USER_LOGIN ) );
				return $this->disconnect_and_reconnect_site();
			}

			$is_connected = $this->test_connection();
			if ( is_wp_error( $is_connected ) ) {
				WP_CLI::line( sprintf( '-- The connection looks broken (%s | %s); fixing.', $is_connected->get_error_code(), $is_connected->get_error_message() ) );
				return $this->disconnect_and_reconnect_site();
			}

			// TODO: verify plan?

			WP_CLI::line( '-- Everything looks good!' );
			return true;
		}

		WP_CLI::line( '- Verifying VIP machine user exists (or creating one, if not)' );
		$user = $this->maybe_create_user();
		if ( is_wp_error( $user ) ) {
			WP_CLI::warning( $user->get_error_message() );
			return false;
		}

		// Put the machine user in charge of things now
		wp_set_current_user( $user->ID );

		WP_CLI::line( '- Provisioning via Jetpack Start API' );

		$provision_args = array(
			'user_id' => $user->ID,
			'wpcom_user_id' => WPCOM_VIP_JP_START_WPCOM_USER_ID,
		);

		// Always force connect; if we have a remote connection, just force JPS to reuse it
		$provision_args['force_connect'] = 1;

		// Don't bother provisioning the plan
		if ( ! isset( $assoc_args['skip_plan'] ) ) {
			$provision_args['plan'] = 'professional';
		}

		$data = $this->run_jetpack_bin( 'partner-provision.sh', $provision_args );

		if ( is_wp_error( $data ) ) {
			// If connection exists, re-run with force_connect and no plan?
			// TODO: get better error code from API
			if ( 'You already have this plan from this partner.' === $data->get_error_message() ) {
				WP_CLI::line( '-- Looks like this site was previously connected; fetching auth details.' );
				return $this->connect_site( [ 'force_connect' => true ] );
			}

			WP_CLI::warning( sprintf( '-- Failed to provision Jetpack connection with error: (%s) %s', $data->get_error_code(), $data->get_error_message() ) );
			return false;
		}

		WP_CLI::line( sprintf( '-- Completed provisioning: %s', var_export( $data, true ) ) );

		// HACK: Jetpack options can get stuck in notoptions, which can lead to a broken state.
		wp_cache_delete( 'notoptions', 'options' );

		WP_CLI::line( '- Checking Jetpack status' );

		$status_cmd = 'jetpack status';
		WP_CLI::runcommand( $status_cmd, [
			'exit_error' => false,
		] );

		update_option( 'vip_jetpack_start_connected_on', time(), false );

		return true;
	}

	private function disconnect_site() {
		WP_CLI::line( '- Disconnecting Jetpack' );
		Jetpack::disconnect();
	}

	private function disconnect_and_reconnect_site() {
		$this->disconnect_site();
		return $this->connect_site();
	}

	private function maybe_create_user() {
		$user = get_user_by( 'login', WPCOM_VIP_MACHINE_USER_LOGIN );

		if ( ! $user ) {
			$cmd = sprintf(
				'user create --url=%s --role=%s --display_name=%s --porcelain %s %s --skip-plugins --skip-themes',
				escapeshellarg( get_site_url() ),
				escapeshellarg( WPCOM_VIP_MACHINE_USER_ROLE ),
				escapeshellarg( WPCOM_VIP_MACHINE_USER_NAME ),
				escapeshellarg( WPCOM_VIP_MACHINE_USER_LOGIN ),
				escapeshellarg( WPCOM_VIP_MACHINE_USER_EMAIL )
			);

			$user_create_cmd_result = WP_CLI::runcommand( $cmd, [
				'return'     => 'all',
				'exit_error' => false,
			] );

			$user_id = $user_create_cmd_result->stdout;

			if ( $user_create_cmd_result->stderr && ! $user_id ) {
				return new WP_Error( 'maybe_create_user-failed', 'Failed to create new user. Reason: ' . $user_create_cmd_result->stderr );
			}

			$user = get_userdata( $user_id );
			if ( ! $user ) {
				return new WP_Error( 'maybe_create_user-failed', 'Failed to create new user.' );
			}
		}

		$user_id = $user->ID;
		if ( is_multisite() ) {
			add_user_to_blog( get_current_blog_id(), $user_id, WPCOM_VIP_MACHINE_USER_ROLE );

			if ( ! is_super_admin( $user_id ) ) {
				grant_super_admin( $user_id );
			}
		} else {
			$user_roles = (array) $user->roles;
			if ( ! in_array( WPCOM_VIP_MACHINE_USER_ROLE, $user_roles ) ) {
				$user->set_role( WPCOM_VIP_MACHINE_USER_ROLE );
			}
		}

		return $user;
	}

	private function run_jetpack_bin( $script, $args = array() ) {
		// Running local versions for now until https://github.com/Automattic/jetpack/pull/7294 lands
		$script_path = __DIR__ . '/bin/' . $script;

		$cmd = sprintf(
			'%s --partner_id=%s --partner_secret=%s --url=%s',
			$script_path,
			escapeshellarg( WPCOM_VIP_JP_START_API_CLIENT_ID ),
			escapeshellarg( WPCOM_VIP_JP_START_API_CLIENT_SECRET ),
			escapeshellarg( get_site_url() )
		);

		foreach ( $args as $arg => $value ) {
			$cmd .= sprintf(
				' --%s=%s',
				$arg,
				is_numeric( $value ) ? intval( $value ) : escapeshellarg( $value )
			);
		}

		WP_CLI::line( sprintf( '-- Running bin script: `%s` with args (%s)', $script, var_export( $args, true ) ) );

		exec( $cmd, $script_output, $script_result );
		$script_output_json = json_decode( end( $script_output ) );

		if ( ! $script_output_json ) {
			return new WP_Error( 'invalid_output', 'Could not parse script output: ' . var_export( $script_output, true ) );
		} elseif ( isset( $script_output_json->error_code ) ) {
			return new WP_Error( $script_output_json->error_code, $script_output_json->error_message );
		}

		return $script_output_json;
	}

	/**
	 * Verifies that the connection is owned by the VIP machine user
	 */
	private function is_vip_connection() {
		$master_user_email = Jetpack::get_master_user_email();
		return $master_user_email === WPCOM_VIP_MACHINE_USER_EMAIL;
	}

	/**
	 * Tests the active connection
	 *
	 * Does a two-way test to verify that the local site can communicate with remote Jetpack/WP.com servers and that Jetpack/WP.com servers can talk to the local site.
	 *
	 * This is a local copy until it lands upstream: https://github.com/Automattic/jetpack/pull/7636
	 */
	private function test_connection() {
		if ( ! Jetpack::is_active() ) {
			return new WP_Error( 'jps-test-no-connection', __( 'Jetpack is not currently connected to WordPress.com', 'jetpack' ) );
		}

		$response = Jetpack_Client::wpcom_json_api_request_as_blog(
			sprintf( '/jetpack-blogs/%d/test-connection', Jetpack_Options::get_option( 'id' ) ),
			Jetpack_Client::WPCOM_JSON_API_VERSION
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'jps-test-fail', sprintf( 'Failed to test connection (#%s: %s)', $response->get_error_code(), $response->get_error_message() ) );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( ! $body ) {
			return new WP_Error( 'jps-test-fail-empty-body', 'Failed to test connection (empty response body)' );
		}

		$result = json_decode( $body );
		$is_connected = (bool) $result->connected;
		if ( ! $is_connected ) {
			return new WP_Error( 'jps-not-connected', 'Connection test failed (WP.com does not think this site is connected or there are authentication or other issues.)' );
		}

		return $is_connected;
	}

	private function validate_constants_or_die() {
		if ( ! defined( 'WPCOM_VIP_JP_START_API_CLIENT_ID' ) || ! defined( 'WPCOM_VIP_JP_START_API_CLIENT_SECRET' ) || ! defined( 'WPCOM_VIP_JP_START_WPCOM_USER_ID' ) ) {
			WP_CLI::error( 'Jetpack Start API constants (`WPCOM_VIP_JP_START_API_CLIENT_ID` and/or `WPCOM_VIP_JP_START_API_CLIENT_SECRET`) are not defined. Please check the `vip-secrets` file to confirm they\'re there, accessible, and valid.' );
		}

	}
}

WP_CLI::add_command( 'jetpack-start', 'Jetpack_Start_CLI_Command' );
