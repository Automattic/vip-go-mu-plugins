<?php
/**
 * Telemetry: Tracks Event class
 *
 * @package Automattic\VIP\Telemetry
 */

declare(strict_types=1);

namespace Automattic\VIP\Telemetry;

use AllowDynamicProperties;
use stdClass;
use WP_Error;
use Automattic\VIP\Support_User\User as Support_User;
use function Automattic\VIP\Logstash\log2logstash;

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

	public string $vipgo_env;

	public string $vipgo_org;

	public bool $is_vip_user = false;
}
