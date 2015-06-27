<?php

/*
  Plugin Name: TinyPass
  Plugin URI: http://www.tinypass.com
  Description: TinyPass enables metered access to your WordPress site
  Author: Tinypass
  Version: 1.0.11
  Author URI: http://www.tinypass.com
 */


define( 'TINYPASS_PLUGIN_FILE_PATH', __FILE__ );
define( 'TINYPASS_PLUGIN_PATH', plugin_dir_url( __FILE__ ) );
define( 'TINYPASS_TPM_JS', 'http://code.tinypass.com/tpl/d1/tpm.js' );

register_activation_hook( __FILE__, 'tinypass_activate' );
register_deactivation_hook( __FILE__, 'tinypass_deactivate' );
register_uninstall_hook( __FILE__, 'tinypass_uninstall' );

if ( !class_exists( 'TPMeterState' ) ) {

	class TPMeterState {

		public $embed_meter = null;
		public $track_page_view = false;
		public $paywall_id = 0;
		public $sandbox = 0;

		public function reset() {
			$this->embed_meter = null;
			$this->track_page_view = false;
			$this->paywall_id = 0;
			$this->sandbox = 0;
		}

	}

}

global $tpmeter;
$tpmeter = new TPMeterState();

//setup
if ( is_admin() ) {
	require_once dirname( __FILE__ ) . '/tinypass-admin.php';
}

add_action( 'init', 'tinypass_init' );
add_action( 'wp_footer', 'tinypass_footer' );
add_shortcode( 'tinypass_offer', 'tinypass_offer_shortcode' );

function tinypass_init() {

	tinypass_include();

	$ss = tinypass_load_settings();

	if ( $ss->isEnabled() && !is_admin() ) {
		wp_enqueue_script( 'tpm.js', TINYPASS_TPM_JS );
		add_filter( 'the_content', 'tinypass_intercept_content', 5 );
	}
}

/**
 * This method determines if the tinypass-meter needs to be
 * embeded at the bottom of the page.
 * 
 * If the post is tagged and the request is for a page then we will embed
 * 
 * If the request is the home page it is embeded but not configured to track onLoad
 */
function tinypass_intercept_content( $content ) {

	global $tpmeter;
	global $post;

	tinypass_include();

	$ss = tinypass_load_settings();

	$storage = new TPStorage();

	//Load the page
	$postSettings = null;
	if ( $post->post_type == 'page' ) {
		$postSettings = $storage->getPostSettings( $post->ID );
	}

	//or non-subscribers metered should be ignored
	$tpmeter->embed_meter = true;

	$pwOptions = $storage->getPaywall( "pw_config" );

	if ( $pwOptions->isDisabledForPriviledgesUsers() && is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
		$tpmeter->embed_meter = false;
	}

	//NOOP if pw is disabled or the wrong mode
	if ( $pwOptions->isEnabled() == false || $pwOptions->isMode( TPPaySettings::MODE_METERED_LIGHT ) == false ) {
		return $content;
	}

	if ( is_home() ) {
		$tpmeter->track_page_view = $pwOptions->isTrackHomePage();
	} else if ( $postSettings != null && $postSettings->isEnabled() ) {
		$tpmeter->track_page_view = true;
	} else {
		//check if current post is tagged for restriction
		$post_terms = get_the_tags( $post->ID );
		if ( $post_terms ) {
			foreach ( $post_terms as $term ) {
				if ( $pwOptions->tagMatches( $term->name ) ) {
					$tpmeter->track_page_view = true;
					break;
				}
			}
		}
	}

	$tpmeter->paywall_id = $pwOptions->getPaywallID( $ss->isProd() );
	$tpmeter->sandbox = $ss->isSand();

	return $content;
}

/**
 * Helper method to include tinypass related files
 */
function tinypass_include() {
	include_once dirname( __FILE__ ) . '/util/TPStorage.php';
	include_once dirname( __FILE__ ) . '/util/TPPaySettings.php';
	include_once dirname( __FILE__ ) . '/util/TPSiteSettings.php';
	include_once dirname( __FILE__ ) . '/util/TPValidate.php';
}

/**
 * Load and init global tinypass settings
 */
function tinypass_load_settings() {
	$storage = new TPStorage();
	$ss = $storage->getSiteSettings();
	return $ss;
}

/**
 * Footer method to add scripts
 */
function tinypass_footer() {
	global $tpmeter;

	if ( $tpmeter->embed_meter ) {
		echo "
<script type=\"text/javascript\">
    window._tpm = window._tpm || [];
    window._tpm['paywallID'] = '" . esc_js( $tpmeter->paywall_id ) . "'; 
    window._tpm['sandbox'] = " . ($tpmeter->sandbox ? 'true' : 'false') . "; 
    window._tpm['trackPageview'] = " . ($tpmeter->track_page_view ? 'true' : 'false') . "; 
</script>\n\n";
	}
}

/**
 * Shortcode function for converting [tinypass_subscribe text="Text Link"] into a short code
 */
function tinypass_offer_shortcode( $attr ) {
	$text = 'Subscribe';
	if ( isset( $attr['text'] ) )
		$text = $attr['text'];

	return '<a href="#" onclick="getTPMeter().showOffer();return false;">' . $text . '</a>';
}