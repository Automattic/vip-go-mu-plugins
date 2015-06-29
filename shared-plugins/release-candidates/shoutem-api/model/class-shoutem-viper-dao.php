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
class ShoutemViperDao extends ShoutemDao {

	public function attach_to_hooks() {
		if (isset($GLOBALS['VipersVideoQuicktags'])) {
			remove_shortcode( 'youtube');
			add_shortcode( 'youtube', array($this, 'shortcode_youtube') );

			remove_shortcode( 'vimeo');
			add_shortcode( 'vimeo', array($this, 'shortcode_vimeo') );


			remove_shortcode( 'quicktime');
			remove_shortcode( 'flash');
			remove_shortcode( 'videofile');
			remove_shortcode( 'video');
			remove_shortcode( 'avi');
			remove_shortcode( 'mpeg');
			remove_shortcode( 'wmv');

			add_shortcode( 'quicktime', array($this, 'shortcode_viper_generic') );
			add_shortcode( 'flash', array($this, 'shortcode_viper_generic') );
			add_shortcode( 'videofile', array($this, 'shortcode_viper_generic') );
			add_shortcode( 'video', array($this, 'shortcode_viper_generic') );
			add_shortcode( 'avi', array($this, 'shortcode_viper_generic') );
			add_shortcode( 'mpeg', array($this, 'shortcode_viper_generic') );
			add_shortcode( 'wmv', array($this, 'shortcode_viper_generic') );
		}
		remove_action('shoutem_get_post_start',array($this,'on_shoutem_post_start'));
		add_action('shoutem_get_post_start',array($this,'on_shoutem_post_start'));
	}

	public function on_shoutem_post_start($params) {
		$this->attachments = &$params['attachments_ref'];
	}

	function put_video($url, $provider, $pid) {
		$video = array(
			'id' => $pid,
			'src' => $url,
			'provider' => $provider
		);
		$this->attachments['videos'] []= $video;
		return '<attachment id="'.esc_attr( $pid ).'" type="video" xmlns="v1" />';
	}

	function shortcode_viper_generic( $atts, $content = '' ) {
		$shortcode_atts = shortcode_atts(array(
            'se_visible'        => 'true',
        ), $atts );

		$se_visible = $shortcode_atts['se_visible'];

        if ($se_visible != 'true') {
        	return '';
        }
		if (!empty($content)) {
			$this->put_video($content, '', '');
		}
		return '';
	}

	function shortcode_vimeo( $atts, $content = '' ) {

		$shortcode_atts = shortcode_atts(array(
            'se_visible'        => 'true',
        ), $atts );

		$se_visible = $shortcode_atts['se_visible'];

        if ($se_visible != 'true') {
        	return '';
        }

		if ( empty($content) ) {
			return '';
		}

		$video_id = '';
		// If a URL was passed
		if ( 'http://' == substr( $content, 0, 7 ) ) {
			preg_match( '#http://(www.vimeo|vimeo)\.com(/|/clip:)(\d+)(.*?)#i', $content, $matches );
			if ( empty($matches) || empty($matches[3]) ) return '';

			$video_id = $matches[3];
		}
		// If a URL wasn't passed, assume a video ID was passed instead
		else {
			$video_id = $content;
		}
		if (!empty($video_id)) {
			return $this->put_video('http://player.vimeo.com/video/'.$video_id, 'vimeo', $video_id);
		}
		return '';


	}


	function shortcode_youtube($atts, $content = '') {
		$shortcode_atts = shortcode_atts(array(
            'se_visible'        => 'true',
        ), $atts );

		$se_visible = $shortcode_atts['se_visible'];

        if ($se_visible != 'true') {
        	return '';
        }

		if ( empty($content) ) {
			return '';
		}

		$video_id = '';
		// If a URL was passed
		if ( 'http://' == substr( $content, 0, 7 ) ) {

			if ( false === stristr( $content, 'playlist' ) &&
				false === stristr( $content, 'view_play_list' )) { //disregard playlists

				preg_match( '#http://(www.youtube|youtube|[A-Za-z]{2}.youtube)\.com/(watch\?v=|w/\?v=|\?v=)([\w-]+)(.*?)#i', $content, $matches );
				if ( empty($matches) || empty($matches[3]) ) return '';

				$video_id = $matches[3];
			}
		}
		// If a URL wasn't passed, assume a video ID was passed instead
		else {
			$video_id = $content;
		}
		if (!empty($video_id)) {
			return $this->put_video('http://www.youtube.com/v/'.$video_id, 'youtube', $video_id);
		}

		return '';

	}
}