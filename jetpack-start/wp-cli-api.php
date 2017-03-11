<?php

class Jetpack_Start_API_CLI_Command extends WP_CLI_Command {
	/**
	 * Connect the site using Jetpack Start
	 *
	 * Creates a machine user (if needed) and then uses the Jetpack Start API to initialize a connection and sets up the necessary local bits.
	 *
	 * ## EXAMPLES
	 *
	 *     wp jetpack-start api connect
	 *
	 */
	public function connect( $args, $assoc_args ) {
		if ( ! defined( 'WPCOM_VIP_JP_START_API_BASE' ) || ! defined( 'WPCOM_VIP_JP_START_API_TOKEN' ) ) {
			WP_CLI::error( 'Jetpack Start API constants (`WPCOM_VIP_JP_START_API_BASE` and/or `WPCOM_VIP_JP_START_API_TOKEN`) are not defined. Please check the `vip-secrets` file to confirm they\'re there and accessible.' );
		}

		// TODO: if Jetpack is connected, bail?

		$user = $this->maybe_create_user();

		$url = $this->get_api_url( '/product-keys' );
		$args = [
			'site_url' => get_site_url(),
			'site_name' => get_bloginfo( 'name' ),
			'site_lang' => get_locale(),
			'user_id' => $user->ID,
			'user_role' => WPCOM_VIP_MACHINE_USER_ROLE,
		];
		$headers = [
			 'Authorization' => 'Bearer ' . WPCOM_VIP_JPPHP_TOKEN,
		];

		$request = wp_remote_post( $url, [
			'body' => $args,
			'headers' => $headers,
		] );

		if ( is_wp_error( $request ) ) {
			WP_CLI::error( '>> Failed to fetch keys from Jetpack Start: ' . $request->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $request );
		if ( 200 !== $response_code ) {
			WP_CLI::error( '>> Got non-200 response from Jetpack Start API: ' . $response_code );
		}

		$body = wp_remote_retrieve_body( $request );
		$data = wp_json_decode( $body );
		if ( ! $data || ! $data['success'] ) {
			WP_CLI::error( '>> Got invalid response from Jetpack Start API: ' . $body );
		}

		// Connect Akismet
		$akismet_key = $data['akismet_api_key'];
		$akismet_result = WP_CLI::runcommand( sprintf(
			'wp jetpack-start akismet --akismet_key="%s"',
			escapeshellarg( $akismet_key )
		) );

		// Connect VaultPress
		$vaultpress_key = $data['vaultpress_registration_key'];
		$vaultpress_result = WP_CLI::runcommand( sprintf(
			'wp jetpack-start vaultpress --vaultpress_key="%s"',
			escapeshellarg( $vaultpress_key )
		) );

		// Connect Jetpack
		$jetpack_id = $data['jetpack_id'];
		$jetpack_secret = $data['jetpack_secret'];
		$jetpack_access_token = $data['jetpack_access_token'];
		$jetpack_result = WP_CLI::runcommand( sprintf(
			'wp jetpack-start vaultpress --jetpack_id="%s" --jetpack_secret="%s", --jetpack_access_token="%s"',
			escapeshellarg( $jetpack_id ),
			escapeshellarg( $jetpack_secret ),
			escapeshellarg( $jetpack_access_token )
		) );
	}

	private function maybe_create_user() {
		$user = get_user_by( 'login', WPCOM_VIP_MACHINE_USER_LOGIN );
		if ( ! $user ) {
			$cmd = sprintf(
				'user create %s %s --role="%s" --display_name="%s" --porcelain',
				escapeshellarg( get_site_url() ),
				escapeshellarg( WPCOM_VIP_MACHINE_USER_LOGIN ),
				escapeshellarg( WPCOM_VIP_MACHINE_USER_EMAIL ),
				escapeshellarg( WPCOM_VIP_MACHINE_USER_ROLE ),
				escapeshellarg( WPCOM_VIP_MACHINE_USER_NAME )
			);

			WP_CLI::line( '-- Creating VIP machine user' );
			$user_id = WP_CLI::runcommand( $cmd );

			$user = get_userdata( $user_id );
			if ( ! $user ) {
				WP_CLI::error( '>> Failed to create user' );
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

	private function get_api_url( $endpoint ) {
		return WPCOM_VIP_JPPHP_API_BASE . $endpoint;
	}
}

WP_CLI::add_command( 'jetpack-start api', 'Jetpack_Start_API_CLI_Command' );
