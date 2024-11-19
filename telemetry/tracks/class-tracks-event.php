<?php
/**
 * Telemetry: Tracks Event class
 *
 * @package Automattic\VIP\Telemetry\Tracks
 */

declare(strict_types=1);

namespace Automattic\VIP\Telemetry\Tracks;

use Automattic\VIP\Telemetry\Telemetry_Event;
use WP_Error;
use function Automattic\VIP\Logstash\log2logstash;

/**
 * Class that creates and validates Tracks events.
 *
 * @see \Automattic\VIP\Parsely\Telemetry\Tracks_Event
 *
 * @since 3.12.0
 */
class Tracks_Event extends Telemetry_Event {
	/**
	 * Event name regex. Spaces, mixed case, and special characters are not allowed.
	 */
	protected const EVENT_NAME_REGEX = '/^[a-z_][a-z0-9_]*$/';

	/**
	 * Property name regex. Event props should be in snake_case. Example: compressed_size is correct, but compressedSize is not.
	 * Property names with leading underscores are reserved for special properties.
	 */
	protected const PROPERTY_NAME_REGEX = '/^[a-z_][a-z0-9_]*$/';

	/**
	 * @var string The event's prefix.
	 */
	private string $prefix;

	/**
	 * @var string The event's name.
	 */
	private string $event_name;

	/**
	 * @var array Any properties included in the event.
	 */
	private array $event_properties;

	/**
	 * @var float The event's creation timestamp.
	 */
	private float $event_timestamp;

	/**
	 * Variable containing the event's data or an error if one was encountered
	 * during the event's creation.
	 *
	 * @var Tracks_Event_DTO|WP_Error
	 */
	protected Tracks_Event_DTO|WP_Error $data;

	/**
	 * Constructor.
	 *
	 * @param string                            $prefix The event's prefix.
	 * @param string                            $event_name The event's name.
	 * @param array<string, mixed>|array<empty> $event_properties Any properties included in the event.
	 */
	public function __construct( string $prefix, string $event_name, array $event_properties = [] ) {
		$this->prefix           = $prefix;
		$this->event_name       = $event_name;
		$this->event_properties = $event_properties;
		$this->event_timestamp  = microtime( true );
	}

	/**
	 * Returns the event's data.
	 *
	 * @return Tracks_Event_DTO|WP_Error Event object if the event was created successfully, WP_Error otherwise.
	 */
	public function get_data(): Tracks_Event_DTO|WP_Error {
		if ( ! isset( $this->data ) ) {
			$event_data        = $this->process_properties( $this->prefix, $this->event_name, $this->event_properties );
			$validation_result = $this->get_event_validation_result( $event_data );

			$this->data = $validation_result ?? $event_data;
		}

		return $this->data;
	}

	/**
	 * Returns the event's data for JSON representation.
	 */
	public function jsonSerialize(): mixed {
		$data = $this->get_data();

		if ( is_wp_error( $data ) ) {
			return (object) [];
		}

		return $data;
	}

	/**
	 * Returns whether the event can be recorded.
	 *
	 * @return bool|WP_Error True if the event is recordable.
	 */
	public function is_recordable(): bool|WP_Error {
		$data = $this->get_data();

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		return true;
	}

	/**
	 * Processes the event's properties to get them ready for validation.
	 *
	 * @param string $event_prefix The event's prefix.
	 * @param string $event_name The event's name.
	 * @param array<string, mixed>|array<empty> $event_properties Any event properties to be processed.
	 * @return Tracks_Event_DTO The resulting event object with processed properties.
	 */
	protected function process_properties(
		string $event_prefix,
		string $event_name,
		array $event_properties
	): Tracks_Event_DTO {
		$event = static::encode_properties( $event_properties );
		$event = static::set_user_properties( $event );

		// Set event name. If the event name doesn't have the prefix, add it.
		$event->_en = preg_replace(
			'/^(?:' . $event_prefix . ')?(.+)/',
			$event_prefix . '\1',
			$event_name
		) ?? '';

		// Set event timestamp.
		if ( ! isset( $event->_ts ) ) {
			$event->_ts = static::milliseconds_since_epoch( $this->event_timestamp );
		}

		// Remove non-routable IPs to prevent record from being discarded.
		if ( isset( $event->_via_ip ) &&
			1 === preg_match( '/^192\.168|^10\./', $event->_via_ip ) ) {
			unset( $event->_via_ip );
		}

		$base_props = get_base_properties_of_track_event();

		if ( isset( $base_props['vipgo_env'] ) ) {
			$event->vipgo_env = $base_props['vipgo_env'];
		}

		if ( isset( $base_props['vipgo_org'] ) ) {
			$event->vipgo_org = $base_props['vipgo_org'];
		}

		if ( isset( $base_props['is_vip_user'] ) ) {
			$event->is_vip_user = $base_props['is_vip_user'];
		}

		return $event;
	}

