<?php /*

**************************************************************************

Plugin Name:  Gallery Style Cleanup
Description:  This plugin replaces the default [gallery] shortcode with a valid XHTML version. The only drawback is that the gallery CSS always gets outputted, even if there isn't a gallery in the page.
Author:       Alex M. of Automattic
Author URI:   http://automattic.com/

**************************************************************************

Based on concepts from the following plugins:

* http://wordpress.org/extend/plugins/gallery-shortcode-style-to-head/
* http://wordpress.org/extend/plugins/cleaner-gallery/

**************************************************************************/

class Gallery_Style_Cleanup {

	// Initalize the plugin by registering the hooks
	function __construct() {

		// Replace the [gallery] shortcode output
		add_filter( 'post_gallery', array(&$this, 'gallery_shortcode'), 10, 2 );

		// CSS is moved to the head (always outputted as there's no reliable way to look forward at $posts)
		add_action( 'wp_head', array(&$this, 'gallery_style') );
	}


	// This is based on WordPress' gallery_shortcode() with the CSS removed and widths added
	function gallery_shortcode( $unused, $attr ) {
		global $post;

		static $instance = 0;
		$instance++;

		// We're trusting author input, so let's at least make sure it looks like a valid orderby statement
		if ( isset( $attr['orderby'] ) ) {
			$attr['orderby'] = sanitize_sql_orderby( $attr['orderby'] );
			if ( !$attr['orderby'] )
				unset( $attr['orderby'] );
		}

		extract(shortcode_atts(array(
			'order'      => 'ASC',
			'orderby'    => 'menu_order ID',
			'id'         => $post->ID,
			'itemtag'    => 'dl',
			'icontag'    => 'dt',
			'captiontag' => 'dd',
			'columns'    => 3,
			'size'       => 'thumbnail',
			'include'    => '',
			'exclude'    => ''
		), $attr));

		$id = intval($id);
		if ( 'RAND' == $order )
			$orderby = 'none';

		if ( !empty($include) ) {
			$include = preg_replace( '/[^0-9,]+/', '', $include );
			$_attachments = get_posts( array('include' => $include, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby) );

			$attachments = array();
			foreach ( $_attachments as $key => $val ) {
				$attachments[$val->ID] = $_attachments[$key];
			}
		} elseif ( !empty($exclude) ) {
			$exclude = preg_replace( '/[^0-9,]+/', '', $exclude );
			$attachments = get_children( array('post_parent' => $id, 'exclude' => $exclude, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby) );
		} else {
			$attachments = get_children( array('post_parent' => $id, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby) );
		}

		if ( empty($attachments) )
			return '';

		if ( is_feed() ) {
			$output = "\n";
			foreach ( $attachments as $att_id => $attachment )
				$output .= wp_get_attachment_link($att_id, $size, true) . "\n";
			return $output;
		}

		$itemtag = tag_escape($itemtag);
		$captiontag = tag_escape($captiontag);
		$columns = intval($columns);
		$itemwidth = $columns > 0 ? floor(100/$columns) : 100;

		$selector = "gallery-{$instance}";

		$output = apply_filters('gallery_style', "
			<div class='gallery galleryid-{$id}'>");

		$i = 0;
		foreach ( $attachments as $id => $attachment ) {
			$link = isset($attr['link']) && 'file' == $attr['link'] ? wp_get_attachment_link($id, $size, false, false) : wp_get_attachment_link($id, $size, true, false);

			$output .= "<{$itemtag} class='gallery-item' style='width:{$itemwidth}%'>";
			$output .= "
				<{$icontag} class='gallery-icon'>
					$link
				</{$icontag}>";
			if ( $captiontag && trim($attachment->post_excerpt) ) {
				$output .= "
					<{$captiontag} class='gallery-caption'>
					" . wptexturize($attachment->post_excerpt) . "
					</{$captiontag}>";
			}
			$output .= "</{$itemtag}>";
			if ( $columns > 0 && ++$i % $columns == 0 )
				$output .= '<br style="clear: both" />';
		}

		$output .= "
				<br style='clear: both;' />
			</div>\n";

		return $output;
	}


	// Output the generic CSS for galleries
	function gallery_style() {
		echo apply_filters( 'gallery_style', '<style type="text/css">.gallery { margin: auto; } .gallery .gallery-item { float: left; margin-top: 10px; text-align: center; } .gallery img { border: 2px solid #cfcfcf; } .gallery .gallery-caption { margin-left: 0; }</style>' ) . "\n";
	}
}

add_action( 'init', 'Gallery_Style_Cleanup', 5 );
function Gallery_Style_Cleanup() {
	global $Gallery_Style_Cleanup;
	$Gallery_Style_Cleanup = new Gallery_Style_Cleanup();
}

?>