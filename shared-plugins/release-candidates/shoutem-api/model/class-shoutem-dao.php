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
class ShoutemDao {

	public function __construct() {
		global $shoutem_api;
		$this->options = $shoutem_api->shoutem_options->get_options();
	}

	/**
	 * @param array
	 * @param map
	 * @return array array with remaped keys map(old_key_val=>new_key_val)
	 */
	protected function array_remap_keys($input, $map) {
		$remaped_array = array();
		foreach($input as $key => $val) {
			if(array_key_exists($key, $map)) {
				$remaped_array[$map[$key]] = $val;
			}
		}
		return $remaped_array;
	}

	protected function current_gmtdate() {
		return gmdate("Y-m-d H:i:s", time());
	}

	protected function current_date() {
		return date("Y-m-d H:i:s", time());
	}

	/**
	 * Returns array containing results and paging info
	 * @param results
	 * @param params array(offset,limit)
	 * @param limit
	 * return array( data => results, paging => pagingInfo)
	 */
	protected function add_paging_info($results, $params) {
		$offset = (int)$params['offset'];
		$limit = (int)$params['limit'];
		$next_page = count($results) > $limit; // wheter there is a next page
		$paging = array();

		if ($offset != 0) { // if it's not first
			$paging["previous"] = array(
				"offset" => max($offset - $limit, 0),
				"limit" => $limit
			);
		}

		if ($next_page) {
			$paging["next"] = array(
				"offset" => $offset + $limit,
				"limit" => $limit
			);
		}

		return array(
			"data" => array_slice($results, 0, $limit),
			"paging" => $paging
		);
	}

	public function get_leading_image($post_id) {
		//Post thumbnail is the wordpress term for leading-image

		$post_thumbnail = false;
		if (function_exists("get_the_post_thumbnail")) {
	 		$post_thumbnail = get_the_post_thumbnail($post_id);
		}
		if ($post_thumbnail) {
			$images = strip_images($post_thumbnail);
			if (count($images) > 0) {
				$image = $images[0];
				$image['id'] = "";
				return $image;
			}
		}
		return false;

	}

	public function include_leading_image_in_attachments(&$attachments, $post_id) {
		$leading_image = false;
		$thumbnail_image = false;
		if ($this->options['include_featured_image']) {
			$thumbnail_image = $this->get_leading_image($post_id);
			$thumbnail_image = apply_filters('shoutem_leading_image',$thumbnail_image,$post_id);
		}

		$se_leading_img = get_post_meta($post_id, 'se_leading_img', true);
		if ($se_leading_img) {
			$leading_image = array(
				'src' => $se_leading_img
			);
		} else if ($thumbnail_image) {
			$leading_image = $thumbnail_image;
		} else if (!empty($this->options['lead_img_custom_field_regex'])) {
			$custom_field_regex = $this->options['lead_img_custom_field_regex'];
			$post_keys = get_post_custom_keys($post_id);
			if ($post_keys) {
				foreach( $post_keys as $custom_key) {

					if (preg_match($custom_field_regex, $custom_key) > 0) {
						$leading_image = array(
							'src' => get_post_meta($post_id, $custom_key, true)
							);
						break;
					}
				}
			}
		}

		if ($leading_image) {
			$leading_image['attachment-type'] = "leading_image";
			array_unshift($attachments['images'],$leading_image);
		}
	}
}