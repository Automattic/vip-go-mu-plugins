<?php

class MediaPass_Plugin_ContentListExtensions {
	const TARGET_CATEGORY = 'category-list-update-protection';
	const TARGET_TAG	  = 'tag-list-update-protection';
	const TARGET_AUTHOR	  = 'author-list-update-protection';
	const TARGET_POST	  = 'content-list-update-protection';
	const TARGET_PAGE	  = 'page-list-update-protection';
	
	private function can_protect_posts() {
		return current_user_can('publish_posts');
	}
	
	private function can_protect_meta() {
		return current_user_can('manage_categories');
	}
	
	public function __construct(){
		if( ! is_admin() ) {
			return;
		}
		
		if( $this->can_protect_posts() ) {
			add_action('manage_posts_columns'		, array(&$this,'add_post_list_column'));
			add_action('manage_posts_custom_column'	, array(&$this,'add_post_custom_column'));
			add_action('admin_head-edit.php'		, array(&$this,'add_bulk_post_actions'));
			add_action('admin_print_footer_scripts' , array(&$this,'print_list_extensions_script'));
			
			add_action('manage_pages_columns'		, array(&$this,'add_post_list_column'));
			add_action('manage_pages_custom_column' , array(&$this,'add_page_custom_column'));			
		}  
		
		if( $this->can_protect_meta() ) {
			add_action('manage_edit-category_columns'	, array(&$this,'add_mediapass_column'));
			add_action('manage_category_custom_column'	, array(&$this,'add_category_custom_column'),10,3);

			add_action('manage_edit-post_tag_columns'	, array(&$this,'add_mediapass_column'));
			add_action('manage_post_tag_custom_column'	, array(&$this,'add_tag_custom_column'),10,3);
			
			add_action('manage_users_columns'		, array(&$this,'add_mediapass_column'));
			add_action('manage_users_custom_column' , array(&$this,'add_user_custom_column'),10,3);
		}
		
		add_action('wp_ajax_content-list-update-protection' , array(&$this,'action_update_post_protection'));
		add_action('wp_ajax_author-list-update-protection'  , array(&$this,'action_update_author_protection'));
		add_action('wp_ajax_page-list-update-protection'  , array(&$this,'action_update_page_protection'));
		add_action('wp_ajax_category-list-update-protection', array(&$this,'action_update_category_protection'));
		add_action('wp_ajax_tag-list-update-protection'		, array(&$this,'action_update_tag_protection'));
	}

	public function add_mediapass_column( $columns ) {
		$columns['mediapass'] = 'MediaPass';
		
		return $columns;
	}
	
	public function add_post_list_column( $columns ){
		$replacement = array();
		
		foreach($columns as $col => $val) {
			if( $col == 'title' ) {
				$replacement[$col] = $val;
				$replacement['mediapass'] = 'MediaPass';
			} else {
				$replacement[$col] = $val;
			}
		}
		
		$columns = $replacement;
		return $columns;
	}
	
	public function add_category_custom_column($nothing,$column,$cat){
		if( $column !== 'mediapass' ) {
			return;
		}
		
		$selected = get_option(MediaPass_Plugin::OPT_PLACEMENT_CATEGORIES);
		
		$result = !empty($selected) && in_array($cat,$selected);
		
		return $this->dump_protection_markup($result, $cat);
	}
	
	public function add_tag_custom_column($nothing,$column,$tag){
		if( $column !== 'mediapass' ) {
			return;
		}
		
		$selected = get_option(MediaPass_Plugin::OPT_PLACEMENT_TAGS);	
		
		$result = !empty($selected) && in_array($tag,$selected);
		
		return $this->dump_protection_markup($result, $tag);
	}
	
	public function add_user_custom_column($nothing,$column,$user){
		if( $column !== 'mediapass' ) {
			return;
		}
		
		$selected = get_option(MediaPass_Plugin::OPT_PLACEMENT_AUTHORS);	
		
		$result = !empty($selected) && in_array($user,$selected);
		
		return $this->dump_protection_markup($result, $user);
	}

	public function add_page_custom_column($column){
		global $post;
		
		if( $column !== 'mediapass' ) {
			return;
		}
		
		$result = MediaPass_ContentHelper::has_existing_protection($post->post_content);
		
		echo $this->dump_protection_markup($result, $post->ID);
	}
	
	public function add_post_custom_column($column) {
		global $post;
		
		if( $column !== 'mediapass' ) {
			return;
		}
		
		$result = MediaPass_ContentHelper::has_existing_protection($post->post_content);
		$included_posts = get_option(MediaPass_Plugin::OPT_INCLUDED_POSTS);
		if (isset($included_posts[$post->ID])){
			$result = $included_posts[$post->ID];
		}
		if (MediaPass_Plugin::has_premium_meta($post)){
			$result = true;
		}
		$excluded_posts = get_option(MediaPass_Plugin::OPT_EXCLUDED_POSTS);
		if (isset($excluded_posts[$post->ID])){
			$result = false;
		}
		
		
		echo $this->dump_protection_markup($result, $post->ID);
	}
	
