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
class ShoutemPagesController extends ShoutemController {

	function get() {
		$params = $this->accept_standard_params_and('post_id','include_raw_post');
		$this->validate_required_params('post_id');


		$uid = $this->caching->unique_id($this->request->params);
		$cached = $this->caching->get_cached($uid);
		if ($cached) {
			$this->response->send_json($cached);
		} else {
			$postsDao = $this->dao_factory->get_posts_dao();
			$data = $postsDao->get($this->request->params);

			$json_string = $this->view->encode_record_as_json($data);
			$this->caching->store_to_cache($uid, $json_string);
			$this->response->send_json($json_string);
		}
	}

	function find() {
		$this->accept_standard_params_and();
		$this->request->use_default_params($this->default_paging_params());
		$dao = $this->dao_factory->get_posts_dao();
		$params = $this->request->params;

		$data = $dao->pages($params);

		$this->view->show_recordset($data);
	}

}
