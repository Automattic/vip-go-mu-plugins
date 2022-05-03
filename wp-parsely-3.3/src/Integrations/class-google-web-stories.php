<?php
/**
 * Integrations: Google Web Stories integration class
 *
 * @package Parsely
 * @since   3.2.0
 */

declare(strict_types=1);

namespace Parsely\Integrations;

/**
 * Integrates Parse.ly tracking with the Google Web Stories plugin.
 *
 * @since 3.2.0
 */
final class Google_Web_Stories implements Integration {
	/**
	 * Applies the hooks that integrate the plugin or theme with the Parse.ly
	 * plugin.
	 *
	 * @since 3.2.0
	 */
	public function integrate(): void {
		if ( defined( 'WEBSTORIES_PLUGIN_FILE' ) ) {
			add_action( 'web_stories_print_analytics', array( $this, 'render_amp_analytics_tracker' ) );
		}
	}

	/**
	 * Loads additional JavaScript for Google's Web Stories WordPress plugin.
	 * This relies on the `amp-analytics` element.
	 *
	 * See more at: https://www.parse.ly/help/integration/google-amp.
	 *
	 * @since 3.2.0
	 */
	public function render_amp_analytics_tracker(): void {
		$json = Amp::construct_amp_json();
		if ( strlen( $json ) > 0 ) {
			?>
			<amp-analytics type="parsely">
				<script type="application/json">
					<?php
					// Output contains the API key, and it's already escaped in the construct_amp_json function.
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo $json;
					?>
				</script>
			</amp-analytics>
			<?php
		}
	}
}