	public function add_bulk_post_actions() {
		$box = '<div id="mediapass-bulk-post-actions" class="alignleft actions" style="display:none;">';
		$box .= '<select>';
		$box .=  '<option value="none">MediaPass Protection</option>';
		$box .=  '<option value="protect">Protect selected posts</option>';
		$box .=  '<option value="remove">Remove protection from selected posts</option>';
		$box .= '</select>';
		$box .= '</div>';
		
		echo $box;
	}
	
	private function get_current_content_mode() {
		$screen = get_current_screen();
		
		$opts = new stdClass();
		
		if( $screen->taxonomy == 'category' ) {
			$opts->targetAction = self::TARGET_CATEGORY;
		} else if( $screen->taxonomy == 'post_tag' ) {
			$opts->targetAction = self::TARGET_TAG;
		} else if( $screen->base == 'users' ) {
			$opts->targetAction = self::TARGET_AUTHOR;
		} else if( $screen->base == 'pages' ) {
			$opts->targetAction = self::TARGET_PAGE;
		} else {
			$opts->targetAction = self::TARGET_POST;
		}
		
		return $opts;
	}
	
	public function print_list_extensions_script() {
		$opts = $this->get_current_content_mode();
		
		$opts->nonce = wp_create_nonce($opts->targetAction );
		
		$s  = '<script type="text/javascript">';	
		$s .= 'jQuery(document).ready(function($){';
		$s .= 'var opts = '. json_encode($opts) . ';';
		$s .= 'MM.ContentListExtensionsInstance = new MM.WP.ContentListExtensions(opts);';
		$s .= 'MM.ContentEditorExtensionsInstance = new MM.WP.ContentEditorExtensions();';
		$s .= 'MM.ContentListExtensionsInstance.renderBulkActionControl();';
		$s .= '});</script>';
		
		echo $s;
	}
	
	private function dump_protection_markup($result,$id){
		if( $result ) {
			$r = '<div style="height:24px;"><img src="' . plugins_url('/images/protected_icon.png', __FILE__) .'" /><span class="mp-post-protection-indicator protected">Premium</span>';
			$r .= '<a href="#" style="display:none;" class="mp-post-action-protection" mp-post-id="'. $id .'" mp-protection-action="remove">Disable!</a>';
			$r .= '</div>';
			return $r;
		} else {
			$r = '<div style="height: 24px;"><img src="' . plugins_url('/images/unprotected_icon.png', __FILE__) .'" /><span class="mp-post-protection-indicator unprotected">Free</span>';
			$r .= '<a href="#" style="display:none;" class="mp-post-action-protection" mp-post-id="'. $id . '"mp-protection-action="protect">Enable!</a>';
			$r .= '</div>';
			return $r;
		}
	}
	
	public function action_update_content_protection($target,$opt){
		check_ajax_referer( $target, 'nonce');
		
		if( ! $this->can_protect_meta() ) { die(); }
		
		$data = json_decode(stripslashes($_POST['data']),true);
		
		$authors = $data['selectedPosts'];
		$action  = $data['actionRequested'];
		
		$selected = get_option($opt);
		$selected = empty($selected) ? array() : $selected;
		
		$new = array();
		
		if( $action == 'remove' ) {
			foreach($selected as $k => $v) {
				if( ! in_array($v, $authors) ) {
					$new[$k] = $v;
				}
			}
		} else {
			$new = array_unique(array_merge($selected,$authors));
		}
		
		update_option($opt, $new);
		
		$resp = array();
		$resp['result'] = "success";
		$resp['updatedStatus'] = $action == 'remove' ? 'unprotected' : 'protected';
		$resp['updatedPosts'] = $new;
		
		echo json_encode($resp);
		
		die();
	}
	
