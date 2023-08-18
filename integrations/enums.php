<?php
/**
 * Enums.
 *
 * @package Automattic\VIP\Integrations
 */

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- Disabling due to enums.

/**
 * Enum which represent all possible statuses for the client integration via VIP.
 *
 * These should be in sync with the statuses available on the backend.
 */
abstract class Client_Integration_Status {
	const BLOCKED = 'blocked';
}

/**
 * Enum which represent all possible statuses for the site integration via VIP.
 *
 * These should be in sync with the statuses available on the backend.
 */
abstract class Site_Integration_Status {
	const ENABLED  = 'enabled';
	const DISABLED = 'disabled';
	const BLOCKED  = 'blocked';
}
