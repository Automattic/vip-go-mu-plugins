<?php
/*
Plugin Name: The Attached Image
Plugin URI: http://return-true.com/2008/12/wordpress-plugin-the-attached-image/
Description: Display the first image attached to a post. Use the_attached_image() in the post loop. Order can be changed using menu order via the WP gallery. Based on the post image WordPress plugin by Kaf Oseo.
Version: 2.4.2-WPCOM
Author: Paul Robinson
Author URI: http://return-true.com

	Copyright (c) 2008, 2009 Paul Robinson (http://return-true.com)
	The Attached Image is released under the GNU General Public License (GPL)
	http://www.gnu.org/licenses/gpl.txt

	This is a WordPress 2 plugin (http://wordpress.org).
	Based on the post image WordPress plugin by Kaf Oseo.
	Comments are there for those who wish to learn or make mods.
*/


/* Before we start let's make the options page in the WP admin */

add_action('admin_menu', 'att_add_options');

function att_add_options() {
	add_theme_page('The Attached Image Options', 'The Attached Image', 8, 'attachedoptions', 'att_options_page');	
}

function att_options_page() {
    // variables for the field and option names 
    $opt_name = array('img_size' =>'att_img_size',
					  'css_class' => 'att_css_class',
					  'img_width' => 'att_img_width',
					  'img_height' => 'att_img_height',
					  'default_img' => 'att_default_img',
					  'href' => 'att_href',
					  'alt' => 'att_alt',
					  'link_title' => 'att_link_title',
					  'img_tag' => 'att_img_tag',
					  'echo' => 'att_echo',
					  'href_rel' => 'att_href_rel',
					  'img_order' => 'att_img_order');
    $hidden_field_name = 'att_submit_hidden';

    // Read in existing option value from database
    $opt_val = array('img_size' => get_option( $opt_name['img_size'] ),
					 'css_class' => get_option( $opt_name['css_class'] ),
					 'img_width' => get_option( $opt_name['img_width'] ),
					 'img_height' => get_option( $opt_name['img_height'] ),
					 'default_img' => get_option( $opt_name['default_img'] ),
					 'href' => get_option( $opt_name['href'] ),
					 'alt' => get_option( $opt_name['alt'] ),
					 'link_title' => get_option( $opt_name['link_title'] ),
					 'img_tag' => get_option( $opt_name['img_tag']),
					 'echo' => get_option( $opt_name['echo']),
					 'href_rel' => get_option( $opt_name['href_rel']),
					 'img_order' => get_option( $opt_name['img_order']));

    // See if the user has posted us some information
    // If they did, this hidden field will be set to 'Y'
    if(isset($_POST[ $hidden_field_name ]) && $_POST[ $hidden_field_name ] == 'Y' ) {
        // Read their posted value
        $opt_val = array('img_size'    => sanitize_text_field( $_POST[ $opt_name['img_size'] ] ),
						 'css_class'   => sanitize_text_field( $_POST[ $opt_name['css_class'] ] ),
						 'img_width'   => sanitize_text_field( $_POST[ $opt_name['img_width'] ] ),
						 'img_height'  => sanitize_text_field( $_POST[ $opt_name['img_height'] ] ),
						 'default_img' => sanitize_text_field( $_POST[ $opt_name['default_img'] ] ),
						 'href'        => sanitize_text_field( $_POST[ $opt_name['href'] ] ),
						 'alt'         => sanitize_text_field( $_POST[ $opt_name['alt'] ] ),
						 'link_title'  => sanitize_text_field( $_POST[ $opt_name['link_title'] ] ),
						 'img_tag'     => sanitize_text_field( $_POST[ $opt_name['img_tag'] ] ),
						 'echo'        => sanitize_text_field( $_POST[ $opt_name['echo'] ] ),
						 'href_rel'    => sanitize_text_field( $_POST[ $opt_name['href_rel'] ] ),
						 'img_order'   => sanitize_text_field( $_POST[ $opt_name['img_order'] ] ));

        // Save the posted value in the database
        update_option( $opt_name['img_size'], $opt_val['img_size'] );
		update_option( $opt_name['css_class'], $opt_val['css_class'] );
		update_option( $opt_name['img_width'], $opt_val['img_width'] );
		update_option( $opt_name['img_height'], $opt_val['img_height'] );
		update_option( $opt_name['default_img'], $opt_val['default_img'] );
		update_option( $opt_name['href'], $opt_val['href'] );
		update_option( $opt_name['alt'], $opt_val['alt'] );
		update_option( $opt_name['link_title'], $opt_val['link_title'] );
		update_option( $opt_name['img_tag'], $opt_val['img_tag'] );
		update_option( $opt_name['echo'], $opt_val['echo'] );
		update_option( $opt_name['href_rel'], $opt_val['href_rel'] );
		update_option( $opt_name['img_order'], $opt_val['img_order'] );

        // Put an options updated message on the screen

?>

<div id="message" class="updated fade">
  <p><strong>
    <?php esc_html_e('Options saved.', 'att_trans_domain' ); ?>
    </strong></p>
</div>

<?php
	}
?>
<div class="wrap">
<h2><?php esc_html_e( 'The Attached Image', 'att_trans_domain' ); ?></h2>
<?php
if(isset($_GET['wpatt-page']) && $_GET['wpatt-page'] == 'docs') {
	require_once('att_docs.php'); //select the documentation.
} else {
	require_once('att_options.php'); //select the options entry page.	
}
?>
</div>
<?php
}