	public function action_update_tag_protection(){
		$this->action_update_content_protection(self::TARGET_TAG, MediaPass_Plugin::OPT_PLACEMENT_TAGS);
	}
	public function action_update_category_protection() {
		$this->action_update_content_protection(self::TARGET_CATEGORY,MediaPass_Plugin::OPT_PLACEMENT_CATEGORIES);
	}
	public function action_update_author_protection() {
		$this->action_update_content_protection(self::TARGET_AUTHOR,MediaPass_Plugin::OPT_PLACEMENT_AUTHORS);
	}
	public function action_update_page_protection() {
		check_ajax_referer( self::TARGET_PAGE, 'nonce' );
		
		if( ! $this->can_protect_posts() ) { die(); }
		
		$data = json_decode(stripslashes($_POST['data']),true);
		
		$posts  = $data['selectedPosts'];
		$action = $data['actionRequested'];
		
		$processed = array();
		
		foreach($posts as $p) {
			$the_post = get_post($p);
			
			//if( $action == 'remove' && MediaPass_ContentHelper::has_existing_protection($the_post->post_content) ) {
			//	$the_post->post_content = MediaPass_ContentHelper::strip_all_shortcodes($the_post->post_content);
			//}
			
			wp_update_post($the_post);
			
			// If the post belongs to a Premium Category, Tag, or Author but the user marks it as Free then we should exclude it
			// If the post has been previously excluded and the user wants to add it back to be Premium then remove the post from the Exclusion
			
			// Alter the exclusion options
			$excluded_posts = get_option(MediaPass_Plugin::OPT_EXCLUDED_POSTS);
			$included_posts = get_option(MediaPass_Plugin::OPT_INCLUDED_POSTS);
			
			if (MediaPass_Plugin::has_premium_meta($the_post)){
				if ($action == 'remove'){
					// Remove inclusion, this post is now free
					$included_posts[$the_post->ID] = false;
					unset($included_posts[$the_post->ID]);
					// Add an exclusion, so the post is free even if it has premium meta data
					$excluded_posts[$the_post->ID] = true;
				} else {
					// Add this inclusion, this post is now premium
					$included_posts[$the_post->ID] = true;
					// Remove any exclusions
					if (isset($excluded_posts[$the_post->ID])){
						unset($excluded_posts[$the_post->ID]);
					}
				}
			} else { 
				// This will include or not include posts without premium meta data
				if ($action == 'remove'){
					$included_posts[$the_post->ID] = false;
					unset($included_posts[$the_post->ID]);
				} else {
					$included_posts[$the_post->ID] = true;
					// Remove any exclusions
					if (isset($excluded_posts[$the_post->ID])){
						unset($excluded_posts[$the_post->ID]);
					}
				}
			}
			
			update_option( MediaPass_Plugin::OPT_EXCLUDED_POSTS , $excluded_posts );
			update_option( MediaPass_Plugin::OPT_INCLUDED_POSTS , $included_posts );
			
			array_push($processed,$p);
		}
		
		$resp = array();
		$resp['result'] = "success";
		$resp['updatedStatus'] = $action == 'remove' ? 'unprotected' : 'protected';
		$resp['updatedPosts'] = $processed;
		
		echo json_encode($resp);
		
		die();
	}		
	
	public function action_update_post_protection() {
		check_ajax_referer( self::TARGET_POST, 'nonce' );
		
		if( ! $this->can_protect_posts() ) { die(); }
		
		$data = json_decode(stripslashes($_POST['data']),true);
		
		$posts  = $data['selectedPosts'];
		$action = $data['actionRequested'];
		
		$processed = array();
		
		foreach($posts as $p) {
			$the_post = get_post($p);
			
			//if( $action == 'remove' && MediaPass_ContentHelper::has_existing_protection($the_post->post_content) ) {
			//	$the_post->post_content = MediaPass_ContentHelper::strip_all_shortcodes($the_post->post_content);
			//}

			wp_update_post($the_post);
			
			// If the post belongs to a Premium Category, Tag, or Author but the user marks it as Free then we should exclude it
			// If the post has been previously excluded and the user wants to add it back to be Premium then remove the post from the Exclusion
			
			// Alter the exclusion options
			$excluded_posts = get_option(MediaPass_Plugin::OPT_EXCLUDED_POSTS);
			$included_posts = get_option(MediaPass_Plugin::OPT_INCLUDED_POSTS);
			
			if (MediaPass_Plugin::has_premium_meta($the_post)){
				if ($action == 'remove'){
					// Remove inclusion, this post is now free
					$included_posts[$the_post->ID] = false;
					unset($included_posts[$the_post->ID]);
					// Add an exclusion, so the post is free even if it has premium meta data
					$excluded_posts[$the_post->ID] = true;
				} else {
					// Add this inclusion, this post is now premium
					$included_posts[$the_post->ID] = true;
					// Remove any exclusions
					if (isset($excluded_posts[$the_post->ID])){
						unset($excluded_posts[$the_post->ID]);
					}
				}
			} else { 
				// This will include or not include posts without premium meta data
				if ($action == 'remove'){
					$included_posts[$the_post->ID] = false;
					unset($included_posts[$the_post->ID]);
				} else {
					$included_posts[$the_post->ID] = true;
					// Remove any exclusions
					if (isset($excluded_posts[$the_post->ID])){
						unset($excluded_posts[$the_post->ID]);
					}
				}
			}
			
			update_option( MediaPass_Plugin::OPT_EXCLUDED_POSTS , $excluded_posts );
			update_option( MediaPass_Plugin::OPT_INCLUDED_POSTS , $included_posts );
			
			array_push($processed,$p);
		}

		
		$resp = array();
		$resp['result'] = "success";
		$resp['updatedStatus'] = $action == 'remove' ? 'unprotected' : 'protected';
		$resp['updatedPosts'] = $processed;
		
		echo json_encode($resp);
		
		die();
	}
}

?>