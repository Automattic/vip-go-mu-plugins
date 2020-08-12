<?php
/**
 * Thin helper to assist with scheduling long-running CLI commands asynchronously.
 */

use \Automattic\WP\Cron_Control\Events_Store;

class VIP_Go_Scheduler_Command extends WPCOM_VIP_CLI_Command {

	// Key for object cache entry that stores the timestamp for the event
	const SCHEDULE_TIMESTAMP_KEY = 'vip_go_async_cmd_ts';
	// Cron event hook
	const SCHEDULE_EVENT_KEY = 'vip_go_async_cmd_run';
	/**
	 * Schedule an arbitrary CLI command to be executed later as a single event.
	 *
	 * @subcommand schedule
	 *
	 * ## OPTIONS
	 *
	 * [--when]
	 * : A timestamp for when to run
	 *
	 * [--cmd]
	 * : command to run on schedule, without `wp`
	 */
	public function schedule( $args, $assoc_args ) {
		self::validate_args( $args, $assoc_args );

		// Normalize the command
		$command   = self::normalize_command( $assoc_args['cmd'] );
		$cache_key = md5( $command );

		$timestamp = $assoc_args['when'] === 'now' ? time() + 1 : $assoc_args['when'];

		$events = self::get_commands( $command );

		$scheduled_or_running = array_filter(
			$events,
			function( $evt ) {
				return in_array( $evt->status, [ 'pending', 'running' ], true );
			}
		);

		if ( ! $scheduled_or_running ) {
			WP_CLI::line( 'Scheduling the command: ' . $command );
			wp_schedule_single_event( $timestamp, self::SCHEDULE_EVENT_KEY, [ $command ] );
			wp_cache_set( $cache_key, $timestamp, self::SCHEDULE_TIMESTAMP_KEY );
		} else {
			WP_CLI::error( 'This command is already scheduled or running.' );
		}
	}

	/**
	 * Check the status of the command
	 *
	 * @subcommand check-status
	 *
	 * ## OPTIONS
	 *
	 * [--cmd]
	 * : command to run on schedule, without `wp`
	 */
	public function check( $args, $assoc_args ) {
		self::validate_args( $args, $assoc_args );

		$command   = self::normalize_command( $assoc_args['cmd'] );
		$cache_key = md5( $command );

		// This is not guaranteed to exist, but worth a shot.
		$timestamp = wp_cache_get( $cache_key, self::SCHEDULE_TIMESTAMP_KEY );

		$events = self::get_commands( $command );

		if ( ! $events ) {
			WP_CLI::error( 'No events found' );
		}

		// phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.Found,Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure
		if ( $timestamp ) {
			$event_idx = array_search( (string) $timestamp, array_column( $events, 'timestamp' ) );

			if ( null !== $event_idx && isset( $events[ $event_idx ] ) ) {
				WP_CLI::success( sprintf( 'Found event, status: %s; created: %s; last modified: %s', $events[ $event_idx ]->status, $events[ $event_idx ]->created, $events[ $event_idx ]->last_modified ) );
				exit;
			}
		} else {
			WP_CLI::warning( "Exact match couldn't be found, but there are commands with the same arguments:" );
			foreach ( $events as $event ) {
				WP_CLI::line( sprintf( 'Status: %s; created: %s; last modified: %s', $event->status, $event->created, $event->last_modified ) );
			}
		}
	}

	/**
	 * This is the runner itself.
	 *
	 * @param string $command
	 * @return void
	 */
	public static function runner( $command ) {
		$cache_key = md5( $command );

		$result = WP_CLI::runcommand(
			$command,
			[
				// Grab an object that includes STDERR, STDOUT and exit code
				'return'     => 'all',
				// Do not exit on error
				'exit_error' => false,
			]
		);

		wp_cache_delete( $cache_key, self::SCHEDULE_TIMESTAMP_KEY );

		if ( $result->stderr ) {
			WP_CLI::warning( $result->stderr );
		}

		if ( $result->return_code !== 0 ) {
			WP_CLI::warning( sprintf( 'The scheduled command `%s` has non-zero exit code', $command, $result->return_code ) );
		}
	}

	/**
	 * Grab any events we have stored that match the command by hook and the argument (the command to be ran).
	 *
	 * @param string $command
	 * @return array 
	 */
	public static function get_commands( string $command ): array {
		global $wpdb;

		$command      = self::normalize_command( $command );
		$events_store = Events_Store::instance();

		// phpcs:ignore 
		$events = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$events_store->get_table_name()} WHERE action = %s AND args = %s LIMIT 10", self::SCHEDULE_EVENT_KEY, maybe_serialize( [ $command ] ) ) );

		return $events;
	}

	/**
	 * Strip leading `wp` and trim the string
	 *
	 * @param string $command
	 * @return string normalized command
	 */
	public static function normalize_command( string $command ): string {
		return trim( str_replace( 'wp ', '', $command ) );
	}

	/**
	 * Validate required arguments
	 *
	 * @param array $args
	 * @param array $assoc_args
	 * @return void
	 */
	public static function validate_args( $args, $assoc_args ) {
		$required = [ 'cmd' ];
		foreach ( $required as $req_arg ) {
			if ( ! isset( $assoc_args[ $req_arg ] ) || empty( $assoc_args[ $req_arg ] ) ) {
				WP_CLI::error( sprintf( 'Required argument is missing or empty: %s', $req_arg ) );
			}
		}
	}
}

WP_CLI::add_command(
	'vip cmd-scheduler',
	'VIP_Go_Scheduler_Command',
	[
		'before_invoke' => function() {
			// Cron Control is a hard dependency, so let's just bail right away if it's not available
			if ( ! class_exists( '\Automattic\WP\Cron_Control\Events_Store' ) ) {
				WP_CLI::error( 'Cron Control is not loaded' );
			}
		},
	]
);

add_action( VIP_Go_Scheduler_Command::SCHEDULE_EVENT_KEY, [ 'VIP_Go_Scheduler_Command', 'runner' ] );
