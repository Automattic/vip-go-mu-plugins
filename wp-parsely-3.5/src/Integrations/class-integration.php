<?php
/**
 * Integrations: Abstract base class for all integration implementations
 *
 * @package Parsely
 * @since   2.6.0
 */

declare(strict_types=1);

namespace Parsely\Integrations;

use Parsely\Parsely;

/**
 * Base class that all integrations should extend from.
 *
 * @since 2.6.0
 * @since 3.5.2 Converted from interface to abstract class.
 */
abstract class Integration {
	/**
	 * Instance of Parsely class.
	 *
	 * @var Parsely
	 */
	protected static $parsely;

	/**
	 * Constructor.
	 *
	 * @param Parsely $parsely Instance of Parsely class.
	 */
	public function __construct( Parsely $parsely ) {
		self::$parsely = $parsely;
	}

	/**
	 * Applies the hooks that integrate the plugin or theme with the Parse.ly
	 * plugin.
	 *
	 * @since 2.6.0
	 */
	abstract public function integrate(): void;
}
