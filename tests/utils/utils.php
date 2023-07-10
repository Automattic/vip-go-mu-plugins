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

	return $property;
}

/**
 * Gets private property of a class by making it as public.
 *
 * @param class-string $class_name Name of the class.
 * @param string       $property_name Name of the property.
 *
 * @return ReflectionProperty
 */
function get_private_property_as_public( $class_name, $property_name ) {
	$property = get_private_property( $class_name, $property_name );

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

	return $method;
}

/**
 * Gets private method of a class by making it as public.
 *
 * @param class-string $class_name Name of the class.
 * @param string       $method Name of the method.
 *
 * @return ReflectionMethod
 */
function get_private_method_as_public( $class_name, $method ) {
	$method = get_private_method( $class_name, $method );
	$method->setAccessible( true );

	return $method;
}
