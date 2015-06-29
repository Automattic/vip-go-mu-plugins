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
class ShoutemPostsCommentsController extends ShoutemController {

	function get() {
		$this->validate_required_params('post_id', 'comment_id');

		$dao = $this->dao_factory->get_posts_comments_dao();
		$data = $dao->get($this->request->params);

		$this->view->show_record($data);
	}

	function index() {
		$this->validate_required_params('post_id');
		$this->request->use_default_params($this->default_paging_params());

		$params = $this->request->params;
		$dao = $this->dao_factory->get_posts_comments_dao();
		$result = $dao->find($params);
		$this->view->show_recordset($result);
	}

	function create() {

		$params = $this->request->params;

		if(isset($params['session_id'])) {
			// logged user.
			$this->validate_required_params('session_id', 'post_id', 'message');
			$user =  $this->request->credentials->get_user();
			$params['user_id'] = $user->ID;
			$params['author'] = $user->user_nicename;
			$params['author_email'] = $user->user_email;
			$params['author_url'] = $user->user_url;
		} else {
			// anonymous user
			$this->validate_required_params('author_nickname','author_email', 'post_id', 'message');
			$params['author'] = $params['author_nickname'];
			if(!isset($params['author_url'])) {
				$params['author_url'] = '';
			}
			$params['user_id'] = 0;
		}

		$dao = $this->dao_factory->get_posts_comments_dao();

		try {
			$new_comment = $dao->create($params);
		} catch (ShoutemApiException $e) {
			$this->response->send_error(500, $e->get_error_message());
		}

		if ($new_comment == false) {
			$this->response->send_error(500, "Error saving comment");
			return;
		}

		$this->view->show_record($new_comment);
	}

	function delete() {
		$this->validate_required_params('session_id', 'comment_id', 'post_id');
		$dao = $this->dao_factory->get_posts_comments_dao();

		try{
			$result = $dao->delete(
				$this->request->params +
				array('user_id' => $this->request->credentials->data['uid']));
		} catch (ShoutemApiException $e) {
			$result = false;
		}
		if ($result == false) {
			$this->response->send_error(500, "Error deleting comment");
			return;
		}

		// no reponse - status OK
	}

}