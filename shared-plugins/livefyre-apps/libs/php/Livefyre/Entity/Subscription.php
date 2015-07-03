<?php
namespace Livefyre\Entity;

use Livefyre\Entity\SubscriptionType;

class Subscription {

	private $to;
	private $by;
	private $type;
	private $createdAt;

	public function __construct($to, $by, $type, $createdAt = null) {
		$this->to = $to;
		$this->by = $by;
		$this->type = $type;
		$this->createdAt = $createdAt;
	}

	public static function serializeFromJson($json) {
		return new self($json->{"to"}, $json->{"by"}, $json->{"type"}, $json->{"createdAt"});
	}

    public function serializeToJson() {
    	return array_filter(get_object_vars($this));
	}

	public function getTo() {
		return $this->to;
	}
	public function setTo($to) {
		$this->to = $to;
	}
	public function getBy() {
		return $this->by;
	}
	public function setBy($by) {
		$this->by = $by;
	}
	public function getType() {
		return $this->type;
	}
	public function setType($type) {
		$this->type = $type;
	}
	public function getCreatedAt() {
		return $this->createdAt;
	}
	public function setCreatedAt($createdAt) {
		$this->createdAt = $createdAt;
	}
}
