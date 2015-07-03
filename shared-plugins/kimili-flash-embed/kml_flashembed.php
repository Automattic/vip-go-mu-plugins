<?php
/*
Plugin Name: Kimili Flash Embed
Plugin URI: http://www.kimili.com/plugins/kml_flashembed
Description: Provides a wordpress interface for Geoff Stearns' excellent standards compliant <a href="http://blog.deconcept.com/flashobject/">Flash detection and embedding JavaScript</a>. The syntax is <code>[kml_flashembed movie=&quot;filename.swf&quot; height=&quot;250&quot; width=&quot;400&quot; /]</code>.
Version: 1.4.3b
Author: Michael Bester
Author URI: http://www.kimili.com
Update: http://www.kimili.com/plugins/kml_flashembed/wp
*/

/*
*
*	KIMILI FLASH EMBED
*
*	Copyright 2008 Michael Bester (http://www.kimili.com)
*	Released under the GNU General Public License (http://www.gnu.org/licenses/gpl.html)
*
*/

/***********************************************************************
*	Global Vars
************************************************************************/

$kml_flashembed_ver		= "1.4.3b";
$kml_flashembed_root	= plugins_url( '', __FILE__ );


/***********************************************************************
*	Handle shortcodes
************************************************************************/

function kml_flash_shortcode( $atts, $content = '' ) {
	
	$r = '';

	if ( isset( $atts['movie'] ) && isset( $atts['height'] ) && isset( $atts['width'] ) ) {
		
		$atts['fversion'] = ( isset( $atts['fversion'] ) ) ? $atts['fversion'] : 6;
		
		if ( isset( $atts['fvars'] ) ) {
			$fvarpair_regex = "/(?<!([$|\?]\{))\s+;\s+(?!\})/";
			$atts['fvars'] = preg_split($fvarpair_regex, $atts['fvars'], -1, PREG_SPLIT_NO_EMPTY);
		}
		
		// Convert any quasi-HTML in alttext back into tags
		$content = ( isset( $atts['alttext'] ) ) ? preg_replace( "/{(.*?)}/i", "<$1>", $atts['alttext'] ) : $content;
		
		// If we're not serving up a feed, generate the script tags
		if ( ! is_feed() ) {
			$r	= kml_flashembed_build_fo_script( $atts, $content );
		} else {
			$r	= kml_flashembed_build_object_tag( $atts, $content );
		}
	}
 	return $r; 
}

// A filter function that runs do_shortcode() but only with this plugin's shortcodes
// Thanks to the ever-magnificent Viper007Bond http://v007.me/82w
function kimili_shortcode_hack( $content ) {
	global $shortcode_tags;

	// Backup current registered shortcodes and clear them all out
	$orig_shortcode_tags = $shortcode_tags;
	remove_all_shortcodes();

	// Register all of this plugin's shortcodes
	add_shortcode( 'kml_flashembed', 'kml_flash_shortcode' );
	add_shortcode( 'kml_swfembed', 'kml_flash_shortcode' );

	// Do the shortcodes (only this plugins's are registered)
	$content = do_shortcode( $content );

	// Put the original shortcodes back
	$shortcode_tags = $orig_shortcode_tags;

	return $content;
}

/***********************************************************************
*	Build the Javascript from the tags
************************************************************************/

