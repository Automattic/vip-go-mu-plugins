<?php
/**
 * Interact with plugin's REST API via WP-CLI
 *
 * @package a8c_Cron_Control
 */

namespace Automattic\WP\Cron_Control\CLI;

use \Automattic\WP\Cron_Control\Event;

/**
 * Make requests to Cron Control's REST API
 */
class REST_API extends \WP_CLI_Command {
	/**
	 * Retrieve the current event queue
	 *
	 * @subcommand get-queue
	 * @param array $args Array of positional arguments.
	 * @param array $assoc_args Array of flags.
	 */
	public function get_queue( $args, $assoc_args ) {
		// Build and make request.
		$queue_request = new \WP_REST_Request( 'POST', '/' . \Automattic\WP\Cron_Control\REST_API::API_NAMESPACE . '/' . \Automattic\WP\Cron_Control\REST_API::ENDPOINT_LIST );
		$queue_request->add_header( 'Content-Type', 'application/json' );
		$queue_request->set_body(
			wp_json_encode(
				array(
					'secret' => \WP_CRON_CONTROL_SECRET,
				)
			)
		);

		$queue_request = rest_do_request( $queue_request );

		// Oh well.
		if ( $queue_request->is_error() ) {
			\WP_CLI::error( $queue_request->as_error()->get_error_message() );
		}

		// Get the decoded JSON object returned by the API.
		$queue_response = $queue_request->get_data();

		// No events, nothing more to do.
		if ( empty( $queue_response['events'] ) ) {
			\WP_CLI::warning( __( 'No events in the current queue', 'automattic-cron-control' ) );
			return;
		}

		// Prepare items for display.
		$events_for_display      = $this->format_events( $queue_response['events'] );
		$total_events_to_display = count( $events_for_display );
		/* translators: 1: Event count */
		\WP_CLI::log( sprintf( _n( 'Displaying %s event', 'Displaying %s events', $total_events_to_display, 'automattic-cron-control' ), number_format_i18n( $total_events_to_display ) ) );

		// And reformat.
		$format = 'table';
		if ( isset( $assoc_args['format'] ) ) {
			if ( 'ids' === $assoc_args['format'] ) {
				\WP_CLI::error( __( 'Invalid output format requested', 'automattic-cron-control' ) );
			} else {
				$format = $assoc_args['format'];
			}
		}

		\WP_CLI\Utils\format_items(
			$format,
			$events_for_display,
			array(
				'timestamp',
				'action',
				'instance',
				'scheduled_for',
				'internal_event',
				'schedule_name',
				'event_args',
			)
		);
	}

	/**
	 * Format event data into something human-readable
	 *
	 * @param array $events Events to display.
	 * @return array
	 */
	private function format_events( $events ) {
		$formatted_events = array();

		foreach ( $events as $event_data ) {
			$event = Event::find( [
				'timestamp'     => $event_data['timestamp'],
				'action_hashed' => $event_data['action'],
				'instance'      => $event_data['instance'],
			] );

			$formatted_events[] = [
				'timestamp'      => $event->get_timestamp(),
				'action'         => $event->get_action(),
				'instance'       => $event->get_instance(),
				'scheduled_for'  => date_i18n( TIME_FORMAT, $event->get_timestamp() ),
				'internal_event' => $event->is_internal() ? __( 'true', 'automattic-cron-control' ) : '',
				'schedule_name'  => is_null( $event->get_schedule() ) ? __( 'n/a', 'automattic-cron-control' ) : $event->get_schedule(),
				'event_args'     => maybe_serialize( $event->get_args ),
			];
		}

		return $formatted_events;
	}
}

\WP_CLI::add_command( 'cron-control rest-api', 'Automattic\WP\Cron_Control\CLI\REST_API' );
