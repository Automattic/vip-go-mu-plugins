<?php
/**
 * Fake integration with multiple configs.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing

class FakeMultiConfigIntegration extends Integration {
	/**
	 * A boolean indicating if the integration have multiple configs.
	 *
	 * @var bool
	 */
	protected bool $have_multiple_configs = true;

	public function is_loaded(): bool {
		return false;
	}

	public function load(): void { }

	public function configure(): void { }
}
