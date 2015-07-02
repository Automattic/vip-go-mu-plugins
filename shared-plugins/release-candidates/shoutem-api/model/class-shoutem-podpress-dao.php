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
class ShoutemPodpressDao extends ShoutemDao {

	public function attach_to_hooks() {

		if (isset($GLOBALS['podPress'])) {
			global $podPress;
			//remove default podpress filter to prevent it from injecting player html into the post
			remove_filter('the_content', array(&$podPress, 'insert_content'));
		}

		remove_action('shoutem_get_post_start',array($this,'on_shoutem_post_start'));
		add_action('shoutem_get_post_start',array($this,'on_shoutem_post_start'));
	}

	public function on_shoutem_post_start($params) {
		$attachments = &$params['attachments_ref'];
		$post = $params['wp_post'];
		//Podpress support.
		if (function_exists('podPress_get_post_meta') && isset($GLOBALS['podPress'])) {
			global $podPress;

			//podcasts are stored in post metadata
			$audio_meta = podPress_get_post_meta($post->ID,'_podPressMedia',true);
			if (!$audio_meta) {
				$audio_meta = podPress_get_post_meta($post->ID,'podPressMedia',true);
			}
			if ($audio_meta) {
				foreach($audio_meta as $key => $audio) {
				$uri = $podPress->convertPodcastFileNameToWebPath($post->ID, $key, $audio['URI'], 'web');
					$audio_record = array(
						'id' => '',
						'src' => $uri,
						'type' => 'audio',
						'duration' => ''
		 			);
		 			$attachments['audio'] []= $audio_record;
				}
			}
		}
		return '';
	}
}

?>