<?php
namespace Livefyre\Api;

use Livefyre\Core\Network;
use Livefyre\Routing\Client;
use Livefyre\Entity\Topic;
use Livefyre\Entity\Subscription;
use Livefyre\Entity\SubscriptionType;
use Livefyre\Utils\JWT;
use Livefyre\Api\Domain;

class PersonalizedStream {

	const BASE_URL = "%s/api/v4/";
	const STREAM_URL = "%s/api/v4/";

	const NETWORK_TOPICS_URL_PATH = ":topics/";
	const COLLECTION_TOPICS_URL_PATH = ":collection=%s:topics/";
	const SUBSCRIPTION_URL_PATH = ":subscriptions/";
	const SUBSCRIBER_URL_PATH = ":subscribers/";
	const TIMELINE_PATH = "timeline/";

	/* Topic API */
	public static function getTopic($core, $id) {
		$url = self::getUrl($core);
		$url = $url . Topic::generateUrn($core, $id);

		$response = Client::GET($url, self::getHeaders($core));
		
		$body = self::getData($response);
		if (!property_exists($body, "topic")) {
			return null;
		}

		return Topic::serializeFromJson($body->{"topic"});
	}

	public static function createOrUpdateTopic($core, $id, $label) {
		return self::createOrUpdateTopics($core, array($id => $label))[0];
	}

	public static function deleteTopic($core, $topic) {
		return self::deleteTopics($core, array($topic)) == 1;
	}

	/* Multiple Topic API */
	public static function getTopics($core, $limit = 100, $offset = 0) {
		$url = self::getTopicsUrl($core) . "?limit=" . $limit . "&offset=" . $offset; 

		$response = Client::GET($url, self::getHeaders($core));
		
		$body = self::getData($response);
		if (!property_exists($body, "topics")) {
			return null;
		}

		$topics = array();
		foreach ($body->{"topics"} as &$topic) {
			$topics[] = Topic::serializeFromJson($topic);
		}

		return $topics;
	}

	public static function createOrUpdateTopics($core, $topicMap) {
		$topics = array();
		$json = array();
		foreach ($topicMap as $id => $label) {
			if (empty($label) || strlen($label) > 128) {
				throw new \InvalidArgumentException("topic label should be 128 char or under and not empty");
			}

		    $topic = Topic::create($core, $id, $label);
			$topics[] = $topic;
			$json[] = $topic->serializeToJson();
		}

		$data = json_encode(array("topics" => $json));
		$url = self::getTopicsUrl($core);

		$response = Client::POST($url, self::getHeaders($core), $data);
		return $topics;
	}

	public static function deleteTopics($core, $topics) {
		$data = json_encode(array("delete" => self::getTopicIds($topics)));
		$url =  self::getTopicsUrl($core);

		$response = Client::PATCH($url, self::getHeaders($core), $data);

		$body = self::getData($response);
		if (!property_exists($body, "deleted")) {
			return 0;
		}

		return $body->{"deleted"};
	}

	/* Collection Topic API */
	public static function getCollectionTopics($site, $collectionId) {
		$url = self::getCollectionTopicsUrl($site, $collectionId);

		$response = Client::GET($url, self::getHeaders($site));

		$body = self::getData($response);
		if (!property_exists($body, "topicIds")) {
			return null;
		}

		return $body->{"topicIds"};
	}

	public static function addCollectionTopics($site, $collectionId, $topics) {
		$data = json_encode(array("topicIds" => self::getTopicIds($topics)));
		$url = self::getCollectionTopicsUrl($site, $collectionId);

		$response = Client::POST($url, self::getHeaders($site), $data);

		$body = self::getData($response);
		if (!property_exists($body, "added")) {
			return 0;
		}

		return $body->{"added"};
	}

	public static function replaceCollectionTopics($site, $collectionId, $topics) {
		$data = json_encode(array("topicIds" => self::getTopicIds($topics)));
		$url = self::getCollectionTopicsUrl($site, $collectionId);

		$response = Client::PUT($url, self::getHeaders($site), $data);

		$body = self::getData($response);
		return (!((property_exists($body, "added") && $body->{"added"} > 0)
			|| (property_exists($body, "removed") && $body->{"removed"} > 0)));
	}

	public static function removeCollectionTopics($site, $collectionId, $topics) {
		$data = json_encode(array("delete" => self::getTopicIds($topics)));
		$url = self::getCollectionTopicsUrl($site, $collectionId);

		$response = Client::PATCH($url, self::getHeaders($site), $data);
		
		$body = self::getData($response);
		if (!property_exists($body, "removed")) {
			return 0;
		}

		return $body->{"removed"};
	}

