<?php
namespace Livefyre\Routing;

use Requests;

class Client {
	public static function GET($url, $headers = array()) {
		if (function_exists("wp_remote_get")) {
			return wp_remote_get($url, array("headers" => $headers));
		}
		return Requests::get($url, $headers);
	}

	public static function POST($url, $headers = array(), $data = array()) {
		if (function_exists("wp_remote_post")) {
			return wp_remote_post($url, array("headers"=>$headers, "body"=>$data));
		}
		return Requests::post($url, $headers, $data);
	}

	public static function PUT($url, $headers = array(), $data = array()) {
		if (function_exists("wp_remote_request")) {
			return wp_remote_request($url, array("headers"=>$headers, "body"=>$data));
		}
		return Requests::put($url, $headers, $data);
	}

	public static function DELETE($url, $headers = array(), $data = array()) {
		if (function_exists("wp_remote_request")) {
			return wp_remote_request($url, array("headers"=>$headers, "body"=>$data));
		}
		return Requests::delete($url, $headers, $data);
	}

	public static function PATCH($url, $headers = array(), $data = array()) {
		if (function_exists("wp_remote_request")) {
			return wp_remote_request($url, array("headers"=>$headers, "body"=>$data));
		}
		return Requests::patch($url, $headers, $data);
	}
}