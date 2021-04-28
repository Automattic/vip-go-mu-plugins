<?php

namespace WorDBless;

trait Singleton {

	private static $instance = null;

	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	private function __construct() {}

}
