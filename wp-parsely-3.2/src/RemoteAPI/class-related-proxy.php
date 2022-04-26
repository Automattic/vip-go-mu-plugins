<?php
/**
 * Parsely Related REST API Proxy
 *
 * @package Parsely
 * @since 3.2.0
 */

declare(strict_types=1);

namespace Parsely\RemoteAPI;

/**
 * Proxy for the /related endpoint.
 *
 * @since 3.2.0
 */
class Related_Proxy extends Base_Proxy {
	protected const ENDPOINT     = 'https://api.parsely.com/v2/related';
	protected const QUERY_FILTER = 'wp_parsely_related_endpoint_args';
}
