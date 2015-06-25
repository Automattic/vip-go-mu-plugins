<?php
	function mediapass_overlay_shortcode( $atts, $content = null ) {
		$noscript_url = MediaPass_Plugin::FE_PREFIX . 'subscription/noscriptredirect?key=' . get_option(MediaPass_Plugin::OPT_USER_ID) . '&uri=' . site_url();
		$script_url = MediaPass_Plugin::FE_PREFIX . 'static/js/mm.js';

		return '<noscript><meta http-equiv="REFRESH" content="0; url=' . esc_url( $noscript_url ) . '"></noscript>
		<script type="text/javascript" src="' . esc_url( $script_url ) . '"></script>
		<script type="text/javascript">MediaPass.init(' . intval( get_option( MediaPass_Plugin::OPT_USER_ID ) ) . ', { asset: \'' . esc_js( get_option( MediaPass_Plugin::OPT_USER_NUMBER ) ) . '\'});</script>
		<div id="media-pass-tease">' . do_shortcode( $content ) . '</div>';
	}
	
	function mediapass_inpage_shortcode( $atts, $content = null ) {
		$noscript_url = MediaPass_Plugin::FE_PREFIX . 'subscription/noscriptredirect?key=' . get_option(MediaPass_Plugin::OPT_USER_ID) . '&uri=' . site_url();
		$script_url = MediaPass_Plugin::FE_PREFIX . 'static/js/mm.js';

		return '<noscript><meta http-equiv="REFRESH" content="0; url=' . esc_url( $noscript_url ) . '"></noscript>
		<script type="text/javascript" src="' . esc_url( $script_url ) . '"></script>
		<script type="text/javascript">MediaPass.init(' . intval( get_option( MediaPass_Plugin::OPT_USER_ID ) ) . ', { asset: \'' . esc_js( get_option( MediaPass_Plugin::OPT_USER_NUMBER ) ) . '\'});</script>
		<div class="media-pass-article">' . do_shortcode( $content ) . '</div>';
	}

	function mediapass_video_shortcode( $atts, $content = null ) {
	   extract( shortcode_atts( array(
			"width" => ' ',		  
			"height" => ' ',		  
			"delay" => ' ',		  
			"title" => ' ',		  
			"vid" => ' ',		  
		  
		  ), $atts ) );

		$mp_vidvars = json_encode( array(
			'width' => $width,
			'height' => $height,
			'delay' => $delay,
			'title' => $title,
			'vid' => $vid,
		) );

		$noscript_url = MediaPass_Plugin::FE_PREFIX .'subscription/noscriptredirect?key=' . get_option(MediaPass_Plugin::OPT_USER_ID) . '&uri=' . site_url();
		$script_url = MediaPass_Plugin::FE_PREFIX . 'static/js/mm.js';

		return '<!-- ' . intval( $delay ) . ' --><noscript><meta http-equiv="REFRESH" content="0; url=' . esc_url( $noscript_url ) . '"></noscript><script type="text/javascript" src="' . esc_url( $script_url ) . '"></script>
		<script type="text/javascript">MediaPass.init(' . intval( get_option( MediaPass_Plugin::OPT_USER_ID ) ) . ', ' . $mp_vidvars . ');</script><div id="media-pass-video">'. do_shortcode( $content ) . '</div> ';

	}	   
	
	add_shortcode( 'mpinpage'	, 'mediapass_inpage_shortcode' 	);
	add_shortcode( 'mpoverlay'	, 'mediapass_overlay_shortcode' );
	add_shortcode( 'mpvideo'	, 'mediapass_video_shortcode' 	);
?>