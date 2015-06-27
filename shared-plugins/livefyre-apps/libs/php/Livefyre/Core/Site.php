<?php
namespace Livefyre\Core;

use Livefyre\Utils\JWT;
use Livefyre\Utils\IDNA;
use Livefyre\Routing\Client;
use Livefyre\Api\Domain;

class Site {
	private $_network;
	private $_id;
	private $_key;
	private $_IDNA;

	private static $TYPE = array(
		"reviews", "sidenotes", "ratings", "counting", "liveblog", "livechat", "livecomments");

	public function __construct($network, $id, $key) {
		$this->_network = $network;
		$this->_id = $id;
		$this->_key = $key;
		$this->_IDNA = new IDNA(array('idn_version' => 2008));
	}

	public function buildCollectionMetaToken($title, $articleId, $url, $options = array()) {
		if (filter_var($this->_IDNA->encode($url), FILTER_VALIDATE_URL) === false) {
			throw new \InvalidArgumentException("provided url is not a valid url");
		}
		if (strlen($title) > 255) {
			throw new \InvalidArgumentException("title length should be under 255 char");
		}

		$collectionMeta = array(
		    "url" => $url,
		    "title" => $title,
		    "articleId" => $articleId
		);

		if (array_key_exists("type", $options) AND !in_array($options["type"], self::$TYPE)) {
			throw new \InvalidArgumentException("type is not a recognized type. must be in " . implode(",", self::$TYPE));
		}

		return JWT::encode(array_merge($collectionMeta, $options), $this->_key);
	}

	public function buildChecksum($title, $url, $tags = "", $type) {
		if (filter_var($this->_IDNA->encode($url), FILTER_VALIDATE_URL) === false) {
			throw new \InvalidArgumentException("provided url is not a valid url");
		}
		if (strlen($title) > 255) {
			throw new \InvalidArgumentException("title length should be under 255 char");
		}

		$checksum = array("tags" => $tags, "title" => $title, "url" => $url, "type" => $type);
		return md5(str_replace('\/','/',json_encode($checksum)));
	}

	public function createCollection($title, $articleId, $url, $options = array()) {
		$token = $this->buildCollectionMetaToken($title, $articleId, $url, $options);
		$checksum = $this->buildChecksum($title, $url, array_key_exists("tags", $options) ? $options["tags"] : "");
		$uri = sprintf("%s/api/v3.0/site/%s/collection/create/", Domain::quill($this), $this->_id) . "?sync=1";
		$data = json_encode(array("articleId" => $articleId, "collectionMeta" => $token, "checksum" => $checksum));
		$headers = array("Content-Type" => "application/json", "Accepts" => "application/json");

		$response = Client::POST($uri, $headers, $data);
		if ($response->status_code === 200) {
			return json_decode($response->body)->{"data"}->{"collectionId"};
		}
		return NULL;
	}

	public function getCollectionContent($articleId) {
		$url = sprintf("%s/bs3/%s/%s/%s/init", Domain::bootstrap($this), $this->_network->getName(), $this->_id, base64_encode($articleId));
		$response = Client::GET($url);

		return json_decode($response->body);
	}

	public function getCollectionId($articleId) {
		$content = $this->getCollectionContent($articleId);
		return $content->{"collectionSettings"}->{"collectionId"};
	}

	/* Getters */
	public function getUrn() {
		return $this->_network->getUrn() . ":site=" . $this->_id;
	}
	public function getNetworkName() {
		return $this->_network->getNetworkName();
	}
	public function buildLivefyreToken() {
		return $this->_network->buildLivefyreToken();
	}
	public function getNetwork() {
		return $this->_network;
	}
	public function getId() {
		return $this->_id;
	}
	public function getKey() {
		return $this->_key;
	}
}
