<?php
/*
  Copyright 2011 by ShoutEm, Inc. (www.shoutem.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

require_once "class-shoutem-ngg-dao.php";
require_once "class-shoutem-flagallery-dao.php";


class ShoutemPhotosDao extends ShoutemDao {

	public function __construct() {
		/*
		 * Combines state pattern with demultiplexing functionality.
		 * Multiplexed State
		 *
		 * There is a list of concrete photo providers (states), for example: NextGenGallery, Flash Gallery.
		 *
		 * Concrete state is selected based on category or post id. This id consists of a provider name and the id of the resource,
		 * @see get_provider_name_and_id.
		 *
		 * This class incorporates provider_name to the output of the provider @see add_provider_name_to_record,
		 * @see add_provider_name_to_recordset.
		 */
		$this->providers = array(
			'ngg' => new ShoutemNGGDao(),
			'flag' =>  new ShoutemFlaGalleryDao()
		);
	}

	public function get($params) {

		$provider_name_and_id = $this->get_provider_name_and_id($params['post_id']);

		//get provider
		$provider_name = $provider_name_and_id['provider_name'];
		$provider = $this->providers[$provider_name];

		if (!$provider->available()) {
			return false;
		}

		//create params for concrete provider
		$id =  $provider_name_and_id['id'];
		$new_params = $params; //value copy
		$new_params['post_id'] = $id;

		$record = $provider->get($new_params);
		$this->add_provider_name_to_record($record,$provider_name);

		return $record;
	}

	public function find($params) {

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

		if (isset($record['post_id'])) {
			$record['post_id'] = $provider_name.':'.$record['post_id'];
		}
		if (isset($record['category_id'])) {
			$record['category_id'] = $provider_name.':'.$record['category_id'];

		}
	}
}