function kml_flashembed_build_fo_script($atts, $content = '') {
	
	global $kml_flashembed_root;
	
	if (is_array($atts)) extract($atts);
	
	$rand = mt_rand();  // For making sure this instance is unique
	
	// Extract the filename minus the extension...
	$swfname = (strrpos($movie, "/") === false) ?
							$movie :
							substr($movie, strrpos($movie, "/") + 1, strlen($movie));
	$swfname = (strrpos($swfname, ".") === false) ?
							$swfname :
							substr($swfname, 0, strrpos($swfname, "."));
	
	// ... to use as a default ID if an ID is not defined.
	$fid = (isset($fid)) ? $fid : "fm_" . $swfname . "_" . $rand;
	// ... as well as an empty target if that isn't defined.
	if (empty($target)) {              
		$target = "so_targ_" . $swfname . "_" . $rand;
		$classname = (empty($targetclass)) ? "flashmovie" : $targetclass;
	}
  	
	$express_install = ( isset( $useexpressinstall ) && $useexpressinstall == 'true' ) ? $kml_flashembed_root.'/lib/expressinstall.swf' : '';
	
	$params = new stdClass;
	$flash_vars = new stdClass;
	
	// Loop through and compile params
	foreach( array( 'bgcolor', 'quality', 'play', 'loop', 'menu', 'scale', 'wmode', 'align', 'salign', 'base', 'allowscriptaccess', 'allowfullscreen' ) as $param ) {
		if( isset( ${$param} ) )
			$params->$param = ${$param};
	}
	
	// Loop through and compile flashvars
	for ($i = 0; $i < count($fvars); $i++) {
		$thispair	= trim($fvars[$i]);
		$nvpair		= explode("=",$thispair);
		$name		= trim($nvpair[0]);
		$value		= "";
		for ($j = 1; $j < count($nvpair); $j++) {			// In case someone passes in a fvars with additional "="       
			$value		.= trim($nvpair[$j]);
			$value		= preg_replace('/&#038;/', '&', $value);
			if ((count($nvpair) - 1)  != $j) {
				$value	.= "=";
			}
		}
		// Prune out JS or PHP values -- security risk that shouldn't be allowed.
		if (preg_match("/^\\$\\{.*\\}/i", $value)) { 		// JS
			$value		= ''; // not allowed
		} else if (preg_match("/^\\?\\{.*\\}/i", $value)) {	// PHP
			$value		= ''; // not allowed
		} else {
			$value = $value;
		}
		$flash_vars->$name = $value;
	}
	
	$div = sprintf( '<div id="%1$s" class="%2$s">%3$s</div>', esc_attr( $target ), esc_attr( $classname ), wp_kses_post( $content ) );
	$script = sprintf( 'swfobject.embedSWF("%1$s", "%2$s", "%3$s", "%4$s", "%5$s", "%6$s", %7$s, %8$s);', esc_js( $movie ), esc_js( $target ), esc_js( $width ), esc_js( $height ), esc_js( $fversion ), esc_js( $express_install ), json_encode( $flash_vars ), json_encode( $params ) );
	
	$output = sprintf( '%s<script type="text/javascript">%s</script>', $div, $script );
	
	// Add NoScript content
	if ( ! empty( $noscript ) )
		$output .= '<noscript>' . wp_kses_post( $noscript ) . '</noscript>';
	
	return $output;
}
           
/***********************************************************************
*	Build a Satay Object for RSS feeds
************************************************************************/

