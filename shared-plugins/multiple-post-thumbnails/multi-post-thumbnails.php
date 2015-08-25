<?php
/*
Plugin Name: Multiple Post Thumbnails
Plugin URI: http://wordpress.org/extend/plugins/multiple-post-thumbnails/
Description: Adds the ability to add multiple post thumbnails to a post type.
Version: 1.6
Author: Chris Scott
Author URI: http://voceplatforms.com/
*/

/*  Copyright 2010 Chris Scott (cscott@voceconnect.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


if (!class_exists('MultiPostThumbnails')) {

	class MultiPostThumbnails {

		public function __construct($args = array()) {
			$this->register($args);
		}

		/**
		 * Register a new post thumbnail.
		 *
		 * Required $args contents:
		 *
		 * label - The name of the post thumbnail to display in the admin metabox
		 *
		 * id - Used to build the CSS class for the admin meta box. Needs to be unique and valid in a CSS class selector.
		 *
		 * Optional $args contents:
		 *
		 * post_type - The post type to register this thumbnail for. Defaults to post.
		 *
		 * priority - The admin metabox priority. Defaults to 'low'.
		 * 
		 * context - The admin metabox context. Defaults to 'side'.
		 *
		 * @param array|string $args See above description.
		 * @return void
		 */
		public function register($args = array()) {
			global $wp_version;
			
			$defaults = array(
				'label' => null,
				'id' => null,
				'post_type' => 'post',
				'priority' => 'low',
				'context' => 'side',
			);

			$args = wp_parse_args($args, $defaults);

			// Create and set properties
			foreach($args as $k => $v) {
				$this->$k = $v;
			}

			// Need these args to be set at a minimum
			if (null === $this->label || null === $this->id) {
				if (WP_DEBUG) {
					trigger_error(sprintf(__("The 'label' and 'id' values of the 'args' parameter of '%s::%s()' are required", 'multiple-post-thumbnails'), __CLASS__, __FUNCTION__));
				}
				return;
			}

			// add theme support if not already added
			if (!current_theme_supports('post-thumbnails')) {
				add_theme_support( 'post-thumbnails' );
			}

			add_action('add_meta_boxes', array($this, 'add_metabox'));
			if (version_compare($wp_version, '3.5', '<')) {				
				add_filter('attachment_fields_to_edit', array($this, 'add_attachment_field'), 20, 2);
			}
			add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
			add_action('admin_print_scripts-post.php', array($this, 'admin_header_scripts'));
			add_action('admin_print_scripts-post-new.php', array($this, 'admin_header_scripts'));
			add_action("wp_ajax_set-{$this->post_type}-{$this->id}-thumbnail", array($this, 'set_thumbnail'));
			add_action('delete_attachment', array($this, 'action_delete_attachment'));
			add_filter('is_protected_meta', array($this, 'filter_is_protected_meta'), 20, 2);
		}
		
		/**
		 * get the meta key used to store a post's thumbnail
		 * 
		 * @return string 
		 */
		public function get_meta_key() {
			return "{$this->post_type}_{$this->id}_thumbnail_id";
		}

		/**
		 * Add admin metabox for thumbnail chooser
		 *
		 * @return void
		 */
		public function add_metabox() {
			add_meta_box("{$this->post_type}-{$this->id}", __($this->label, 'multiple-post-thumbnails'), array($this, 'thumbnail_meta_box'), $this->post_type, $this->context, $this->priority);
		}

		/**
		 * Output the thumbnail meta box
		 *
		 * @return string HTML output
		 */
		public function thumbnail_meta_box() {
			global $post;
			
			$thumbnail_id = get_post_meta($post->ID, $this->get_meta_key(), true);
			echo $this->post_thumbnail_html($thumbnail_id);	
		}

		/**
		 * Throw this in the media attachment fields
		 *
		 * @param string $form_fields
		 * @param string $post
		 * @return void
		 */
		public function add_attachment_field($form_fields, $post) {
			$calling_post_id = 0;
			if (isset($_GET['post_id']))
				$calling_post_id = absint($_GET['post_id']);
			elseif (isset($_POST) && count($_POST)) // Like for async-upload where $_GET['post_id'] isn't set
				$calling_post_id = $post->post_parent;

			if (!$calling_post_id)
				return $form_fields;

			// check the post type to see if link needs to be added
			$calling_post = get_post($calling_post_id);
			if (is_null($calling_post) || $calling_post->post_type != $this->post_type) {
				return $form_fields;
			}

			$referer = wp_get_referer();
			$query_vars = wp_parse_args(parse_url($referer, PHP_URL_QUERY));
			
			if( (isset($_REQUEST['context']) && $_REQUEST['context'] != $this->id) || (isset($query_vars['context']) && $query_vars['context'] != $this->id) )
				return $form_fields;

			$ajax_nonce = wp_create_nonce("set_post_thumbnail-{$this->post_type}-{$this->id}-{$calling_post_id}");
			$link = sprintf('<a id="%4$s-%1$s-thumbnail-%2$s" class="%1$s-thumbnail" href="#" onclick="MultiPostThumbnails.setAsThumbnail(\'%2$s\', \'%1$s\', \'%4$s\', \'%5$s\');return false;">' . __( 'Set as %3$s', 'multiple-post-thumbnails' ) . '</a>', esc_js( $this->id ), esc_js( $post->ID ), esc_html( $this->label ), esc_js( $this->post_type ), esc_js( $ajax_nonce ) );
			$form_fields["{$this->post_type}-{$this->id}-thumbnail"] = array(
				'label' => $this->label,
				'input' => 'html',
				'html' => $link);
			return $form_fields;
		}

		/**
		 * Enqueue admin JavaScripts
		 *
		 * @return void
		 */
		public function enqueue_admin_scripts( $hook ) {
			global $wp_version;
			
			// only load on select pages
			if ( ! in_array( $hook, array( 'post-new.php', 'post.php', 'media-upload-popup' ) ) )
				return;

			if (version_compare($wp_version, '3.5', '<')) {	
				add_thickbox();
				wp_enqueue_script( "mpt-featured-image", $this->plugins_url( 'js/multi-post-thumbnails-admin.js', __FILE__ ), array( 'jquery', 'media-upload' ) );
			} else { // 3.5+ media modal
				wp_enqueue_media();
				wp_enqueue_script( "mpt-featured-image", $this->plugins_url( 'js/multi-post-thumbnails-admin.js', __FILE__ ), array( 'jquery', 'set-post-thumbnail' ) );
				wp_enqueue_script( "mpt-featured-image-modal", $this->plugins_url( 'js/media-modal.js', __FILE__ ), array( 'jquery', 'media-models' ) );				
			}
		}
		
		public function admin_header_scripts() {
			$post_id = esc_js( get_the_ID() );
			echo "<script>var post_id = $post_id;</script>";
		}

		/**
		 * Deletes the post meta data for posts when an attachment used as a
		 * multiple post thumbnail is deleted from the Media Libray
		 *
		 * @global object $wpdb
		 * @param int $post_id
		 */
		public function action_delete_attachment($post_id) {
			global $wpdb;

			$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->postmeta WHERE meta_key = '%s' AND meta_value = %d", $this->get_meta_key(), $post_id ));
		}
		
		/**
		 * make the meta for storing thumbnails protected so it doesn't show in the Custom Fields metabox
		 * 
		 * @param boolean $protected Passed in from filter
		 * @param type $meta_key Passed in from filter
		 * @return boolean 
		 */
		public function filter_is_protected_meta($protected, $meta_key) {
			if (apply_filters('mpt_unprotect_meta', false)) {
				return $protected;
			}
			
			if ($meta_key == $this->get_meta_key()) {
				$protected = true;
			}
			
			return $protected;
		}

		private function plugins_url($relative_path, $plugin_path) {
			$template_dir = get_template_directory();

			foreach ( array('template_dir', 'plugin_path') as $var ) {
				$$var = str_replace('\\' ,'/', $$var); // sanitize for Win32 installs
				$$var = preg_replace('|/+|', '/', $$var);
			}
			if(0 === strpos($plugin_path, $template_dir)) {
				$url = get_template_directory_uri();
				$folder = str_replace($template_dir, '', dirname($plugin_path));
				if ( '.' != $folder ) {
					$url .= '/' . ltrim($folder, '/');
				}
				if ( !empty($relative_path) && is_string($relative_path) && strpos($relative_path, '..') === false ) {
					$url .= '/' . ltrim($relative_path, '/');
				}
				return $url;
			} else {
				return plugins_url($relative_path, $plugin_path);
			}
		}

		/**
		 * Check if post has an image attached.
		 *
		 * @param string $post_type The post type.
		 * @param string $id The id used to register the thumbnail.
		 * @param string $post_id Optional. Post ID.
		 * @return bool Whether post has an image attached.
		 */
		public static function has_post_thumbnail($post_type, $id, $post_id = null) {
			if (null === $post_id) {
				$post_id = get_the_ID();
			}

			if (!$post_id) {
				return false;
			}

			return get_post_meta($post_id, "{$post_type}_{$id}_thumbnail_id", true);
		}

		/**
		 * Display Post Thumbnail.
		 *
		 * @param string $post_type The post type.
		 * @param string $thumb_id The id used to register the thumbnail.
		 * @param string $post_id Optional. Post ID.
		 * @param int $size Optional. Image size.  Defaults to 'post-thumbnail', which theme sets using set_post_thumbnail_size( $width, $height, $crop_flag );.
		 * @param string|array $attr Optional. Query string or array of attributes.
		 * @param bool $link_to_original Optional. Wrap link to original image around thumbnail?
		 */
		public static function the_post_thumbnail($post_type, $thumb_id, $post_id = null, $size = 'post-thumbnail', $attr = '', $link_to_original = false) {
			echo self::get_the_post_thumbnail($post_type, $thumb_id, $post_id, $size, $attr, $link_to_original);
		}

		/**
		 * Retrieve Post Thumbnail.
		 *
		 * @param string $post_type The post type.
		 * @param string $thumb_id The id used to register the thumbnail.
		 * @param int $post_id Optional. Post ID.
		 * @param string $size Optional. Image size.  Defaults to 'thumbnail'.
		 * @param bool $link_to_original Optional. Wrap link to original image around thumbnail?
		 * @param string|array $attr Optional. Query string or array of attributes.
		  */
		public static function get_the_post_thumbnail($post_type, $thumb_id, $post_id = NULL, $size = 'post-thumbnail', $attr = '' , $link_to_original = false) {
			global $id;
			$post_id = (NULL === $post_id) ? get_the_ID() : $post_id;
			$post_thumbnail_id = self::get_post_thumbnail_id($post_type, $thumb_id, $post_id);
			$size = apply_filters("{$post_type}_{$post_id}_thumbnail_size", $size);
			if ($post_thumbnail_id) {
				do_action("begin_fetch_multi_{$post_type}_thumbnail_html", $post_id, $post_thumbnail_id, $size); // for "Just In Time" filtering of all of wp_get_attachment_image()'s filters
				$html = wp_get_attachment_image( $post_thumbnail_id, $size, false, $attr );
				do_action("end_fetch_multi_{$post_type}_thumbnail_html", $post_id, $post_thumbnail_id, $size);
			} else {
				$html = '';
			}

			if ($link_to_original && $html) {
				$html = sprintf('<a href="%s">%s</a>', wp_get_attachment_url($post_thumbnail_id), $html);
			}

			return apply_filters("{$post_type}_{$thumb_id}_thumbnail_html", $html, $post_id, $post_thumbnail_id, $size, $attr);
		}

		/**
		 * Retrieve Post Thumbnail ID.
		 *
		 * @param string $post_type The post type.
		 * @param string $id The id used to register the thumbnail.
		 * @param int $post_id Post ID.
		 * @return int
		 */
		public static function get_post_thumbnail_id($post_type, $id, $post_id) {
			return get_post_meta($post_id, "{$post_type}_{$id}_thumbnail_id", true);
		}

		/**
		 *
		 * @param string $post_type The post type.
		 * @param string $id The id used to register the thumbnail.
		 * @param int $post_id Optional. The post ID. If not set, will attempt to get it.
		 * @param string $size Optional. The thumbnail size to use. If set, use wp_get_attachment_image_src() instead of wp_get_attachment_url()
		 * @return mixed Thumbnail url or false if the post doesn't have a thumbnail for the given post type, and id.
		 */
		public static function get_post_thumbnail_url($post_type, $id, $post_id = 0, $size = null) {
			if (!$post_id) {
				$post_id = get_the_ID();
			}

			$post_thumbnail_id = self::get_post_thumbnail_id($post_type, $id, $post_id);

			if ($size) {
				if ($url = wp_get_attachment_image_src($post_thumbnail_id, $size)) {
					$url = $url[0];
				} else {
					$url = '';
				}
			} else {
				$url = wp_get_attachment_url($post_thumbnail_id);
			}

			return $url;
		}

		/**
		 * Output the post thumbnail HTML for the metabox and AJAX callbacks
		 *
		 * @param string $thumbnail_id The thumbnail's post ID.
		 * @return string HTML
		 */
		private function post_thumbnail_html($thumbnail_id = null) {
			global $content_width, $_wp_additional_image_sizes, $post_ID, $wp_version;
			
			$url_class = "";
			$ajax_nonce = wp_create_nonce("set_post_thumbnail-{$this->post_type}-{$this->id}-{$post_ID}");
			
			if (version_compare($wp_version, '3.5', '<')) {
				// Use the old thickbox for versions prior to 3.5
				$image_library_url = get_upload_iframe_src('image');
				// if TB_iframe is not moved to end of query string, thickbox will remove all query args after it.
				$image_library_url = add_query_arg( array( 'context' => $this->id, 'TB_iframe' => 1 ), remove_query_arg( 'TB_iframe', $image_library_url ) );
				$url_class = "thickbox";
			} else {
				// Use the media modal for 3.5 and up
				$image_library_url = "#";
				$modal_js = sprintf(
					'var mm_%3$s = new MediaModal({
						calling_selector : "#set-%1$s-%2$s-thumbnail",
						cb : function(attachment){
							MultiPostThumbnails.setAsThumbnail(attachment.id, "%2$s", "%1$s", "%4$s");
						}
					});',
					esc_js( $this->post_type ), esc_js( $this->id ), md5($this->id), esc_js( $ajax_nonce )
				);
			}
			$format_string = '<p class="hide-if-no-js"><a title="%1$s" href="%2$s" id="set-%3$s-%4$s-thumbnail" class="%5$s" data-thumbnail_id="%7$s" data-uploader_title="%1$s" data-uploader_button_text="%1$s">%%s</a></p>';
			$set_thumbnail_link = sprintf( $format_string, sprintf( esc_attr__( "Set %s" , 'multiple-post-thumbnails' ), $this->label ), $image_library_url, $this->post_type, $this->id, $url_class, $this->label, $thumbnail_id );
			$content = sprintf( $set_thumbnail_link, sprintf( esc_html__( "Set %s", 'multiple-post-thumbnails' ), $this->label ) );

			if ($thumbnail_id && get_post($thumbnail_id)) {
				$old_content_width = $content_width;
				$content_width = 266;
				if ( !isset($_wp_additional_image_sizes["{$this->post_type}-{$this->id}-thumbnail"]))
					$thumbnail_html = wp_get_attachment_image($thumbnail_id, array($content_width, $content_width));
				else
					$thumbnail_html = wp_get_attachment_image($thumbnail_id, "{$this->post_type}-{$this->id}-thumbnail");
				if (!empty($thumbnail_html)) {
					$content = sprintf($set_thumbnail_link, $thumbnail_html);
					$format_string = '<p class="hide-if-no-js"><a href="#" id="remove-%1$s-%2$s-thumbnail" onclick="MultiPostThumbnails.removeThumbnail(\'%2$s\', \'%1$s\', \'%4$s\');return false;">%3$s</a></p>';
					$content .= sprintf( $format_string, esc_js( $this->post_type ), esc_js( $this->id ), sprintf( esc_html__( "Remove %s", 'multiple-post-thumbnails' ), $this->label ), $ajax_nonce );
				}
				$content_width = $old_content_width;
			}
			
			if (version_compare($wp_version, '3.5', '>=')) {
				$content .= sprintf('<script>%s</script>', $modal_js);
			}
			
			return $content;
		}

		/**
		 * Set/remove the post thumbnail. AJAX handler.
		 *
		 * @return string Updated post thumbnail HTML.
		 */
		public function set_thumbnail() {
			global $post_ID; // have to do this so get_upload_iframe_src() can grab it
			$post_ID = intval($_POST['post_id']);
			if ( !current_user_can('edit_post', $post_ID))
				die('-1');
			$thumbnail_id = intval($_POST['thumbnail_id']);

			check_ajax_referer("set_post_thumbnail-{$this->post_type}-{$this->id}-{$post_ID}");

			if ($thumbnail_id == '-1') {
				delete_post_meta($post_ID, $this->get_meta_key());
				die($this->post_thumbnail_html(null));
			}

			if ($thumbnail_id && get_post($thumbnail_id)) {
				$thumbnail_html = wp_get_attachment_image($thumbnail_id, 'thumbnail');
				if (!empty($thumbnail_html)) {
					$this->set_meta($post_ID, $this->post_type, $this->id, $thumbnail_id);
					die($this->post_thumbnail_html($thumbnail_id));
				}
			}

			die('0');
		}
		
		/**
		 * set thumbnail meta
		 * 
		 * @param int $post_ID
		 * @param string $post_type
		 * @param string $thumbnail_id ID used to register the thumbnail
		 * @param int $thumbnail_post_id ID of the attachment to use as the thumbnail
		 * @return bool result of update_post_meta
		 */
		public static function set_meta($post_ID, $post_type, $thumbnail_id, $thumbnail_post_id) {
			return update_post_meta($post_ID, "{$post_type}_{$thumbnail_id}_thumbnail_id", $thumbnail_post_id);
		}

	}

	if ( is_admin() )
		load_plugin_textdomain( 'multiple-post-thumbnails', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
}
