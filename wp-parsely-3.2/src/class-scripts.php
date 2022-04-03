<?php
/**
 * Scripts class
 *
 * @package Parsely
 * @since 3.0.0
 */

declare(strict_types=1);

namespace Parsely;

/**
 * Inserts the scripts and tracking code into the site's front-end
 *
 * @since 1.0.0
 * @since 3.0.0 Moved from class-parsely to separate file
 */
class Scripts {
	/**
	 * Instance of Parsely class.
	 *
	 * @var Parsely
	 */
	private $parsely;

	/**
	 * Constructor.
	 *
	 * @param Parsely $parsely Instance of Parsely class.
	 */
	public function __construct( Parsely $parsely ) {
		$this->parsely = $parsely;
	}

	/**
	 * Register js scripts.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function run(): void {
		$parsely_options = $this->parsely->get_options();
		if ( $this->parsely->api_key_is_set() && true !== $parsely_options['disable_javascript'] ) {
			add_action( 'init', array( $this, 'register_scripts' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_js_tracker' ) );
		}
	}

	/**
	 * Register JavaScripts, if there's an API key value saved.
	 *
	 * @since 2.5.0
	 * @since 3.0.0 Rename from register_js
	 *
	 * @return void
	 */
	public function register_scripts(): void {
		wp_register_script(
			'wp-parsely-tracker',
			$this->parsely->get_tracker_url(),
			array(),
			PARSELY_VERSION,
			true
		);

		$loader_asset = require plugin_dir_path( PARSELY_FILE ) . 'build/loader.asset.php';
		wp_register_script(
			'wp-parsely-loader',
			plugin_dir_url( PARSELY_FILE ) . 'build/loader.js',
			$loader_asset['dependencies'],
			$loader_asset['version'],
			true
		);
	}

	/**
	 * Enqueues the JavaScript code required to send off beacon requests.
	 *
	 * @since 2.5.0 Rename from insert_parsely_javascript
	 * @since 3.0.0 Rename from load_js_tracker
	 *
	 * @return void
	 */
	public function enqueue_js_tracker(): void {
		$parsely_options = $this->parsely->get_options();

		global $post;
		$display = true;
		if ( in_array( get_post_type(), $parsely_options['track_post_types'], true ) && ! Parsely::post_has_trackable_status( $post ) ) {
			$display = false;
		}
		if ( ! $parsely_options['track_authenticated_users'] && $this->parsely->parsely_is_user_logged_in() ) {
			$display = false;
		}
		if ( ! in_array( get_post_type(), $parsely_options['track_post_types'], true ) && ! in_array( get_post_type(), $parsely_options['track_page_types'], true ) ) {
			$display = false;
		}

		/**
		 * Filters whether to enqueue the Parsely JavaScript tracking script from the CDN.
		 *
		 * If true, the script is enqueued.
		 *
		 * @since 2.5.0
		 *
		 * @param bool $display True if the JavaScript file should be included. False if not.
		 */
		if ( ! apply_filters( 'wp_parsely_load_js_tracker', $display ) ) {
			return;
		}

		if ( false === has_filter( 'script_loader_tag', array( $this, 'script_loader_tag' ) ) ) {
			add_filter( 'script_loader_tag', array( $this, 'script_loader_tag' ), 10, 3 );
		}

		wp_enqueue_script( 'wp-parsely-loader' );
		wp_enqueue_script( 'wp-parsely-tracker' );

		// If we don't have an API secret, there's no need to set the API key.
		// Setting the API key triggers the UUID Profile Call function.
		if ( isset( $parsely_options['api_secret'] ) && is_string( $parsely_options['api_secret'] ) && '' !== $parsely_options['api_secret'] ) {
			$js_api_key = "window.wpParselyApiKey = '" . esc_js( $this->parsely->get_api_key() ) . "';";
			wp_add_inline_script( 'wp-parsely-loader', $js_api_key, 'before' );
		}
	}

	/**
	 * Filter the script tag for certain scripts to add needed attributes.
	 *
	 * @since 2.5.0
	 *
	 * @param string $tag    The `script` tag for the enqueued script.
	 * @param string $handle The script's registered handle.
	 * @param string $src    Unused? The script's source URL.
	 * @return string Amended `script` tag.
	 */
	public function script_loader_tag( string $tag, string $handle, string $src ): string {
		$parsely_options = $this->parsely->get_options();
		if ( in_array(
			$handle,
			array(
				'wp-parsely-loader',
				'wp-parsely-tracker',
				'wp-parsely-recommended-widget',
			),
			true
		) ) {
			/**
			 * Filter whether to include the CloudFlare Rocket Loader attribute (`data-cfasync=false`) in the script.
			 * Only needed if the site being served is behind Cloudflare. Should return false otherwise.
			 * https://support.cloudflare.com/hc/en-us/articles/200169436-How-can-I-have-Rocket-Loader-ignore-specific-JavaScripts
			 *
			 * @since 3.0.0
			 *
			 * @param bool $enabled True if enabled, false if not.
			 * @param string $handle The script's registered handle.
			 */
			if ( apply_filters( 'wp_parsely_enable_cfasync_attribute', false, $handle ) ) {
				$tag = preg_replace( '/^<script /', '<script data-cfasync="false" ', $tag );
			}
		}

		if ( null !== $tag && 'wp-parsely-tracker' === $handle ) {
			$tag = preg_replace( '/ id=(["\'])wp-parsely-tracker-js\1/', ' id="parsely-cfg"', $tag );
			$tag = preg_replace(
				'/ src=/',
				' data-parsely-site="' . esc_attr( $parsely_options['apikey'] ) . '" src=',
				$tag
			);
		}

		return $tag ?? '';
	}
}
