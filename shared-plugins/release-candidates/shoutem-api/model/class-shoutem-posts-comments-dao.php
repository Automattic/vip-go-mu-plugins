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
function shoutem_api_comment_duplicate_trigger() {
	throw new ShoutemApiException('comment_duplicate_trigger');
}

function shoutem_api_comment_flood_trigger() {
	throw new ShoutemApiException('comment_flood_trigger');
}

class ShoutemPostsCommentsDao extends ShoutemDao {

	public function get($params) {
        if (!is_numeric($params['comment_id'])) {
            throw new ShoutemApiException('invalid_params');
        }

		$wp_comment = get_comment($params['comment_id']);
		return $this->get_comment($wp_comment, $params);
	}

	public function find($params) {
        if (!is_numeric($params['post_id'])) {
            throw new ShoutemApiException('invalid_params');
        }

		$limit = $params['limit'];
		$offset = $params['offset'];

		$wp_comments = get_comments(array(
			'post_id' 	=> $params['post_id'],
			'number'  	=> $offset + $limit + 1,
			'order'		=> 'ASC',
			'status'	=> 'approve'
		));

		//needed since get_comments does not support offset, limit params
		$wp_comments = array_slice($wp_comments, $offset,$limit + 1);
		$result = array();
		foreach ($wp_comments as $wp_comment) {
			$result[] = $this->get_comment($wp_comment, $params);
		}
		return $this->add_paging_info($result,$params);
	}

	/**
	 * @throws ShoutemApiException
	 */
	public function create($record) {

		$commentdata =(array(
			'comment_author' => $record['author'],
			'comment_author_email' => $record['author_email'],
			'comment_author_url' => $record['author_url'],
			'user_id' => (int)$record['user_id'],
			'comment_content' => $record['message'],
			'comment_post_ID' => (int)$record['post_id'],
			'comment_type' => ''
		));

		add_action('comment_duplicate_trigger', 'shoutem_api_comment_duplicate_trigger');
		add_action('comment_flood_trigger', 'shoutem_api_comment_flood_trigger');

		$comment_id = wp_new_comment($commentdata);
		$comment = false;
		if($comment_id !== false) {
			$comment = get_comment($comment_id);
		}

		if($comment !== false) {
			return $this->get_comment($comment, $record);
		} else {
			throw new ShoutemApiException('comment_create_error');
		}

	}


	public function delete($record) {
		return wp_delete_comment($record['comment_id'],true);
	}

	private function is_comment_deletable($wp_comment, $params) {
		if (!isset($params['wp_user'])) {
			return false;
		}
		$cur_user = $params['wp_user'];

		//can delete if current user is administrator
		if ($cur_user->wp_capabilities && array_key_exists('administrator',$cur_user->wp_capabilities)) {
			return true;
		}
		//can delete if current user created the comment
		return $wp_comment->user_id == $cur_user->ID;
	}

	private function get_comment($wp_comment, $params) {
		$remaped_comment = $this->array_remap_keys($wp_comment,
		array (
				'comment_ID'			=> 'comment_id',
				'comment_author'		=> 'author',
				'comment_author_url'	=> 'author_url',
				'comment_author_email'	=> 'author_image_url',
				'comment_date_gmt'		=> 'published_at',
				'comment_content'		=> 'message',
				'comment_approved'		=> 'approved',
		));
		$remaped_comment['likeable'] = false;
		$remaped_comment['likes_count'] = 0;
		$remaped_comment['deletable'] = $this->is_comment_deletable($wp_comment, $params);
		$user_id = $wp_comment->user_id;
		if ($user_id > 0) {
			$user = get_userdata($user_id);
			$remaped_comment['author'] = $user->user_nicename;
			$remaped_comment['author_image_url'] = $user->user_email;
			$remaped_comment['author_url'] = $user->user_url;
		}
		return $remaped_comment;
	}

}

?>