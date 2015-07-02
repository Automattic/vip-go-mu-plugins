<?php
/**
 * This class is designed to work with NextGenGallery Wordpress plugin.
 */
class ShoutemNGGDao extends ShoutemDao {

	function attach_to_hooks() {
		remove_action('shoutem_get_post_start',array($this,'on_shoutem_post_start'));
		add_action('shoutem_get_post_start',array($this,'on_shoutem_post_start'));
		$this->attach_to_shortcodes();
	}

	public function on_shoutem_post_start($params) {
		$this->attachments = &$params['attachments_ref'];
	}

	public static function available() {
		return isset($GLOBALS['nggdb']);
	}

	public function get($params) {
		global $nggdb;

		$pid = $params['post_id'];

		$image = $nggdb->find_image($pid);

		if ($image) {
			return $this->convert_to_se_post($image, $params);
		}
		return false;
	}

	public function find($params) {
		global $nggdb;

		$offset = $params['offset'];
		$limit = $params['limit'];
		$category_id = $params['category_id'];

		$images = $nggdb->get_gallery($category_id,'sortorder','ASC',true,$limit + 1,$offset);

		$results = array();
		foreach($images as $image) {
			$results []= $this->convert_to_se_post($image, $params);
		}

		return $this->add_paging_info($results, $params);
	}

	public function categories($params) {
		global $nggdb;

		$offset = $params['offset'];
		$limit = $params['limit'];

		$galleries = $nggdb->find_all_galleries('gid', 'ASC', true, $limit + 1, $offset);

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

	/**
	 * Converts the image from the NGG format to the post format
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
						'height' => $image->meta_data['height'],
						'thumbnail_url' => $image->thumbURL
					)
				)
			)
		);
		sanitize_attachments($result['attachments']);
		return $result;
	}


	public function attach_to_shortcodes() {
		if (isset($GLOBALS['nggdb'])) {
			remove_shortcode( 'album');
			add_shortcode( 'album', array($this, 'shortcode_album' ) );

        	remove_shortcode( 'imagebrowser');
        	remove_shortcode( 'slideshow');
	        remove_shortcode( 'nggallery');
	        add_shortcode( 'nggallery', array($this, 'shortcode_gallery') );
	        add_shortcode( 'slideshow', array($this, 'shortcode_gallery') );
	        add_shortcode( 'imagebrowser', array($this, 'shortcode_gallery') );
		}
	}

	function get_gallery($db, $id, &$images) {
		$out = "";
		$gallery = $db->get_gallery($id,'sortorder','ASC',true);
		if(!$gallery) return $out;
		foreach($gallery as $image) {

			$pid = $image->pid;
			$image = array(
				'src' => $image->imageURL,
				'id' => $pid,
				'width' => $image->meta_data['width'],
				'height' => $image->meta_data['height'],
				'thumbnail_url' => $image->thumbURL
			);
			$images []= $image;
			$out .= '<attachment id="'.esc_attr( $pid ).'" type="image" xmlns="v1" />';
		}
		return $out;
	}

	/**
	 * NGG album shortcode
	 */
	function shortcode_album($atts) {
		global $nggdb;
		$shortcode_atts = shortcode_atts(array(
            'id'        => 0,
            'se_visible' => 'true'
        ), $atts );

		$id         = $shortcode_atts['id'];
		$se_visible = $shortcode_atts['se_visible'];

        if ($se_visible != 'true') {
        	return '';
        }

        $out = '';
        $album = $nggdb->find_album($id);
        if ($album && is_array($album->gallery_ids)) {
        	foreach($album->gallery_ids as $gallery_id) {
        		$out .= $this->get_gallery($nggdb, $gallery_id, $this->attachments['images']);
        	}
        }

       	return $out;
	}

	/**
	 * NGG gallery shortcode
	 */
	function shortcode_gallery($atts) {
		global $nggdb;

		$shortcode_atts = shortcode_atts(array(
            'id'        => 0,
            'se_visible' => 'true'
        ), $atts );

		$id         = $shortcode_atts['id'];
		$se_visible = $shortcode_atts['se_visible'];

        if ($se_visible != 'true') {
        	return '';
        }
        $out = '';
        $out .= $this->get_gallery($nggdb, $id, $this->attachments['images']);


        return $out;
	}
}