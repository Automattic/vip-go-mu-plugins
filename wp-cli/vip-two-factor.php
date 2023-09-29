<?php

class VIP_Two_Factor_Command extends WPCOM_VIP_CLI_Command {
	/**
	 * Reports on the 2FA status for users of a site. Lists all users by default.
	 * Note: Users without `manage_options` cap will be listed as "n/a" for 2FA status.
	 *
	 * ## OPTIONS
	 *
	 * [--2fa-enabled=<true|false>]
	 * : Filter by whether 2FA is enabled or not.
	 *
	 * [--role=<string>]
	 * : Filter by user role.
	 *
	 * [--2fa-provider=<string>]
	 * : Filter by 2FA provider. Accepts values of: email, totp, fido_u2f, backup_codes, dummy
	 *
	 * [--user_login=<login|id|email>]
	 * : Filter by user login, ID, or email.
	 *
	 *
	 * ## EXAMPLES
	 *
	 *      wp vip two-factor report
	 *      wp vip two-factor report --2fa-enabled=true
	 *      wp vip two-factor report --2fa-provider=email
	 *      wp vip two-factor report --role=administrator
	 *      wp vip two-factor report --role=administrator --2fa-enabled=false
	 *      wp vip two-factor report --role=administrator --2fa-enabled=true --2fa-provider=email
	 *      wp vip two-factor report --user_login=wpvip
	 *
	 * @subcommand report
	 * @synopsis [--2fa-enabled=<true|false>] [--role=<string>] [--2fa-provider=<string>] [--user_login=<login|id|email>]
	 */
	public function report( $args, $assoc_args ) {
		if ( ! apply_filters( 'wpcom_vip_enable_two_factor', true ) ) {
			WP_CLI::error( 'This site has disabled Two Factor.' );
		}

		$twofa_enabled_flag  = \WP_CLI\Utils\get_flag_value( $assoc_args, '2fa-enabled', null );
		$role_flag           = \WP_CLI\Utils\get_flag_value( $assoc_args, 'role', null );
		$twofa_provider_flag = strtolower( \WP_CLI\Utils\get_flag_value( $assoc_args, '2fa-provider', null ) );
		$user_id_flag        = \WP_CLI\Utils\get_flag_value( $assoc_args, 'user_login', null );
		$format              = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
		if ( 'false' === $twofa_enabled_flag && $twofa_provider_flag ) {
			WP_CLI::error( 'Cannot filter by Two Factor provider when searching for users without Two Factor.' );
		}


		$providers            = Two_Factor_Core::get_providers();
		$default_provider_map = [
			'Two_Factor_Email'        => 'email',
			'Two_Factor_Totp'         => 'totp',
			'Two_Factor_FIDO_U2F'     => 'fido_u2f',
			'Two_Factor_Backup_Codes' => 'backup_codes',
			'Two_Factor_Dummy'        => 'dummy',
		];
		$providers_map        = array_intersect_key( $default_provider_map, $providers );
		if ( $twofa_provider_flag ) {
			if ( ! in_array( $twofa_provider_flag, $providers_map, true ) ) {
				WP_CLI::error(
					sprintf(
						'Invalid Two Factor provider "%s". Valid values are: %s',
						$twofa_provider_flag,
						implode( ', ', array_values( $providers_map ) )
					)
				);
			}

			$twofa_enabled_flag  = 'true'; // In case `--2fa-enabled` was not passed in.
			$twofa_provider_flag = array_search( $twofa_provider_flag, $providers_map, true );
		}

		$cap = apply_filters( 'wpcom_vip_two_factor_enforcement_cap', 'manage_options' );

		if ( $user_id_flag ) {
			if ( is_numeric( $user_id_flag ) ) {
				$user = get_user_by( 'id', $user_id_flag );
			} else {
				$user = get_user_by( 'login', $user_id_flag );
				if ( ! $user ) {
					$user = get_user_by( 'email', $user_id_flag );
				}
			}
			if ( ! $user ) {
				WP_CLI::error( sprintf( 'User "%s" not found.', $user_id_flag ) );
			}

			$users = [ $user ];
		} else {
			$user_args = [];
			if ( $twofa_enabled_flag ) {
				$user_args['capability__in'] = $cap;
			}
			if ( $role_flag ) {
				$user_args['role'] = $role_flag;
			}

			$users = get_users( $user_args );
		}

		foreach ( $users as $idx => $user ) {
			$user->two_factor_enabled   = 'false';
			$user->two_factor_providers = '';

			if ( Two_Factor_Core::is_user_using_two_factor( $user->ID ) ) {
				$user->two_factor_enabled = 'true';

				$user_providers = Two_Factor_Core::get_enabled_providers_for_user( $user );
				if ( $twofa_provider_flag && ! in_array( $twofa_provider_flag, $user_providers, true ) ) {
					unset( $users[ $idx ] );
				}
				$user_providers             = array_map( function ( $provider ) use ( $providers ) {
					return $providers[ $provider ]->get_label();
				}, $user_providers );
				$user->two_factor_providers = implode( ', ', $user_providers );
			} elseif ( ! user_can( $user->ID, $cap ) ) {
				$user->two_factor_enabled = 'n/a';
			}

			if ( $twofa_enabled_flag && $user->two_factor_enabled !== $twofa_enabled_flag ) {
				unset( $users[ $idx ] );
			}
		}

		$fields   = array( 'ID', 'display_name', 'roles' );
		$fields[] = 'two_factor_enabled';
		$fields[] = 'two_factor_providers';
		WP_CLI\Utils\format_items( $format, $users, $fields );
	}
}

WP_CLI::add_command( 'vip two-factor', 'VIP_Two_Factor_Command' );
