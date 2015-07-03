<?php
/**
 * Plugin Name: WP.com Geo Uniques
 * Description: Batcache-friendly way to handle geo-targetting of users at a specific set of locations.
 * Author: Automattic, WordPress.com VIP
 * Version: 0.3
 * License: GPLv2
 */
class WPCOM_Geo_Uniques {

	const GEO_API_ENDPOINT = 'https://public-api.wordpress.com/geo/';
	const COOKIE_NAME = '_wpcom_geo'; // must be prefixed with "_" since batcache ignores cookies starting with "wp"
	const ACTION_PARAM = 'wpcom-geolocate';

	private static $expiry_time = 604800; // 1 week
	private static $default_location = 'default';
	private static $supported_locations = array();
	private static $simple_mode = true;

	static function after_setup_theme() {
		if ( is_admin() )
			return;

		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST )
			return;

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX )
			return;

		// Add default to list of supported countries
		self::add_location( self::get_default_location() );

		// If the theme hasn't registered any locations, bail. 
		$locations = self::get_registered_locations();
		if ( count( $locations ) <= 1 )
			return;

		static::$simple_mode = (bool) apply_filters( 'wpcom_geo_simple_mode', static::$simple_mode );

		// Note: For simple mode, we don't need to init anything simple location handling is on-demand
		if ( false === static::$simple_mode ) {
			self::init_advanced_geolocation();
		}
	}

	static function init_advanced_geolocation() {
		add_action( 'wp_enqueue_scripts' , array( __CLASS__, 'add_wpcom_geo_js' ) );

		if ( ! self::user_has_location_cookie() ) {
			// TODO: Temporary until we get the global endpoint
			if ( '/geolocate' === $_SERVER['REQUEST_URI']  ) {
				self::geolocate_user_and_die();
			}

			add_action( 'wp_head', array( __CLASS__, 'geolocate_advanced_js' ), -1 ); // We want this to run super early
		}
	}

	static function add_wpcom_geo_js() {
		wp_register_script( 'wpcom-geo-js', plugins_url( 'js/wpcom-geo.js', __FILE__ ) );
	}

	// TODO: delete after self-geolocation is removed
	static function geolocate_user_and_die() {
		$location = self::get_geolocation_data_from_ip();

		if ( ! $location || '-' == $location->country_short ) {
			header( 'HTTP/1.1 404 Not Found' );
			exit;
		}

		$location_trimmed = array(
			'latitude'        => $location->latitude,
			'longitude'       => $location->longitude,
			'country_short'   => $location->country_short,
			'country_long'    => $location->country_long,
			'region'          => $location->region,
			'city'            => $location->city,
		);

		header( 'Content-type: text/javascript' );
		echo json_encode( $location_trimmed );
		exit;
	}

	static function geolocate_advanced_js() {
		$geolocate_script_src = apply_filters( 'wpcom_geo_client_js_src', '' );

		if ( empty( $geolocate_script_src ) ) {
			_doing_it_wrong( __METHOD__, 'Please specify a script src via the `wpcom_geo_client_js_src` filter that utilizes the wpcom_geo JS API.', 0.3 );
			return;
		}

		$settings = array(
			'geolocation_endpoint' => apply_filters( 'wpcom_geo_api_endpoint', self::GEO_API_ENDPOINT ),
			'locations' => self::get_registered_locations(),
			'default_location' => self::get_default_location(),
			'cookie_name' => self::COOKIE_NAME,
			'expiry_date' => date( 'D, d M Y H:i:s T', strtotime( "+" . static::$expiry_time . " seconds", current_time( 'timestamp', 1 ) ) ),
			'expiry_time' => self::$expiry_time,
		);

		$settings = apply_filters( 'wpcom_geo_client_js_settings', $settings );

		// We don't care much for other scripts since the client-js will result in a page reload.
		// So, let's output it all now and early without waiting for other things.
		wp_enqueue_script( 'wpcom-geo-client-js', $geolocate_script_src, array( 'wpcom-geo-js' ) );
		wp_localize_script( 'wpcom-geo-client-js', 'wpcom_geo_settings', $settings );

		wp_print_scripts( 'wpcom-geo-client-js' );
	}

	static function is_valid_location( $location ) {
		return in_array( $location, static::$supported_locations );
	}

	static function get_default_location() {
		return static::$default_location;
	}

	static function set_default_location( $location ) {
		if ( ! self::is_valid_location( $location ) ) {
			static::add_location( $location );
		}

		static::$default_location = $location;
	}

	static function add_location( $location ) {
		static::$supported_locations = array_merge( static::$supported_locations, (array) $location );
	}

	static function get_registered_locations() {
		return static::$supported_locations;
	}

	static function get_user_location() {
		static $user_location;

		if ( isset( $user_location ) )
			return $user_location;

		if ( static::$simple_mode && ! self::user_has_location_cookie() ) {
			$user_location = self::get_user_location_from_global( '$_SERVER[ "GEOIP_COUNTRY_CODE" ]' );
		} else {
			$user_location = self::get_user_location_from_global( sprintf( '$_COOKIE[ "%s" ]', static::COOKIE_NAME ) );
		}

		return $user_location;
	}

	static function get_user_location_from_global( $global_var ) {
		$checks = array();
		foreach ( self::get_registered_locations() as $location ) {
			$checks[] = sprintf(
				'( "%1$s" == strtolower( %2$s ) ) { return "%1$s"; }',
				$location,
				$global_var
			);
		}

		$test  = sprintf( 'if ( empty( %s ) ) { return "%s"; }', $global_var, esc_js( self::get_default_location() ) );
		$test .= sprintf( ' elseif %s', implode( ' elseif ', $checks ) );
		$test .= sprintf( ' else { return "%s"; }', esc_js( self::get_default_location() ) );

		$user_location = static::run_vary_cache_on_function( $test );
		return $user_location;
	}

	static function user_has_location_cookie() {
		// TODO: add static var in case this is called multiple times
		// TODO: should currently only be used in advanced mode
		return static::run_vary_cache_on_function( 'return isset( $_COOKIE[ "' . self::COOKIE_NAME . '" ] );' );
	}

	private static function ip2location( $location_type = 'country_short' ) {
		$location_full = self::get_geolocation_data_from_ip();

		if ( $location_full && property_exists( $location_full, $location_type ) )
			$location = $location_full->$location_type;

		if ( empty( $location ) )
			$location = static::$default_location;

		$location = apply_filters( 'wpcom_geo_location', $location, $location_full, $location_type );

		return strtolower( $location );
	}

	private static function get_geolocation_data_from_ip() {

		$location_full = apply_filters( 'wpcom_geo_pre_get_geolocation_data_from_ip', false, $_SERVER['REMOTE_ADDR'] );
		if ( false !== $location_full )
			return $location_full;

		$location_full = null;

		if ( function_exists( 'ip2location' ) ) {
			$ip_address    = apply_filters( 'wpcom_geo_ip_address', $_SERVER['REMOTE_ADDR'] );
			$location_full = ip2location( $ip_address );
			$location_full = apply_filters( 'wpcom_geo_location_full', $location_full );
		} elseif ( defined( 'WP_DEBUG' ) && true === WP_DEBUG ) {
			// Add some fake data for dev
			$location_full                = new stdClass;
			$location_full->latitude      = '43.6525';
			$location_full->longitude     = '-79.381667';
			$location_full->country_short = 'CA';
			$location_full->country_long  = 'CANADA';
			$location_full->region        = 'ONTARIO';
			$location_full->city          = 'TORONTO';

			// Allows for debugging with specific test data
			$location_full = apply_filters( 'wpcom_geo_location_full_debug', $location_full );
		}

		return $location_full;
	}

	// Make it play nice with Batcache. Real nice.
	private static function run_vary_cache_on_function( $test ) {
		if ( function_exists( 'vary_cache_on_function' ) ) {
			vary_cache_on_function( $test );
		}

		$test_func = create_function( '', $test );
		return $test_func();
	}
}

add_action( 'after_setup_theme', array( 'WPCOM_Geo_Uniques', 'after_setup_theme' ), 1 );

// helper functions
function wpcom_geo_add_location( $location ) {
	WPCOM_Geo_Uniques::add_location( $location );
}

function wpcom_geo_get_user_location() {
	return WPCOM_Geo_Uniques::get_user_location();
}

function wpcom_geo_set_default_location( $location ) {
	WPCOM_Geo_Uniques::set_default_location( $location );
}
