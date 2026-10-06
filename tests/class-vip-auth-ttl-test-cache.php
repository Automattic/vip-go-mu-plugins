<?php

/**
 * Cache boundary with a controlled clock; production limiter functions remain real.
 */
class VIP_Auth_TTL_Test_Cache extends WP_Object_Cache {
	public $now         = 0;
	public $expirations = [];

	/**
	 * Record expiry only when a counter is initially added.
	 */
	public function add( $key, $data, $group = 'default', $expire = 0 ) {
		$result = parent::add( $key, $data, $group, $expire );
		if ( $result && CACHE_GROUP_LOGIN_LIMIT === $group ) {
			$this->expirations[ $key ] = $expire ? $this->now + $expire : PHP_INT_MAX;
		}
		return $result;
	}

	/**
	 * Record expiry of lockout writes.
	 */
	public function set( $key, $data, $group = 'default', $expire = 0 ) {
		if ( CACHE_GROUP_LOGIN_LIMIT === $group ) {
			$this->expirations[ $key ] = $expire ? $this->now + $expire : PHP_INT_MAX;
		}
		return parent::set( $key, $data, $group, $expire );
	}

	/**
	 * Expire entries against the controlled clock before returning cached values.
	 */
	public function get( $key, $group = 'default', $force = false, &$found = null ) {
		if ( CACHE_GROUP_LOGIN_LIMIT === $group && isset( $this->expirations[ $key ] ) && $this->now >= $this->expirations[ $key ] ) {
			parent::delete( $key, $group );
		}
		return parent::get( $key, $group, $force, $found );
	}
}
