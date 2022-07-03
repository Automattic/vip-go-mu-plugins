<?php

namespace Automattic\VIP\Utils;

class Jetpack_IP_Manager {
	private static ?self $instance = null;

	public const OPTION_NAME  = 'vip_jetpack_ips';
	public const ENDPOINT     = 'https://jetpack.com/ips-v4.json';
	private const CACHE_GROUP = 'vip';
	private const CACHE_KEY   = 'jetpack_ips_lock';

	/**
	 * @codeCoverageIgnore -- called to early to be covered
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function update_jetpack_ips(): array {
		$response = vip_safe_wp_remote_get( self::ENDPOINT );
		if ( ! is_wp_error( $response ) ) {
			$code = wp_remote_retrieve_response_code( $response );
			if ( 200 === (int) $code ) {
				$body = wp_remote_retrieve_body( $response );
				$ips  = json_decode( $body, true );
	
				if ( is_array( $ips ) && ! empty( $ips ) ) {
					$data = [
						'ips' => $ips,
						'exp' => time() + DAY_IN_SECONDS,
					];

					update_option( self::OPTION_NAME, $data );
					return $data;
				}
			}
		}

		return [];
	}

	public static function get_jetpack_ips(): array {
		$data = get_option( self::OPTION_NAME, false );
		if ( ! is_array( $data ) ) {
			$data = self::instance()->update_jetpack_ips();
		}

		$ips = $data['ips'] ?? [];
		$exp = $data['exp'] ?? 0;

		if ( $exp < time() && wp_cache_add( self::CACHE_KEY, true, self::CACHE_GROUP, 300 ) ) {
			try {
				$fresh_data = self::instance()->update_jetpack_ips();
				if ( ! empty( $fresh_data ) ) {
					$ips = $fresh_data['ips'];
				}
			} finally {
				wp_cache_delete( self::CACHE_KEY, self::CACHE_GROUP );
			}
		}

		return $ips;
	}
}
