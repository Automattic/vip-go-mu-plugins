<?php

namespace WorDBless;

/**
 * Class InsertId is a basic counter to be used to get an unique ID when simulating an insert.
 *
 * Simply call bump_and_get() when you need to get a new uniqueID
 */
class InsertId {

	/**
	 * The current ID
	 *
	 * @var integer
	 */
	public static $id = 10;

	/**
	 * Bump the current ID and return it
	 *
	 * @return integer
	 */
	public static function bump_and_get() {
		return ++ self::$id;
	}

}
