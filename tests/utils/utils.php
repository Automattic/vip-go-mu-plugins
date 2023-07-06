<?php
/**
 * File which contain common utils needed in testing.
 *
 * @package Automattic\Test\Utils
 */

namespace Automattic\Test\Utils;

use ReflectionClass;

require_once __DIR__ . '/../utils/parsely.utils.php';

/**
 * Gets private property of a class.
 *
 * @param class-string $class_name Name of the class.
 * @param string       $property_name Name of the property.
 *
 * @return ReflectionProperty
 */
function get_private_property( $class_name, $property_name ) {
	$reflector = new ReflectionClass( $class_name );
	$property  = $reflector->getProperty( $property_name );

	$property->setAccessible( true );

	return $property;
}

/**
 * Gets private method of a class.
 *
 * @param class-string $class_name Name of the class.
 * @param string       $method Name of the method.
 *
 * @return ReflectionMethod
 */
function get_private_method( $class_name, $method ) {
	$reflector = new ReflectionClass( $class_name );
	$method    = $reflector->getMethod( $method );

	$method->setAccessible( true );

	return $method;
}
