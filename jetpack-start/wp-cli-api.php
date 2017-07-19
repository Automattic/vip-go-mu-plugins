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

		WP_CLI::success( sprintf( 'Cancelled subscription for site_id = %s (body: %s)', $site_id, var_export( $data, true ) ) );
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
			return new WP_Error( 'invalid_output', 'Could not parse script output: ' . $script_output );
		} elseif ( isset( $script_output_json->error ) ) {
			return new WP_Error( $script_output_json->error, $script_output );
		}

		return $script_output_json;
	}

	/**
	 * Cancel's the current Jetpack plan granted by this partner, if applicable
	 *
	 * Returns success or error JSON
	 *
	 * <token_json>
	 * : JSON blob of WPCOM API token
	 */
	public function partner_cancel( $args, $named_args ) {
		list( $token_json ) = $args;

		if ( ! $token_json || ! ( $token = json_decode( $token_json ) ) ) {
			$this->partner_provision_error( new WP_Error( 'missing_access_token',  sprintf( __( 'Invalid token JSON: %s', 'jetpack' ), $token_json ) ) );
		}

		if ( isset( $token->error ) ) {
			$this->partner_provision_error( new WP_Error( $token->error, $token->message ) );
		}

		if ( ! isset( $token->access_token ) ) {
			$this->partner_provision_error( new WP_Error( 'missing_access_token', __( 'Missing or invalid access token', 'jetpack' ) ) );
		}

		$blog_id    = Jetpack_Options::get_option( 'id' );

		if ( ! $blog_id ) {
			$this->partner_provision_error( new WP_Error( 'site_not_registered',  __( 'This site is not connected to Jetpack', 'jetpack' ) ) );
		}

		$request = array(
			'headers' => array(
				'Authorization' => "Bearer " . $token->access_token,
				'Host'          => defined( 'JETPACK__WPCOM_JSON_API_HOST_HEADER' ) ? JETPACK__WPCOM_JSON_API_HOST_HEADER : 'public-api.wordpress.com',
			),
			'timeout' => 60,
			'method'  => 'POST',
			'body'    => json_encode( array( 'site_id' => $blog_id ) )
		);

		$url = sprintf( 'https://%s/rest/v1.3/jpphp/%d/partner-cancel', $this->get_api_host(), $blog_id );

		$result = Jetpack_Client::_wp_remote_request( $url, $request );

		if ( is_wp_error( $result ) ) {
			$this->partner_provision_error( $result );
		}

		WP_CLI::log( json_encode( $result ) );
	}

	/**
	 * Provision a site using a Jetpack Partner license
	 *
	 * Returns JSON blob
	 *
	 * ## OPTIONS
	 *
	 * <token_json>
	 * : JSON blob of WPCOM API token
	 * --user_id=<user_id>
	 * : Local ID of user to connect as (if omitted, user will be required to redirect via wp-admin)
	 * [--plan=<plan_name>]
	 * : Slug of the requested plan, e.g. premium
	 * [--wpcom_user_id=<user_id>]
	 * : WordPress.com ID of user to connect as (must be whitelisted against partner key)
	 * [--force_register=<register>]
	 * : Whether to force a site to register
	 * [--onboarding=<onboarding>]
	 * : Guide the user through an onboarding wizard
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp jetpack-start partner_provision '{ some: "json" }' premium 1
	 *     { success: true }
	 *
	 * @synopsis <token_json> [--wpcom_user_id=<user_id>] [--plan=<plan_name>] [--force_register=<register>] [--onboarding=<onboarding>]
	 */
	public function partner_provision( $args, $named_args ) {
		list( $token_json ) = $args;

		if ( ! $token_json || ! ( $token = json_decode( $token_json ) ) ) {
			$this->partner_provision_error( new WP_Error( 'missing_access_token',  sprintf( __( 'Invalid token JSON: %s', 'jetpack' ), $token_json ) ) );
		}

		if ( isset( $token->error ) ) {
			$message = isset( $token->message )
				? $token->message
				: '';
			$this->partner_provision_error( new WP_Error( $token->error, $message ) );
		}

		if ( ! isset( $token->access_token ) ) {
			$this->partner_provision_error( new WP_Error( 'missing_access_token', __( 'Missing or invalid access token', 'jetpack' ) ) );
		}

		$blog_id    = Jetpack_Options::get_option( 'id' );
		$blog_token = Jetpack_Options::get_option( 'blog_token' );

		if ( ! $blog_id || ! $blog_token || ( isset( $named_args['force_register'] ) && intval( $named_args['force_register'] ) ) ) {
			// this code mostly copied from Jetpack::admin_page_load
			Jetpack::maybe_set_version_option();
			$registered = Jetpack::try_registration();
			if ( is_wp_error( $registered ) ) {
				$this->partner_provision_error( $registered );
			} elseif ( ! $registered ) {
				$this->partner_provision_error( new WP_Error( 'registration_error', __( 'There was an unspecified error registering the site', 'jetpack' ) ) );
			}

			$blog_id    = Jetpack_Options::get_option( 'id' );
			$blog_token = Jetpack_Options::get_option( 'blog_token' );
		}

		// if the user isn't specified, but we have a current master user, then set that to current user
		if ( ! get_current_user_id() && $master_user_id = Jetpack_Options::get_option( 'master_user' ) ) {
			wp_set_current_user( $master_user_id );
		}

		$site_icon = ( function_exists( 'has_site_icon') && has_site_icon() )
			? get_site_icon_url()
			: false;

		/** This filter is documented in class.jetpack-cli.php */
		if ( apply_filters( 'jetpack_start_enable_sso', true ) ) {
			$redirect_uri = add_query_arg(
				array( 'action' => 'jetpack-sso', 'redirect_to' => urlencode( admin_url() ) ),
				wp_login_url() // TODO: come back to Jetpack dashboard?
			);
		} else {
			$redirect_uri = admin_url();
		}

		$request_body = array(
			'jp_version'    => JETPACK__VERSION,
			'redirect_uri'  => $redirect_uri
		);

		if ( $site_icon ) {
			$request_body['site_icon'] = $site_icon;
		}

		if ( get_current_user_id() ) {
			$user = wp_get_current_user();

			// role
			$role = Jetpack::translate_current_user_to_role();
			$signed_role = Jetpack::sign_role( $role );

			$secrets = Jetpack::init()->generate_secrets( 'authorize' );

			// Jetpack auth stuff
			$request_body['scope']  = $signed_role;
			$request_body['secret'] = $secrets['secret_1'];

			// User stuff
			$request_body['user_id']    = $user->ID;
			$request_body['user_email'] = $user->user_email;
			$request_body['user_login'] = $user->user_login;
		}

		// optional additional params
		if ( isset( $named_args['wpcom_user_id'] ) && ! empty( $named_args['wpcom_user_id'] ) ) {
			$request_body['wpcom_user_id'] = $named_args['wpcom_user_id'];
		}

		if ( isset( $named_args['plan'] ) && ! empty( $named_args['plan'] ) ) {
			$request_body['plan'] = $named_args['plan'];
		}

		if ( isset( $named_args['onboarding'] ) && ! empty( $named_args['onboarding'] ) ) {
			$request_body['onboarding'] = intval( $named_args['onboarding'] );
		}

		$request = array(
			'headers' => array(
				'Authorization' => "Bearer " . $token->access_token,
				'Host'          => defined( 'JETPACK__WPCOM_JSON_API_HOST_HEADER' ) ? JETPACK__WPCOM_JSON_API_HOST_HEADER : 'public-api.wordpress.com',
			),
			'timeout' => 60,
			'method'  => 'POST',
			'body'    => json_encode( $request_body )
		);

		$url = sprintf( 'https://%s/rest/v1.3/jpphp/%d/partner-provision', $this->get_api_host(), $blog_id );

		// add calypso env if set
		if ( getenv( 'CALYPSO_ENV' ) ) {
			$url = add_query_arg( array( 'calypso_env' => getenv( 'CALYPSO_ENV' ) ), $url );
		}

		$result = Jetpack_Client::_wp_remote_request( $url, $request );

		if ( is_wp_error( $result ) ) {
			$this->partner_provision_error( $result );
		}

		$response_code = wp_remote_retrieve_response_code( $result );
		$body_json     = json_decode( wp_remote_retrieve_body( $result ) );

		if( 200 !== $response_code ) {
			if ( isset( $body_json->error ) ) {
				$this->partner_provision_error( new WP_Error( $body_json->error, $body_json->message ) );
			} else {
				$this->partner_provision_error( new WP_Error( 'server_error', sprintf( __( "Request failed with code %s" ), $response_code ) ) );
			}
		}

		if ( isset( $body_json->access_token ) ) {
			// authorize user and enable SSO
			Jetpack::update_user_token( $user->ID, sprintf( '%s.%d', $body_json->access_token, $user->ID ), true );

			if ( $active_modules = Jetpack_Options::get_option( 'active_modules' ) ) {
				Jetpack::delete_active_modules();
				Jetpack::activate_default_modules( 999, 1, $active_modules, false );
			} else {
				Jetpack::activate_default_modules( false, false, array(), false );
			}

			/**
			 * Auto-enable SSO module for new Jetpack Start connections
			 *
			 * @since 5.0.0
			 *
			 * @param bool $enable_sso Whether to enable the SSO module. Default to true.
			 */
			if ( apply_filters( 'jetpack_start_enable_sso', true ) ) {
				Jetpack::activate_module( 'sso', false, false );
			}
		}

		WP_CLI::log( json_encode( $body_json ) );
	}

	private function validate_constants_or_die() {
		if ( ! defined( 'WPCOM_VIP_JP_START_API_CLIENT_ID' ) || ! defined( 'WPCOM_VIP_JP_START_API_CLIENT_SECRET' ) || ! defined( 'WPCOM_VIP_JP_START_WPCOM_USER_ID' ) ) {
			WP_CLI::error( 'Jetpack Start API constants (`WPCOM_VIP_JP_START_API_CLIENT_ID` and/or `WPCOM_VIP_JP_START_API_CLIENT_SECRET`) are not defined. Please check the `vip-secrets` file to confirm they\'re there, accessible, and valid.' );
		}

	}
}

WP_CLI::add_command( 'jetpack-start', 'Jetpack_Start_CLI_Command' );
