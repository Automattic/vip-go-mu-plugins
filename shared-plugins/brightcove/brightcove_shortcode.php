<?php

add_action( 'wp_enqueue_scripts', 'brightcove_enqueue_frontend_scripts' );
add_shortcode('brightcove','brightcove_add');

function brightcove_add($atts) {
	GLOBAL $bcGlobalVariables;

	$defaults = array(
					'playerid' => '',
					'playerkey' => '',
					'playlistid' => '',
					'videoid' => '',
					'width' => $bcGlobalVariables['defaultWidth'],
					'height'  => $bcGlobalVariables['defaultHeight'],
					'playlist_width' => $bcGlobalVariables['defaultWidthPlaylist'],
					'playlist_height' => $bcGlobalVariables['defaultHeightPlaylist'],
					'link_url'=> ''
					);
	$combined_attr = shortcode_atts( $defaults, $atts );
	
	$width = sanitize_key( $combined_attr['width'] );        //Using key to allow for blanks
	$height = sanitize_key( $combined_attr['height'] );		//Using key to allow for blanks
	$playerid =	sanitize_key( $combined_attr['playerid'] );	
	$playerkey = sanitize_key( $combined_attr['playerkey'] );
	$videoid = sanitize_key( $combined_attr['videoid'] );
	$playlistid = sanitize_key( $combined_attr['playlistid'] );
	$playlist_width = sanitize_key( $combined_attr['playlist_width'] );	
	$playlist_height = sanitize_key( $combined_attr['playlist_height'] );	
	$link_url = sanitize_key( $combined_attr['link_url'] );			

	$html = '<div style="display:none"></div>';
	$html = $html . '<object id="' . esc_attr( rand() ) .'" class="BrightcoveExperience">';
  	$html = $html . '<param name="bgcolor" value="#FFFFFF" />';
  	$html = $html . '<param name="wmode" value="transparent" />';
  	
 	if ($playerid != '') {
    		$html = $html . '<param name="playerID" value="'. esc_attr( $playerid ) .'" />';
  	}

  	if ($playerkey != '') {
    		$html = $html . '<param name="playerKey" value="'. esc_attr( $playerkey ) .'"/>';
  	}
  	
	$html = $html . '<param name="isVid" value="true" />';
	$html = $html . '<param name="isUI" value="true" />';
	$html = $html . '<param name="dynamicStreaming" value="true" />';
	if ( $link_url ) {
		$html .= '<param name="linkBaseURL" value="' . esc_url( $link_url )  . '" />';
	}

  	if ($videoid != '') {
    		$html = $html . '<param name="@videoPlayer" value="'.esc_attr( $videoid ) .'" />';
    		$html = $html . '<param name="width" value="' . esc_attr( $width ) . '" />';
  		$html = $html . '<param name="height" value="'. esc_attr( $height ) .'" />';
  	}
  	if ($playlistid != '') {
    		$html = $html . '<param name="@playlistTabs" value="'.esc_attr( $playlistid ).'" />';
    		$html = $html . '<param name="@videoList" value="'.esc_attr( $playlistid ).'" />';
    		$html = $html . '<param name="@playlistCombo" value="'.esc_attr( $playlistid ).'" />';
    		$html = $html . '<param name="width" value="' . esc_attr( $playlist_width ) . '" />';
  		$html = $html . '<param name="height" value="'. esc_attr( $playlist_height ) .'" />';
  	}
  	
	$html = $html . '</object>';

	return $html;
}


