<?php

class MediaPass_Plugin_ContentFilters {
	
	function __construct() {
		//add_filter('the_content', array(&$this,'mp_content_enable_overlay'));
		//add_filter('the_content', array(&$this,'mp_content_placement_category_filter'));
		//add_filter('the_content', array(&$this,'mp_content_placement_tag_filter'));
		//add_filter('the_content', array(&$this,'mp_content_placement_author_filter'));
		
		add_filter('the_content', array(&$this,'mp_content_placement_exemptions'));
	}
		
	function mp_content_placement_category_filter($content) {
		global $post;
		
		if( ! is_single() ) {
			return $content;	
		}
		
		$selected = get_option(MediaPass_Plugin::OPT_PLACEMENT_CATEGORIES);
		$selected = empty($selected) ? array() : $selected;
		
		if( empty($selected) ){
			return $content;
		}

		$post_categories = get_the_category( $post->ID );
		$post_category_ids = ! empty( $post_categories ) ? wp_list_pluck( $post_categories, 'term_id' ) : array();

		$category_overlap = array_intersect($selected, $post_category_ids);
	
		if( ! empty( $category_overlap  ) ) {
			$content = MediaPass_ContentHelper::enable_overlay($content);
		}		
		
		return $content;
	}

	function mp_content_placement_author_filter($content) {
		global $post;
		
		if( ! is_single() ) {
			return $content;	
		}
		
		$selected = get_option(MediaPass_Plugin::OPT_PLACEMENT_AUTHORS);
		$selected = empty($selected) ? array() : $selected;
		
		if( in_array( $post->post_author, $selected ) ) {
			$content = MediaPass_ContentHelper::enable_overlay($content);
		}		
		
		return $content;
	}
	
	function mp_content_placement_tag_filter($content) {
		global $post;
		
		if( ! is_single() ) {
			return $content;	
		}
		
		$selected = get_option(MediaPass_Plugin::OPT_PLACEMENT_TAGS);
		$selected = empty($selected) ? array() : $selected;

		$tags = get_the_tags( $post->ID );
		$tag_ids = ! empty( $tags ) ? wp_list_pluck( $tags, 'term_id' ) : array();

		$tag_overlap = array_intersect($selected, $tag_ids);
		
		if( ! empty( $tag_overlap ) ) {
			$content = MediaPass_ContentHelper::enable_overlay($content);
		}		
		
		return $content;
	}
	
	function mp_content_placement_exemptions($content, $CheckEditPermission = true) {
		global $post;
		
		$excluded_posts = get_option(MediaPass_Plugin::OPT_EXCLUDED_POSTS);
		$included_posts = get_option(MediaPass_Plugin::OPT_INCLUDED_POSTS);
		
		$enable = false;
		if (current_user_can('edit_posts') && !CheckEditPermission){ // when the user is editing the content, we want to show the short codes
			$enable = false;
		// has premium meta
		} else if (MediaPass_Plugin::has_premium_meta($post) && (count($excluded_posts) == 0 || !isset($excluded_posts[$post->ID]))) {
			$enable = true;
		} else if (MediaPass_Plugin::has_premium_meta($post) && isset($excluded_posts[$post->ID])) {
			$enable = false;
		// following doesn't have premium meta
		} else if (isset($included_posts[$post->ID])){
			$enable = $included_posts[$post->ID]; // will take the form of true or false
		} else if (MediaPass_ContentHelper::has_existing_protection($content)){
			$enable = true;
		}
		
		// either enable or disable the short codes / overlay
		if ($enable){
			$content = MediaPass_ContentHelper::enable_overlay($content, "", !$CheckEditPermission);
		} else {
			$content = MediaPass_ContentHelper::strip_all_shortcodes($content);
		}
		
		/*
		if (current_user_can('edit_posts') || (!MediaPass_Plugin::has_premium_meta($post) && (isset($excluded_posts[$post->ID]) || !$included_posts[$post->ID]))){
			$content = MediaPass_ContentHelper::strip_all_shortcodes($content);
		} else if (MediaPass_ContentHelper::has_existing_protection($content)){
			$content = MediaPass_ContentHelper::enable_overlay($content);
		} else {
			$content = MediaPass_ContentHelper::enable_overlay($content);
		}
		*/
		
		return $content;
	}
	
	/* To be removed
	function mp_content_enable_overlay($content){
		global $post;
		
		if (MediaPass_Plugin::has_premium_meta($post) || MediaPass_ContentHelper::has_existing_protection($content)){
			return MediaPass_ContentHelper::enable_overlay($content);
		}
		return $content;
	} */
}
?>