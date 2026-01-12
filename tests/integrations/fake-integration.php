<?php
/**
 * Fake Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Generic.Files.OneObjectStructurePerFile.MultipleFound

class FakeIntegration extends Integration {

	public function is_loaded(): bool {
		return false;
	}

	public function load(): void { }

	public function configure(): void { }
}

class FakeIntegrationWithPendoTracking extends FakeIntegration {
	protected bool $enable_pendo_tracking = true;
}
