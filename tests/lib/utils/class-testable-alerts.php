<?php
// phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod.Found -- "useless overrides" override method visibility

namespace Automattic\VIP\Utils;

require_once __DIR__ . '/../../../lib/utils/class-alerts.php';

class Testable_Alerts extends Alerts {
	public static $svc_address = 'test.host';
	public static $svc_port    = 9999;

	protected static function get_service_address() {
		return self::$svc_address;
	}

	protected static function get_service_port() {
		return self::$svc_port;
	}

	public static function clear_instance() {
		parent::clear_instance();
	}

	public function send( array $body ) {
		return parent::send( $body );
	}

	public function validate_channel_or_user( $channel_or_user ) {
		return parent::validate_channel_or_user( $channel_or_user );
	}

	public function validate_message( $message ) {
		return parent::validate_message( $message );
	}

	public function validate_opsgenie_details( $details ) {
		return parent::validate_opsgenie_details( $details );
	}
}
