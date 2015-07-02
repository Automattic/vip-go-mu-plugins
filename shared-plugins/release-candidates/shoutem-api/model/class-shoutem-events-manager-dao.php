<?php
/**
 * This class is designed to work only with Events Manager Wordpress plugin.
 */
 define('ALL_CATEGORY_ID', -1);
class ShoutemEventsManagerDao extends ShoutemDao {

	public static function available() {
		return class_exists('EM_Categories');
	}

	public function categories($params) {

		$results = array();
		$results []= array(
			'name' => 'All',
			'category_id' => ALL_CATEGORY_ID,
			'allowed' => true
		);

		$categories = EM_Categories::get(array(
			'offset' => $params['offset'],
			'limit' => $params['limit']
		));
		foreach ($categories as $category) {
			$results []= array(
				'name' => $category->name,
				'category_id' => $category->id,
				'allowed' => true
			);
		}
		return $this->add_paging_info($results, $params);
	}

	/**
	 * get event.
	 * Required params event_id
	 */
	public function get($params) {
		$post_id = 0;
		if (array_key_exists('post_id', $params)) {
			$post_id = $params['post_id'];
		} else if (array_key_exists('event_id', $params)) {
			$post_id = $params['event_id'];
		} else {
			$post_id = $params['id'];
		}
		$event = EM_Events::get(array($post_id));
		$remaped_event = "";
		if (is_array($event) && array_key_exists($post_id, $event)) {
			$event = $event[$post_id];
		}
		return $this->convert_to_se_event($event);
	}

	public function filter_events($events, $params) {
		$filtered_events = array();
		$categoryId = $params['category_id'];
		foreach ($events as $event) {
			//filter by category only if not in category all
			if (strcmp(''.$categoryId, ''.ALL_CATEGORY_ID) !== 0) {
				$categories = new EM_Categories($event);
				if (is_object($categories) &&
					!array_key_exists($categoryId, $categories->categories)) {
					continue;
				}
			}
			$filtered_events []= $event;
		}
		return $filtered_events;
	}

	public function find($params) {
		$events = EM_Events::get(array(
			'limit' => 0,
			'offset' => 100
		));
		$events = self::filter_events($events, $params);
		$events = array_slice($events, $params['offset']);

		$remaped_events = array();
		foreach($events as $event) {
			$remaped_events[] = $this->convert_to_se_event($event);
		}
		return $paged_posts = $this->add_paging_info($remaped_events,$params);
	}

	private function convert_old_em_event_to_se_event($event) {
		$remaped_event = array(
			'post_id' => $event->id,
			'start_time' => $event->start_date.' '.$event->start_time,
			'end_time' => $event->end_date.' '.$event->end_time,
			'name' => $event->name,
			'description' => $event->location->description,
			'image_url' => $event->location->image_url
		);
		$user_id = $event->location->owner;

		if ($user_id > 0) {
			$user = get_userdata($user_id);
			$remaped_event['owner'] = array(
					'id' => $user_id,
					'name' => $user->user_nicename
					);
		}

		$venue = array();
		$location = $event->location;

		if (is_object($location)) {
			$venue = array (
				'id' => '',
				'name' => $location->name,
				'street' => $location->address,
				'city' =>  $location->town,
				'latitude' => $location->latitude,
				'longitude' => $location->longitude,
			);
		}
		$remaped_event['place'] = $venue;

		return $remaped_event;

	}

	/**
	 * Convert from Events Manager event to a event format defined
	 * by ShoutEm Data Exchange Protocol @link http://fiveminutes.jira.com/wiki/display/SE/Data+Exchange+Protocol
	 */
	private function convert_to_se_event($event) {
		$new_em_plugin = property_exists($event, 'event_id');
		if (!$new_em_plugin) {
			$remaped_event = self::convert_old_em_event_to_se_event($event);
		} else {

			//new event manager
			$remaped_event = array(
				'post_id' => $event->event_id,
				'start_time' => $event->event_start_date.' '.$event->event_start_time,
				'end_time' => $event->event_end_date.' '.$event->event_end_time,
				'name' => $event->name,
				'description' => $event->post_content,
				'image_url' => $event->image_url
			);
			$user_id = $event->event_owner;

			if ($user_id > 0) {
				$user = get_userdata($user_id);
				$remaped_event['owner'] = array(
						'id' => $user_id,
						'name' => $user->user_nicename
						);
			}

			$venue = array();
			$location = EM_Locations::get(array($event->location_id));
			if (is_array($location) && count($location) > 0) {
				$location = $location[$event->location_id];
				$venue = array (
					'id' => '',
					'name' => $location->location_name,
					'street' => $location->location_address,
					'city' =>  $location->location_town,
					'state' => $location->location_state,
					'country' => $location->location_country,
					'latitude' => $location->location_latitude,
					'longitude' => $location->location_longitude,
				);
			}
			$remaped_event['place'] = $venue;
		}
		$striped_attachments = array();
		$remaped_event['description'] = sanitize_html($remaped_event['description'],$striped_attachments);
		if (property_exists($event, 'post_id')) {
			$this->include_leading_image_in_attachments($striped_attachments, $event->post_id);
		}
		$remaped_event['body'] = $remaped_event['description'];
		$remaped_event['summary'] = html_to_text($remaped_event['description']);
		$remaped_event['attachments'] = $striped_attachments;
		return $remaped_event;
	}
}
