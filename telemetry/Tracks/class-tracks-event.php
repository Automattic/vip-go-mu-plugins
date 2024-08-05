<?php
/**
 * Telemetry: Tracks Event class
 *
 * @package Automattic\VIP\Telemetry
 */

declare(strict_types=1);

namespace Automattic\VIP\Telemetry;

use stdClass;
use WP_Error;

/**
 * Class that creates and validates Tracks events.
 *
 * @since 3.12.0
 */
class Tracks_Event {
	/**
	 * Event name prefix.
	 */
	protected const EVENT_NAME_PREFIX = 'viptelemetry_';

	/**
	 * Event name regex.
	 */
	protected const EVENT_NAME_REGEX = '/^(([a-z0-9]+)_){1}([a-z0-9_]+)$/';

	/**
	 * Property name regex.
	 */
	protected const PROPERTY_NAME_REGEX = '/^[a-z_][a-z0-9_]*$/';

	/**
	 * Variable containing the event's data or an error if one was encountered
	 * during the event's creation.
	 *
	 * @var stdClass|WP_Error
	 */
	protected $data = null;

	/**
	 * Constructor.
	 *
	 * @param string                            $event_name The event's name.
	 * @param array<string, mixed>|array<empty> $event_properties Any properties included in the event.
	 */
	public function __construct( string $event_name, array $event_properties ) {
		$event_data        = self::process_properties( $event_name, $event_properties );
		$validation_result = self::get_event_validation_result( $event_data );

		$this->data = $validation_result ?? $event_data;
	}

	/**
	 * Returns the event's data.
	 *
	 * @return stdClass|WP_Error Event object if the event was created successfully, WP_Error otherwise.
	 */
	public function get_data() {
		return $this->data;
	}

	/**
	 * Returns whether the event can be recorded.
	 *
	 * @return bool|WP_Error True if the event is recordable.
	 *                       False if the event is not recordable.
	 *                       WP_Error if the event is generating an error.
	 */
	public function is_recordable() {
		if ( ! Telemetry_System::is_wpadmin_telemetry_allowed() ) {
			return false;
		}

		// Don't record events during unit tests and CI runs.
		if ( 'wptests_capabilities' === wp_get_current_user()->cap_key ) {
			return false;
		}

		if ( is_wp_error( $this->data ) ) {
			return $this->data;
		}

		return true;
	}

	/**
	 * Processes the event's properties to get them ready for validation.
	 *
	 * @param string $event_name The event's name.
	 * @param array<string, mixed>|array<empty> $event_properties Any event properties to be processed.
	 * @return stdClass The resulting event object with processed properties.
	 */
	protected static function process_properties(
		string $event_name,
		array $event_properties
	): stdClass {
		$event = (object) self::sanitize_properties_array( $event_properties );
		$event = self::set_user_properties( $event );

		// Set event name.
		$event->_en = preg_replace(
			'/^(?:' . self::EVENT_NAME_PREFIX . ')?(.*)/',
			self::EVENT_NAME_PREFIX . '\1',
			$event_name
		) ?? '';

		// Set event timestamp.
		if ( ! isset( $event->_ts ) ) {
			$event->_ts = self::get_timestamp();
		}

		// Remove non-routable IPs to prevent record from being discarded.
		if ( property_exists( $event, '_via_ip' ) &&
			1 === preg_match( '/^192\.168|^10\./', $event->_via_ip ) ) {
			unset( $event->_via_ip );
		}

		// Set VIP environment if it exists.
		if ( defined( 'VIP_GO_APP_ENVIRONMENT' ) ) {
			$app_environment = constant( 'VIP_GO_APP_ENVIRONMENT' );
			if ( is_string( $app_environment ) && '' !== $app_environment ) {
				$event->vipgo_env = $app_environment;
			}
		}

		return $event;
	}

