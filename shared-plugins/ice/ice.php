<?php
/*
Plugin Name: Ice Visual Revisions
Description: Adds revision tracking to the visual editor. Modified, added, or deleted text is shown in color, along with the user and time of change.
Version: 1.0-beta2
Author: Automattic, Andrew Ozz, Nikolay Bachiyski

Copyright (c) Automattic, Andrew Ozz

Includes the Ice plugin for TinyMCE released under the GPL version 2 by: The New York Times, CMS Group, Matthew DeLambo

Released under the GPL v.2

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
*/

require_once dirname( __FILE__ ) . '/class-ice-span-filter.php';

class ICE_Visual_Revisions {

	var $tracking_css_classes = array( 'ins' => 'ice-wp-ins', 'del' => 'ice-wp-del' );

	function __construct() {
		add_filter( 'mce_external_plugins', array( $this, 'load_plugins' ) );
		add_filter( 'wp_insert_post_data', array( $this, 'save_revisions_content' ), 1, 2 );
		add_filter( 'the_editor_content', array( $this, 'load_revisions_content' ) , 1 );
		add_action( 'wp_head', array( $this, 'add_preview_css' ) );
	}

	// Add plugins to TinyMCE
	function load_plugins( $plugins ) {
		global $current_screen;
		// Only add to the default editor (for now?), supporting this in other editor instances would require more post-processing
		if ( 'post' != $current_screen->id )
			return $plugins;

		$url = plugin_dir_url( __FILE__ );

		$plugins['ice'] = $url . 'ice/editor_plugin.js';
		$plugins['icerevisions'] = $url . 'icerevisions/editor_plugin.js';

		// Add buttons to TinyMCE
		add_filter( 'mce_buttons', array( $this, 'add_mce_buttons' ) );
		add_filter( 'mce_buttons_2', array( $this, 'add_mce_buttons_2' ) );

		// And to DFW
		add_filter( 'wp_fullscreen_buttons', array( $this, 'fullscreen_button' ) );

		// Add Ice specific editor settings
		add_filter( 'tiny_mce_before_init', array( $this, 'mce_settings' ), 10, 2 );

		// Output the JS in the footer
		add_action( 'admin_print_footer_scripts', array( $this, 'add_js' ) );

		return $plugins;
	}

	function add_mce_buttons( $buttons ) {
		return array_merge( $buttons, array(
			'|',
			'iceaccept',
			'icereject',
			'|',
			'ice_toggleshowchanges'
		));
	}

	function add_mce_buttons_2( $buttons ) {
		return array_merge( $buttons, array(
			'|',
			'iceacceptall',
			'icerejectall'
		));
	}

	function add_js() {
		// Ice throws range errors when switching to fullscreen before the MCE instance is initialized.
		// This hides the "fullscreen" button in the HTML editor if it's loaded first.
		?>
		<script type="text/javascript">
		jQuery(document ).ready(function( $){
			if ( typeof(tinymce ) != 'undefined' && $( '#wp-content-wrap' ).hasClass( 'html-active' ) ) {
				var css = $( '<style type="text/css">#wp-content-wrap #qt_content_fullscreen{display:none}</style>' );
				$(document.head ).append(css );

				$( '#content-tmce' ).one( 'click', function(){
					css.remove();
				});
			}
		});
		</script>
		<?php
	}

	// $settings is a PHP associative array containing all init strings: $settings['mce_setting_name'] = 'setting string';
	function mce_settings( $settings, $editor_id ) {
		global $current_user, $post;

		if ( 'content' != $editor_id )
			return $settings;

		$new_post = $post->post_status == 'auto-draft';

		/*
		Any of the following can be set by using the 'mce_ice_settings' filter.
		Note that the array is json encoded before adding it to the MCE settings (default values shown ).
			'deleteTag' => 'span', **
			'insertTag' => 'span', **
			'deleteClass' => 'ice-wp-del', **
			'insertClass' => 'ice-wp-ins', **
			'changeIdAttribute' => 'data-cid',
			'userIdAttribute' => 'data-userid',
			'userNameAttribute' => 'data-username',
			'timeAttribute' => 'data-time',
			'preserveOnPaste' => 'p',
			'isTracking' => true,
			'contentEditable' => true,
			'css' => 'css/ice.css',
			'manualInit' => false,
			'user' => array{ 'name' => 'Some Name', 'id' => rand() }
		*/
		$ice_settings = array(
			'user' => array(
				'name' => esc_attr( $current_user->display_name ),
				'id' => $current_user->ID
			),
			'manualInit' => true,
			'isTracking' => !$new_post,
			'deleteClass' => $this->tracking_css_classes['del'],
			'insertClass' => $this->tracking_css_classes['ins'],
			'css' => '../icerevisions/css/ice-revisions.css'
		);

		$ice_settings = apply_filters( 'mce_ice_settings', $ice_settings );
		$settings['ice'] = json_encode( $ice_settings );

		return $settings;
	}

	// save the content with revisions as meta
	function save_revisions_content( $data, $postarr ) {

		if ( empty( $postarr['post_ID'] ) )
			return $data;

		// save the content in post_meta
		$post_id = (int)$postarr['post_ID'];
		update_post_meta( $post_id, '_ice_revisions_content', $data['post_content'] );

		// remove the change tracking spans
		$data['post_content'] = $this->strip_spans( $data['post_content'] );

		return $data;
	}

	// load the content with revisions when post is published
	function load_revisions_content( $content ) {
		global $post, $post_ID, $pagenow;

		if ( !isset( $post ) || !isset( $post_ID ) || !current_user_can( 'edit_post', $post_ID ) )
			return $content;

		$meta = get_post_meta( $post_ID, '_ice_revisions_content' );
		$content = empty( $meta )? $content : (string)array_pop( $meta );

		return $content;
	}

	// add styles to the change tracking when previewing a post
	function add_preview_css() {
		if ( is_preview() ) {
			?>
			<style type="text/css">
			<?php echo $this->tracking_css_classes['ins']; ?>,
			<?php echo $this->tracking_css_classes['del']; ?> {
				-webkit-border-radius: 3px;
				border-radius: 3px;
				color: #000;
				padding: 1px 0 2px;
			}
			<?php echo $this->tracking_css_classes['ins']; ?> {
				background-color: #e5ffcd;
			}
			<?php echo $this->tracking_css_classes['del']; ?> {
				text-decoration: line-through;
				color: #555;
				background-color: #e8e8e8;
			}
			</style>
			<?php
		}
	}

	// show "track changes" button in fullscreen mode
	function fullscreen_button( $buttons ) {
		if ( !user_can_richedit() )
			return $buttons;

		?>
		<style type="text/css" scoped="scoped">
		#wp_fs_ice_toggleshowchanges span {background: url("<?php echo plugin_dir_url( __FILE__ ) . 'ice/img/ice-showchanges.png'; ?>");
		</style>
		<?php

		$buttons['ice_toggleshowchanges'] = array( 'title' => __( 'Show Track Changes', 'mce-revisions' ), 'onclick' => "tinymce.execCommand( 'ice_toggleshowchanges' );", 'both' => false );
		return $buttons;
	}

	// strip change tracking spans, expects slashed $content
	function strip_spans( $content ) {
		// short-circuit if no tracking spans
		if ( strpos( $content, 'data-userid=' ) === false && strpos( $content, 'ice-wp-' ) === false )
			return $content;

		$span_filter = new ICE_Span_Filter( stripslashes( $content ), $this->tracking_css_classes );
		return addslashes( $span_filter->filter() );
	}
}

new ICE_Visual_Revisions;
