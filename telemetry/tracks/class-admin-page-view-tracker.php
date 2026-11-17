<?php
/**
 * Telemetry: Admin Page View Tracker class
 *
 * @package Automattic\VIP\Telemetry\Tracks
 */

declare(strict_types=1);

namespace Automattic\VIP\Telemetry\Tracks;

use Automattic\VIP\Telemetry\Tracks;
use function Automattic\VIP\Integrations\wpvip_get_enabled_integrations;

/**
 * Records a Tracks event when an editor views a supported wp-admin screen on a
 * production site that uses one of the integrations we track.
 */
final class Admin_Page_View_Tracker {
	/**
	 * Admin screen hook suffixes where page views are recorded.
	 */
	private const ALLOWED_SCREENS = [
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
		'settings_page_remote-data-blocks-settings',
		'toplevel_page_vip-block-governance',
		'toplevel_page_safe-publish',
		'toplevel_page_safe-publish-settings',
		'toplevel_page_safe-publish-audit-log',
		'safe-publish_page_safe-publish-settings',
		'safe-publish_page_safe-publish-audit-log',
	];

	/**
	 * Tracks instance, created when the first page view is recorded.
	 *
	 * @var Tracks|null
	 */
	private static ?Tracks $tracks = null;

	/**
	 * Registers the hook that records page views.
	 */
	public static function init(): void {
		// Use a high priority value so the current screen is fully set up.
		add_action( 'admin_enqueue_scripts', [ self::class, 'record_page_view' ], 99 );
	}

	/**
	 * Records a page view for the current admin screen if it is supported.
	 *
	 * @param string $hook_suffix The current admin screen hook suffix.
	 */
	public static function record_page_view( string $hook_suffix ): void {
		if ( ! self::should_record_page_view( $hook_suffix ) ) {
			return;
		}

		self::$tracks ??= new Tracks( 'vip_wordpress_' );
		self::$tracks->record_event( 'admin_page_view', self::get_event_properties( $hook_suffix ) );
	}

	/**
	 * Determines if a page view should be recorded for the current request.
	 *
	 * @param string $hook_suffix The current admin screen hook suffix.
	 */
	public static function should_record_page_view( string $hook_suffix ): bool {
		if ( ! self::is_enabled_for_environment() || ! current_user_can( 'edit_posts' ) ) {
			return false;
		}

		$has_tracked_integration = false;
		foreach ( wpvip_get_enabled_integrations() as $integration ) {
			if ( $integration->should_track_admin_pages() ) {
				$has_tracked_integration = true;
				break;
			}
		}
		if ( ! $has_tracked_integration ) {
			return false;
		}

		/**
		 * Filters the admin screens where page views are recorded in Tracks.
		 *
		 * @param string[] $screens Admin screen hook suffixes, e.g. `edit.php` or `toplevel_page_my-plugin`.
		 */
		$screens = apply_filters( 'vip_telemetry_admin_page_view_screens', self::ALLOWED_SCREENS );

		return in_array( $hook_suffix, $screens, true );
	}

	/**
	 * Checks if page views may be recorded in the current environment: VIP
	 * production environments that are not sandboxed or FedRAMP.
	 */
	public static function is_enabled_for_environment(): bool {
		$is_production = defined( 'VIP_GO_APP_ENVIRONMENT' ) && 'production' === constant( 'VIP_GO_APP_ENVIRONMENT' );
		$is_fedramp    = defined( 'VIP_IS_FEDRAMP' ) && true === constant( 'VIP_IS_FEDRAMP' );

		return $is_production && ! $is_fedramp && is_wpvip_site();
	}

	/**
	 * Returns the properties of a page view event.
	 *
	 * @param string $hook_suffix The current admin screen hook suffix.
	 * @return array<string, string>
	 */
	private static function get_event_properties( string $hook_suffix ): array {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$roles  = array_values( wp_get_current_user()->roles );

		return array_filter(
			[
				'screen_id'    => $hook_suffix,
				'post_type'    => $screen->post_type ?? '',
				'taxonomy'     => $screen->taxonomy ?? '',
				'user_role'    => $roles[0] ?? '',
				'country_code' => sanitize_text_field( wp_unslash( $_SERVER['GEOIP_COUNTRY_CODE'] ?? '' ) ),
			],
			fn( $value ) => '' !== $value
		);
	}
}
