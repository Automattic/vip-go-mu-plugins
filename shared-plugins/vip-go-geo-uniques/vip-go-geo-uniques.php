<?php
/**
 * Plugin Name: VIP Go Geo Uniques
 * Description: Varnish-friendly way to handle geo-targetting of users at a specific set of locations.
 * Version: 0.1.0
 * Author: Automattic, WordPress VIP
 * License: GPLv2
 **/

class VIP_Go_Geo_Uniques {

	private static $default_location = 'default';
	private static $supported_locations = array();

	function __construct() {
		add_action( 'init', array( $this, 'init' ), 1 );
	}

	static function get_country_code() {
		if ( ! empty( $_SERVER['HTTP_X_COUNTRY_CODE'] ) ) {
			$loc = $_SERVER['HTTP_X_COUNTRY_CODE'];

			if ( self::is_valid_location( $loc ) ) {
				return $loc;
			}
		}

		return self::get_default_location();
	}

	static function get_default_location() {
		return self::$default_location;
	}

	static function set_default_location( $loc ) {
		self::$default_location = $loc;
	}

	static function add_location( $loc ) {
		self::$supported_locations[] = $loc;
		return true;
	}

	static function is_valid_location( $loc ) {
		return in_array( $loc, self::$supported_locations );
	}

	function init() {
		if ( is_admin() ) {
			return;
		}

		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return;
		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		if ( count( self::$supported_locations ) < 1 ) {
			return;
		}

		if ( ! self::is_valid_location( self::get_default_location() ) ) {
			self::add_location( self::get_default_location() );
		}

		add_action( 'send_headers', function() {
			header('Vary: X-Country-Code');
		});
	}
}

new VIP_Go_Geo_Uniques();

function vip_geo_get_country_code() {
	return VIP_Go_Geo_Uniques::get_country_code();
}

function vip_geo_set_default_location( $loc ) {
	return VIP_Go_Geo_Uniques::set_default_location( $loc );
}

function vip_geo_add_location( $loc ) {
	return VIP_Go_Geo_Uniques::add_location( $loc );
}
