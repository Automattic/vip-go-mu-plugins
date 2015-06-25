<?php 
class Skyword_Shortcode {

	function __construct() {
		add_shortcode( 'cf', array($this, 'customfields_shortcode') );
	}
	function customfields_shortcode( $atts, $text ) {
		global $post;
		return get_post_meta( $post->ID, $text, true );
	}
}
global $skyword_custom_shortcodes;
$skyword_custom_shortcodes = new Skyword_Shortcode;