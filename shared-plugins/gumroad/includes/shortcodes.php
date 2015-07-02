<?php
/**
 * Plugin shortcode functions
 *
 * @package GUM
 * @author  Phil Derksen <pderksen@gmail.com>, Nick Young <mycorpweb@gmail.com>
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Function to process the [gumroad] shortcode
 * 
 * @since 1.1.0
 */
function gum_gumroad_shortcode( $attr ) {
	
	$defaults = array(
					'id'     => '',
					'type'   => 'overlay',
					'class'  => '',
					'text'   => 'Buy Now',
					'wanted' => 'false',
					'locale' => 'false'
				);
	
	
	extract( shortcode_atts( $defaults, $attr ) );
	
	gum_load_js( $type );
	
	$attr = array_merge( $defaults, $attr );
	
	if( ! empty( $id ) ) {
		if( $type == 'embed' ) {
			$html = gum_embed_button( $id );
		} else {
			$html = gum_overlay_button( $attr );
		}
		
		$before_html = '';
		$after_html  = '';
		
		$before_html = apply_filters( 'gum_shortcode_before', $before_html );
		$html        = apply_filters( 'gum_shortcode_html', $html );
		$after_html  = apply_filters( 'gum_shortcode_after', $after_html );
		
		return $before_html . $html . $after_html;
	}
}
add_shortcode( 'gumroad', 'gum_gumroad_shortcode' );

