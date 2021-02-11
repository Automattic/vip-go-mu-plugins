<?php

/**
 * VIP Backup User Roles CLI

 * @package wp-cli
 */
class VIP_Backup_User_Role_CLI extends \WPCOM_VIP_CLI_Command {

	/**
	 * List Available Backups
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : The serialization format for the value. total_bytes displays the total size of matching options in bytes.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - count
	 *   - yaml
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {

		$backup_roles = get_option( 'vip_backup_user_roles', [] );
		$backup_roles = array_reverse( $backup_roles, true );

		$data = [];

		foreach ( $backup_roles as $time => $roles ) {
			$data[] = [
				'time_key'  => $time,
				'time_h'    => gmdate( 'Y-m-d H:i:s', $time ),
				'num_roles' => count( $roles ),
			];
		}

		$formatter = new \WP_CLI\Formatter( $assoc_args, [ 'time_key', 'time_h', 'num_roles' ], 'role_backups' );
		$formatter->display_items( $data );

	}

	/**
	 * View Specific Backup
	 *
	 * ## OPTIONS
	 *
	 * [<time_key>]
	 * : See `vip role-backup list`. Defaults to latest
	 *
	 * [--names-only]
	 * : Get value in a particular format.
	 *
	 * [--format=<format>]
	 * : Get value in a particular format.
	 * ---
	 * default: var_export
	 * options:
	 *   - var_export
	 *   - json
	 *   - yaml
	 * ---
	 *
	 */
	public function view( $args, $assoc_args ) {

		$key = $args[0] ?? 'latest';
		$names_only = WP_CLI\Utils\get_flag_value(
			$assoc_args,
			'names-only',
			false
		);
		$backup_roles = get_option( 'vip_backup_user_roles', [] );

		if ( 'latest' === $key ) {
			$backup = array_pop( $backup_roles );
		} else {
			if ( isset( $backup_roles[ $key ] ) ) {
				$backup = $backup_roles[ $key ];
			} else {
				\WP_CLI::error( 'Specified backup time_key not found.' );
			}
		}

		if ( $names_only ) {
			$backup = array_keys( $backup );
		}

		\WP_CLI::print_value( $backup, $assoc_args );

	}

	/**
	 * Restore A Backup
	 *
	 * ## OPTIONS
	 *
	 * [<time_key>]
	 * : See `vip role-backup list`. Defaults to latest
	 *
	 */
	public function restore( $args, $assoc_args ) {

		global $wpdb;
		$key = $args[0] ?? 'latest';

		$backup_roles = get_option( 'vip_backup_user_roles', [] );

		if ( 'latest' === $key ) {
			$backup = array_pop( $backup_roles );
		} else {
			if ( isset( $backup_roles[ $key ] ) ) {
				$backup = $backup_roles[ $key ];
			} else {
				\WP_CLI::error( 'Specified backup time_key not found.' );
			}
		}

		update_option( $wpdb->prefix . 'user_roles', $backup );

		\WP_CLI::success( sprintf( 'Restored %s backup with %d roles', $key, count( $backup ) ) );

	}

	/**
	 * Backup Current Roles Now
	 *
	 * ## OPTIONS
	 *
	 */
	public function now( $args, $assoc_args ) {

		\WP_CLI::confirm( 'This will remove the oldest backup. Ok?' );

		\Automattic\VIP\do_user_role_backup();

		\WP_CLI::success( 'Current roles backed up.' );

	}

}

WP_CLI::add_command( 'vip role-backup', __NAMESPACE__ . '\VIP_Backup_User_Role_CLI' );
