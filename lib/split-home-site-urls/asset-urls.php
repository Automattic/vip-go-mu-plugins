<?php
/*
 Plugin name: Rewrite Static URLs
 Description: Rewrite static assets to be served from a different URL
 Version: 1.5
 Author: Erick Hitter
 Author URI: https://ethitter.com/
 License: GPLv2
*/

/*
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; version 2 of the License.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

namespace Automattic\VIP\Split_Home_Site_URLs;

class Asset_URLs {
	/**
	 * Singleton
	 */
	private static $instance = null;

	/**
	 * Class variables
	 */
	private $static_host = null;

	/**
	 * Silence is golden!
	 */
	private function __construct() {}

	/**
	 * Instantiate singleton
	 */
	public static function get_instance() {
		if ( ! is_a( self::$instance, __CLASS__ ) ) {
			self::$instance = new self;
			self::$instance->setup();
		}

		return self::$instance;
	}

	/**
	 * Register plugin's actions and filters
	 *
	 * @return null
	 */
	private function setup() {
		add_action( 'plugins_loaded', array( $this, 'action_plugins_loaded' ) );

		// Static assets other than uploads
		if ( $this->should_rewrite_non_upload_assets() ) {
			// Enqueued assets
			add_filter( 'script_loader_src', array( $this, 'filter_enqueued_asset' ), 10, 2 );
			add_filter( 'style_loader_src', array( $this, 'filter_enqueued_asset' ), 10, 2 );

			// Theme assets
			add_filter( 'template_directory_uri', array( $this, 'filter_theme_paths' ) );
			add_filter( 'stylesheet_directory_uri', array( $this, 'filter_theme_paths' ) );
			add_filter( 'stylesheet_uri', array( $this, 'filter_stylesheet_uri' ) );

			// Plugin assets
			add_filter( 'plugins_url', array( $this, 'filter_plugin_paths' ) );
			add_filter( 'jetpack_static_url', array( $this, 'filter_jetpack_static_urls' ), 999 );

			// Concatenated assets
			add_filter( 'ngx_http_concat_site_url', array( $this, 'filter_concat_base_url' ) );

			// TODO: Filter images in content
			add_filter( 'pre_option_upload_url_path', array( $this, 'filter_upload_url_path' ) );
		}
	}

	/**
	 * Expose static host for filtering
	 *
	 * @action plugins_loaded
	 * @return null
	 */
	public function action_plugins_loaded() {
		$host              = parse_url( home_url( '/' ), PHP_URL_HOST );
		$this->static_host = apply_filters( 'wpcom_vip_asset_urls_static_host', $host );
	}

	/**
	 * Rewrite enqueued assets to static host
	 *
	 * @param string $src
	 * @param string $handle
	 * @filter script_loader_src
	 * @filter style_loader_src
	 * @return string
	 */
	public function filter_enqueued_asset( $src, $handle ) {
		return $this->staticize( $src, $handle );
	}

	/**
	 * Rewrite theme assets to static host
	 *
	 * @param string $uri
	 * @filter template_directory_uri
	 * @filter stylesheet_directory_uri
	 * @return string
	 */
	public function filter_theme_paths( $uri ) {
		return $this->staticize( $uri, current_filter() );
	}

	/**
	 * Rewrite theme's stylesheet to static host
	 *
	 * @param string $uri
	 * @filter template_directory_uri
	 * @filter stylesheet_uri
	 * @return string
	 */
	public function filter_stylesheet_uri( $uri ) {
		return $this->staticize( $uri, current_filter() );
	}

	/**
	 * Rewrite certain plugin assets to static host
	 * Since plugins can do many crazy things, only select extensions are rewritten
	 *
	 * @param string $uri
	 * @filter plugins_url
	 * @return string
	 */
	public function filter_plugin_paths( $uri ) {
		$allowed_exts = array(
			'gif',
			'png',
			'jpg',
			'jpeg',
			'js',
			'css',
		);

		$extension = pathinfo( $uri, PATHINFO_EXTENSION );
		if ( ! in_array( $extension, $allowed_exts, true ) ) {
			return $uri;
		}

		return $this->staticize( $uri, current_filter() );
	}

	/**
	 * Rewrite Jetpack's static paths if they aren't served from an a8c CDN
	 *
	 * @param string $uri
	 * @filter jetpack_static_url
	 * @return string
	 */
	public function filter_jetpack_static_urls( $uri ) {
		// Extract hostname from URL
		$host = parse_url( $uri, PHP_URL_HOST );

		// Explode hostname on '.'
		$exploded_host = explode( '.', $host );

		// Retrieve the name and TLD
		if ( count( $exploded_host ) > 1 ) {
			$name = $exploded_host[ count( $exploded_host ) - 2 ];
			$tld = $exploded_host[ count( $exploded_host ) - 1 ];
			// Rebuild domain excluding subdomains
			$domain = $name . '.' . $tld;
		} else {
			$domain = $host;
		}

		// Array of Automattic domains
		$domain_whitelist = array( 'wordpress.com', 'wp.com' );

		// Return $uri if an Automattic domain, as it's already CDN'd
		if ( in_array( $domain, $domain_whitelist, true ) ) {
			return $uri;
		}

		// URI isn't served from a8c CDN, so serve from ours
		return $this->staticize( $uri, current_filter() );
	}

	/**
	 * Rewrite all upload URLs to point to the CDN
	 * Ensures images not processed with Photon are at least served from the CDN
	 *
	 * @param string $url
	 * @filter pre_option_upload_url_path
	 * @return string
	 */
	public function filter_upload_url_path( $url ) {
		// Rebuild upload URL
		$upload_path = trim( get_option( 'upload_path' ) );
		if ( empty( $upload_path ) ) {
			$upload_path = 'wp-content/uploads';
		}

		$url = 'https://' . $this->static_host . '/' . $upload_path;

		if ( is_multisite() && ! ( is_main_network() && is_main_site() ) ) {
			$url .= '/sites/' . get_current_blog_id();
		}

		// Bypass is_ssl() to determine if home is SSL or not, as it uses $_SERVER and context could break things
		$home_url_scheme = parse_url( get_option( 'home' ), PHP_URL_SCHEME );

		$url = set_url_scheme( $url, $home_url_scheme );

		return esc_url_raw( $url );
	}

	/**
	 * Rewrite URLs for concatenated assets
	 *
	 * @param string $url
	 * @filter ngx_http_concat_site_url
	 * @return string
	 */
	public function filter_concat_base_url( $url ) {
		return 'https://' . $this->static_host;
	}

	/**
	 ** UTILITY METHODS
	 **/

	/**
	 * Restrict static-asset rewriting to "front-end" requests
	 *
	 * @return bool
	 */
	private function should_rewrite_non_upload_assets() {
		// Allow dynamic exclusions
		$override = apply_filters( 'wpcom_vip_asset_urls_skip_rewrites_for_request', null );
		if ( is_bool( $override ) ) {
			return $override;
		}

		// Admin should use its domain to avoid CORS, mixed content, and authentication issues, et al
		if ( is_admin() ) {
			return false;
		}

		// Previews use the site URL when this library is active
		// Can't use is_preview() because this is called before the query is parsed
		// Logged-out users are redirected later on, as it's also premature to check that here
		if ( isset( $_GET['preview'] ) ) {
			return false;
		}

		// Skip on some Core front-end screens to avoid CORS and mixed content
		if ( false !== stripos( $_SERVER['REQUEST_URI'], 'wp-login.php' ) ) {
			return false;
		}

		if ( false !== stripos( $_SERVER['REQUEST_URI'], 'wp-signup.php' ) ) {
			return false;
		}

		// Rewrite by default
		return true;
	}

	/**
	 * Rewrite host to static
	 *
	 * @param string $src
	 * @param string $context Optional.
	 * @return string
	 */
	private function staticize( $src, $context = '' ) {
		// Abort if called too early
		if ( ! $this->static_host ) {
			return $src;
		}

		// Don't rewrite PHP URLs
		$extension = pathinfo( $src, PATHINFO_EXTENSION );
		if ( 0 === strpos( $extension, 'php' ) ) {
			return $src;
		}

		// Attempt to rewrite if proper conditions are met.
		$parsed_host = parse_url( $src, PHP_URL_HOST );
		if ( $parsed_host && $parsed_host !== $this->static_host && $this->should_staticize( $parsed_host ) && apply_filters( 'wpcom_vip_asset_urls_staticize', true, $parsed_host, $src, $context ) ) {
			$src = str_replace( $parsed_host, $this->static_host, $src );
		}

		// Return something!
		return $src;
	}

	/**
	 * Is current host appropriate for staticization?
	 *
	 * @param string $host
	 * @return bool
	 */
	private function should_staticize( $host ) {
		$site_url_host    = parse_url( site_url( '/' ), PHP_URL_HOST );
		$should_staticize = $site_url_host === $host;

		return (bool) apply_filters( 'wpcom_vip_asset_urls_should_staticize', $should_staticize, $host, $site_url_host );
	}
}

Asset_URLs::get_instance();