	/**
	 * Sets the Tracks User ID and User ID Type depending on the current
	 * environment.
	 *
	 * @param stdClass $event The event to annotate with identity information.
	 * @return stdClass The new event object including identity information.
	 */
	protected static function set_user_properties( stdClass $event ): stdClass {
		$wp_user_id = get_current_user_id();

		// Only track logged-in users.
		if ( 0 === $wp_user_id ) {
			return $event;
		}

		// Users in the VIP environment.
		if ( defined( 'VIP_GO_APP_ID' ) ) {
			$app_id = constant( 'VIP_GO_APP_ID' );
			if ( is_integer( $app_id ) && 0 < $app_id ) {
				$event->_ui = $app_id . '_' . $wp_user_id;
				$event->_ut = 'vip_go_app_wp_user';

				return $event;
			}
		}

		// All other environments.
		$wp_base_url = get_option( 'home' );
		if ( ! is_string( $wp_base_url ) || '' === $wp_base_url ) {
			$wp_base_url = get_option( 'siteurl' );
		}

		/**
		 * The base URL of the site.
		 *
		 * @var string $wp_base_url
		 */
		$event->_ui = wp_hash( sprintf( '%s|%s', $wp_base_url, $wp_user_id ) );
		$event->_ut = 'wpparsely:user_id';

		return $event;
	}

	/**
	 * Validates the event object.
	 *
	 * @param stdClass $event Event object to validate.
	 * @return ?WP_Error null if validation passed, error otherwise.
	 */
	protected static function get_event_validation_result( stdClass $event ): ?WP_Error {
		// Check that required fields are defined.
		if ( ! $event->_en ) {
			return new WP_Error(
				'invalid_event',
				__( 'The _en property must be specified', 'vip-telemetry' ),
				array( 'status' => 400 )
			);
		}

		// Validate Event Name (_en).
		if ( ! self::event_name_is_valid( $event->_en ) ) {
			return new WP_Error(
				'invalid_event_name',
				__( 'A valid event name must be specified', 'vip-telemetry' ),
				array( 'status' => 400 )
			);
		}

		// Validate property names format.
		foreach ( array_keys( (array) $event ) as $key ) {
			if ( ! self::property_name_is_valid( $key ) && '_en' !== $key ) {
				return new WP_Error(
					'invalid_property_name',
					__( 'A valid property name must be specified', 'vip-telemetry' ),
					array( 'status' => 400 )
				);
			}
		}

		// Validate User ID (_ui) and User ID Type (_ut).
		if ( ! ( property_exists( $event, '_ui' ) && property_exists( $event, '_ut' ) ) ) {
			return new WP_Error(
				'empty_user_information',
				__( 'Could not determine user identity and type', 'vip-telemetry' ),
				array( 'status' => 400 )
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
		return false !== preg_match( self::EVENT_NAME_REGEX, $event_name );
	}

	/**
	 * Checks if the passed property name is valid.
	 *
	 * @param string $property_name The property's name.
	 * @return bool Whether the property name is valid.
	 */
	protected static function property_name_is_valid( string $property_name ): bool {
		return false !== preg_match( self::PROPERTY_NAME_REGEX, $property_name );
	}

	/**
	 * Sanitizes the passed properties array.
	 *
	 * @param array<string, mixed>|array<empty> $event_properties The array to be sanitized.
	 * @return array<string, mixed>|array<empty> The sanitized array.
	 */
	protected static function sanitize_properties_array( array $event_properties ): array {
		$result = array();

		foreach ( $event_properties as $key => $value ) {
			if ( is_string( $value ) ) {
				$result[ $key ] = $value;
				continue;
			}

			$result[ $key ] = wp_json_encode( $value );
		}

		return $result;
	}

	/**
	 * Builds a JS compatible timestamp for the event.
	 *
	 * @return string
	 */
	protected static function get_timestamp(): string {
		$timestamp = round( microtime( true ) * 1000 );

		return number_format( $timestamp, 0, '', '' );
	}
}
