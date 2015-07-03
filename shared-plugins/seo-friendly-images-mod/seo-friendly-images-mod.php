<?php 

/*
Plugin Name: SEO Friendly Images
Plugin URI: http://www.prelovac.com/vladimir/wordpress-plugins/seo-friendly-images
Description: Automatically adds alt and title attributes to all your images. Improves traffic from search results and makes them W3C/xHTML valid as well.
Version: 2.4.4-mod
Author: Vladimir Prelovac
Author URI: http://www.prelovac.com/vladimir

To-Do: 
- localization

Changes for WordPress.com VIP integrations:
- utilizing settings_fields()
- removing images and cleaning up information section in options page
- disabling version notifications

Copyright 2008	Vladimir Prelovac  vprelovac@gmail.com

*/

$seo_friendly_images_localversion="2.4.4-mod";

function seo_friendly_images_add_pages()
{
	add_options_page('SEO Friendly Images options', 'SEO Friendly Images', 'administrator', 'seo_friendly_images_options', 'seo_friendly_images_options_page');
	add_action( 'admin_init', 'seo_friendly_images_register_settings', 10 );
}

function seo_friendly_images_register_settings() {
	$options = array(
						'seo_friendly_images_alt',
						'seo_friendly_images_title',
						'seo_friendly_images_override',
					);
	foreach ( $options as $option_name ) {
		register_setting( 'seo_friendly_images_settings', $option_name );
	}
}
		
// Options Page
function seo_friendly_images_options_page()
{ 

	global $seo_friendly_images_localversion;
	
	/*
	 * Disabled version check for VIP usage
	$status=seo_friendly_images_getinfo();
			
	$theVersion = $status[1];
	$theMessage = $status[3];	
	
	if( (version_compare(strval($theVersion), strval($seo_friendly_images_localversion), '>') == 1) )
	{
		$msg = 'Latest version available '.' <strong>'.$theVersion.'</strong><br />'.$theMessage;	
			_e('<div id="message" class="updated fade"><p>' . $msg . '</p></div>');			
	}
	*/
	
	// If form was submitted
	if ( 'seo_friendly_images_options' == $_REQUEST['option_page']  && true == $_REQUEST['updated'] ) {
			check_admin_referer('seo_friendly_images_options');
			$msg_status = 'SEO Friendly Images options saved.';
			  // Show message
			 _e('<div id="message" class="updated fade"><p>' . $msg_status . '</p></div>');
	}
	
		// Fetch code from DB
		$alt_text = get_option('seo_friendly_images_alt');
		$title_text = get_option('seo_friendly_images_title');
		$override =( get_option('seo_friendly_images_override') == 1 ) ? true : false;
			
	// Configuration Page

	
	echo <<<END
<div class="wrap" style="max-width:950px !important;">
	<h2>SEO Friendly Images</h2>
				
	<div id="poststuff" style="margin-top:10px;">
	
	<div id="sideblock" style="float:right;width:220px;margin-left:10px;"> 
		 <h2>Information</h2>
		 <div id="dbx-content" style="text-decoration:none;">
			<a style="text-decoration:none;" href="http://www.prelovac.com/vladimir/wordpress-plugins/seo-image"> SEO Friendly Images Home</a><br /><br />
			<a style="text-decoration:none;" href="http://wordpress.org/extend/plugins/seo-image/">WordPress Plugin Page</a><br /><br />
			<a style="text-decoration:none;" href="http://www.prelovac.com/vladimir/forum"> Support and Help</a><br />
 		</div>
 	</div>
	
	 <div id="mainblock" style="width:710px">
	 
		<div class="dbx-content">
		 	<form name="sfiform" action="options.php" method="post">
END;
	settings_fields( 'seo_friendly_images_settings' );
	$checked = checked( $override, 1, false );
	echo <<<END
   				<h2>General Options</h2>
   
 <p>SEO Friendly Images automatically adds alt and title attributes to all your images in all your posts specified by parameters below.</p>							
<p>You can enter any text in the field including two special tags:</p>
<ul>
<li>%title - replaces post title</li>
<li>%name - replaces image file name (without extension)</li>
<li>%category - replaces post category</li>
</ul>



<h4>Images options</h4>


<div>
<label for="alt_text"><b>ALT</b> attribute (example: %name %title)</label><br>
<input style="border:1px solid #D1D1D1; width:165px;"  id="alt_text" name="seo_friendly_images_alt" value="$alt_text"/>
</div><br>

<div>
<label for="title_text"><b>TITLE</b> attribute (example: %name photo)</label><br>
<input style="border:1px solid #D1D1D1;	 width:165px;"	id="title_text" name="seo_friendly_images_title" value="$title_text"/>
</div>

<br />
<div><input id="check1" type="checkbox" name="seo_friendly_images_override" value="1" $checked />
<label for="check1">Override default Wordpress alt (recommended)</label></div> 




<br/><br /><p>Example:<br />
In a post titled Car Pictures there is a picture named Ferrari.jpg<br /><br />
Setting alt attribute to "%name %title" will produce alt="Ferrari Car Pictures"<br />
Setting title attribute to "%name photo" will produce title="Ferrari photo"</p>

<div class="submit"><input type="submit" name="Submit" value="Update options" /></div>
			</form>
		</div>
					
		<br/><br/><h3>&nbsp;</h3>	
	 </div>

	</div>
	
<h5>a plugin by <a href="http://www.prelovac.com/vladimir/">Vladimir Prelovac</a></h5>
</div>
END;
	
}

// Add Options Page
add_action('admin_menu', 'seo_friendly_images_add_pages');


function remove_extension($name) {
	return preg_replace('/(.+)\..*$/', '$1', $name);
} 

