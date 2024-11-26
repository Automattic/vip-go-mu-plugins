<?php
/**
 * Telemetry: Tracks Event DTO class
 *
 * @package Automattic\VIP\Telemetry\Tracks
 */

declare(strict_types=1);

namespace Automattic\VIP\Telemetry\Tracks;

use AllowDynamicProperties;

/**
 * Class that holds necessary properties of Tracks events.
 *
 * @since 3.12.0
 */
#[AllowDynamicProperties]
class Tracks_Event_DTO {
	/** @var string _en stands for Event Name in A8c Tracks */
	public string $_en; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/** @var string _ui stands for User ID in A8c Tracks */
	public string $_ui; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/** @var string _ut stands for User Type in A8c Tracks */
	public string $_ut; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/** @var string */
	public string $_ts; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/** @var string */
	public string $_via_ip; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	public string $vip_env;

	public int $vip_org;

	public string $hosting_provider = 'other';

	public bool $is_vip_user = false;

	public bool $is_multisite = false;

	public string $wp_version = '';
}
