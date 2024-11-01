<?php
/**
 * Telemetry: Tracks class
 *
 * @package Automattic\VIP\Telemetry
 */

declare(strict_types=1);

namespace Automattic\VIP\Telemetry;

use Automattic\VIP\Telemetry\Tracks\Tracks_Client;
use Automattic\VIP\Telemetry\Tracks\Tracks_Event;
use WP_Error;

use function Automattic\VIP\Telemetry\Tracks\get_base_properties_of_track_event;
use function Automattic\VIP\Telemetry\Tracks\get_base_properties_of_track_user;

/**
 * This class comprises the mechanics of sending events to the Automattic
 * Tracks system.
 */
class Tracks extends Telemetry_System {

	/**
	 * The prefix for all event names.
	 *
	 * @var string
	 */
	protected string $event_prefix;

	/**
	 * Event queue.
	 *
	 * @var Telemetry_Event_Queue
	 */
	private Telemetry_Event_Queue $queue;

	/**
	 * @param array<string, mixed> The global event properties to be included with every event.
	 */
	private array $global_event_properties = [];

	/**
	 * Avoids adding the `Tracks` properties multiple times.
	 */
	private static $has_track_props_passed_to_js = false;

	/**
	 * Tracks constructor.
	 * 
	 * @param string $event_prefix The prefix for all event names. Defaults to 'vip_'.
	 * @param array<string, mixed> $global_event_properties The global event properties to be included with every event.
	 * @param Telemetry_Event_Queue|null $queue The event queue to use. Falls back to the default queue when none provided.
	 * @param Tracks_Client|null $client The client instance to use. Falls back to the default client when none provided.
	 */
	public function __construct( string $event_prefix = 'vip_', array $global_event_properties = [], Telemetry_Event_Queue $queue = null, Tracks_Client $client = null ) {
		$this->event_prefix            = $event_prefix;
		$this->global_event_properties = $global_event_properties;
		$client                      ??= new Tracks_Client();
		$this->queue                   = $queue ?? new Telemetry_Event_Queue( $client );

		$this->pass_tracks_props_to_js();
	}

	/**
	 * Passes the base properties for a track event to JavaScript.
	 */
	public function pass_tracks_props_to_js(): void {
		if ( self::$has_track_props_passed_to_js ) {
			return;
		}

		add_action( 'admin_head', function () {
			?>
			<script type="text/javascript"> var VIP_TRACKS_BASE_PROPS = <?php echo wp_json_encode( $this->get_tracks_props() ); ?>; </script>
			<?php
	
			self::$has_track_props_passed_to_js = true;
		} );
	}

	/**
	 * Returns the base properties for a track event.
	 *
	 * @return array<string, mixed> The base properties.
	 */
	public function get_tracks_props(): array {
		return array_merge( get_base_properties_of_track_event(), get_base_properties_of_track_user() );
	}

	/**
	 * Records an event to Tracks by using the Tracks API.
	 *
	 * If the event doesn't pass validation, it gets silently discarded.
	 *
	 * @param string                            $event_name The event name. Must be snake_case.
	 * @param array<string, mixed>|array<empty> $event_properties Any additional properties to include with the event.
	 *                                                            Key names must be lowercase and snake_case.
	 * @return bool|WP_Error True if recording the event succeeded.
	 *                       False if telemetry is disabled.
	 *                       WP_Error if recording the event failed.
	 */
	public function record_event(
		string $event_name,
		array $event_properties = []
	): bool|WP_Error {
		if ( [] !== $this->global_event_properties ) {
			$event_properties = array_merge( $this->global_event_properties, $event_properties );
		}

		$event = new Tracks_Event( $this->event_prefix, $event_name, $event_properties );

		return $this->queue->record_event_asynchronously( $event );
	}
}
