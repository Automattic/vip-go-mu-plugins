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
class ShoutemController {

	public function ShoutemController($shoutem_api, $request, $response, $dao_factory, $authentication, $caching) {
		$this->shoutem_api = $shoutem_api;
		$this->request = $request;
		$this->response = $response;
		$this->dao_factory = $dao_factory;
		$this->authentication = $authentication;
		$this->caching = $caching;
		$this->view = new ShoutemControllerView($request, $response);
	}

	protected function default_paging_params() {
		return array (
			'offset' => 0,
			'limit' => 100,
			'version' => 1
		);
	}

	public function accept_standard_params_and() {
		$params = array('session_id','offset','limit','method'); //standard params
		foreach(func_get_args() as $accepted_param) {
			$params []= $accepted_param;
		}
		return $this->request->filter_params($params);
	}

	// validating stuff

	public function validate_required_plugins() {
		foreach(func_get_args() as $required_plugin) {
			if (!$this->is_plugin_active($required_plugin)) {
				$this->response->send_error(400, "Missing required plugin");
			}
		}
	}

	public function validate_required_params() {
		foreach(func_get_args() as $required_param) {
			if (!isset($this->request->params[$required_param])) {
				$this->response->send_error(400, "Missing required parameter $required_param");
			}
		}
	}

	public function validate_request_credentials() {
		// Authenticating user seesion from session_id param, current request can be GET/POST depends on API client app
		// For that reason we use $_REQUEST
		if(isset($_REQUEST['session_id'])) {
            $session_id = trim($_REQUEST['session_id']);
            // check if we have a Base64 string
            if (!preg_match('{^(?:[a-z0-9+/]{4})*(?:[a-z0-9+/]{2}==|[a-z0-9+/]{3}=)?$}i', $session_id)) {
                $session_id = '';
            }
			$credentials = $this->authentication->get_credentials($session_id);

			if($credentials == false || $credentials->is_valid() == false) {
				$this->response->send_error(401, "Invalid user credentials");
			} else {
				$this->request->credentials = $credentials;
				$this->request->params['wp_user'] = $credentials->get_user();
			}
		}
	}

	private function is_plugin_active($plugin) {
		return in_array( $plugin, apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) );
	}
	/**
	 * Template method to be called before automatically each method
	 */
	public function doBefore() {

	}

	// callbacks

	public function before() {
		// $this->validate_request_credentials();
		$this->doBefore();
	}

	public function after() {
	}

}

?>