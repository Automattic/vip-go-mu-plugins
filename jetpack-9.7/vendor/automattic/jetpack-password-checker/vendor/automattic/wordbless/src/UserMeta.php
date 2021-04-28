<?php

namespace WorDBless;

class UserMeta {

	private static $instance = null;

	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new Metadata( 'user' );
		}
		return self::$instance;
	}

	private function __construct() {}

}
