<?php

include_once( dirname( __FILE__ ) . '/class-syndication-wp-xmlrpc-client.php' );
include_once( dirname( __FILE__ ) . '/class-syndication-wp-xml-client.php' );
include_once( dirname( __FILE__ ) . '/class-syndication-wp-rest-client.php' );
include_once( dirname( __FILE__ ) . '/class-syndication-wp-rss-client.php' );

class Syndication_Client_Factory {

	public static function get_client( $transport_type, $site_ID ) {

		$class = self::get_transport_type_class( $transport_type );
		if( class_exists( $class ) ) {
			return new $class( $site_ID );
		}

		throw new Exception(' transport class not found' );

	}

	public static function display_client_settings( $site, $transport_type ) {

		$class = self::get_transport_type_class( $transport_type );
		if( class_exists( $class ) ) {
			return call_user_func( array( $class, 'display_settings' ), $site );
		}

		throw new Exception( 'transport class not found: '. $class );

	}

	public static function save_client_settings( $site_ID, $transport_type ) {

		$class = self::get_transport_type_class( $transport_type );
		if( class_exists( $class ) ) {
			return call_user_func( array( $class, 'save_settings' ), $site_ID );
		}

		throw new Exception( 'transport class not found' );

	}

	public static function get_transport_type_class( $transport_type ) {
		return 'Syndication_' . $transport_type . '_Client';
	}

}