function the_attached_image($args='') {
	global $post;
		
	parse_str($args); //Tenutive support for the old options method. Please use the options page, it's much neater.
	
	
	if( !isset($img_size) && !get_option('att_img_size') )
		$img_size = 'thumbnail';
	elseif(isset($img_size))
		$img_size = $img_size;
	else
		$img_size = get_option('att_img_size');
		
	if( !isset($css_class) && !get_option('att_css_class') )
		$css_class = 'attached-image';
	elseif(isset($css_class))
		$css_class = $css_class;
	else
		$css_class = get_option('att_css_class');
		
	if( !isset($img_tag) && !get_option('att_img_tag') )
		$img_tag = true;
    elseif(isset($img_tag))
		$img_tag = $img_tag;
	else 
		$img_tag = get_option('att_img_tag');
		
	if( !isset($echo) && !get_option('att_echo') ) 
		$echo = true;
    elseif(isset($echo))
		$echo = $echo; 
	else
		$echo = get_option('att_echo');
		
	if( !isset($href) )
		$href = false;
		
	if( !isset($link) && !get_option('att_href') ) {
		$link = 'none';
    } elseif(isset($link)) {
		$link = $link;
                
        if($link != 'none') {
			$href = true;
		} else {
			$href = false;	
		}
	} else {
		$link = get_option('att_href');
		
		if($link != 'none') {
			$href = true;
		} else {
			$href = false;	
		}
		
		if(get_post_meta($post->ID, 'att_custom_link', true) != "") {
			$href = true;
			$link = "custom"; //override the link to custom because the custom field is set.
		}
		
	}
	
	if( !isset($alt) && !get_option('att_alt') ) {
		$alt = 'image-name'; 
	} elseif(isset($alt)) {
		$alt = $alt;
	} else {
		if(get_post_meta($post->ID, 'att_custom_alt', true)) {
			$alt = 'custom';
		} else {
			$alt = get_option('att_alt');
		}
	}
		
	if( !isset($link_title) && !get_option('att_link_title') ) {
		$link_title = 'image-name'; 
	} elseif(isset($link_title)) {
		$link_title = $link_title;
	} else {
		if(get_post_meta($post->ID, 'att_custom_link_title', true)) {
			$link_title = 'custom';
		} else {
			$link_title = get_option('att_link_title');
		}
	}
	
	if( !isset($default) && !get_option('att_default_img') )
		$default = false;
	elseif(isset($default))
		$default = $default;
	else 
		$default = get_option('att_default_img');
		
	if( !isset($width) && !get_option('att_img_width'))
		$width = false;
	elseif(isset($width))
		$width = $width;
	else
		$width = get_option('att_img_width');
		
	if( !isset($height) && !get_option('att_img_height'))
		$height = false;
	elseif(isset($height))
		$height = $height;
	else 
		$height = get_option('att_img_height');
		
	if( !isset($image_order) && !get_option('att_img_order') ) {
		$image_order = 1;
	} elseif(isset($image_order)) {
		if(is_numeric($image_order))
			$image_order = intval($image_order);
		else
			$image_order = 1;
	} else {
		$image_order = get_option('att_img_order');
		if(is_numeric($image_order))
			$image_order = intval($image_order);
		else
			$image_order = 1;
	}
	
	if( !isset($rel) && !get_option('att_href_rel') )
		$rel = false; 
    elseif(isset($rel))
		$rel = $rel;
	else
		$rel = get_option('att_href_rel');
	
	if($custom_img_meta = get_post_meta($post->ID, 'att_custom_img', true)) {
			$attachments = array(get_post($custom_img_meta));
			$custom_img = true;
	} else {
			$custom_img = false;	
	}
	
	//If WP's post array is empty we can't do anything but only if a custom image hasn't been set.
	if(empty($post) && $custom_img === false && get_post_meta($post->ID, 'att_default_pic', true) == "")
		return false;
	
	if($custom_img === false) {
		//Get the attachments for the current post. Limit to one and order by the menu_order so that the image shown can be changed by the WP gallery.
		if(function_exists('wp_enqueue_style')) {
			$attachments = get_children(array('post_parent' => $post->ID,
											  'post_status' => 'inherit',
											  'post_type' => 'attachment',
											  'post_mime_type' => 'image',
											  'order' => 'ASC',
											  'orderby' => 'menu_order ID'));
		} else { 
			//WP2.5 Compat...
			$attachments = get_children('post_parent='.$post->ID.'&post_type=attachment&post_mime_type=image&orderby="menu_order ASC, ID ASC"');
		};
	}
	
	$m_width = get_post_meta($post->ID, 'att_width', true);
	$m_height = get_post_meta($post->ID, 'att_height', true);
	
	if(!$width === false && !$m_width == false && !$height === false) {
		$width = $m_width;
		$height = false;
	} elseif(!$height === false && !$m_height == false && !$width === false) {
		$height = $m_height;
		$width = false;
	} else {
		$width = (!$m_width == false) ? $m_width : $width;
		$height = (!$m_height == false) ? $m_height : $height;
	}
	// ^^ Check for custom fields. To stop function call follow through we need to cancel out the $width or the $height if only one has been set by meta.
	
	if(empty($attachments)) { //If attachments is empty then we should check for a default image via meta or via function call.
		if($pic_meta = get_post_meta($post->ID, 'att_default_pic', true)) {
			$default = $pic_meta;
		} elseif($default === false) {
			return false;
		}
		
		$image = '<img src="' . esc_url( get_bloginfo('url') . $default ) . '" class="' . esc_attr( $css_class ) . '" ';
		
		//get the alt text
		$alt_text = get_alt($alt);
		if(!empty($alt_text)) {
			$image .= 'alt="' . esc_attr( $alt_text ) . '" ' ;
		}
				
		if(stristr($link, 'post') === false && stristr($link, 'custom') === false) {
			//get the title text
			$img_title_text = get_title($link_title);
			$image .= 'title="' . esc_attr( $img_title_text ) . '" ';
		}
		
		if($height === false && $width === false) { //Sort out the height & width depending on what has been supplied by the user.
			//Get the image size using ABSPATH. Suppresion of errors via @ is not expensive despite what you have heard. It's the generation of the error.
			$default_info = @getimagesize(substr(ABSPATH,0,-1).$default); 
			$image .= !empty($default_info[3]) ? $default_info[3].' />' : ' />'; 
		} else {
			if(!$width === false && !$height === false) {
				$image .= 'width="' . esc_attr( $width ) . '" height="' . esc_attr( $height ) . '" />';
			} elseif(!$width === false) {
				$image .= 'width="' . esc_attr( $width ) . '" />';
			} elseif(!$height === false) {
				$image .= 'height="' . esc_attr( $height ) . '" />';
			}
		}
		
		if($href === true || $href == 'true') { //Do you want a href & where should it point.
			switch ($link) {
				case 'post' :
					$a_href = '<a href="' . esc_url( get_permalink($post->ID) ) . '" title="' . esc_attr( $post->post_title ) . '">%%%</a>';
				break;
				case 'custom' :
					$link_meta = get_post_meta($post->ID, 'att_custom_link', true); //no need to check since it wouldn't be here if it were empty.
					$a_href = '<a href="' . esc_url( $link_meta ) . '">%%%</a>';
				break;
			}
		}
		
		if(isset($a_href) && !empty($a_href)) { //If they wanted a link put the img tag into it.
			$image = str_replace('%%%', $image, $a_href);
		}
		
	}
	
	if(!isset($image) && empty($image)) { //Gets the correct image depending upon whether or not $image has been set or not.
	
		$i = 0;
		
		foreach($attachments as $id => $attachment) :
			$i++;
			if($i == $image_order) :
				$attachment = $attachment;
				break;
			endif;
		endforeach;

	
		//$attachment = current($attachments);

		$img_url = wp_get_attachment_url($attachment->ID); //Get URL to attachment

		//Pick the right size & get it via WP. If a custom height & width was set cancel out WP's.
		if ( in_array($img_size, array('thumbnail', 'medium', 'large')) ) {
			if ( $intermediate = image_downsize($attachment->ID, $img_size) ) {
				$img_url = $intermediate[0];

				if($width === false && $height === false) {
					$width = $intermediate[1];
					$height = $intermediate[2];
				}
			}
		} elseif ( $img_size == 'full' ) {
			//Get the image's size since it will make our HTML invalid & the code won't close the img tag without a width & height.
			$split_pos = strpos($img_url, 'wp-content');
			$split_len = (strlen($img_url) - $split_pos);
			$abs_img_url = substr($img_url, $split_pos, $split_len);
			$full_info = @getimagesize(ABSPATH.$abs_img_url);
			if($width === false && $height === false) {
				$width = $full_info[0];
				$height = $full_info[1];
			}
		} 

		if($img_tag === true || $img_tag == 'true') { //Do they want an image tag along with setting the height & width.
			$image = '<img src="' . esc_url( $img_url ) . '" class="' . esc_attr( $css_class ) . '"';

			$alt_text = get_alt($alt); //Get alt text
			if(!empty($alt_text)) {
				$image .= ' alt="' . esc_attr( $alt_text ) . '"';
			}

			if(stristr($link, 'none')) {
				$title_text = get_title($link_title);
				$image .= ' title="' . esc_attr( $title_text ) . '"';
			}

			if(!$width === false && !$height === false) {
				$image .= ' width="' . esc_attr( $width ) . '" height="' . esc_attr( $height ) . '" />';
			} elseif(!$width === false) {
				$image .= ' width="' . esc_attr( $width ) . '" />';
			} elseif(!$height === false) {
				$image .= ' height="' . esc_attr( $height ) . '" />';
			} else {
				$image .= ' />';
			}
		} else { //You don't want a img tag then? Well heres the URL.
			$image = $img_url;
		}
		
		if($href === true || $href == 'true') { //Do you want a href & where should it point.
			//First lets figure out what title text they want...
			
			$a_title_text = get_title($link_title);
			
			switch ($link) {
				case 'post' :
					$a_href = '<a href="' . esc_url( get_permalink($post->ID) ) . '" title="' . esc_attr( $a_title_text ) . '">%%%</a>';
				break;
				case 'attachment' :
					$a_href = '<a href="' . esc_url( get_attachment_link($attachment->ID) ) . '" title="' . esc_attr( $a_title_text ) . '">%%%</a>';
				break;
				case 'custom' :
					$link_meta = get_post_meta($post->ID, 'att_custom_link', true); //no need to check since it wouldn't be here if it were empty.
					$a_href = '<a href="' . esc_url( $link_meta ) . '" title="' . esc_attr( $a_title_text ) . '">%%%</a>';
				break;
				default :
					if(!$rel === false) {
						$a_href = '<a href="' . esc_url( wp_get_attachment_url($attachment->ID) ) . '" rel="' . esc_attr( $rel ) . '" title="' . esc_attr( $a_title_text ) . '">%%%</a>';
					} else {
						$a_href = '<a href="' . esc_url( wp_get_attachment_url($attachment->ID) ) . '" title="' . esc_attr( $a_title_text ) . '">%%%</a>';
					}
				break;
			}
		}
		if(isset($a_href) && !empty($a_href)) { //If they wanted a link put the img tag into it.
			$image = str_replace('%%%', $image, $a_href);
		}
	
	}
	
	if($echo === true || $echo == 'true') //Echo it?
		echo $image;
	else //Ok we'll return it instead.
		return $image;
	
}

