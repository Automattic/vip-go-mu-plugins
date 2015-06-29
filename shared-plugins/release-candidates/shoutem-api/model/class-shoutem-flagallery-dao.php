<?php
/**
 * This class is designed to work only with GRAND FLAGallery Wordpress plugin.
 */
class ShoutemFlaGalleryDao extends ShoutemDao {

	function attach_to_hooks() {
		remove_action('shoutem_get_post_start',array($this,'on_shoutem_post_start'));
		add_action('shoutem_get_post_start',array($this,'on_shoutem_post_start'));
		$this->attach_to_shortcodes();
	}

	public function on_shoutem_post_start($params) {
		$this->attachments = &$params['attachments_ref'];
	}

	public static function available() {
		return isset($GLOBALS['flagdb']);
	}

	public function get($params) {
		global $flagdb;

		$pid = $params['post_id'];

		$image = $flagdb->find_image($pid);

		if ($image) {
			return $this->convert_to_se_post($image, $params);
		}
		return false;
	}

	public function find($params) {
		global $flagdb;
		$offset = $params['offset'];
		$limit = $params['limit'];
		$category_id = $params['category_id'];

		$images = $flagdb->get_gallery($category_id,'sortorder','ASC',true,$limit + 1,$offset);

		$results = array();


		foreach($images as $image) {
			$results []= $this->convert_to_se_post($image, $params);
		}
		return $this->add_paging_info($results, $params);
	}

	public function categories($params) {
		global $flagdb;
		$offset = $params['offset'];
		$limit = $params['limit'];

		$galleries = $flagdb->find_all_galleries('gid', 'ASC', true, $limit + 1, $offset);

		$results = array();

		foreach($galleries as $gallery) {
			$result_gallery = array(
				'category_id' => $gallery->gid,
				'name' => $gallery->name,
				'allowed' => true
			);
			$results []= $result_gallery;
		}

		return $this->add_paging_info($results, $params);
	}

	private function add_thumbnail_to_attachment($image, &$attachment) {
		//Add thumbnail only when thumb dimensions exists (this is because fla has different aspect ratio for image and thumb)
		if (property_exists($image, 'thumbURL') &&
			array_key_exists('thumbnail', $image->meta_data) &&
			array_key_exists('width', $image->meta_data['thumbnail']) &&
			array_key_exists('height', $image->meta_data['thumbnail'])) {
			$attachment['thumbnail_url'] = $image->thumbURL;
			$attachment['thumbnail_width'] = $image->meta_data['thumbnail']['width'];
			$attachment['thumbnail_height'] = $image->meta_data['thumbnail']['height'];
		}
	}

	/**
	 * Converts the image from the FLAGallery format to the post format
	 * defined by the ShoutEm Data Exchange Protocol: @link http://fiveminutes.jira.com/wiki/display/SE/Data+Exchange+Protocol
	 */
	private function convert_to_se_post($image, $params) {
		$user_data = get_userdata($image->author);
		$remaped_post['author'] = $user_data->display_name;

		$result = array(
			'post_id' => $image->pid,
			'published_at' => $image->imagedate,
			'body' => $image->description,
			'title' => $image->alttext,
			'summary' => $image->description,
			'commentable' => 'no',
			'comments_count' => 0,
			'likeable' => false,
			'likes_count' => 0,
			'author' => $user_data->display_name,
			'link' => $image->imageURL,
			'attachments' => array(
				'images' => array(
					array(
						'src' => $image->imageURL,
						'id' => '',
						'width' => $image->meta_data['width'],
						'height' => $image->meta_data['height']
					)
				)
			)
		);
		$this->add_thumbnail_to_attachment($image, $result['attachments']['images'][0]);
		sanitize_attachments($result['attachments']);
		return $result;
	}



	function attach_to_shortcodes(){
		if (isset($GLOBALS['flagdb'])) {

			remove_shortcode( 'flagallery');
			add_shortcode( 'flagallery', array($this, 'shortcode_flagallery' ));

			//FlaGallery video just remove for now.
			remove_shortcode( 'grandflv');
			add_shortcode( 'grandflv', array($this, 'shortcode_noop' ));
			remove_shortcode( 'grandvideo');
			add_shortcode( 'grandvideo', array($this, 'shortcode_noop' ));

			//FlaGallery mp3 support
			remove_shortcode( 'grandmp3');
			add_shortcode( 'grandmp3', array($this, 'shortcode_grandmp3' ) );
			remove_shortcode( 'grandmusic');
			add_shortcode( 'grandmusic', array($this, 'shortcode_grandmusic' ) );
		}
	}

