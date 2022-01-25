<?php
/**
 * Integration interface
 *
 * @package Parsely\Integrations
 * @since 2.6.0
 */

declare(strict_types=1);

namespace Parsely\Integrations;

/**
 * Integration classes are expected to implement this interface.
 *
 * @since 2.6.0
 */
interface Integration {
	/**
	 * Apply the hooks that integrate the plugin or theme with the Parse.ly plugin.
	 *
	 * @since 2.6.0
	 */
	public function integrate(): void;
}
