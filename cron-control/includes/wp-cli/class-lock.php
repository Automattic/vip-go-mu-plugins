<?php
/**
 * Manage plugin's locks via WP-CLI
 *
 * @package a8c_Cron_Control
 */

namespace Automattic\WP\Cron_Control\CLI;

/**
 * Manage Cron Control's internal locks
 */
class Lock extends \WP_CLI_Command {
	/**
	 * Manage the lock that limits concurrent job executions
	 *
	 * @subcommand manage-run-lock
	 * @synopsis [--reset]
	 * @param array $args Array of positional arguments.
	 * @param array $assoc_args Array of flags.
	 */
	public function manage_run_lock( $args, $assoc_args ) {
		$lock_name        = \Automattic\WP\Cron_Control\Events::LOCK;
		$lock_limit       = \Automattic\WP\Cron_Control\JOB_CONCURRENCY_LIMIT;
		$lock_description = __( 'This lock limits the number of events run concurrently.', 'automattic-cron-control' );

		$this->get_reset_lock( $args, $assoc_args, $lock_name, $lock_limit, $lock_description );
	}

	/**
	 * Manage the lock that limits concurrent execution of jobs with the same action
	 *
	 * @subcommand manage-event-lock
	 * @synopsis <action> [--reset]
	 * @param array $args Array of positional arguments.
	 * @param array $assoc_args Array of flags.
	 */
	public function manage_event_lock( $args, $assoc_args ) {
		if ( empty( $args[0] ) ) {
			\WP_CLI::error( sprintf( __( 'Specify an action', 'automattic-cron-control' ) ) );
		}

		$lock_name = \Automattic\WP\Cron_Control\Events::instance()->get_lock_key_for_event_action(
			(object) array(
				'action' => $args[0],
			)
		);

		$lock_limit       = 1;
		$lock_description = __( "This lock prevents concurrent executions of events with the same action, regardless of the action's arguments.", 'automattic-cron-control' );

		$this->get_reset_lock( $args, $assoc_args, $lock_name, $lock_limit, $lock_description );
	}

	/**
	 * Retrieve a lock's current value, or reset it
	 *
	 * @param array  $args Array of positional arguments.
	 * @param array  $assoc_args Array of flags.
	 * @param string $lock_name Name of lock to reset.
	 * @param int    $lock_limit Lock's maximum concurrency.
	 * @param string $lock_description Human-friendly explanation of lock's purpose.
	 */
	private function get_reset_lock( $args, $assoc_args, $lock_name, $lock_limit, $lock_description ) {
		// Output information about the lock.
		\WP_CLI::log( $lock_description . "\n" );

		/* translators: 1: Lock limit */
		\WP_CLI::log( sprintf( __( 'Maximum: %s', 'automattic-cron-control' ), number_format_i18n( $lock_limit ) ) . "\n" );

		// Reset requested.
		if ( isset( $assoc_args['reset'] ) ) {
			\WP_CLI::warning( __( 'Resetting lock...', 'automattic-cron-control' ) . "\n" );

			$lock      = \Automattic\WP\Cron_Control\Lock::get_lock_value( $lock_name );
			$timestamp = \Automattic\WP\Cron_Control\Lock::get_lock_timestamp( $lock_name );

			/* translators: 1: Previous lock value */
			\WP_CLI::log( sprintf( __( 'Previous value: %s', 'automattic-cron-control' ), number_format_i18n( $lock ) ) );
			/* translators: 1: Previous lock timestamp */
			\WP_CLI::log( sprintf( __( 'Previously modified: %s UTC', 'automattic-cron-control' ), date_i18n( TIME_FORMAT, $timestamp ) ) . "\n" );

			\WP_CLI::confirm( sprintf( __( 'Are you sure you want to reset this lock?', 'automattic-cron-control' ) ) );
			\WP_CLI::log( '' );

			\Automattic\WP\Cron_Control\Lock::reset_lock( $lock_name );
			\WP_CLI::success( __( 'Lock reset', 'automattic-cron-control' ) . "\n" );
			\WP_CLI::log( __( 'New lock values:', 'automattic-cron-control' ) );
		}

		// Output lock state.
		$lock      = \Automattic\WP\Cron_Control\Lock::get_lock_value( $lock_name );
		$timestamp = \Automattic\WP\Cron_Control\Lock::get_lock_timestamp( $lock_name );

		/* translators: 1: Current lock value */
		\WP_CLI::log( sprintf( __( 'Current value: %s', 'automattic-cron-control' ), number_format_i18n( $lock ) ) );
		/* translators: 1: Current lock timestamp */
		\WP_CLI::log( sprintf( __( 'Last modified: %s UTC', 'automattic-cron-control' ), date_i18n( TIME_FORMAT, $timestamp ) ) );
	}
}

\WP_CLI::add_command( 'cron-control locks', 'Automattic\WP\Cron_Control\CLI\Lock' );
