<?php
/**
 * File which contain common utils needed in testing.
 *
 * @package Automattic\Test\Utils
 */

namespace Automattic\Test\Utils;

use ErrorException;
use ReflectionClass;
use ReflectionProperty;

require_once __DIR__ . '/../utils/parsely-utils.php';

/**
 * For testing purpose gets private property of a class by making it as public.
 *
 * @param class-string $class_name Name of the class.
 * @param string       $property_name Name of the property.
 *
 * @return ReflectionProperty
 */
function get_class_property_as_public( $class_name, $property_name ) {
	$reflector = new ReflectionClass( $class_name );
	$property  = $reflector->getProperty( $property_name );

	$property->setAccessible( true ); // NOSONAR.

	return $property;
}

/**
 * For testing purpose gets private method of a class by making it as public.
 *
 * @param class-string $class_name Name of the class.
 * @param string       $method Name of the method.
 *
 * @return ReflectionMethod
 */
function get_class_method_as_public( $class_name, $method ) {
	$reflector = new ReflectionClass( $class_name );
	$method    = $reflector->getMethod( $method );

	$method->setAccessible( true ); // NOSONAR.

	return $method;
}

/**
 * For testing purpose gets private static property of a class by making it as public.
 *
 * @param string $class_name Name of the class.
 * @param string $property_name Name of the property.
 *
 * @return ReflectionProperty
 */
function get_static_property_as_public( string $class_name, string $property_name ): ReflectionProperty {
	$reflection = new ReflectionClass( $class_name );
	$property   = $reflection->getProperty( $property_name );
	$property->setAccessible( true ); // NOSONAR.

	return $property;
}

/**
 * Setup custom error handler and returns current level of error reporting.
 */
function setup_custom_error_reporting(): int {
	set_error_handler( static function ( int $errno, string $errstr ) { // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		if ( error_reporting() & $errno ) { // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI
			throw new ErrorException( $errstr, $errno ); // NOSONAR.
		}

		return false;
	}, E_USER_WARNING );

	return error_reporting(); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting
}

/**
 * Reset custom error handler and error reporting.
 *
 * @param integer $original_error_reporting Original error reporting level.
 *
 * @return void
 */
function reset_custom_error_reporting( int $original_error_reporting ): void {
	restore_error_handler();
	error_reporting( $original_error_reporting ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting
}
