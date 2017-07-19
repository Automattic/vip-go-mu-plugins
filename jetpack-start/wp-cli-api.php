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
	 * ## EXAMPLES
	 *
	 *     wp jetpack-start api cancel
	 *
	 */
	public function cancel( $args, $assoc_args ) {
		$this->validate_constants_or_die();

		$data = $this->run_jetpack_bin( 'partner-cancel.sh' );

		if ( is_wp_error( $data ) ) {
			WP_CLI::error( sprintf( 'Failed to cancel plan: %s', $data->get_error_message() ) );
		}

		WP_CLI::line( sprintf( 'Cancelled subscription for site_id = %s (body: %s)', $site_id, var_export( $data, true ) ) );

		WP_CLI::line( 'Disconnecting Jetpack…' );
		WP_CLI::runcommand( sprintf(
			'jetpack disconnect blog --url=%s',
			escapeshellarg( get_site_url() )
		) );
	}

	/**
	 * Connect the site using Jetpack Start
	 *
	 * Creates a machine user (if needed) and then uses the Jetpack Start API to initialize a connection and sets up the necessary local bits.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Provision even if Jetpack is already connected.
	 *
	 * [--network]
	 * : Connect all subsites of this multisite network
	 *
	 * ## EXAMPLES
	 *
	 *     wp jetpack-start api connect
	 *     wp jetpack-start api connect --force
	 *     wp jetpack-start api connect --network
	 *     wp jetpack-start api connect --force --network
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

	private function connect_site( $assoc_args ) {
		$force_connection = WP_CLI\Utils\get_flag_value( $assoc_args, 'force', false );
		if ( ! $force_connection && Jetpack::is_active() ) {
			$master_user_id = Jetpack_Options::get_option( 'master_user' );
			if ( empty( $master_user_id ) ) {
				WP_CLI::warning( 'Jetpack is already active (connected), but we could not determine the master user; bailing. Run this command with `--force` to bypass this check or disconnect Jetpack before continuing.' );
				return false;
			}

			$master_user_login = get_userdata( $master_user_id )->user_login;
			WP_CLI::warning( sprintf( 'Jetpack is already active (connected) and the master user is "%s"; bailing. Run this command with `--force` to bypass this check or disconnect Jetpack before continuing.', $master_user_login ) );
			return false;
		}

		WP_CLI::line( '-- Verifying VIP machine user exists (or creating one, if not)' );
		$user = $this->maybe_create_user();
		if ( is_wp_error( $user ) ) {
			WP_CLI::warning( $user->get_error_message() );
			return false;
		}

		WP_CLI::line( '-- Provisioning via Jetpack Start API' );
		$data = $this->run_jetpack_bin( 'partner-provision.sh', array(
			'user_id' => $user->ID,
			'wpcom_user_id' => WPCOM_VIP_JP_START_WPCOM_USER_ID,
			'plan' => 'professional',
		) );

		if ( is_wp_error( $data ) ) {
			WP_CLI::warning( sprintf( 'Failed to provision Jetpack connection with error: (%s) %s', $data->get_error_code(), $data->get_error_message() ) );
			return false;
		}

		update_option( 'vip_jetpack_start_connected_on', time(), false );

		return true;
	}

	private function maybe_create_user() {
		$user = get_user_by( 'login', WPCOM_VIP_MACHINE_USER_LOGIN );
		if ( ! $user ) {
			$cmd = sprintf(
				'user create --url=%s --role=%s --display_name=%s --porcelain %s %s',
				escapeshellarg( get_site_url() ),
				escapeshellarg( WPCOM_VIP_MACHINE_USER_ROLE ),
				escapeshellarg( WPCOM_VIP_MACHINE_USER_NAME ),
				escapeshellarg( WPCOM_VIP_MACHINE_USER_LOGIN ),
				escapeshellarg( WPCOM_VIP_MACHINE_USER_EMAIL )
			);

			$user_id = WP_CLI::runcommand( $cmd, [
				'return' => true,
			] );

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
			'%s --partner_id=%s --partner_secret=%s',
			$script_path,
			escapeshellarg( WPCOM_VIP_JP_START_API_CLIENT_ID ),
			escapeshellarg( WPCOM_VIP_JP_START_API_CLIENT_SECRET )
		);

		if ( isset( $args['user_id'] ) ) {
			$cmd .= ' --user_id=' . (int) $args['user_id'];
		}

		if ( isset( $args['plan'] ) ) {
			$cmd .= ' --plan=' . escapeshellarg( $args['plan'] );
		}

		if ( isset( $args['wpcom_user_id'] ) ) {
			$cmd .= ' --wpcom_user_id=' . (int) $args['wpcom_user_id'];
		}

		if ( isset( $args['url'] ) ) {
			$cmd .= ' --url=' . $args['url'];
		}

		exec( $cmd, $script_output, $script_result );
		$script_output_json = json_decode( end( $script_output ) );

		if ( ! $script_output_json ) {
			return new WP_Error( 'invalid_output', 'Could not parse script output: ' . var_export( $script_output, true ) );
		} elseif ( isset( $script_output_json->error_code ) ) {
			return new WP_Error( $script_output_json->error_code, $script_output_json->error_message );
		}

		return $script_output_json;
	}

	private function validate_constants_or_die() {
		if ( ! defined( 'WPCOM_VIP_JP_START_API_CLIENT_ID' ) || ! defined( 'WPCOM_VIP_JP_START_API_CLIENT_SECRET' ) || ! defined( 'WPCOM_VIP_JP_START_WPCOM_USER_ID' ) ) {
			WP_CLI::error( 'Jetpack Start API constants (`WPCOM_VIP_JP_START_API_CLIENT_ID` and/or `WPCOM_VIP_JP_START_API_CLIENT_SECRET`) are not defined. Please check the `vip-secrets` file to confirm they\'re there, accessible, and valid.' );
		}

	}
}

WP_CLI::add_command( 'jetpack-start', 'Jetpack_Start_CLI_Command' );
