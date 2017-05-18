<?php

class Jetpack_Start_CLI_Command extends WP_CLI_Command {
	const API_ERROR_EXISTING_SUBSCRIPTION = 'Failed to provision WPCOM store subscription';
	const API_ERROR_USER_PERMISSIONS = 'User does not have permission to administer the given site.';

	/**
	 * Cancel a Jetpack Start subscription
	 *
	 * ## OPTIONS
	 *
	 * [--jetpack_site_id=<site_id>]
	 * : WP.com site ID for the shadow site. If not specified, grabs the site ID from Jetpack.
	 *
	 * ## EXAMPLES
	 *
	 *     wp jetpack-start api cancel
	 *     wp jetpack-start api cancel --jetpack_site_id=999999999
	 *
	 */
	public function cancel( $args, $assoc_args ) {
		$site_id = WP_CLI\Utils\get_flag_value( $assoc_args, 'jetpack_site_id', false );

		if ( empty( $site_id ) ) {
			$site_id = Jetpack_Options::get_option( 'id' );
			if ( empty( $site_id ) ) {
				WP_CLI::error( 'Could not get WP.com blog_id from the local Jetpack install. Please specify the `--jetpack_site_id` of the Jetpack Shadow site to cancel the subscription. You can get this from the Jetpack Debugger or using WP.com Network Admin.' );
			}
		}

		$data = $this->api_cancel_subscription( $site_id );
		if ( is_wp_error( $data ) ) {
			WP_CLI::error( $data->get_error_message() );
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
		if ( ! defined( 'WPCOM_VIP_JP_START_API_BASE' ) || ! defined( 'WPCOM_VIP_JP_START_API_TOKEN' ) ) {
			WP_CLI::error( 'Jetpack Start API constants (`WPCOM_VIP_JP_START_API_BASE` and/or `WPCOM_VIP_JP_START_API_TOKEN`) are not defined. Please check the `vip-secrets` file to confirm they\'re there, accessible, and valid.' );
		}

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

			foreach ( $sites as $site ) {
				switch_to_blog( $site );

				WP_CLI::line( sprintf( 'Starting %s (site %d)', home_url( '/' ), $site ) );

				$this->connect_site( $assoc_args );

				WP_CLI::line( sprintf( 'Done with %s, on to the next site!', home_url( '/' ) ) );
				WP_CLI::line( '' );
			}

			switch_to_blog( $starting_blog_id );
		} else {
			$this->connect_site( $assoc_args );
		}

		WP_CLI::success( 'All done! Welcome to Jetpack! ✈️️✈️️✈️️' );
	}

