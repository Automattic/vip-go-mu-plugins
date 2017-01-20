<?php

class Jetpack_Start_CLI_Command extends WP_CLI_Command {
	/**
	 * Install Akismet keys
	 *
	 * ## OPTIONS
	 *
	 * --akismet_key=<akismet_key>
	 * : The Akismet API key provided by the Jetpack Start API
	 *
	 * ## EXAMPLES
	 *
	 *     wp jetpack-start akismet --akismet_key=12345
	 *
	 * @subcommand akismet
	 */
	public function install_akismet_keys( $args, $assoc_args ) {
		if ( ! class_exists( 'Akismet' ) ) {
			WP_CLI::error( 'Failed to install Akismet keys; Please activate the Akismet plugin and re-run this script.' );
		}

		if ( defined( 'WPCOM_API_KEY' ) && ! empty( WPCOM_API_KEY ) ) {
			WP_CLI::error( 'Skipping Akismet install; WPCOM_API_KEY is defined which will always take preference.' );
		}

		WP_CLI::line( 'Configuring Akismet' );

		$akismet_key = $assoc_args['akismet_key'];
		$result = $this->register_akismet( $akismet_key );
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( '- Failed to configure Akismet: ' . $result->get_error_message() );
		}

		WP_CLI::success( 'Akismet configured successfully.' );
	}

	private function register_akismet( $key ) {
		update_option( 'wordpress_api_key', $key );

		$verification_response = Akismet::verify_key( $key );
		if ( $verification_response !== 'valid' ) {
			return new WP_Error( 'jp-start-akismet', 'Akismet key not valid: ' . $verification_response );
		}

		return true;
	}

	/**
	 * Install VaultPress keys
	 *
	 * ## OPTIONS
	 *
	 * --vaultpress_key=<vaultpress_key>
	 * : The VaultPress API key provided by the Jetpack Start API.
	 *
	 * [--vaultpress_ssh_key=<vaultpress_ssh_key>]
	 * : The VaultPress SSH key provided by the Jetpack Start API.
	 *
	 * ## EXAMPLES
	 *
	 *     wp jetpack-start vaultpress --vaultpress_key=12345
	 *     wp jetpack-start vaultpress --vaultpress_key=12345 --vaultpress_ssh_key="asdfgh!!!"
	 *
	 * @subcommand vaultpress
	 */
	public function install_vaultpress_keys( $args, $assoc_args ) {
		if ( ! class_exists( 'VaultPress' ) ) {
			WP_CLI::error( 'Failed to install VaultPress keys; Please activate the VaultPress plugin and re-run this script.' );
		}

		WP_CLI::line( 'Configuring VaultPress' );

		$vaultpress_api_key = $args['vaultpress_key'];
		$result = $this->register_vaultpress( $vaultpress_api_key );
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( '- Failed to configure VaultPress: ' . $result->get_error_message() );
		}
		
		$vaultpress_ssh_key = $assoc_args['vaultpress_ssh_key'];
		if ( $vaultpress_ssh_key ) {
			$result = $this->install_vaultpress_ssh_keys( $vaultpress_ssh_key );
			if ( $result ) {
				WP_CLI::line( '- Added VaultPress SSH key to ' . $result );
			} else {
				WP_CLI::warning( '- Failed to find valid, accessible path to add SSH key' );
			}
		}

		// TODO: set up WP=>VaultPress SSH access

		WP_CLI::success( 'VaultPress configured successfully.' );
	}

	private function register_vaultpress( $api_key ) {
		$nonce = wp_create_nonce( 'vp_register_' . $api_key );
		$vaultpress_args = array(
			'registration_key' => $api_key,
			'nonce' => $nonce
		);

		$vp = VaultPress::init();
		$vp_response = $vp->contact_service( 'register', $vaultpress_args );

		if ( is_wp_error( $vp_response ) ) {
			return $vp_response;
		}

		$vp->update_option( 'key', $vp_response['key'] );
		$vp->update_option( 'secret', $vp_response['secret'] );

		$vp_check_result = $vp->check_connection( true );
		if ( ! $vp_check_result ) {
			return new WP_Error( 'jp-start-vp-connection', 'Site connection not working. Perhaps this domain is inaccessible? Error details: ' . $vp->get_option('connection_error_message') );
		}

		return true;
	}

	private function install_vaultpress_ssh_keys( $ssh_key ) {
		// write VP ssh key to ~/.ssh/authorized_keys
		foreach ( array( 'authorized_keys', 'authorized_keys2' ) as $authorized_keys_file ) {
			$authorized_keys_path = sprintf( '%s/.ssh/%s', getenv( 'HOME' ), $authorized_keys_file );

			// Add the key to authorized keys if the file exists and doesn't already contain this key
			if ( file_exists( $authorized_keys_path ) && ! strpos( file_get_contents( $authorized_keys_path ), $ssh_key ) ) {
				file_put_contents( $authorized_keys_path, $ssh_key, FILE_APPEND | LOCK_EX );
				return $authorized_keys_path;
			}
		}

		return false;
	}

	/**
	 * Install Jetpack keys
	 *
	 * ## OPTIONS
	 *
	 * --jetpack_id=<jetpack_id>
	 * : The Jetpack ID key provided by the Jetpack Start API.
	 *
	 * --jetpack_secret=<jetpack_secret>
	 * : The Jetpack secret key provided by the Jetpack Start API.
	 *
	 * --jetpack_access_token=<jetpack_access_token>
	 * : The Jetpack Access Token provided by the Jetpack Start API.
	 *
	 * [--jetpack_is_private]
	 * : Add this flag if the site is not public (i.e. not accessible via the internet)
	 *
	 * [--jetpack_init_modules]
	 * : Whether to initialize the default recommended modules.
	 *
	 * ## EXAMPLES
	 *
	 *     wp jetpack-start jetpack --jetpack_id=12345 --jetpack_secret="qwerty..." --jetpack_access_token="zxcv..."
	 *
	 * @subcommand jetpack
	 */
	public function install_jetpack_keys( $args, $assoc_args ) {
		if ( ! class_exists( 'Jetpack' ) ) {
			WP_CLI::error( 'Failed to install Jetpack keys: please activate the Jetpack plugin and re-run this script.' );
		}

		if ( ! is_user_logged_in() ) {
			WP_CLI::error( 'Failed to install Jetpack keys: please re-run this script with the `--user` param set to the Jetpack Connection Owner' );
		}

		WP_CLI( 'Configuring Jetpack' );

		$user_id = wp_get_current_user_id();

		$this->register_jetpack( $user_id, $assoc_args );

		if ( isset( $assoc_args['jetpack_init_modules'] ) ) {
			WP_CLI::line( '- Configuring recommended Jetpack modules' );

			$this->init_recommended_jetpack_modules();
		}

		WP_CLI::success( 'Jetpack configured successfully.' );
	}

	private function register_jetpack( $user_id, $args ) {
		$jetpack_id = (int) $args['jetpack_id'];
		$jetpack_secret = (string) $args['jetpack_secret'];
		$jetpack_access_token = (string) $args['jetpack_access_token'];
		$jetpack_is_private = isset( $args['jetpack_is_private'] );

		Jetpack_Options::update_options(
			array(
				'id'         	=> $jetpack_id,
				'blog_token' 	=> $jetpack_secret,
				'public'     	=> $jetpack_is_private,
				'master_user'	=> $user_id,
				'user_tokens'	=> array(
					$user_id => sprintf( '%s.%s',  $jetpack_access_token, $user_id ),
				),
			)
		);
	}

	private function init_recommended_jetpack_modules() {	
		require_once( JETPACK__PLUGIN_DIR . 'class.jetpack-admin.php' );
		$modules = Jetpack_Admin::init()->get_modules();
		foreach ( $modules as $module => $value ) {
			if ( in_array( 'Jumpstart', $value['feature'] ) ) {
				Jetpack::activate_module( $value['module'], false, false );
				Jetpack::state( 'message', 'no_message' );

				WP_CLI::line( sprintf( '-- Activated Jetpack `%s` module', $value['name'] ) );
			}
		}
	}
}

WP_CLI::add_command( 'jetpack-start', 'Jetpack_Start_CLI_Command' );
