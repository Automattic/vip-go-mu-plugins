<?php
namespace Livefyre\Entity;

use Livefyre\Api\PersonalizedStream;

class TimelineCursor {

	const DATE_FORMAT = "Y-m-d\TH:i:s.z\Z";

	private $_core;
	private $_resource;
	private $_cursorTime;
	private $_next = FALSE;
	private $_previous = FALSE;
	private $_limit;

	public function __construct($core, $resource, $limit, $startTime) {
		$this->_core = $core;
		$this->_resource = $resource;
		$this->_limit = $limit;
		$this->_cursorTime = gmdate(self::DATE_FORMAT, $startTime);
	}

	public function next($limit = null) {
		$limit = (is_null($limit)) ? $this->_limit : $limit;

		$data = PersonalizedStream::getTimelineStream($this->_core, $this->_resource, $limit, null, $this->_cursorTime);
		$cursor = $data->{"meta"}->{"cursor"};
		
		$this->_next = $cursor->{"hasNext"};
		$this->_previous = $cursor->{"next"} !== null;
		$this->_cursorTime = $cursor->{"next"};

		return $data;
	}

	public function previous($limit = null) {
		$limit = (is_null($limit)) ? $this->_limit : $limit;

		$data = PersonalizedStream::getTimelineStream($this->_core, $this->_resource, $limit, $this->_cursorTime, null);
		$cursor = $data->{"meta"}->{"cursor"};
		
		$this->_previous = $cursor->{"hasPrev"};
		$this->_next = $cursor->{"prev"} !== null;
		$this->_cursorTime = $cursor->{"prev"};

		return $data;
	}

	public function getResource() {
		return $this->_resource;
	}
	// returns whichever format is stored - can be either UUID or time.
	public function getCursorTime() {
		return $this->_cursorTime;
	}
	public function setCursorTime($newTime) {
		$this->_cursorTime = gmdate(self::DATE_FORMAT, $newTime);
	}
	public function hasPrevious() {
		return $this->_previous;
	}
	public function hasNext() {
		return $this->_next;
	}
	public function getLimit() {
		return $this->_limit;
	}
	public function setLimit($limit) {
		$this->_limit = $limit;
	}
}