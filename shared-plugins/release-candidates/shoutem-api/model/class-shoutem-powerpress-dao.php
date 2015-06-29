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
class ShoutemPowerpressDao extends ShoutemDao {

	public function attach_to_hooks() {
		remove_filter('the_content', 'powerpress_content');
		remove_action('shoutem_get_post_start',array($this,'on_shoutem_post_start'));
		add_action('shoutem_get_post_start',array($this,'on_shoutem_post_start'));
	}

	public function on_shoutem_post_start($params) {


		$attachments = &$params['attachments_ref'];
		$post = $params['wp_post'];

		//Powerpress support
		if (function_exists('powerpress_get_enclosure')) {

			$audio_meta = powerpress_get_enclosure_data($post->ID);
			if ($audio_meta) {
				$audio_meta = array($audio_meta);
			} else {
				$audio_meta = array();
			}

			$podpress_meta = powerpress_get_enclosure_data_podpress($post->ID);
			if ($podpress_meta && is_array($podpress_meta)) {
				//$podpress_meta['url'] = urlencode($podpress_meta['url']);
				$audio_meta = array_merge($audio_meta, array($podpress_meta));
			}

			foreach($audio_meta as $audio) {
				$url = $audio['url'];
				$audio_record = array(
					'id' => '',
					'src' => $url,
					'type' => 'audio',
					'duration' => ''
	 			);
	 			$attachments['audio'] []= $audio_record;
			}
		}
		return '';
	}
}

?>