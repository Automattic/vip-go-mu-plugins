<?php

namespace Automattic\VIP\Prometheus;

use Prometheus\Counter;
use Prometheus\RegistryInterface;

/**
 * @codeCoverageIgnore
 */
class Login_Stats_Collector implements CollectorInterface {
	private Counter $login_limit_exceeded_counter;
	private Counter $password_reset_limit_exceeded_counter;
	private Counter $successful_login_counter;
	private Counter $failed_login_counter;

	public function initialize( RegistryInterface $registry ): void {
		$this->login_limit_exceeded_counter = $registry->getOrRegisterCounter(
			'security',
			'login_limit_exceeded_total',
			'Number of rate-limited login requests',
			[ 'site_id' ]
		);

		$this->password_reset_limit_exceeded_counter = $registry->getOrRegisterCounter(
			'security',
			'password_reset_limit_exceeded_total',
			'Number of rate-limited password reset requests',
			[ 'site_id' ]
		);

		$this->successful_login_counter = $registry->getOrRegisterCounter(
			'security',
			'successful_logins_total',
			'Number of successful login attempts',
			[ 'site_id' ]
		);

		$this->failed_login_counter = $registry->getOrRegisterCounter(
			'security',
			'failed_logins_total',
			'Number of failed login attempts',
			[ 'site_id' ]
		);

		add_action( 'login_limit_exceeded', [ $this, 'login_limit_exceeded' ] );
		add_action( 'password_reset_limit_exceeded', [ $this, 'password_reset_limit_exceeded' ] );
		add_action( 'wp_login', [ $this, 'wp_login' ] );
		add_action( 'wp_login_failed', [ $this, 'wp_login_failed' ] );
	}

	public function login_limit_exceeded(): void {
		$this->login_limit_exceeded_counter->inc( [ (string) get_current_blog_id() ] );
	}

	public function password_reset_limit_exceeded(): void {
		$this->password_reset_limit_exceeded_counter->inc( [ (string) get_current_blog_id() ] );
	}

	public function wp_login(): void {
		$this->successful_login_counter->inc( [ (string) get_current_blog_id() ] );
	}

	public function wp_login_failed(): void {
		$this->failed_login_counter->inc( [ (string) get_current_blog_id() ] );
	}

	public function collect_metrics(): void {
		// Do nothing
	}
}
