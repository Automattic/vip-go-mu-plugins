<?php

namespace Automattic\VIP\Prometheus;

use Prometheus\Counter;
use Prometheus\RegistryInterface;

/**
 * @codeCoverageIgnore
 */
class Error_Stats_Collector implements CollectorInterface {
	private Counter $error_counter;

	public function initialize( RegistryInterface $registry ): void {
		$this->error_counter = $registry->getOrRegisterCounter(
			'error',
			'count',
			'Number of runtime errors',
			[ 'site_id', 'error_type' ]
		);
		add_action( 'php_error_handler', [ $this, 'php_error_handler' ], 10, 4 );
	}

	public function php_error_handler( $error_type, $message, $file, $line ): void {
		$this->error_counter->inc( [ Plugin::get_instance()->get_site_label(), (string) $error_type ] );
	}

	public function collect_metrics(): void {
		/* Do nothing */
	}

	public function process_metrics(): void {
		/* Do nothing */
	}
}