	/* UserSubscription API */
	public static function getSubscriptions($network, $userId) {
		$url = self::getSubscriptionUrl($network, $network->getUserUrn($userId));

		$response = Client::GET($url, self::getHeaders($network));

		$body = self::getData($response);
		if (!property_exists($body, "subscriptions")) {
			return null;
		}

		$subscriptions = array();
		foreach ($body->{"subscriptions"} as &$sub) {
			$subscriptions[] = Subscription::serializeFromJson($sub);
		}

		return $subscriptions;
	}

	public static function addSubscriptions($network, $userToken, $topics) {
		$userId = JWT::decode($userToken, $network->getKey())->user_id;
		$userUrn = $network->getUserUrn($userId);
		$data = json_encode(array("subscriptions" => self::buildSubscriptions($topics, $userUrn)));
		$url = self::getSubscriptionUrl($network, $userUrn);

		$response = Client::POST($url, self::getHeaders($network, $userToken), $data);

		$body = self::getData($response);
		if (!property_exists($body, "added")) {
			return 0;
		}

		return $body->{"added"};
	}

	public static function replaceSubscriptions($network, $userToken, $topics) {
		$userId = JWT::decode($userToken, $network->getKey())->user_id;
		$userUrn = $network->getUserUrn($userId);
		$data = json_encode(array("subscriptions" => self::buildSubscriptions($topics, $userUrn)));
		$url = self::getSubscriptionUrl($network, $userUrn);

		$response = Client::PUT($url, self::getHeaders($network, $userToken), $data);

		$body = self::getData($response);
		return (!((property_exists($body, "added") && $body->{"added"} > 0)
			|| (property_exists($body, "removed") && $body->{"removed"} > 0)));
	}

	public static function removeSubscriptions($network, $userToken, $topics) {
		$userId = JWT::decode($userToken, $network->getKey())->user_id;
		$userUrn = $network->getUserUrn($userId);
		$data = json_encode(array("delete" => self::buildSubscriptions($topics, $userUrn)));
		$url = self::getSubscriptionUrl($network, $userUrn);

		$response = Client::PATCH($url, self::getHeaders($network, $userToken), $data);
		
		$body = self::getData($response);
		if (!property_exists($body, "removed")) {
			return 0;
		}

		return self::getData($response)->{"removed"};
	}

	public static function getSubscribers($network, $topic, $limit = 100, $offset = 0) {
		$url = self::getUrl($network) . $topic->getId() . self::SUBSCRIBER_URL_PATH  . "?limit=" . $limit . "&offset=" . $offset; ;

		$response = Client::GET($url, self::getHeaders($network));
		
		$body = self::getData($response);
		if (!property_exists($body, "subscriptions")) {
			return null;
		}

		$subscriptions = array();
		foreach ($body->{"subscriptions"} as &$sub) {
			$subscriptions[] = Subscription::serializeFromJson($sub);
		}

		return $subscriptions;
	}

	public static function getTimelineStream($core, $resource, $limit = 50, $until = null, $since = null) {
		$url = self::getTimelineUrl($core) . "?resource=" . $resource . "&limit=" . $limit;

		if (isset($until)) {
			$url .= "&until=" . $until;
		} elseif (isset($since)) {
			$url .= "&since=" . $since;
		}

		$response = Client::GET($url, self::getHeaders($core));

		return json_decode($response->body);
	}

	/* Helper Methods */
	private static function getHeaders($core, $userToken = null) {
		$token = ($userToken === null) ? $core->buildLivefyreToken() : $userToken;
		return array(
			"Authorization" => "lftoken " . $token,
			"Content-Type" => "application/json"
		);
	}

	private static function getUrl($core) {
		return sprintf(self::BASE_URL, Domain::quill($core));
	}

	private static function getTopicsUrl($core) {
		return self::getUrl($core) . $core->getUrn() . self::NETWORK_TOPICS_URL_PATH;
	}

	private static function getCollectionTopicsUrl($site, $collectionId) {
		return self::getUrl($site) . $site->getUrn() . sprintf(self::COLLECTION_TOPICS_URL_PATH, $collectionId);
	}

	private static function getSubscriptionUrl($network, $userUrn) {
		return self::getUrl($network) . $userUrn . self::SUBSCRIPTION_URL_PATH;
	}

	private static function getTimelineUrl($core) {
		return sprintf(self::STREAM_URL, Domain::bootstrap($core)) . self::TIMELINE_PATH;
	}

	private static function getTopicIds($topics) {
		$topicIds = array();
		foreach ($topics as &$topic) {
			$topicIds[] = $topic->getId();
		}
		return $topicIds;
	}

	private static function buildSubscriptions($topics, $userUrn) {
		$subscriptions = array();
		foreach($topics as &$topic) {
			$subscriptions[] = (new Subscription($topic->getId(), $userUrn, SubscriptionType::personalStream))->serializeToJson();
		}
		return $subscriptions;
	}

	private static function getData($response) {
		return json_decode($response->body)->{"data"};
	}
}
