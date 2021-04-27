<?php

namespace WorDBless;

class PostMeta {

	private static $instance = null;

	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new Metadata( 'post' );
		}
		return self::$instance;
	}

	private function __construct() {}

}
