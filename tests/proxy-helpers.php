<?php
namespace Automattic\VIP\Proxy;

abstract class Proxy_Verification_Helper {
	/** @var mixed */
	private static $verification_key = null;

	public static function get_proxy_verification_key(): ?string {
		$key = self::$verification_key;
		return ! empty( $key ) ? (string) $key : null;
	}

	public static function set_proxy_verification_key( $key ): void {
		self::$verification_key = $key;
	}
}

function _get_wpcom_vip_proxy_verification(): ?string {
	return Proxy_Verification_Helper::get_proxy_verification_key();
}