function kml_flashembed_build_object_tag($atts, $content = '') {
	
	$out	= array();	
	if (is_array($atts)) extract($atts);
	
	// Build a query string based on the $fvars attribute
	$querystring = (count($fvars) > 0) ? "?" : "";
	for ($i = 0; $i < count($fvars); $i++) {
		$thispair	= trim($fvars[$i]);
		$nvpair		= explode("=",$thispair);
		$name		= trim($nvpair[0]);
		$value		= "";
		for ($j = 1; $j < count($nvpair); $j++) {			// In case someone passes in a fvars with additional "="
			$value		.= trim($nvpair[$j]);
			$value		= preg_replace('/&#038;/', '&', $value);
			if ((count($nvpair) - 1)  != $j) {
				$value	.= "=";
			}
		}
		// Prune out JS or PHP values
		if (preg_match("/^\\$\\{.*\\}/i", $value)) { 		// JS
			$value		= ''; // not allowed
		} else if (preg_match("/^\\?\\{.*\\}/i", $value)) {	// PHP
			$value		= ''; // not allowed
		}
		// else {
		//	$value = '"'.$value.'"';
		//}
		$querystring .= $name . '=' . $value;
		if ($i < count($fvars) - 1) {
			$querystring .= "&";
		}
	}
	
									$out[] = '';    
						  	  		$out[] = '<object	type="application/x-shockwave-flash"';
									$out[] = '			data="'.esc_attr( $movie.$querystring ).'"'; 
	if (isset($base)) 	   		 	$out[] = '			base="'.esc_attr( $base ).'"';
									$out[] = '			width="'.esc_attr( $width ).'"';
									$out[] = '			height="'.esc_attr( $height ).'">';
									$out[] = '	<param name="movie" value="' . esc_attr( $movie.$querystring ) . '" />';
	if (isset($play))				$out[] = '	<param name="play" value="' . esc_attr( $play ) . '" />';
	if (isset($loop))				$out[] = '	<param name="loop" value="' . esc_attr( $loop ) . '" />';
	if (isset($menu)) 				$out[] = '	<param name="menu" value="' . esc_attr( $menu ) . '" />';
	if (isset($scale)) 				$out[] = '	<param name="scale" value="' . esc_attr( $scale ) . '" />';
	if (isset($wmode)) 				$out[] = '	<param name="wmode" value="' . esc_attr( $wmode ) . '" />';
	if (isset($align)) 				$out[] = '	<param name="align" value="' . esc_attr( $align ) . '" />';
	if (isset($salign)) 			$out[] = '	<param name="salign" value="' . esc_attr( $salign ) . '" />';    
	if (isset($base)) 	   		 	$out[] = '	<param name="base" value="' . esc_attr( $base ) . '" />';
	if (isset($allowscriptaccess))	$out[] = '	<param name="allowScriptAccess" value="' . esc_attr( $allowscriptaccess ) . '" />';
	if (isset($allowfullscreen))	$out[] = '	<param name="allowFullScreen" value="' . esc_attr( $allowfullscreen ) . '" />';
									$out[] = wp_kses_post( $content );
	 								$out[] = '</object>';

	return join( "\n", $out );
	
}

/***********************************************************************
*	Add the call to flashobject.js
************************************************************************/

function kml_flashembed_add_flashobject_js() {
	global $kml_flashembed_ver, $kml_flashembed_root;
	wp_enqueue_script( 'swfobject' );
}


/***********************************************************************
*	Toolbar Button Functions                                             
*	Props to Alex Rabe for fuguring out the WP 2.1 buttonsnap workaround
* 	http://alexrabe.boelinger.com/?page_id=46
************************************************************************/

function kml_flashembed_addbuttons() {  
  
	global $kml_flashembed_root;  
 	
	// Check activated RTE
	if ( 'true' == get_user_option('rich_editing')  ) {  
		add_filter( 'mce_external_plugins', 'kml_flashembed_plugin', 0 );
		add_filter( 'mce_buttons', 'kml_flashembed_button',0 );	
	}
	
}

// used to insert button in wordpress 2.1x and 2.5 editor  
function kml_flashembed_button($buttons) {  
	array_push($buttons, "separator", "kfe");  
	return $buttons;  
}  
  
// Tell TinyMCE that there is a plugin 
function kml_flashembed_plugin($plugins) {  
	
	global $kml_flashembed_root;
	
	$plugins['kfe'] = $kml_flashembed_root.'/kfe/editor_plugin_tmce3.js';
	
	return $plugins;  
}

// Load the TinyMCE plugin : editor_plugin.js (wp2.1)  
function kml_flashembed_load() {
	
	global $kml_flashembed_root;
	
	$pluginURL = $kml_flashembed_root.'/kfe/';
	
	echo 'tinyMCE.loadPlugin("kfe", "'.$pluginURL.'");'."\n"; 
	return;  
}


/***********************************************************************
*	Initialize the plugin 
************************************************************************/

add_action('init', 'kml_flashembed_addbuttons');
add_action('wp_enqueue_scripts', 'kml_flashembed_add_flashobject_js');
add_filter( 'the_content', 'kimili_shortcode_hack', 7 );
