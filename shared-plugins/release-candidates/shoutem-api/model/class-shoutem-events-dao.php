<?php
 require_once "class-shoutem-events-calendar-dao.php";
require_once "class-shoutem-events-manager-dao.php";

 /**
  * Multiplexed State pattern. See this->providers for a list of states.
  */
class ShoutemEventsDao extends ShoutemDao {

	public function __construct() {
		//list of supported providers
		$this->providers = array(
			'em' => new ShoutemEventsManagerDao(),
			'ec' =>  new ShoutemEventsCalendarDao()
		);
	}


	public function get($params) {
		//splits the id parameter to get

		if (isset($params['post_id'])) {
			$id = $params['post_id'];
		} else {
			$id = $params['event_id'];
		}

		$provider_name_and_id = $this->get_provider_name_and_id($id);
		//get provider
		$provider_name = $provider_name_and_id['provider_name'];
		$provider = $this->providers[$provider_name];

		if (!$provider->available()) {
			return false;
		}

		//create params for concrete provider
		$id =  $provider_name_and_id['id'];
		$new_params = $params; //value copy
		$new_params['event_id'] = $new_params['post_id'] = $new_params['id'] =  $id;

		$record = $provider->get($new_params);
		$this->add_provider_name_to_record($record,$provider_name);

		return $record;
	}

	public function find($params) {

		//splits the id parameter to get
		$provider_name_and_id = $this->get_provider_name_and_id($params['category_id']);

		//get provider
		$provider_name = $provider_name_and_id['provider_name'];
		$provider = $this->providers[$provider_name];
		if (!$provider->available()) {
			return false;
		}
		//create params for concrete provider
		$id =  $provider_name_and_id['id'];

		$new_params = $params; //value copy
		$new_params['category_id'] = $id;

		$recordset = $provider->find($new_params);
		$this->add_provider_name_to_recordset($recordset['data'],$provider_name);

		return $recordset;

	}

	public function categories($params) {
		$new_params = array(
			'offset' => 0,
			'limit' => 100
		);
		$results = array();
		//concatinate the categories
		foreach($this->providers as $provider_name => $provider) {
			if (!$provider->available()) {
				continue;
			}

			$result = $provider->categories($new_params);
			$this->add_provider_name_to_recordset($result['data'],$provider_name);
			$results = array_merge($results, $result['data']);

		}

		$results = array_slice($results, $params['offset']);

		return $this->add_paging_info($results, $params);
	}

	/**
	 * get the provider name and the real id from parameter
	 */
	private function get_provider_name_and_id($param) {
		$param_parts = explode(':',$param,2);
		return array(
			'provider_name' => $param_parts[0],
			'id' => $param_parts[1]
			);
	}

	/**
	 * Converts id field value to provider_name:id_field_value
	 */
	private function add_provider_name_to_recordset(&$recordset,$provider_name) {
		foreach($recordset as &$record) {
			$this->add_provider_name_to_record($record, $provider_name);
		}
	}

	/**
	 * Converts id field values to provider_name:id_field_value
	 */
	private function add_provider_name_to_record(&$record,$provider_name) {

		if (isset($record['event_id'])) {
			$record['id'] = $record['event_id'] = $record['post_id'] = $provider_name.':'.$record['event_id'];
		} else if (isset($record['post_id'])) {
			$record['id'] = $record['event_id'] = $record['post_id'] = $provider_name.':'.$record['post_id'];
		}
		if (isset($record['id'])) {
			$record['id'] = $provider_name.':'.$record['id'];
		}
		if (isset($record['category_id'])) {
			$record['category_id'] = $provider_name.':'.$record['category_id'];
		}
	}

}
