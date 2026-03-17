<?php

namespace Automattic\VIP\CLI;

use WP_CLI;
use WPCOM_VIP_CLI_Command;

final class User_Unban_CLI_Command extends WPCOM_VIP_CLI_Command {

	public static function get_instance(): self {
		static $instance = null;

		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	public static function register() {
		$instance = self::get_instance();

		WP_CLI::add_command(
			'vip security unban ip',
			[ $instance, 'unban_ip' ],
			[
				'shortdesc' => 'Unban an IP address.',
				'synopsis'  => [
					[
						'type'        => 'positional',
						'name'        => 'ip',
						'description' => 'The IP address(es) to unban.',
						'optional'    => false,
						'repeating'   => true,
					],
				],
			],
		);

		WP_CLI::add_command(
			'vip security unban user',
			[ $instance, 'unban_user' ],
			[
				'shortdesc' => 'Unban a user.',
				'synopsis'  => [
					[
						'type'        => 'positional',
						'name'        => 'user',
						'description' => 'The user to unban (ID, login, or email).',
						'optional'    => false,
						'repeating'   => false,
					],
					[
						'type'        => 'positional',
						'name'        => 'ip',
						'description' => 'The IP address(es) to unban.',
						'optional'    => true,
						'repeating'   => true,
					],
				],
			],
		);
	}

	public function unban_ip( array $args, array $_assoc ): void {
		foreach ( $args as $ip ) {
			if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				WP_CLI::warning( sprintf( 'Invalid IP address: %s', $ip ) );
				continue;
			}

			wp_cache_delete( $ip, CACHE_GROUP_LOGIN_LIMIT );
			wp_cache_delete( $ip, CACHE_GROUP_LOST_PASSWORD_LIMIT );
			wp_cache_delete( CACHE_KEY_LOCK_PREFIX . $ip, CACHE_GROUP_LOGIN_LIMIT );
			wp_cache_delete( CACHE_KEY_LOCK_PREFIX . $ip, CACHE_GROUP_LOST_PASSWORD_LIMIT );

			WP_CLI::success( sprintf( 'Unbanned IP %s', $ip ) );
		}
	}

	public function unban_user( array $args, array $_assoc ): void {
		$user_arg = $args[0];
		$user     = null;

		$user = get_user_by( 'login', $user_arg );

		if ( ! $user && strpos( $user_arg, '@' ) !== false ) {
			$user = get_user_by( 'email', $user_arg );
		}

		if ( ! $user && is_numeric( $user_arg ) ) {
			$user = get_user_by( 'ID', (int) $user_arg );
		}

		if ( ! $user ) {
			WP_CLI::error( sprintf( 'User not found: %s', $user_arg ) );
			return;
		}

		$login = $user->user_login;

		wp_cache_delete( $login, CACHE_GROUP_LOGIN_LIMIT );
		wp_cache_delete( $login, CACHE_GROUP_LOST_PASSWORD_LIMIT );
		wp_cache_delete( CACHE_KEY_LOCK_PREFIX . $login, CACHE_GROUP_LOGIN_LIMIT );
		wp_cache_delete( CACHE_KEY_LOCK_PREFIX . $login, CACHE_GROUP_LOST_PASSWORD_LIMIT );

		$num_args = count( $args );
		for ( $i = 1; $i < $num_args; ++$i ) {
			$ip = $args[ $i ];

			if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				WP_CLI::warning( sprintf( 'Invalid IP address: %s', $ip ) );
				continue;
			}

			$keys   = [ $ip, $ip . '|' . $login ];
			$groups = [ CACHE_GROUP_LOGIN_LIMIT, CACHE_GROUP_LOST_PASSWORD_LIMIT ];

			foreach ( $keys as $key ) {
				foreach ( $groups as $group ) {
					wp_cache_delete( $key, $group );
					wp_cache_delete( CACHE_KEY_LOCK_PREFIX . $key, $group );
				}
			}

			WP_CLI::success( sprintf( 'Unbanned user %s for IP %s', $login, $ip ) );
		}

		WP_CLI::success( sprintf( 'Unbanned user %s', $login ) );
	}
}

User_Unban_CLI_Command::register();
