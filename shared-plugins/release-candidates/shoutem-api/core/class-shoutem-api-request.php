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
class ShoutemApiRequest {
	function ShoutemApiRequest($dao_factory) {
		$this->dao_factory = $dao_factory;
		// Fetching all the params, it can be GET/POST request depends on API client app
		// For that reason we use $_REQUEST
		$this->params = $_REQUEST; 
		$this->credentials = null;
	}

	function get_validated_user($params) {
		if(!isset($params['session_id'])) {
			return false;
		}
		$users_dao = $this->dao_factory->get_users_dao();
		return $users_dao->get_validated_user_from_session_id($params['session_id']);
	}

	function filter_params($accepted_params) {
		$filtered_params = array();
		foreach($accepted_params as $accepted_param) {
			if (array_key_exists($accepted_param,$this->params)) {
				$filtered_params[$accepted_param] = $this->params[$accepted_param];
			}
		}
		$this->params = $filtered_params;
	}

	function use_default_params($default_params) {
		$this->params += $default_params;
	}
}
