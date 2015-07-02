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
class ShoutemServiceController extends ShoutemController {
	public function info() {

		$data = array (
			'api_version' => $this->shoutem_api->api_version,
			'server_type' => 'wordpress',
			'platform_version' => phpversion()
		);
		if (function_exists('get_bloginfo')) {
			$extended = array(
			'charset' => get_bloginfo('charset'),
			'cms_version' => get_bloginfo('version'),
			'text_direction' => get_bloginfo('text_direction'),
			'language' => get_bloginfo('language')
			);
			$data['extended'] = $extended;
		}
		$this->view->show_record($data);
	}


	public function plugins() {
		if (!function_exists('get_plugins'))
				require_once (ABSPATH."wp-admin/includes/plugin.php");

		$plugins = get_plugins();

		$results = array();

		foreach($plugins as $plugin) {
			$results []= array(
				'name' => $plugin['Name'],
				'version' => $plugin['Version'],
				'url' => $plugin['PluginURI']
			);
		}

		$this->view->show_record($results);
	}
}
