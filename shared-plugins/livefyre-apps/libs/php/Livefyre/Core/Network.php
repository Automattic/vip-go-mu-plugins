<?php
namespace Livefyre\Core;

use Livefyre\Routing\Client;
use Livefyre\Utils\JWT;
use Livefyre\Api\Domain;

class Network {
	const DEFAULT_USER = "system";
	const DEFAULT_EXPIRES = 86400;

	private $_name;
	private $_key;
	private $_networkName;

	public function __construct($name, $key) {
		$this->_name = $name;
		$this->_key = $key;
		$this->_ssl = true;
		$n = explode(".", $name);
		$this->_networkName = $n[0];
	}

	public function setUserSyncUrl($urlTemplate) {
		if (strpos($urlTemplate, "{id}") === false) {
			throw new \InvalidArgumentException("urlTemplate should contain {id}");
		}

		$url = sprintf("%s", Domain::quill($this));
		$data = array("actor_token" => $this->buildLivefyreToken(), "pull_profile_url" => $urlTemplate);
		$response = Client::POST($url, array(), $data);
		
		return $response->status_code == 204;
	}

	public function syncUser($userId) {
		$data = array("lftoken" => $this->buildLivefyreToken());
		$url = sprintf("%s/api/v3_0/user/%s/refresh", Domain::quill($this), $userId);

		$response = Client::POST($url, array(), $data);
		
		return $response->status_code == 200;
	}

	public function buildLivefyreToken() {
		return $this->buildUserAuthToken(self::DEFAULT_USER, self::DEFAULT_USER, self::DEFAULT_EXPIRES);
	}

	public function buildUserAuthToken($userId, $displayName, $expires) {
		if (!ctype_alnum($userId)) {
			throw new \InvalidArgumentException("userId must be alphanumeric");
		}

		$token = array(
		    "domain" => $this->_name,
		    "user_id" => $userId,
		    "display_name" => $displayName,
		    "expires" => time() + (int)$expires
		);

		return JWT::encode($token, $this->_key);
	}

	public function validateLivefyreToken($lfToken) {
		$tokenAttributes = JWT::decode($lfToken, $this->_key);

		return $tokenAttributes->domain == $this->_name
			&& $tokenAttributes->user_id == self::DEFAULT_USER
			&& $tokenAttributes->expires >= time();
	}

	public function getSite($siteId, $siteKey) {
		return new Site($this, $siteId, $siteKey);
	}

	/* Getters */
	public function getUrn() {
		return "urn:livefyre:" . $this->_name;
	}
	public function getUserUrn($user) {
        return $this->getUrn().":user=".$user;
    }
	public function getNetworkName() {
		return $this->_networkName;
	}
    public function getName() {
    	return $this->_name;
    }
    public function getKey() {
    	return $this->_key;
    }
    public function isSsl() {
    	return $this->_ssl;
    }
    public function setSsl($ssl) {
    	$this->_ssl = $ssl;
    }
}
