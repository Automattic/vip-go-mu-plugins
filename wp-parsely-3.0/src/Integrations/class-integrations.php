<?php
/**
 * Integrations collection
 *
 * @package Parsely\Integrations
 * @since   2.6.0
 */

declare(strict_types=1);

namespace Parsely\Integrations;

/**
 * Integrations are registered to this collection.
 *
 * The `integrate()` method is called on each registered integration, on the init hook.
 *
 * @since 2.6.0
 */
class Integrations {
	/**
	 * Collection of registered integrations.
	 *
	 * @var array
	 */
	private $integrations = array();

	/**
	 * Register an integration.
	 *
	 * @since 2.6.0
	 *
	 * @param string        $key             A unique identifier for the integration.
	 * @param string|object $class_or_object Fully-qualified class name, or an instantiated object.
	 *                             If a class name is passed, it will be instantiated.
	 * @return void
	 */
	public function register( string $key, $class_or_object ): void {
		// If a Foo::class or other fully qualified class name is passed, instantiate it.
		if ( ! is_object( $class_or_object ) ) {
			$class_or_object = new $class_or_object();
		}
		$this->integrations[ $key ] = $class_or_object;
	}

	/**
	 * Integrate each integration by calling the method that does the add_action() and add_filter() calls.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	public function integrate(): void {
		foreach ( $this->integrations as $integration ) {
			$integration->integrate();
		}
	}
}
