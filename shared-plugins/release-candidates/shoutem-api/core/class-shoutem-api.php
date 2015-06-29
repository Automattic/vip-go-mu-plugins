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
define ("SHOUTEM_API_SUMMARY_LENGTH", 50);

class ShoutemApi {

	function __construct($base_dir,$shoutem_plugin_file) {
		$this->base_dir = $base_dir;
		$this->api_version = "unknown";

		if (!function_exists('get_plugin_data')) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}
		if (function_exists('get_plugin_data')) {
			$plugin_data = get_plugin_data($shoutem_plugin_file);
			$this->api_version = $plugin_data['Version'];
		}

	}

	function init() {
		add_action('template_redirect', array($this,'template_redirect'), -9999);

		$this->shoutem_options = new ShoutemApiOptions($this);
		$options = $this->shoutem_options->get_options();

		$this->shoutem_options->add_listener(array($this,'options_changed'));
		$this->authentication = new ShoutemApiAuthentication($options['encryption_key']);


		$this->dao_factory = ShoutemStandardDaoFactory::instance();

		$this->request = new ShoutemApiRequest($this->dao_factory);
		$this->response = new ShoutemApiResponse($this->base_dir);
		$this->caching = new ShoutemApiCaching($this->shoutem_options);

	}

	function options_changed() {
		$options = $this->shoutem_options->get_options();
		$this->authentication = new ShoutemApiAuthentication($options['encryption_key']);
	}

	function template_redirect() {
		//shoutem api uri is someserver/..../?shoutemapi&method=method_uri&[params]
		// if 'shoutemapi' key is presentin GET/POST request then it will start api execution 
		// API client app can use GET/POST method that's why we used $_REQUEST  
		$is_shoutem_api_call = isset($_REQUEST['shoutemapi']);
		if($is_shoutem_api_call) {
			$this->dispatch();
			exit;
		}
	}

	function controller_path($exploded_class_uri) {
		$imploded_class_uri = implode('-',$exploded_class_uri);
		return "$this->base_dir/controllers/class-shoutem-$imploded_class_uri-controller.php";
	}

	function controller_class($exploded_class_uri) {
		$class_name = '';
		foreach ($exploded_class_uri as $class_name_part) {
			$class_name = $class_name.ucfirst($class_name_part);
		}
		return 'Shoutem'.$class_name.'Controller';
	}

	function dispatch_to_method($exploded_class_uri, $method_name) {
		// new is a keyword so let's map that to create
		if ($method_name == 'new') {
			$method_name = 'create';
		}

		$controller_path = $this->controller_path($exploded_class_uri);
		$controller_class = $this->controller_class($exploded_class_uri);


		if(!file_exists($controller_path)) {
			return false;
		}

		require_once $controller_path;

		if(!class_exists($controller_class)) {
			return false;
		}

		if(!method_exists($controller_class,$method_name)){
			return false;
		}

		$controller = new $controller_class(
			$this,
			$this->request,
			$this->response,
			$this->dao_factory,
			$this->authentication,
			$this->caching
		);

		try {
			$controller->before();
			$controller->$method_name();
			$controller->after(); // we might have some use for this later
		} catch (Exception $e) {
			$this->response->send_error(500, $e->get_error_message());
		}

		return true;
	}

	/**
	 * calls appropriate class and method based on method parameter,
	 * or sends error if method is not found
	 */
	function dispatch() {
		// Fetching method name from the request object, it can be GET/POST request depends on API client app
		// For that reason we use $_REQUEST
		if(!isset($_REQUEST['method']) || $_REQUEST['method'] == null) {
			$this->response->send_error(404, "Method not specified");
		}

        // check that method is of form class_part_1/class_part_2/...
        if (!preg_match('{^[a-z_]\w+(?:/[a-z_]\w+)*$}i', $_REQUEST['method'])) {
            $this->response->send_error(400, "Method not valid");
        }

		$method_uri = $_REQUEST['method'];
		$exploded_method_uri = explode('/', $method_uri);

		$dispatch_success = $this->dispatch_to_method($exploded_method_uri,'index');
		if(!$dispatch_success) {
			//try method uri in form: class_part_0/class_part_1/.../class_part_n/method_name

			$last_element = array_slice($exploded_method_uri,-1,1);
			$method_name = $last_element[0];

			$dispatch_success = $this->dispatch_to_method(
				array_slice($exploded_method_uri,0,count($exploded_method_uri) - 1),
				$method_name);
		}

		if(!$dispatch_success) {
			$this->response->send_error(501);
		}
	}
}

// utility stuff
function string_ends_with($str, $end) {
	return $end === substr($str, -strlen($end));
}