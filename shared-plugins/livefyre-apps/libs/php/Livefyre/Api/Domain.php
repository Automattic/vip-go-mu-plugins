<?php
namespace Livefyre\Api;

class Domain {
	const BASE_URL = "https://%s.quill.fyre.co/api/v4/";
	const STREAM_URL = "https://bootstrap.livefyre.com/api/v4/";

	/* Topic API */
	public static function quill($core) {
		if (method_exists($core, "isSsl")) {
			$ssl = $core->isSsl();
		} else {
			$ssl = $core->getNetwork()->isSsl();
		}
		return $ssl ? sprintf("https://%s.quill.fyre.co", $core->getNetworkName()) : sprintf("http://quill.%s.fyre.co", $core->getNetworkName());
	}

	public static function bootstrap($core) {
		if (method_exists($core, "isSsl")) {
			$ssl = $core->isSsl();
		} else {
			$ssl = $core->getNetwork()->isSsl();
		}
		return $ssl ? "https://bootstrap.livefyre.com" : sprintf("http://bootstrap.%s.fyre.co", $core->getNetworkName());
	}
}
