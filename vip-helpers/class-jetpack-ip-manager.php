<?php

namespace Automattic\VIP\Utils;

class Jetpack_IP_Manager {
	private static ?self $instance = null;

	public const OPTION_NAME = 'vip_jetpack_ips';
	public const ENDPOINT    = 'https://jetpack.com/ips-v4.json';
	public const LOCK_NAME   = 'update_jetpack_ips';
	public const LOCK_GROUP  = 'vip';

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
		$is_shutdown = 'shutdown' === current_action();
		// @codeCoverageIgnoreStart -- we cannot really test this, as we cannot check what the action handler returns
		if ( $is_shutdown && ! wp_cache_add( self::LOCK_NAME, true, self::LOCK_GROUP, MINUTE_IN_SECONDS ) ) {
			return [];
		}
		// @codeCoverageIgnoreEnd

		try {
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
		} finally {
			if ( $is_shutdown ) {
				wp_cache_delete( self::LOCK_NAME, self::LOCK_GROUP );
			}
		}
	}

	public static function get_jetpack_ips(): array {
		$data = get_option( self::OPTION_NAME, false );
		if ( ! is_array( $data ) ) {
			$data = self::instance()->update_jetpack_ips();
		}

		$ips = $data['ips'] ?? [];
		$exp = $data['exp'] ?? 0;

		if ( $exp < time() ) {
			add_action( 'shutdown', [ self::instance(), 'update_jetpack_ips' ] );
		}

		return $ips;
	}
}
