<?php
namespace Automattic\VIP\Prometheus;

use Prometheus\Exception\StorageException;
use Prometheus\MetricFamilySamples;
use Prometheus\Storage\Adapter as StorageAdapter;
use Throwable;

class SafeAdapter implements StorageAdapter {
	private StorageAdapter $wrapped;

	public function __construct( StorageAdapter $real_adapter ) {
		$this->wrapped = $real_adapter;
	}

	/**
	 * @return MetricFamilySamples[]
	 */
	public function collect(): array {
		return $this->wrapped->collect();
	}

	/**
	 * @param mixed[] $data
	 */
	public function updateSummary( array $data ): void {
		$this->invoke_method( __FUNCTION__, $data );
	}

	/**
	 * @param mixed[] $data
	 */
	public function updateHistogram( array $data ): void {
		$this->invoke_method( __FUNCTION__, $data );
	}

	/**
	 * @param mixed[] $data
	 */
	public function updateGauge( array $data ): void {
		$this->invoke_method( __FUNCTION__, $data );
	}

	/**
	 * @param mixed[] $data
	 */
	public function updateCounter( array $data ): void {
		$this->invoke_method( __FUNCTION__, $data );
	}

	/**
	 * Removes all previously stored metrics from underlying storage
	 *
	 * @throws StorageException
	 */
	public function wipeStorage(): void {
		$this->wrapped->wipeStorage();
	}

	private function invoke_method( string $method, array $data ): void {
		if ( isset( $data['labelValues'] ) ) {
			$data['labelValues'] = array_map( 'strval', $data['labelValues'] );
		}

		try {
			$this->wrapped->$method( $data );
		} catch ( Throwable $ex ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			trigger_error( 'Prometheus: metric collection exception: ' . $ex->getMessage(), E_USER_WARNING );
		}
	}
}
