<?php
/**
 * Telemetry: Pendo JavaScript Library class
 *
 * @package Automattic\VIP\Telemetry\Pendo
 */

declare(strict_types=1);

namespace Automattic\VIP\Telemetry\Pendo;

use Automattic\VIP\Telemetry\Pendo;
use WP_Error;
use function Automattic\VIP\Logstash\log2logstash;

/**
 * This class enqueues the Pendo client library when it is supported. In
 * contrast to "Track events" which can be sent via server-to-server requests
 * (see `Pendo_Track_Client`), this client library allows use of the "Pages" and
 * "Features" events of Pendo, which must be sent client-side.
 *
 * https://support.pendo.io/hc/en-us/articles/23335876011803-Events-overview
 *
 * @see Pendo::enable_javascript_library()
 */
class Pendo_JavaScript_Library {
	/**
	 * The Pendo API key.
	 *
	 * @var string
	 */
	private string|null $api_key;

	/**
	 * The global variable name for our Pendo instance.
	 *
	 * @var string
	 */
	private string $browser_global = 'VIP_PENDO_MU_PLUGINS';

	/**
	 * Singleton instance.
	 *
	 * @var Pendo_JavaScript_Library
	 */
	private static $instance;

	private static array $allowed_screens = [
		'admin.php',
		'edit.php',
		'edit-tags.php',
		'index.php',
		'media-new.php',
		'options.php',
		'options-discussion.php',
		'options-general.php',
		'options-media.php',
		'options-permalink.php',
		'options-privacy.php',
		'options-reading.php',
		'options-writing.php',
		'plugins.php',
		'post-new.php',
		'post.php',
		'site-editor.php',
		'themes.php',
		'upload.php',
		'parse-ly_page_parsely-settings',
		'toplevel_page_parsely-dashboard-page',
	];

	private static array $allowed_admin_screens = [
		'vip-block-governance',
	];

	/**
	 * Constructor.
	 */
	private function __construct( string $api_key ) {
		$this->api_key = $api_key;
	}

	/**
	 * Standard singleton class except the caller does not need access to the
	 * instance, so we name it `init`. Do not call this method directly; instead
	 * use `Pendo::enable_javascript_library()`.
	 *
	 * @param string $api_key The Pendo snippet API key.
	 * @return self|WP_Error Instance on success or WP_Error if any error occured.
	 */
	public static function init( string|null $api_key = null ): self|WP_Error {
		// Already initialized? Return instance.
		if ( self::$instance instanceof Pendo_JavaScript_Library ) {
			return self::$instance;
		}

		if ( empty( $api_key ) ) {
			$message = __( 'Pendo snippet API key is not defined', 'vip-telemetry' );
			return new WP_Error( 'pendo_snippet_api_key_not_defined', $message );
		}

		// We currently do not allow customization of the initialization data to
		// minimize the risk of collisions with standard property names.

		self::$instance = new Pendo_JavaScript_Library( $api_key );

		// Use a high priority value to enqueue this late.
		add_action( 'admin_enqueue_scripts', [ self::$instance, 'enqueue_scripts' ], 99, 1 );

		return self::$instance;
	}

	/**
	 * Enqueue the Pendo client library script on supported pages.
	 *
	 * @param string $screen The current admin screen slug.
	 */
	public function enqueue_scripts( string $screen ): void {
		if ( true !== self::should_enqueue_script( $screen ) ) {
			return;
		}

		$initialization_data = $this->get_initialization_data();
		if ( is_wp_error( $initialization_data ) ) {
			return;
		}

		$script_handle = 'vip-pendo-agent-script';
		$script_url    = plugins_url( '/js/pendo-agent.js', __FILE__ );
		$variable_name = sprintf( '%s_%s', $this->browser_global, 'INIT_DATA' );

		wp_enqueue_script( $script_handle, $script_url, [], '0.4', true );
		wp_localize_script( $script_handle, $variable_name, $initialization_data );
	}

	/**
	 * Get the initialization data for the Pendo client library. We currently do
	 * not allow customization of the initialization data to minimize the risk of
	 * collisions with standard property names.
	 */
	private function get_initialization_data(): array|WP_Error {
		$event_properties = get_base_properties_of_pendo_track_event();
		$user_properties  = get_base_properties_of_pendo_user();

		// A null response indicates that the user is not logged-in or we otherwise
		// have no meaningful way to attribute the event.
		if ( empty( $user_properties ) ) {
			$message = __( 'User is not logged-in', 'vip-telemetry' );
			log2logstash( [
				'severity' => 'error',
				'feature'  => 'telemetry',
				'message'  => $message,
			] );
			return new WP_Error( 'pendo_snippet_user_not_logged_in', $message );
		}

		return [
			'apiKey'    => $this->api_key,
			'account'   => [
				// These fields are defined in the VIP Dashboard, but are currently not
				// available to WordPress environments.
				// 'name' => '',
				'id'         => $user_properties['account_id'],
				'vip_org_id' => $user_properties['org_id'],
				'wp_version' => $event_properties['wp_version'],
			],
			'env'       => 'io',
			'globalKey' => $this->browser_global,
			// Plugins are imported from `@pendo/agent`, so if plugins are desired,
			// they need to be configured in the agent script.
			'plugins'   => [],
			'visitor'   => [
				'id'             => $user_properties['visitor_id'],
				'country_code'   => $user_properties['country_code'],
				'full_name'      => $user_properties['visitor_name'],
				// This suffix ensures we don't override role attributes from other
				// contexts, such as the VIP Dashboard.
				'role_wordpress' => $user_properties['role_wordpress'],
			],
		];
	}

	/**
	 * Determine if the Pendo client library should be enqueued for the current
	 * request.
	 *
	 * @param string $screen The current admin screen slug.
	 */
	final public static function should_enqueue_script( string $screen ): bool {
		if ( true !== Pendo::is_pendo_enabled_for_environment() ) {
			return false;
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			return false;
		}


		/**
		 * Filters the list of admin screens where the Pendo JavaScript library should be loaded.
		 *
		 * @param array $allowed_screens Array of admin $screen slugs where Pendo is enabled.
		 */
		$allowed_screens = apply_filters( 'vip_pendo_allowed_screens', self::$allowed_screens );

		if ( ! in_array( $screen, $allowed_screens, true ) ) {
			return false;
		}

		/**
		 * Filters the list of admin.php pages where the Pendo JavaScript library should be loaded.
		 *
		 * These screens are accessed via admin.php?page=[screen_name] and are only used
		 * when the current screen is admin.php.
		 *
		 * @param array $allowed_admin_screens Array of admin.php page slugs where Pendo is enabled.
		 */
		$allowed_admin_screens = apply_filters( 'vip_pendo_allowed_admin_screens', self::$allowed_admin_screens );

		// admin.php is further restricted to specific query vars.
		if ( 'admin.php' === $screen && ! in_array( get_query_var( 'page' ), $allowed_admin_screens, true ) ) {
			return false;
		}

		return true;
	}
}
