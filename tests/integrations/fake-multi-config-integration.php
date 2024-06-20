<?php
/**
 * Fake integration which have multiple setups.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing

class FakeMultiSetupIntegration extends Integration {
	/**
	 * A boolean indicating if the integration have multiple setups of same integration.
	 *
	 * @var bool
	 */
	protected bool $have_multiple_setups = true;

	public function is_loaded(): bool {
		return false;
	}

	public function load(): void { }

	public function configure(): void { }
}
