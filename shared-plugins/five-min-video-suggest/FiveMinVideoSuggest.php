<?php
/***************************************************************************

Plugin Name: The AOL On Network Video Plugin
Plugin URI: http://on.aol.com
Description: The AOL On Network’s video plugin for WordPress, allows you to embed videos in your posts or pages using our vast video library. Browse, search, or use our semantic engine (which suggests videos matching the content of your post). Our player has HTML5 fallback support for non-Flash browsers. Player’s Layout and Advanced Settings can be easily configured using the plugin.
Version: 1.3
Author: The AOL On Network
Author URI: http://on.aol.com

***************************************************************************/

class FiveMinVideoSuggest {

	public function __construct() {
		add_filter( 'the_content', array( $this, 'the_content' ) );
		add_filter( 'tiny_mce_before_init', array( $this, 'tiny_mce_before_init' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'admin_print_styles-post.php', array( $this, 'admin_print_styles' ) );
		add_action( 'admin_print_styles-post-new.php', array( $this, 'admin_print_styles' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
	}

	function admin_print_styles() {
		wp_enqueue_script('fivemin-plugin',"https://spshared.5min.com/Scripts/Plugin.js?v=1.3");
		wp_enqueue_style('fivemin-video-css',  "https://spshared.5min.com/Css/Plugin/Base.css");
	}

	function add_meta_boxes( $post_type ) {
		add_meta_box('FiveMinVideoSuggest', 'Aol Video Suggest', array( $this, 'meta_box_callback' ), $post_type, 'side', 'high');
	}

	function meta_box_callback() {
		$options = get_option('aol_videoSuggest_options');
		$sid = ( isset( $options['sid'] ) && 0 != $options['sid'] ) ? intval( $options['sid'] ) : 203;
		$api = ( isset( $options['api'] ) && '' != $options['api'] ) ? $options['api'] : 'Wordpress';
		?>
		<div class='fivemin-videosuggestbox'>
			<div id="fivemin-plugin" data-api="<?php echo esc_attr( $api ); ?>" data-params="sid=<?php echo esc_attr( $sid ); ?>"></div>
		</div>
		<?php
	}

	// Makes tinymce allow any attribute in the img element so we can add the data-product / data-params attributes we want.
	function tiny_mce_before_init( $init ) {
		$init['extended_valid_elements'] = isset( $init['extended_valid_elements'] ) ? $init['extended_valid_elements'] . ',img[*]' : 'img[*]';
		return $init;
	}


	// filter the_content to convert img's to videos (thats where the magic is done)
	function the_content($content) {

		$pattern = '/<img .*class="fiveminVideoPlayer" [^>]*>/';
		preg_match_all($pattern , $content, $media);

		for($i=0; $i<count($media[0]); $i++)
		{
			$img = ($media[0][$i]);
			preg_match_all('/data-params="(.+?)"/',$img,$params);
			/*
			Array ( [0] => Array ( [0] => data-params="517061814" ) [1] => Array ( [0] => 517061814 ) ) Array ( [0] => Array ( [0] => data-params="500048780" ) [1] => Array ( [0] => 500048780 ) )
			*/
			if (count($params)!=0){
				$paramsAttribute= $params[1][0];

				$splitted= explode('|||', $paramsAttribute);
				$paramsArray=array();
				for($j=0;$j<count($splitted);$j++){
					$keyValuePair=explode('=',$splitted[$j]);
					$paramsArray[$keyValuePair[0]]=$keyValuePair[1];
				}

				$passedParams=implode("&",$splitted);

				$playerSeed = '<div style="overflow:hidden;"><script type="text/javascript" src="http://pshared.5min.com/Scripts/PlayerSeed.js?'.$passedParams.'"></script></div>';

				$content = str_replace($img, $playerSeed, $content);

			}
		}

		return $content;
	}



	// Add settings link on plugin page
	function plugin_action_links($links) {
		$settings_link = '<a href="options-media.php#aol_videoSuggest">Settings</a>';
		array_unshift($links, $settings_link);
		return $links;
	}

	function admin_init(){
		register_setting( 'media', 'aol_videoSuggest_options', array( $this, 'sanitize_options' ) );
		add_settings_section( 'aol_videoSuggest_main', 'Aol Video Settings', '__return_false', 'media' );
		add_settings_field( 'aol_videoSuggest_text_string', 'Syndicator Id', array( $this, 'settings_field_callback' ), 'media', 'aol_videoSuggest_main' );
		add_settings_field( 'aol_videoSuggest_api_name', 'API Name', array( $this, 'settings_api_field_callback' ), 'media', 'aol_videoSuggest_main' );

		add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array( $this, 'plugin_action_links' ) );

		// doing this on all admin pages because, at least for tc, $allowedpost tags is used on more than just the post editor
		global $allowedposttags;
		$new_attributes = array( 'data-product' => array(), 'data-params' => array() );
		if ( isset( $allowedposttags[ 'img' ] ) && is_array( $allowedposttags[ 'img' ] ) )
			$allowedposttags[ 'img' ] = array_merge( $allowedposttags[ 'img' ], $new_attributes );
	}

	function sanitize_options( $input ) {
		$new_input['sid'] = isset( $input['sid'] ) ? intval( $input['sid'] ) : 0;
		$new_input['api'] = isset( $input['api'] ) ? sanitize_text_field( $input['api'] ) : 'Wordpress';
		return $new_input;
	}

	function settings_field_callback() {
		$options = get_option('aol_videoSuggest_options');
		?>
		<a name="aol_videoSuggest"></a>
		<input id="aol_videoSuggest_text_string" name="aol_videoSuggest_options[sid]" size="40" type="text" value="<?php if ( isset( $options['sid'] ) ) echo intval( $options['sid'] ); ?>" />
		</br>
		<?php
	}

	function settings_api_field_callback() {
		$options = get_option('aol_videoSuggest_options');
		?>
		<a name="aol_videoSuggest-api"></a>
		<input id="aol_videoSuggest_api_name" name="aol_videoSuggest_options[api]" size="40" type="text" value="<?php if ( isset( $options['api'] ) ) echo esc_attr( $options['api'] ); ?>" />
		</br>
		<?php
	}
}

$five_min_video_suggest = new FiveMinVideoSuggest;
