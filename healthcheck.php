<?php
namespace Automattic\VIP;

use Automattic\VIP\Utils\Context;
/**
 * Utility Healthcheck class that runs further than cache-healthcheck and allow to verify the app code is working.
 */
class Healthcheck {
	/**
	 * By default the site is considered healthy unless filter value returns falsy value
	 *
	 * @var boolean
	 */
	protected bool $healthy = true;
	public function __construct() {
		$this->check();
	}
	public function check() {
		$this->healthy = (bool) apply_filters( 'vip_site_healthcheck', $this->healthy );
	}

	public function is_healthy() {
		return $this->healthy;
	}
}

/**
 * It's safe to assume that by the time of `template_redirect` the codebase is loaded fully.
 */
if ( isset( $_SERVER['REQUEST_URI'] ) && '/vip-healthcheck' === $_SERVER['REQUEST_URI'] ) {
	add_action( 'template_redirect', function() {
		nocache_headers();
		$is_healthy = ( new Healthcheck() )->is_healthy();
		exit( $is_healthy ? 'Ok' : 'Not ok' );
	}, PHP_INT_MIN );
}
