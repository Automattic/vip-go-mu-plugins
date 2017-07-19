<?php

/**
 * These are extracted commands from Jetpack CLI.
 *
 * We've made some local mods while we wait for an upstream release/update.
 */
class Jetpack_Start_Provision_CLI_Command extends WP_CLI_Command {
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
	 * [--onboarding=<onboarding>]
	 * : Guide the user through an onboarding wizard
	 * [--force_register=<register>]
	 * : Whether to force a site to register
	 * [--force_connect=<force_connect>]
	 * : Force JPS to not reuse existing credentials
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp jetpack-start-provision partner_provision '{ some: "json" }' premium 1
	 *     { success: true }
	 *
	 * @synopsis <token_json> [--wpcom_user_id=<user_id>] [--plan=<plan_name>] [--onboarding=<onboarding>] [--force_register=<register>] [--force_connect=<force_connect>]
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

		if ( isset( $named_args['force_connect'] ) && ! empty( $named_args['force_connect'] ) ) {
			$request_body['force_connect'] = intval( $named_args['force_connect'] );
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

	private function get_api_host() {
		$env_api_host = getenv( 'JETPACK_START_API_HOST', true );
		return $env_api_host ? $env_api_host : JETPACK__WPCOM_JSON_API_HOST;
	}

	private function partner_provision_error( $error ) {
		WP_CLI::log( json_encode( array(
			'success'       => false,
			'error_code'    => $error->get_error_code(),
			'error_message' => $error->get_error_message()
		) ) );
		exit( 1 );
	}
}

WP_CLI::add_command( 'jetpack-start-provision', 'Jetpack_Start_Provision_CLI_Command' );
