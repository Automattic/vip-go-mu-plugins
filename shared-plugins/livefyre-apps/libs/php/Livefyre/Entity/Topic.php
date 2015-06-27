<?php
namespace Livefyre\Entity;

class Topic {

    const TOPIC_IDEN = ":topic=";
	private $id;
	private $label;
	private $createdAt;
	private $modifiedAt;

	public function __construct($id, $label, $createdAt = null, $modifiedAt = null) {
		$this->id = $id;
		$this->label = $label;
		$this->createdAt = $createdAt;
		$this->modifiedAt = $modifiedAt;
	}

	/* new instances should use this method */
	public static function create($core, $id, $label) {
		return new self(self::generateUrn($core, $id), $label);
	}

    public static function generateUrn($core, $id) {
        return $core->getUrn() . self::TOPIC_IDEN . $id;
    }

	public static function serializeFromJson($json) {
		return new self($json->{"id"}, $json->{"label"}, $json->{"createdAt"}, $json->{"modifiedAt"});
	}
    public function serializeToJson() {
    	return array_filter(get_object_vars($this));
	}

    public function getTruncatedId() {
    	$id = $this->id;
    	return substr($id, strrpos($id, "=") + strlen(self::TOPIC_IDEN));
    }
    public function getCreatedAtDate() {
    	return date('r', $this->createdAt);
    }
	public function getModifiedAtDate() {
    	return date('r', $this->modifiedAt);
    }

	public function getId() {
		return $this->id;
	}
	public function setId($id) {
		$this->id = $id;
	}
	public function getLabel() {
		return $this->label;
	}
	public function setLabel($label) {
		$this->label = $label;
	}
	public function getCreatedAt() {
		return $this->createdAt;
	}
	public function setCreatedAt($createdAt) {
		$this->createdAt = $createdAt;
	}
	public function getModifiedAt() {
		return $this->modifiedAt;
	}
	public function setModifiedAt($modifiedAt) {
		$this->modifiedAt = $modifiedAt;
	}
}