	private function connect_site( $assoc_args ) {
		$force_connection = WP_CLI\Utils\get_flag_value( $assoc_args, 'force', false );
		if ( ! $force_connection && Jetpack::is_active() ) {
			WP_CLI::error( 'Jetpack is already active; bailing. Run this command with `--force` to bypass this check or disconnect Jetpack before continuing.' );
		}

		WP_CLI::line( '-- Verifying VIP machine user exists (or creating one, if not)' );
		$user = $this->maybe_create_user();
		if ( is_wp_error( $user ) ) {
			WP_CLI::error( $user->get_error_message() );
		}

		WP_CLI::line( '-- Fetching keys from Jetpack Start API' );
		$data = $this->api_fetch_keys( $user );
		if ( is_wp_error( $data ) ) {
			$message = $data->get_error_message();
			// Use strpos because our API method appends stuff to the error message
			if ( false !== strpos( $message, self::API_ERROR_EXISTING_SUBSCRIPTION ) ) {
				$message = 'There is an existing Jetpack Start subscription for this site. Please disconnect using the `cancel` subcommand and try again.';
			} elseif ( false !== strpos( $message, self::API_ERROR_USER_PERMISSIONS ) ) {
				$message = sprintf( 'This site already has an existing Jetpack shadow site but the `%s` WP.com user is either not a member of the site or not an administrator. Please use `add_user_to_blog` on your WP.com sandbox to add the account before continuing.', WPCOM_VIP_MACHINE_USER_LOGIN );
			}
			WP_CLI::error( 'Failed to fetch keys from Jetpack Start: ' . $message );
		}

		WP_CLI::line( '-- Adding keys to site' );
		$this->install_keys( $data );

		update_option( 'vip_jetpack_start_connected_on', time(), false );
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

	private function api_request( $url, $args ) {
		// get_api_auth_header returns an associative array.
		// The merge will make it easier if we have to add more headers in the future.
		$headers = array_merge( [], $this->get_api_auth_header() );
		$request = wp_remote_post( $url, [
			'body'    => $args,
			'headers' => $headers,
			'timeout' => 60, // jetpack start can be a bit slow so give it time
		] );

		if ( is_wp_error( $request ) ) {
			return $request;
		}

		$response_code = wp_remote_retrieve_response_code( $request );
		$body = wp_remote_retrieve_body( $request );
		if ( 200 !== $response_code ) {
			return new WP_Error( 'api-request-fail-non-200', sprintf( 'got non-200 response from jetpack start api (code: %d | body: %s)', $response_code, $body ) );
		}

		$data = json_decode( $body, true );
		if ( ! $data || ! $data['success'] ) {
			return new WP_Error( 'api-request-fail-invalid-response', sprintf( 'got invalid response from jetpack start api (body: %s)', $body ) );
		}

		return $data;
	}

	private function api_cancel_subscription( $site_id ) {
		$url = $this->get_api_url( '/cancel-subscription' );
		$args = [
			'site_id' => $site_id,
		];

		return $this->api_request( $url, $args );
	}

	private function api_fetch_keys( $user ) {
		$url = $this->get_api_url( '/product-keys' );
		$args = [
			'site_url'  => get_site_url(),
			'site_name' => get_bloginfo( 'name' ),
			'site_lang' => get_locale(),
			'user_id'   => $user->ID,
			'user_role' => WPCOM_VIP_MACHINE_USER_ROLE,
		];

		return $this->api_request( $url, $args );
	}

	private function install_keys( $data ) {
		$runcommand_args = [
			'exit_error' => false,
		];

		$akismet_key = $data['akismet_api_key'] ?? '';
		WP_CLI::line( '---' );
		WP_CLI::line( sprintf( 'Got Akismet key: %s', $akismet_key ) );
		$akismet_result = WP_CLI::runcommand( sprintf(
			'jetpack-keys akismet --akismet_key=%s',
			escapeshellarg( $akismet_key )
		), $runcommand_args );
		WP_CLI::line( '' );

		$vaultpress_key = $data['vaultpress_registration_key'] ?? '';
		WP_CLI::line( '---' );
		WP_CLI::line( sprintf( 'Got VaultPress key: %s', $vaultpress_key ) );
		$vaultpress_result = WP_CLI::runcommand( sprintf(
			'jetpack-keys vaultpress --vaultpress_key=%s',
			escapeshellarg( $vaultpress_key )
		), $runcommand_args );
		WP_CLI::line( '' );

		$jetpack_id = $data['jetpack_id'] ?? '';
		$jetpack_secret = $data['jetpack_secret'] ?? '';
		$jetpack_access_token = $data['jetpack_access_token'] ?? '';
		WP_CLI::line( '---' );
		WP_CLI::line( sprintf( 'Got Jetpack ID: %s', $jetpack_id ) );
		$jetpack_result = WP_CLI::runcommand( sprintf(
			'jetpack-keys jetpack --jetpack_id=%s --jetpack_secret=%s --jetpack_access_token=%s --user=%s',
			escapeshellarg( $jetpack_id ),
			escapeshellarg( $jetpack_secret ),
			escapeshellarg( $jetpack_access_token ),
			escapeshellarg( WPCOM_VIP_MACHINE_USER_LOGIN )
		), $runcommand_args );
		WP_CLI::line( '' );
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

WP_CLI::add_command( 'jetpack-start', 'Jetpack_Start_CLI_Command' );
