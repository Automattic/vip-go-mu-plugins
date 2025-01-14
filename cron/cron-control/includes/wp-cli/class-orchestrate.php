<?php
/**
 * Enable and disable automatic event execution via WP-CLI
 *
 * @package a8c_Cron_Control
 */

namespace Automattic\WP\Cron_Control\CLI;

/**
 * Commands to manage automatic event execution
 */
class Orchestrate extends \WP_CLI_Command {
	/**
	 * Check the status of automatic event execution
	 *
	 * @subcommand check-status
	 * @param array $args Array of positional arguments.
	 * @param array $assoc_args Array of flags.
	 */
	public function get_automatic_execution_status( $args, $assoc_args ) {
		$status = \Automattic\WP\Cron_Control\Events::instance()->run_disabled();

		switch ( $status ) {
			case 0:
				$status = __( 'Automatic execution is enabled', 'automattic-cron-control' );
				break;

			case 1:
				$status = __( 'Automatic execution is disabled indefinitely', 'automattic-cron-control' );
				break;

			default:
				/* translators: 1: Human time diff, 2: Time execution is disabled until */
				$status = sprintf( __( 'Automatic execution is disabled for %1$s (until %2$s UTC)', 'automattic-cron-control' ), human_time_diff( $status ), date_i18n( TIME_FORMAT, $status ) );
				break;
		}

		\WP_CLI::log( $status );
	}

	/**
	 * Change status of automatic event execution
	 *
	 * When using the Go-based runner, it may be necessary to stop execution for a period, or indefinitely
	 *
	 * @subcommand manage-automatic-execution
	 * @synopsis [--enable] [--disable] [--disable_until=<disable_until>]
	 * @param array $args Array of positional arguments.
	 * @param array $assoc_args Array of flags.
	 */
	public function manage_automatic_execution( $args, $assoc_args ) {
		// Update execution status.
		$disable_ts = \WP_CLI\Utils\get_flag_value( $assoc_args, 'disable_until', 0 );
		$disable_ts = absint( $disable_ts );

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'enable', false ) ) {
			$updated = \Automattic\WP\Cron_Control\Events::instance()->update_run_status( 0 );

			if ( $updated ) {
				\WP_CLI::success( __( 'Enabled', 'automattic-cron-control' ) );
				return;
			}

			\WP_CLI::error( __( 'Could not enable automatic execution. Please check the current status.', 'automattic-cron-control' ) );
		} elseif ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'disable', false ) ) {
			$updated = \Automattic\WP\Cron_Control\Events::instance()->update_run_status( 1 );

			if ( $updated ) {
				\WP_CLI::success( __( 'Disabled', 'automattic-cron-control' ) );
				return;
			}

			\WP_CLI::error( __( 'Could not disable automatic execution. Please check the current status.', 'automattic-cron-control' ) );
		} elseif ( $disable_ts > 0 ) {
			if ( $disable_ts > time() ) {
				$updated = \Automattic\WP\Cron_Control\Events::instance()->update_run_status( $disable_ts );

				if ( $updated ) {
					/* translators: 1: Human time diff, 2: Time execution is disabled until */
					\WP_CLI::success( sprintf( __( 'Disabled for %1$s (until %2$s UTC)', 'automattic-cron-control' ), human_time_diff( $disable_ts ), date_i18n( TIME_FORMAT, $disable_ts ) ) );
					return;
				}

				\WP_CLI::error( __( 'Could not disable automatic execution. Please check the current status.', 'automattic-cron-control' ) );
			} else {
				\WP_CLI::error( __( 'Timestamp is in the past.', 'automattic-cron-control' ) );
			}
		}

		\WP_CLI::error( __( 'Please provide a valid action.', 'automattic-cron-control' ) );
	}
}

\WP_CLI::add_command( 'cron-control orchestrate', 'Automattic\WP\Cron_Control\CLI\Orchestrate' );
