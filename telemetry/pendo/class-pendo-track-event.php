<?php
/**
 * Telemetry: Pendo Track Event class
 *
 * @package Automattic\VIP\Telemetry\Pendo
 */

declare(strict_types=1);

namespace Automattic\VIP\Telemetry\Pendo;

use Automattic\VIP\Telemetry\Telemetry_Event;
use WP_Error;
use function Automattic\VIP\Logstash\log2logstash;

/**
 * Class that creates and validates Pendo "Track events."
 */
class Pendo_Track_Event extends Telemetry_Event {
	/**
	 * Snake case regex. Event name and properties should be in snake_case.
	 * Example: compressed_size is correct, but compressedSize is not. Names with
	 * leading underscores are reserved.
	 */
	protected const SNAKE_CASE_REGEX = '/^[a-z_][a-z0-9_]*$/';

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
	private array $event_context;

	/**
	 * @var array Any properties included in the event.
	 */
	private array $event_properties;

	/**
	 * @var float The event's creation timestamp in milliseconds.
	 */
	private float $event_timestamp;

	/**
	 * Constructor.
	 *
	 * @param string                            $prefix The event's prefix.
	 * @param string                            $event_name The event's name.
	 * @param array<string, mixed>|array<empty> $event_context The event's context.
	 * @param array<string, mixed>|array<empty> $event_properties Any properties included in the event.
	 */
	public function __construct( string $prefix, string $event_name, array $event_context = [], array $event_properties = [] ) {
		$this->prefix           = $prefix;
		$this->event_name       = $event_name;
		$this->event_context    = $event_context;
		$this->event_properties = $event_properties;
		$this->event_timestamp  = round( microtime( true ) * 1000 );
	}

	/**
	 * Returns the event's data.
	 *
	 * @return Pendo_Track_Event_DTO|WP_Error Event DTO if the event was created successfully, WP_Error otherwise.
	 */
	protected function generate(): Pendo_Track_Event_DTO|WP_Error {
		$event_dto       = new Pendo_Track_Event_DTO();
		$user_properties = get_base_properties_of_pendo_user();

		if ( null === $user_properties ) {
			return $this->log_and_return_error( $event_dto, __( 'User properties are missing', 'vip-telemetry' ), 'empty_user_information' );
		}

		// Set event name. If the event name doesn't have the prefix, add it.
		$event_name = preg_replace(
			'/^(?:' . $this->prefix . ')?(.+)/',
			$this->prefix . '\1',
			$this->event_name
		) ?? '';

		$event_dto->accountId = $user_properties['account_id']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$event_dto->event     = $event_name;
		$event_dto->timestamp = $this->event_timestamp;
		$event_dto->visitorId = $user_properties['visitor_id']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		$event_dto->properties = (object) $this->flatten_properties_to_strings( $this->event_properties );
		$event_dto->context    = (object) $this->flatten_properties_to_strings( $this->event_context );

		$validation_error = $this->validate( $event_dto );

		return $validation_error ?? $event_dto;
	}

	/**
	 * Flattens an associative array of properties, JSON-encoding the values if
	 * they are not strings.
	 *
	 * @param array<string, mixed> $properties The properties to flatten.
	 * @return array<string, string> The flattened properties.
	 */
	private function flatten_properties_to_strings( array $properties ): array {
		$flattened = [];

		foreach ( $properties as $key => $value ) {
			$flattened[ $key ] = is_string( $value ) || is_bool( $value ) || is_int( $value ) ? $value : wp_json_encode( $value );
		}

		return $flattened;
	}

	private function is_snake_case( string $value ): bool {
		return (bool) preg_match( static::SNAKE_CASE_REGEX, $value );
	}

	private function log_and_return_error( Pendo_Track_Event_DTO $event_dto, string $msg, string $code = 'invalid_event' ): WP_Error {
		log2logstash( [
			'severity' => 'error',
			'feature'  => 'telemetry',
			'message'  => $msg,
			'extra'    => [
				'event' => (array) $event_dto,
			],
		] );

		return new WP_Error( $code, $msg );
	}

	/**
	 * Validates the event DTO.
	 *
	 * @param Pendo_Track_Event_DTO $event Event object to validate.
	 * @return ?WP_Error null if validation passed, error otherwise.
	 */
	private function validate( Pendo_Track_Event_DTO $event_dto ): ?WP_Error {
		if ( ! $event_dto->event ) {
			return $this->log_and_return_error( $event_dto, __( 'The event name must be a non-empty value', 'vip-telemetry' ), 'invalid_event_name' );
		}

		if ( ! $this->is_snake_case( $event_dto->event ) ) {
			return $this->log_and_return_error( $event_dto, __( 'The event name must be in snake_case', 'vip-telemetry' ), 'invalid_event_name' );
		}

		// Validate context names against allow list.
		$context_keys         = array_keys( get_object_vars( $event_dto->context ) );
		$allowed_context_keys = [ 'title', 'url', 'userAgent' ];
		if ( ! empty( array_diff( $context_keys, $allowed_context_keys ) ) ) {
			return $this->log_and_return_error( $event_dto, __( 'Invalid context name specified', 'vip-telemetry' ), 'invalid_context_name' );
		}

		// Validate property names format.
		foreach ( array_keys( get_object_vars( $event_dto->properties ) ) as $key ) {
			if ( ! $this->is_snake_case( $key ) ) {
				return $this->log_and_return_error( $event_dto, __( 'A valid property name must be specified', 'vip-telemetry' ), 'invalid_property_name' );
			}
		}

		return null;
	}
}