	function get_gallery($db, $id, &$images) {
		$out = "";
		$gallery = $db->get_gallery($id,'sortorder','ASC',true);
		if(!$gallery) return $out;
		foreach($gallery as $image) {
			$pid = $image->pid;
			$se_image = array(
				'src' => $image->imageURL,
				'id' => $pid,
				'width' => $image->meta_data['width'],
				'height' => $image->meta_data['height']
			);
			$this->add_thumbnail_to_attachment($image, $se_image);
			$images []= $se_image;
			$out .= '<attachment id="'.esc_attr( $pid ).'" type="image" xmlns="v1" />';
		}
		return $out;
	}

	function shortcode_noop() {
		return '';
	}
	/**
	 * FLA Gallery shortcode
	 */
	function shortcode_flagallery($atts) {

		if (!isset($GLOBALS['flagdb'])) {
			return '';
		}

		global $flagdb;

		$shortcode_atts = shortcode_atts(array(
			'gid' 		=> '',
			'album'		=> '',
			'name'		=> '',
			'orderby' 	=> '',
			'order'	 	=> '',
			'exclude' 	=> '',
			'se_visible' => 'true'
		), $atts );

		$gid        = $shortcode_atts['gid'];
		$album      = $shortcode_atts['album'];
		$name       = $shortcode_atts['name'];
		$orderby    = $shortcode_atts['orderby'];
		$order      = $shortcode_atts['order'];
		$exclude    = $shortcode_atts['exclude'];
		$se_visible = $shortcode_atts['se_visible'];


		if ($se_visible != 'true') {
        	return '';
        }

		$out = '';
		// make an array out of the ids
        if($album) {
        	$gallerylist = $flagdb->get_album($album);
        	$ids = explode( ',', $gallerylist );
    		foreach ($ids as $id) {
    			$out .= $this->get_gallery($flagdb, $id, $this->attachments['images']);
    		}

        } elseif($gid == "all") {
			if(!$orderby) $orderby='gid';
			if(!$order) $order='DESC';
            $gallerylist = $flagdb->find_all_galleries($orderby, $order);
            if(is_array($gallerylist)) {
				$excludelist = explode(',',$exclude);
				foreach($gallerylist as $gallery) {
					if (in_array($gallery->gid, $excludelist))
						continue;
					$out .= $this->get_gallery($flagdb, $gallery->gid, $this->attachments['images']);
				}
			}
        } else {
            $ids = explode( ',', $gid);

    		foreach ($ids as $id) {
    			$out .= $this->get_gallery($flagdb, $id, $this->attachments['images']);
    		}
    	}

        return $out;
	}

	/**
	 * FLA Gallery music playlist shortcode
	 */
	function shortcode_grandmusic( $atts ) {
		$shorcode_atts = shortcode_atts(array(
			'playlist'	=> ''
		), $atts );

		$playlist = $shorcode_atts['playlist'];

		$out = '';

		if($playlist) {
			$flag_options = get_option('flag_options');
			$playlist_path = false;

			if (!$flag_options) {
				return $out;
			}

			$playlist_path = $flag_options['galleryPath'].'playlists/'.$playlist.'.xml';

			if (!file_exists($flag_options['galleryPath'].'playlists/'.$playlist.'.xml')) {
				return $out;
			}

			$playlist_content = file_get_contents($playlist_path);

			preg_match_all( '/.?<item id=".*?">(.*?)<\/item>/si', $playlist_content, $items );
			if (!isset($items[1]) || !is_array($items[1])) {
				return $out;
			}
			foreach($items[1] as $playlist_item) {
				preg_match( '/.?<track>(.*?)<\/track>/i', $playlist_item, $track);
				if (count($track) > 1) {
					$url = $track[1];
				}
				$audio_record = array(
						'id' => '',
						'src' => $url,
						'type' => 'audio',
						'duration' => ''
		 			);

				$this->attachments['audio'] []= $audio_record;
			}

		}
		return $out;
	}

	/**
	 * FLA Gallery music mp3
	 */
	function shortcode_grandmp3( $atts ) {
		$shortcode_atts = shortcode_atts(array(
			'id'	=> ''
		), $atts );

		$id = $shortcode_atts['id'];
		$out = '';

		if($id) {
			$url = wp_get_attachment_url($id);
			$url = str_replace(array('.mp3'), array(''), $url);

			$audio_record = array(
						'id' => '',
						'src' => $url,
						'type' => 'audio',
						'duration' => ''
		 			);

			$this->attachments['audio'] []= $audio_record;
		}
       	return $out;
	}
}
