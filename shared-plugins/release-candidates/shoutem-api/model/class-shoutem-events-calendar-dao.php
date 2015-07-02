<?php
/**
 * This class is designed to work only with Events Calendar Wordpress plugin.
 */
class ShoutemEventsCalendarDao extends ShoutemDao {

	public function available() {
		return class_exists('EC_DB');
	}

	public function categories() {
		return array('data' => array(
			array(
				'category_id' => 'ec',
				'name' => 'events',
				'allowed' => true
			)
			),
			'paging' => array(
			)
		);
	}

	/**
	 * get event.
	 * Required params event_id
	 */
	public function get($params) {
		if (!class_exists('EC_DB')) {
			return false;
		}
		$db = new EC_DB();

		$event = $db->getEvent($params['event_id']);

		if ($event && is_array($event) && count($event) > 0) {
			return $this->convert_to_se_event($event[0]);
		}

		return false;
	}

	public function find($params) {
		if (!class_exists('EC_DB')) {
			return false;
		}
		$db = new EC_DB();

		$events = $db->getUpcomingEvents((int)$params['offset'] + (int)$params['limit'] + 1);
		$events = array_slice($events, $params['offset']);

		$results = array();
		foreach($events as $event) {
			$results []= $this->convert_to_se_event($event);
		}

		return $this->add_paging_info($results, $params);
	}

	private function get_event_time($date, $time) {
		$splitDate = explode("-", $date);
		$month = $splitDate[1];
		$day = $splitDate[2];
		$year = $splitDate[0];

		$split_time = explode(":", $time);

		if (count($split_time) > 1) {
			$hour = $split_time[0];
			$minute = $split_time[1];
			return date(DATE_RSS, mktime($hour, $minute, 0, $month, $day, $year));
		} else {
			return date(DATE_RSS, mktime(0, 0, 0, $month, $day, $year));
		}
	}

	/**
	 * Converts from events calendar event to event as defined by
	 * ShoutEm Data Exchange Protocol: @link http://fiveminutes.jira.com/wiki/display/SE/Data+Exchange+Protocol
	 */
	private function convert_to_se_event($event) {

		$remaped_event = array(
			'event_id' => $event->id,
			'start_time' => $this->get_event_time($event->eventStartDate, $event->eventStartTime),
			'end_time' => $this->get_event_time($event->eventEndDate, $event->eventEndTime),
			'name' => $event->eventTitle,
			'description' => $event->eventDescription,
		);


		$venue = array(
			'name' => $event->eventLocation
		);

		$remaped_event['venue'] = $venue;

		return $remaped_event;
	}
}