function seo_friendly_images_process($matches) {
	
		global $post;

	
		$title = $post->post_title;

		$alttext_rep = get_option('seo_friendly_images_alt');
		$titletext_rep = get_option('seo_friendly_images_title');
		$override= get_option('seo_friendly_images_override');
			
		# take care of unsusal endings
		$matches[0]=preg_replace('|([\'"])[/ ]*$|', '\1 /', $matches[0]);					
		
		
		### Normalize spacing around attributes.
		$matches[0] = preg_replace('/\s*=\s*/', '=', substr($matches[0],0,strlen($matches[0])-2));
		### Get source.
		
		preg_match('/src\s*=\s*([\'"])?((?(1).+?|[^\s>]+))(?(1)\1)/', $matches[0], $source);
	
		
		$saved=$source[2];
		
		### Swap with file's base name.
		preg_match('%[^/]+(?=\.[a-z]{3}(\z|\?))%', $source[2], $source);
		### Separate URL by attributes.
		$pieces = preg_split('/(\w+=)/', $matches[0], -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		### Add missing pieces.
		
		$cats=get_the_category();
		if (!in_array('title=', $pieces)) {
			$titletext_rep=str_replace("%title", $post->post_title, $titletext_rep);
			if ( isset( $source[0] ) )
				$titletext_rep=str_replace("%name", $source[0], $titletext_rep);
			$titletext_rep=str_replace("%category", $cats[0]->slug, $titletext_rep);
			
		
			$titletext_rep=str_replace('"', '', $titletext_rep);
			$titletext_rep=str_replace("'", "", $titletext_rep);
			
			$titletext_rep=str_replace("_", " ", $titletext_rep);
			$titletext_rep=str_replace("-", " ", $titletext_rep);
			//$titletext_rep=ucwords(strtolower($titletext_rep));
	
			
		
			array_push($pieces, ' title="' . $titletext_rep . '"');
		}
		if (!in_array('alt=', $pieces) ) {
			$alttext_rep=str_replace("%title", $post->post_title, $alttext_rep);
			if ( isset( $source[0] ) )
				$alttext_rep=str_replace("%name", $source[0], $alttext_rep);
			$alttext_rep=str_replace("%category", $cats[0]->slug, $alttext_rep);
			
			$alttext_rep=str_replace("\"", "", $alttext_rep);
			$alttext_rep=str_replace("'", "", $alttext_rep);
			
			$alttext_rep=(str_replace("-", " ", $alttext_rep));
			$alttext_rep=(str_replace("_", " ", $alttext_rep));
		
			array_push($pieces, ' alt="' . $alttext_rep . '"');
		}
		else
		{
			
			$key=array_search('alt=',$pieces);
			
			if ( ( trim( $pieces[$key+1] ) == '""' ) 
			|| ( str_replace('"','',trim($pieces[$key+1])) // making sure not an empty delimiter (needle) for strpos 
				&& strpos( $saved, str_replace('"','',trim($pieces[$key+1])) )
				&& $override==1 )
			) {
				
				$alttext_rep=str_replace("%title", $post->post_title, $alttext_rep);
				$alttext_rep=str_replace("%name", $source[0], $alttext_rep);
				$alttext_rep=str_replace("%category", $cats[0]->slug, $alttext_rep);
				
				$alttext_rep=str_replace("\"", "", $alttext_rep);
				$alttext_rep=str_replace("'", "", $alttext_rep);
				
				$alttext_rep=(str_replace("-", " ", $alttext_rep));
				$alttext_rep=(str_replace("_", " ", $alttext_rep));
				
				$pieces[$key+1]='"'.$alttext_rep.'" ';
				
			}
		}
	
	
		return implode('', $pieces).' /';
	}

function seo_friendly_images($content) {
	return preg_replace_callback('/<img[^>]+/', 'seo_friendly_images_process', $content);
}


add_filter('the_content', 'seo_friendly_images', 50);

/*
 * // commenting this section as it is not needed for VIP usage
 *
 
add_action( 'after_plugin_row', 'seo_friendly_images_check_plugin_version' );

function seo_friendly_images_getinfo()
{
		$checkfile = "http://svn.wp-plugins.org/seo-image/trunk/seo-friendly-images.chk";		
		
		$status=array();
		return $status;
		$vcheck = wp_remote_fopen($checkfile);
				
		if($vcheck)
		{
			$version = $seo_friendly_images_localversion;
									
			$status = explode('@', $vcheck);
			return $status;				
		}					
}

function seo_friendly_images_check_plugin_version($plugin)
{
	global $plugindir, $seo_friendly_images_localversion;
	
 	if( strpos($plugin,'seo-friendly-images.php')!==false )
 	{
			

			$status=seo_friendly_images_getinfo();
			
			$theVersion = $status[1];
			$theMessage = $status[3];	
	
			if( (version_compare(strval($theVersion), strval($seo_friendly_images_localversion), '>') == 1) )
			{
				$msg = 'Latest version available '.' <strong>'.$theVersion.'</strong><br />'.$theMessage;				
				echo '<td colspan="5" class="plugin-update" style="line-height:1.2em;">'.$msg.'</td>';
			} else {
				return;
			}
		
	}
}
*/


function seo_friendly_images_install(){		
	if(!get_option('seo_friendly_images_alt')){
		add_option('seo_friendly_images_alt', '%name %title');
	}
	if(!get_option('seo_friendly_images_title')){
		add_option('seo_friendly_images_title', '%title');
	}
	if(!get_option('seo_friendly_images_override')){
		add_option('seo_friendly_images_override', 1);
	}
}


add_action( 'admin_init', 'seo_friendly_images_install' );

?>
