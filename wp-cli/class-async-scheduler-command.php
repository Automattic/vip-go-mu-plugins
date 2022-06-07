<?php
/**
 * Thin helper to assist with scheduling long-running CLI commands asynchronously.
 */
namespace Automattic\VIP\Commands;

use \WP_CLI;
use \Automattic\WP\Cron_Control\Events_Store;
use \Automattic\VIP\Utils\Alerts;

class Async_Scheduler_Command extends \WPCOM_VIP_CLI_Command {

	// Key for object cache entry that stores the timestamp for the event.
	const COMMAND_TIMESTAMP_CACHE_GROUP = 'vip_go_async_cmd_ts';
	// Cron event hook
	const COMMAND_CRON_EVENT_KEY = 'vip_go_async_cmd_run';

	// Audit messages will be sent to this channel in Slack
	const SLACK_NOTIFY_CHANNEL = '#vip-go-async-cmd-audit';
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

		if ( ! isset( $assoc_args['when'] ) ) {
			$assoc_args['when'] = time() + 1;
		}

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
	 * [--when]
	 * : A timestamp to check for a specific command
	 *
	 * [--cmd]
	 * : command to run on schedule, without `wp`
	 */
	public function check( $args, $assoc_args ) {
		self::validate_args( $args, $assoc_args );

		$command   = self::normalize_command( $assoc_args['cmd'] );
		$cache_key = md5( $command );

		// Try to use a flag if it present or fallback to cached value.
		// Cached value is not guaranteed to exist, especially for long-running commands.
		$timestamp = (string) ( $assoc_args['when'] ?? wp_cache_get( $cache_key, self::COMMAND_TIMESTAMP_CACHE_GROUP ) );

		$events = self::get_commands( $command );

		if ( ! $events ) {
			WP_CLI::error( 'No events found' );
		} else {
			WP_CLI::success( 'Found matching events' );
		}

		$events = array_map( function( $event ) use ( $timestamp ) {
			return [
				'ID'              => $event->ID,
				'status'          => $event->status,
				'created'         => $event->created,
				'modified'        => $event->last_modified,
				'timestamp'       => $event->timestamp,
				'timestamp_match' => $timestamp && $event->timestamp === $timestamp ? 'yes' : 'no',
			];
		}, $events );

		WP_CLI\Utils\format_items( 'table', $events, [ 'ID', 'status', 'created', 'modified', 'timestamp', 'timestamp_match' ] );
	}

	/**
	 * This is the runner itself.
	 *
	 * @param string $command
	 * @return void
	 */
	public static function runner( $command ) {
		$cache_key = md5( $command );
		$start     = time();

		Alerts::chat( self::SLACK_NOTIFY_CHANNEL, sprintf( 'Kicking off `%s` on `%s`', $command, gethostname() ), 5 );

		$result       = WP_CLI::runcommand(
			$command,
			[
				// We need to be able to clean up and process success/errors and implement logging (if need be):
				// 1. Grab an object that includes STDERR, STDOUT and exit code.
				// 2. Prevent the runner command termination on WP_CLI::error() in the child command.
				'return'     => 'all',
				'exit_error' => false,
			]
		);
		$took_seconds = time() - $start;
		wp_cache_delete( $cache_key, self::COMMAND_TIMESTAMP_CACHE_GROUP );

		$formatted_message = sprintf(
			'The scheduled command `%s` has finished execution in %d seconds (exit code: %d)

			*Hostname*: %s

			*STDOUT* (truncated):
			```
			%s
			```

			*STDERR*:
			```
			%s
			```
			',
			$command,
			$took_seconds,
			$result->return_code,
			gethostname(),
			// Stdout can be quite lengthy, we don't really need ALL of it,
			// Truncate to last 10 lines - this should be enough for audit purposes.
			$result->stdout ? join( "\n", array_slice( explode( "\n", $result->stdout ), -10, 10 ) ) : 'empty',
			// By contrast, stderr is critical for debugging.
			$result->stderr ?: 'empty' // phpcs:ignore WordPress.PHP.DisallowShortTernary.Found -- Elvis is cool
		);

		// Trim leading/trailing whitespaces.
		$formatted_message = join( "\n", array_map( 'trim', explode( "\n", $formatted_message ) ) );

		// A successfully executed command messaeg has INFORMATION level, an errored one is WARNING.
		$log_level = 0 === (int) $result->return_code ? 5 : 1;

		Alerts::chat( self::SLACK_NOTIFY_CHANNEL, $formatted_message, $log_level );

		if ( 0 !== $result->return_code || $result->stderr ) {
			WP_CLI::warning( sprintf( 'The scheduled command `%s` has non-zero exit code or non-empty STDERR', $command, $result->return_code ) );
			\Automattic\VIP\Logstash\log2logstash( [
				'severity' => 'warning',
				'feature'  => 'vip_async_cmd_scheduler',
				'message'  => 'The scheduled command had non-0 exit code',
				'extra'    => [
					'command'   => $command,
					'stderr'    => $result->stderr,
					'exit_code' => $result->return_code,
					'took'      => $took_seconds,
				],
			] );
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
