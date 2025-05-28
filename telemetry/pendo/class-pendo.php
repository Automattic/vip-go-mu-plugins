<?php
/**
 * Telemetry: Pendo class
 *
 * @package Automattic\VIP\Telemetry
 */

declare(strict_types=1);

namespace Automattic\VIP\Telemetry;

use WP_Error;
use Automattic\VIP\Telemetry\Pendo\Pendo_JavaScript_Library;
use Automattic\VIP\Telemetry\Pendo\Pendo_Track_Client;
use Automattic\VIP\Telemetry\Pendo\Pendo_Track_Event;

use function Automattic\VIP\Telemetry\Pendo\get_base_properties_of_pendo_track_event;

/**
 * This class comprises the mechanics of including Pendo tracking.
 */
class Pendo extends Telemetry_System {
	/**
	 * The event context that is provided to Track events.
	 *
	 * @var array<string, string|number>
	 */
	private array $event_context;

	/**
	 * The prefix for all event names.
	 *
	 * @var string
	 */
	protected string $event_prefix;

	/**
	 * The global event properties to be included with every event.
	 *
	 * @var array<string, string|number>
	 */
	private array $global_event_properties;

	/**
	 * Whether Pendo is enabled in the current environment.
	 *
	 * @var bool
	 */
	private bool $is_enabled = false;

	/**
	 * Event queue.
	 *
	 * @var Telemetry_Event_Queue
	 */
	private Telemetry_Event_Queue $queue;

	/**
	 * Pendo constructor.
	 *
	 * @param string $event_prefix The prefix for all event names. Defaults to 'vip_wordpress_'.
	 * @param array<string, string|number> $global_event_properties The global event properties to be included with every event.
	 * @param Telemetry_Event_Queue|null $queue The event queue to use. Falls back to the default queue when none provided.
	 */
	public function __construct(
		string $event_prefix = 'vip_wordpress_',
		array $global_event_properties = [],
		?Telemetry_Event_Queue $queue = null,
	) {
		$this->event_context           = $this->get_event_context();
		$this->event_prefix            = $event_prefix;
		$this->global_event_properties = $this->get_global_event_properties( $global_event_properties );
		$this->is_enabled              = self::is_pendo_enabled_for_environment();
		$this->queue                   = $queue ?? $this->create_queue();
	}

	private function create_queue(): Telemetry_Event_Queue {
		$track_integration_key = null;
		if ( defined( 'VIP_PENDO_TRACK_INTEGRATION_KEY' ) ) {
			$track_integration_key = constant( 'VIP_PENDO_TRACK_INTEGRATION_KEY' );
		}

		$client = new Pendo_Track_Client( $track_integration_key );

		return new Telemetry_Event_Queue( $client );
	}

	/**
	 * Get the event context passed on every Track event as a sibling to the event
	 * properties. We do not ship IP address or page title.
	 *
	 * https://engageapi.pendo.io/#e45be48e-e01f-4f0a-acaa-73ef6851c4ac
	 *
	 * @return array<string, string|number>
	 */
	private function get_event_context(): array {
		return [
			// Provide the URL path without the origin.
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passing value to Pendo via request body.
			'url'       => wp_parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH ) || '/',

			// phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__HTTP_USER_AGENT__, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passing value to Pendo via request body.
			'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
		];
	}

	/**
	 * Get the global event properties to be included with every event. In addition
	 * to the global properties provided to the constructor, we include additional
	 * properties related to the current environment.
	 *
	 * @param array<string, string|number> $provided_global_event_properties The global event properties provided to the constructor.
	 * @return array<string, string|number>
	 */
	private function get_global_event_properties( array $provided_global_event_properties ): array {
		return array_merge(
			$provided_global_event_properties,
			get_base_properties_of_pendo_track_event(),
		);
	}

	/**
	 * If allowed, inserts the Pendo script into the page to enable Page and
	 * Feature tracking.
	 *
	 * @see Pendo_JavaScript_Library::should_enqueue_script()
	 */
	public static function enable_javascript_library(): void {
		if ( ! defined( 'VIP_PENDO_SNIPPET_API_KEY' ) ) {
			return;
		}

		Pendo_JavaScript_Library::init( constant( 'VIP_PENDO_SNIPPET_API_KEY' ) );
	}

	/**
	 * Checks if Pendo telemetry is allowed in the current environment.
	 *
	 * @return bool
	 */
	final public static function is_pendo_enabled_for_environment(): bool {
		// Do not run if disabled via constant.
		if ( defined( 'VIP_DISABLE_PENDO_TELEMETRY' ) && true === constant( 'VIP_DISABLE_PENDO_TELEMETRY' ) ) {
			return false;
		}

		// Limit to VIP environments.
		if ( ! defined( 'WPCOM_IS_VIP_ENV' ) || true !== constant( 'WPCOM_IS_VIP_ENV' ) ) {
			return false;
		}

		// Limit to production environments.
		if ( ! defined( 'VIP_GO_APP_ENVIRONMENT' ) || 'production' !== constant( 'VIP_GO_APP_ENVIRONMENT' ) ) {
			return false;
		}

		// Do not run on sandboxed environments.
		if ( defined( 'WPCOM_SANDBOXED' ) && true === constant( 'WPCOM_SANDBOXED' ) ) {
			return false;
		}

		// Do not run in FedRAMP environments.
		if ( defined( 'VIP_IS_FEDRAMP' ) && true === constant( 'VIP_IS_FEDRAMP' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Records a "Track event" to Pendo.
	 *
	 * This name is confusing and has nothing to do with Automattic Tracks! It is
	 * the name chosen by Pendo for their user interaction events. They are
	 * distinct from what they interchangely call Page events, Feature events, or
	 * "low-code events".
	 *
	 * If the event doesn't pass validation, it gets silently discarded.
	 *
	 * @param string                       $event_name       The event name. Must be snake_case.
	 * @param array<string, string|number> $event_properties Any additional properties to include with the event.
	 *                                                       Key names must be lowercase and snake_case.
	 * @return bool|WP_Error True if recording the event succeeded.
	 *                       False if telemetry is disabled.
	 *                       WP_Error if recording the event failed.
	 */
	public function record_event(
		string $event_name,
		array $event_properties = []
	): bool|WP_Error {
		if ( ! $this->is_enabled ) {
			return false;
		}

		$event_properties = array_merge( $this->global_event_properties, $event_properties );
		$event            = new Pendo_Track_Event( $this->event_prefix, $event_name, $this->event_context, $event_properties );

		return $this->queue->record_event_asynchronously( $event );
	}
}
