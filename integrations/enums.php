<?php
/**
 * Enums.
 *
 * @package Automattic\VIP\Integrations
 */

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- Disabling due to enums.

/**
 * Enum which represent all possible organization statuses for integration via VIP.
 *
 * These should be in sync with the statuses available on the backend.
 */
abstract class Org_Integration_Status {
	const BLOCKED = 'blocked';
}

/**
 * Enum which represent all possible environment statuses for integration via VIP.
 *
 * These should be in sync with the statuses available on the backend.
 */
abstract class Env_Integration_Status {
	const ENABLED  = 'enabled';
	const DISABLED = 'disabled';
	const BLOCKED  = 'blocked';
}
