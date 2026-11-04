<?php
/**
 * Telemetry: Pendo Track Event DTO class
 *
 * @package Automattic\VIP\Telemetry\Pendo
 */

declare(strict_types=1);

namespace Automattic\VIP\Telemetry\Pendo;

/**
 * Class that holds necessary properties of Pendo "Track events".
 *
 * https://engageapi.pendo.io/#e45be48e-e01f-4f0a-acaa-73ef6851c4ac
 */
class Pendo_Track_Event_DTO {
	/** @var string $accountId */
	public string $accountId; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase

	/** @var object $context */
	public object $context;

	/** @var string $event */
	public string $event;

	/** @var object $properties */
	public object $properties;

	/** @var float $timestamp */
	public float $timestamp;

	/** @var string $type */
	public string $type = 'track';

	/** @var string $visitorId */
	public string $visitorId; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
}
