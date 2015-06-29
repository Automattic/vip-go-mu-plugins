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
class ShoutemPodcastsController extends ShoutemController {
	/**
	 * MAPS_TO: audio/get
	 * REQ PARAMS: post_id
	 * OPT PARAMS: session_id
	 */
	function get() {
		$params = $this->accept_standard_params_and('post_id','include_raw_post');
		$this->validate_required_params('post_id');


		$uid = $this->caching->unique_id($this->request->params);
		$cached = $this->caching->get_cached($uid);
		if ($cached) {
			$this->response->send_json($cached);
		} else {
			/*$data = $this->caching->use_cache(
						array($postsDao,'get'),
						$this->request->params
						);
			*/ //don't use caching here, because there will be mutch less requests on get
			$postsDao = $this->dao_factory->get_posts_dao();
			$data = $postsDao->get($this->request->params);

			$json_string = $this->view->encode_record_as_json($data);
			$this->caching->store_to_cache($uid, $json_string);
			$this->response->send_json($json_string);
		}
	}

	function categories() {
		$this->accept_standard_params_and();
		$this->request->use_default_params($this->default_paging_params());
		$dao = $this->dao_factory->get_posts_dao();

		if (isset($GLOBALS['podPress'])) {
			$this->view->show_recordset(array(
				'data' => array(
					array(
						'category_id' => 'podpress',
						'name' => 'podcasts',
						'allowed' => true
					)
				),
				'paging' => array(
				)
			));
		} else if (function_exists('powerpress_get_enclosure')) {
			$this->view->show_recordset(array(
				'data' => array(
					array(
						'category_id' => 'powerpress',
						'name' => 'podcasts',
						'allowed' => true
					)
				),
				'paging' => array(
				)
			));
		} else {
			$this->view->show_recordset(array(
				'data' => array(
					array()
				),
				'paging' => array(
				)
			));
		}
	}

	/**
	 * MAPS_TO: posts/find
	 * OPT PARAMS: session_id, category_id, offeset (default 0), limit (default 100)
	 */
	function find() {
		$this->accept_standard_params_and('category_id');
		$this->request->use_default_params($this->default_paging_params());


		$uid = $this->caching->unique_id($this->request->params);

		$cached = $this->caching->get_cached($uid);
		if ($cached) {
			$this->response->send_json($cached);
		} else {

			$postsDao = $this->dao_factory->get_posts_dao();
			$params = $this->request->params;
			if ($params['category_id'] == "powerpress") {
				$params['meta_key'] = array('enclosure', '_podPressMedia', 'podPressMedia');
			} else {
				$params['meta_key'] = array('_podPressMedia', 'podPressMedia');
			}

			$params['category_id'] = '';

			$result = $postsDao->find($params);

			$json_string = $this->view->encode_recordset_as_json($result);
			$this->caching->store_to_cache($uid, $json_string);
			$this->response->send_json($json_string);
		}

	}
}
