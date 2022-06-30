<?php

namespace Automattic\VIP\Utils;

class Jetpack_IP_Manager {
	private static ?self $instance = null;

	public const CRON_EVENT_NAME   = 'vip_go_mu_plugins_update_jetpack_ips';
	public const TRANSIENT_NAME    = 'vip_go_mu_plugins_jetpack_ips';
	private const TRANSIENT_EXPIRY = 0;
	public const ENDPOINT          = 'https://jetpack.com/ips-v4.json';

	/**
	 * @codeCoverageIgnore -- called to early to be covered
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * @codeCoverageIgnore -- called to early to be covered
	 */
	private function __construct() {
		add_action( 'init', [ $this, 'init' ] );
	}

	public function init(): void {
		if ( ! wp_next_scheduled( self::CRON_EVENT_NAME ) ) {
			wp_schedule_event( time() + wp_rand( 10, DAY_IN_SECONDS / 2 ), 'daily', self::CRON_EVENT_NAME );
		}
	
		add_action( self::CRON_EVENT_NAME, [ $this, 'update_jetpack_ips' ] );
	}

	public function update_jetpack_ips(): array {
		$response = vip_safe_wp_remote_get( self::ENDPOINT );
		if ( ! is_wp_error( $response ) ) {
			$code = wp_remote_retrieve_response_code( $response );
			if ( 200 === (int) $code ) {
				$body = wp_remote_retrieve_body( $response );
				$ips  = json_decode( $body, true );
	
				if ( is_array( $ips ) && ! empty( $ips ) ) {
					set_transient( self::TRANSIENT_NAME, $ips, self::TRANSIENT_EXPIRY );
					return $ips;
				}
			}
		}

		return [];
	}

	public static function get_jetpack_ips(): array {
		$ips = get_transient( self::TRANSIENT_NAME );
		if ( ! is_array( $ips ) || empty( $ips ) ) {
			$ips = self::instance()->update_jetpack_ips();
		}
	
		return $ips;
	}
}
