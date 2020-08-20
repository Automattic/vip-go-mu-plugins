<?php
/**
 * Thin helper to assist with scheduling long-running CLI commands asynchronously.
 */
namespace Automattic\VIP\Commands;

use \WP_CLI;
use \Automattic\WP\Cron_Control\Events_Store;

class Async_Scheduler_Command extends \WPCOM_VIP_CLI_Command {

	// Key for object cache entry that stores the timestamp for the event.
	const COMMAND_TIMESTAMP_CACHE_GROUP = 'vip_go_async_cmd_ts';
	// Cron event hook
	const COMMAND_CRON_EVENT_KEY = 'vip_go_async_cmd_run';
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

		$timestamp = 'now' === $assoc_args['when'] ? time() + 1 : $assoc_args['when'];

		$events = self::get_commands( $command );

		$scheduled_or_running = array_filter(
			$events,
			function( $evt ) {
				return in_array( $evt->status, [ 'pending', 'running' ], true );
			}
		);

		if ( ! $scheduled_or_running ) {
			WP_CLI::line( sprintf( 'Scheduling the command: `%s` (timestamp: %d)', $command, $timestamp ) );
			wp_schedule_single_event( $timestamp, self::COMMAND_CRON_EVENT_KEY, [ $command ] );
			wp_cache_set( $cache_key, $timestamp, self::COMMAND_TIMESTAMP_CACHE_GROUP );
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
		$timestamp = wp_cache_get( $cache_key, self::COMMAND_TIMESTAMP_CACHE_GROUP );

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
				// We need to be able to clean up and process success/errors and implement logging (if need be):
				// 1. Grab an object that includes STDERR, STDOUT and exit code.
				// 2. Prevent the runner command termination on WP_CLI::error() in the child command.
				'return'     => 'all',
				'exit_error' => false,
			]
		);

		wp_cache_delete( $cache_key, self::COMMAND_TIMESTAMP_CACHE_GROUP );

		if ( $result->stderr ) {
			WP_CLI::warning( $result->stderr );
		}

		if ( 0 !== $result->return_code ) {
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
		$events = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$events_store->get_table_name()} WHERE action = %s AND args = %s LIMIT 10", self::COMMAND_CRON_EVENT_KEY, maybe_serialize( [ $command ] ) ) );

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
	'\Automattic\VIP\Commands\Async_Scheduler_Command',
	[
		'before_invoke' => function() {
			// Cron Control is a hard dependency, so let's just bail right away if it's not available
			if ( ! class_exists( '\Automattic\WP\Cron_Control\Events_Store' ) ) {
				WP_CLI::error( 'Cron Control is not loaded' );
			}
		},
	]
);

add_action( Async_Scheduler_Command::COMMAND_CRON_EVENT_KEY, [ '\Automattic\VIP\Commands\Async_Scheduler_Command', 'runner' ] );
