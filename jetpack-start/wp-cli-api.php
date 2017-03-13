<?php

class Jetpack_Start_API_CLI_Command extends WP_CLI_Command {
	/**
	 * Connect the site using Jetpack Start
	 *
	 * Creates a machine user (if needed) and then uses the Jetpack Start API to initialize a connection and sets up the necessary local bits.
	 *
	 * ## OPTIONS
	 *
	 * [--skip_is_active]
	 * : Provision even if Jetpack is already connected.
	 *
	 * ## EXAMPLES
	 *
	 *     wp jetpack-start api connect
	 *     wp jetpack-start api connect --skip_is_active
	 *
	 */
	public function connect( $args, $assoc_args ) {
		if ( ! defined( 'WPCOM_VIP_JP_START_API_BASE' ) || ! defined( 'WPCOM_VIP_JP_START_API_TOKEN' ) ) {
			WP_CLI::error( 'Jetpack Start API constants (`WPCOM_VIP_JP_START_API_BASE` and/or `WPCOM_VIP_JP_START_API_TOKEN`) are not defined. Please check the `vip-secrets` file to confirm they\'re there, accessible, and valid.' );
		}

		$skip_is_active = WP_CLI\Utils\get_flag_value( $assoc_args, 'skip_is_active', false );
		if ( ! $skip_is_active && Jetpack::is_active() ) {
			WP_CLI::error( 'Jetpack is already active; bailing. Run this command with `--skip_is_active` to bypass this check or disconnect Jetpack before continuing.' );
		}

		WP_CLI::line( '-- Verifying VIP machine user exists (or creating one, if not)' );
		$user = $this->maybe_create_user();
		if ( is_wp_error( $user ) ) {
			WP_CLI::error( $user->get_error_message() );
		}

		WP_CLI::line( '-- Fetching keys from Jetpack Start API' );
		$data = $this->fetch_keys( $user );
		if ( is_wp_error( $data ) ) {
			WP_CLI::error( 'Failed to fetch keys from Jetpack Start: ' . $data->get_error_message() );
		}

		WP_CLI::line( '-- Adding keys to site' );
		$this->install_keys( $data );

		WP_CLI::success( 'All done! Welcome to Jetpack! ✈️️✈️️✈️️' );
	}

	private function maybe_create_user() {
		$user = get_user_by( 'login', WPCOM_VIP_MACHINE_USER_LOGIN );
		if ( ! $user ) {
			$cmd = sprintf(
				'user create --url=%s %s %s --role=%s --display_name=%s --porcelain',
				escapeshellarg( get_site_url() ),
				escapeshellarg( WPCOM_VIP_MACHINE_USER_LOGIN ),
				escapeshellarg( WPCOM_VIP_MACHINE_USER_EMAIL ),
				escapeshellarg( WPCOM_VIP_MACHINE_USER_ROLE ),
				escapeshellarg( WPCOM_VIP_MACHINE_USER_NAME )
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
			if ( ! $user->has_cap( WPCOM_VIP_MACHINE_USER_ROLE ) ) {
				$user->set_role( WPCOM_VIP_MACHINE_USER_ROLE );
			}
		}

		return $user;
	}

	private function fetch_keys( $user ) {
		$url = $this->get_api_url( '/product-keys' );
		$args = [
			'site_url'  => get_site_url(),
			'site_name' => get_bloginfo( 'name' ),
			'site_lang' => get_locale(),
			'user_id'   => $user->ID,
			'user_role' => WPCOM_VIP_MACHINE_USER_ROLE,
		];
		$headers = array_merge( [], $this->get_api_auth_header() );

		$request = wp_remote_post( $url, [
			'body'    => $args,
			'headers' => $headers,
			'timeout' => 30, // Jetpack Start can be a bit slow so give it time
		] );

		if ( is_wp_error( $request ) ) {
			return $request;
		}

		$response_code = wp_remote_retrieve_response_code( $request );
		$body = wp_remote_retrieve_body( $request );
		if ( 200 !== $response_code ) {
			return new WP_Error( 'fetch_keys-fail-non-200', sprintf( 'Got non-200 response from Jetpack Start API (code: %d | body: %s)', $response_code, $body ) );
		}

		$data = json_decode( $body, true );
		if ( ! $data || ! $data['success'] ) {
			return new WP_Error( 'fetch_keys-fail-invalid-response', sprintf( 'Got invalid response from Jetpack Start API (body: %s)', $body ) );
		}

		return $data;
	}

	private function install_keys( $data ) {
		$runcommand_args = [
			'exit_error' => false,
		];

		$akismet_key = $data['akismet_api_key'] ?? '';
		$akismet_result = WP_CLI::runcommand( sprintf(
			'jetpack-start keys akismet --akismet_key=%s',
			escapeshellarg( $akismet_key )
		), $runcommand_args );

		$vaultpress_key = $data['vaultpress_registration_key'] ?? '';
		$vaultpress_result = WP_CLI::runcommand( sprintf(
			'jetpack-start keys vaultpress --vaultpress_key=%s',
			escapeshellarg( $vaultpress_key )
		), $runcommand_args );

		$jetpack_id = $data['jetpack_id'] ?? '';
		$jetpack_secret = $data['jetpack_secret'] ?? '';
		$jetpack_access_token = $data['jetpack_access_token'] ?? '';
		$jetpack_result = WP_CLI::runcommand( sprintf(
			'jetpack-start keys jetpack --jetpack_id=%s --jetpack_secret=%s --jetpack_access_token=%s --jetpack_init_modules --user=%s',
			escapeshellarg( $jetpack_id ),
			escapeshellarg( $jetpack_secret ),
			escapeshellarg( $jetpack_access_token ),
			escapeshellarg( WPCOM_VIP_MACHINE_USER_LOGIN )
		), $runcommand_args );
	}

	private function get_api_auth_header() {
		return [
			'Authorization' => 'Bearer ' . WPCOM_VIP_JP_START_API_TOKEN,
		];
	}

	private function get_api_url( $endpoint ) {
		return WPCOM_VIP_JP_START_API_BASE . $endpoint;
	}
}

WP_CLI::add_command( 'jetpack-start api', 'Jetpack_Start_API_CLI_Command' );
