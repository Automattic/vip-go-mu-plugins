<?php
/**
 * Plugin Name: VIP Logstash integration
 * Description: Helper functions and classes for logstash integration.
 * Author: Automattic
 * License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

require_once __DIR__ . '/class-logger.php';

/**
 * Like {@link log2logstash()} in WPCOM codebase.
 *
 * @since 2020-01-10
 *
 * @param array $data Log entry data.
 * @internal See {@link VIP_Logstash::log2logstash()} for details.
 */
function log2logstash( array $data ) : void {
	Logger::log2logstash( $data );
}