	/**
	 * Sets the Tracks User ID and User ID Type depending on the current
	 * environment.
	 *
	 * @param Tracks_Event_DTO $event The event to annotate with identity information.
	 * @return Tracks_Event_DTO The new event object including identity information.
	 */
	protected static function set_user_properties( Tracks_Event_DTO $event ): Tracks_Event_DTO {
		$base_props = get_base_properties_of_track_user();

		if ( isset( $base_props['_ui'] ) ) {
			$event->_ui = $base_props['_ui'];
		}

		if ( isset( $base_props['_ut'] ) ) {
			$event->_ut = $base_props['_ut'];
		}

		return $event;
	}

	/**
	 * Validates the event object.
	 *
	 * @param Tracks_Event_DTO $event Event object to validate.
	 * @return ?WP_Error null if validation passed, error otherwise.
	 */
	protected function get_event_validation_result( Tracks_Event_DTO $event ): ?WP_Error {
		// Check that required fields are defined.
		if ( ! $event->_en ) {
			$msg = __( 'The _en property must be specified to non-empty value', 'vip-telemetry' );
			log2logstash( [
				'severity' => 'error',
				'feature'  => 'telemetry',
				'message'  => $msg,
				'extra'    => [
					'event' => (array) $event,
				],
			] );
			return new WP_Error(
				'invalid_event',
				$msg
			);
		}

		// Validate Event Name (_en).
		if ( ! static::event_name_is_valid( $event->_en ) ) {
			$msg = __( 'A valid event name must be specified', 'vip-telemetry' );
			log2logstash( [
				'severity' => 'error',
				'feature'  => 'telemetry',
				'message'  => $msg,
				'extra'    => [
					'event' => (array) $event,
				],
			] );
			return new WP_Error(
				'invalid_event_name',
				$msg
			);
		}


		// Validate property names format.
		foreach ( get_object_vars( $event ) as $key => $_ ) {
			if ( ! static::property_name_is_valid( $key ) ) {
				$msg = __( 'A valid property name must be specified', 'vip-telemetry' );
				log2logstash( [
					'severity' => 'error',
					'feature'  => 'telemetry',
					'message'  => $msg,
					'extra'    => [
						'event' => (array) $event,
					],
				] );
				return new WP_Error(
					'invalid_property_name',
					$msg
				);
			}
		}

		// Validate User ID (_ui) and User ID Type (_ut).
		if ( ! isset( $event->_ui ) && ! isset( $event->_ut ) ) {
			$msg = __( 'Could not determine user identity and type', 'vip-telemetry' );
			log2logstash( [
				'severity' => 'error',
				'feature'  => 'telemetry',
				'message'  => $msg,
				'extra'    => [
					'event' => (array) $event,
				],
			] );
			return new WP_Error(
				'empty_user_information',
				$msg
			);
		}

		return null;
	}

	/**
	 * Checks if the passed event name is valid.
	 *
	 * @param string $event_name The event's name.
	 * @return bool Whether the event name is valid.
	 */
	protected static function event_name_is_valid( string $event_name ): bool {
		return 1 === preg_match( static::EVENT_NAME_REGEX, $event_name );
	}

	/**
	 * Checks if the passed property name is valid.
	 *
	 * @param string $property_name The property's name.
	 * @return bool Whether the property name is valid.
	 */
	protected static function property_name_is_valid( string $property_name ): bool {
		return 1 === preg_match( static::PROPERTY_NAME_REGEX, $property_name );
	}

	/**
	 * Sanitizes the passed properties array, JSON-encoding non-string values.
	 *
	 * @param array<string, mixed>|array<empty> $event_properties The array to be sanitized.
	 * @return Tracks_Event_DTO The sanitized object.
	 */
	protected static function encode_properties( array $event_properties ): Tracks_Event_DTO {
		$result = new Tracks_Event_DTO();

		foreach ( $event_properties as $key => $value ) {
			if ( is_string( $value ) ) {
				$result->$key = $value;
				continue;
			}

			$result->$key = wp_json_encode( $value );
		}

		return $result;
	}

	/**
	 * Builds a JS compatible timestamp for the event (integer number of milliseconds since the Unix Epoch).
	 *
	 * @return string
	 */
	protected static function milliseconds_since_epoch( float $microtime ): string {
		$timestamp = round( $microtime * 1000 );

		return number_format( $timestamp, 0, '', '' );
	}
}
