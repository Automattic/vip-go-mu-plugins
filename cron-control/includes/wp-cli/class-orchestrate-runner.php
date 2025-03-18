<?php
/**
 * Execute cron via WP-CLI
 *
 * Not intended for human use, rather it powers the Go-based Runner. Use the `events` command instead.
 *
 * @package a8c_Cron_Control
 */

namespace Automattic\WP\Cron_Control\CLI;

/**
 * Commands used by the Go-based runner to execute events
 */
class Orchestrate_Runner extends \WP_CLI_Command {
	/**
	 * List the next set of events to run; meant for Runner
	 *
	 * Will not be all events, just those atop the curated queue
	 *
	 * Not intended for human use, rather it powers the Go-based Runner. Use the `events list` command instead.
	 *
	 * @subcommand list-due-batch
	 * @synopsis [--queue-size=<numberOfEvents>] [--queue-window=<secondsIntoTheFuture>] [--format=<table|json|csv|etc>]
	 * @param array $args Array of positional arguments.
	 * @param array $assoc_args Array of flags.
	 */
	public function list_due_now( $args, $assoc_args ) {
		if ( 0 !== \Automattic\WP\Cron_Control\Events::instance()->run_disabled() ) {
			\WP_CLI::error( __( 'Automatic event execution is disabled', 'automattic-cron-control' ) );
		}

		// Control how many events are fetched. Note that internal events can exceed this cap.
		$queue_size = \WP_CLI\Utils\get_flag_value( $assoc_args, 'queue-size', null );
		if ( ! is_numeric( $queue_size ) ) {
			$queue_size = null;
		}

		// Control how far into the future events are fetched.
		$queue_window = \WP_CLI\Utils\get_flag_value( $assoc_args, 'queue-window', null );
		if ( ! is_numeric( $queue_window ) ) {
			$queue_window = null;
		}

		$events = \Automattic\WP\Cron_Control\Events::instance()->get_events( $queue_size, $queue_window );
		$events = is_array( $events['events'] ) ? $events['events'] : [];

		$format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

		\WP_CLI\Utils\format_items(
			$format,
			$events,
			array(
				'timestamp',
				'action',
				'instance',
			)
		);
	}

	/**
	 * Run a given event; meant for Runner
	 *
	 * Not intended for human use, rather it powers the Go-based Runner. Use the `events run` command instead.
	 *
	 * @subcommand run
	 * @synopsis --timestamp=<timestamp> --action=<action-hashed> --instance=<instance>
	 * @param array $args Array of positional arguments.
	 * @param array $assoc_args Array of flags.
	 */
	public function run_event( $args, $assoc_args ) {
		if ( 0 !== \Automattic\WP\Cron_Control\Events::instance()->run_disabled() ) {
			\WP_CLI::error( __( 'Automatic event execution is disabled', 'automattic-cron-control' ) );
		}

		$timestamp = \WP_CLI\Utils\get_flag_value( $assoc_args, 'timestamp', null );
		$action    = \WP_CLI\Utils\get_flag_value( $assoc_args, 'action', null );
		$instance  = \WP_CLI\Utils\get_flag_value( $assoc_args, 'instance', null );

		if ( ! is_numeric( $timestamp ) ) {
			\WP_CLI::error( __( 'Invalid timestamp', 'automattic-cron-control' ) );
		}

		if ( ! is_string( $action ) ) {
			\WP_CLI::error( __( 'Invalid action', 'automattic-cron-control' ) );
		}

		if ( ! is_string( $instance ) ) {
			\WP_CLI::error( __( 'Invalid instance', 'automattic-cron-control' ) );
		}

		$now = time();
		if ( $timestamp > $now ) {
			/* translators: 1: Event execution time in UTC, 2: Human time diff */
			\WP_CLI::error( sprintf( __( 'Given timestamp is for %1$s UTC, %2$s from now. The event\'s existence was not confirmed, and no attempt was made to execute it.', 'automattic-cron-control' ), date_i18n( TIME_FORMAT, $timestamp ), human_time_diff( $now, $timestamp ) ) );
		}

		// Prepare environment.
		\Automattic\WP\Cron_Control\set_doing_cron();

		// Run the event.
		$run = \Automattic\WP\Cron_Control\run_event( $timestamp, $action, $instance );

		if ( is_wp_error( $run ) ) {
			$error_data = $run->get_error_data();

			if ( isset( $error_data['status'] ) && 404 === $error_data['status'] ) {
				\WP_CLI::warning( $run->get_error_message() );

				exit;
			} else {
				\WP_CLI::error( $run->get_error_message() );
			}
		} elseif ( isset( $run['success'] ) && true === $run['success'] ) {
			\WP_CLI::success( $run['message'] );
		} else {
			\WP_CLI::error( $run['message'] );
		}
	}

	/**
	 * Get some details needed to execute events; meant for Runner
	 *
	 * Not intended for human use, rather it powers the Go-based Runner. Use the `orchestrate manage-automatic-execution` command instead.
	 *
	 * @subcommand get-info
	 * @param array $args Array of positional arguments.
	 * @param array $assoc_args Array of flags.
	 */
	public function get_info( $args, $assoc_args ) {
		$info = array(
			array(
				'multisite' => is_multisite() ? 1 : 0,
				'siteurl'   => site_url(),
				'disabled'  => \Automattic\WP\Cron_Control\Events::instance()->run_disabled(),
			),
		);

		$format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

		\WP_CLI\Utils\format_items( $format, $info, array_keys( $info[0] ) );
	}
}

\WP_CLI::add_command( 'cron-control orchestrate runner-only', 'Automattic\WP\Cron_Control\CLI\Orchestrate_Runner' );