function get_title($link_title) {
	global $attachment, $default, $post;

	switch($link_title) {
		case 'image-name' :
			if(!empty($attachment->post_title)) {//if this is a default image we won't be able to use the $attachments object
				$title_text = $attachment->post_title; 
			} else {
				$parts = pathinfo($default); //use the filename instead
				$title_text = $parts['filename'];
			}
		break;
		case 'image-description' :
			if(empty($attachment->post_content) && empty($attachment->post_title)) { //If we cant find both then it's the default again
				$parts = pathinfo($default); //use the filename instead
				$title_text = $parts['filename'];
			} else {
				$title_text = (!empty($attachment->post_content)) ? $attachment->post_content : $attachment->post_title;
			}
		break;
		case 'post-title' :
			$title_text = $post->post_title;
		break;
		case 'post-slug' :
			$title_text = $post->post_name;
		break;
		case 'custom' :
			//if it doesn't match any of those it must be custom.
			$title_text = str_replace('"', '', get_post_meta($post->ID, 'att_custom_link_title', true));
		break;
	}	
	  
	return $title_text;
}

function get_alt($alt) {
	global $attachment, $default, $post;
	
	switch($alt) {
		case 'image-name' :
			if(empty($attachment->post_title)) {
				$parts = pathinfo($default);
				$alt_text = $parts['filename'];
			} else {
				$alt_text = $attachment->post_title;
			}
		break;
		case 'image-description' :
			if(empty($attachment->post_content) && empty($attachment->post_title)) { //If we cant find both then it's the default again
				$parts = pathinfo($default); //use the filename instead
				$alt_text = $parts['filename'];
			} else {
				$alt_text = (!empty($attachment->post_content)) ? $attachment->post_content : $attachment->post_title;
			}
		break;
		case 'post-title' :
			$alt_text = $post->post_title;
		break;
		case 'post-slug' :
			$alt_text = $post->post_name;
		break;
		case 'custom' :
			//if it doesn't match any of those it must be custom.
			$alt_text = str_replace('"', '', get_post_meta($post->ID, 'att_custom_alt', true));
		break;
	}
	
	return $alt_text;
}
?>
