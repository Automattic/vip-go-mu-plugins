<?php
/**
 * File which contain common utils needed in testing.
 *
 * @package Automattic\Test\Utils
 */

namespace Automattic\Test\Utils;

use ReflectionClass;
use ReflectionProperty;

require_once __DIR__ . '/../utils/parsely-utils.php';
require_once __DIR__ . '/http-response.php';
require_once __DIR__ . '/run-php.php';
require_once __DIR__ . '/site-details-helpers.php';
require_once __DIR__ . '/trait-captures-errors.php';

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

	return $method;
}

/**
 * @psalm-param class-string $class_name
 */
function get_static_property_as_public( string $class_name, string $property_name ): ReflectionProperty {
	$reflection = new ReflectionClass( $class_name );
	$property   = $reflection->getProperty( $property_name );

	return $property;
}
