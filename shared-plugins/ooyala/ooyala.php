<?php
/*
Plugin Name: Ooyala Video
Plugin URI: http://www.ooyala.com/wordpressplugin/
Description: Easy Embedding of Ooyala Videos based off an Ooyala Account as defined in the <a href="options-general.php?page=ooyala-options"> plugin settings</a>.
Version: 1.7.5
License: GPL
Author: Ooyala

Contact mail: wordpress@ooyala.com
*/

require_once( dirname(__FILE__) . '/class-wp-ooyala-backlot-api.php' );

class Ooyala_Video {

	const VIDEOS_PER_PAGE = 8;
	var $plugin_dir;
	var $plugin_url;
	var $partner_code;
	var $secret_code;

	/**
	 * Singleton
	 */
	public static function init() {
		static $instance = false;

		if ( !$instance ) {
			load_plugin_textdomain( 'ooyalavideo', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
			$instance = new Ooyala_Video;
		}

		return $instance;
	}

	/**
	 * Constructor
	 */
	function __construct() {

		$this->plugin_dir = plugin_dir_path( __FILE__ );
		$this->plugin_url = plugin_dir_url( __FILE__ );

		if ( is_admin() ) {
			require_once( dirname( __FILE__ ) . '/ooyala-options.php' );
/*
			$partner_code = get_option( 'ooyalavideo_partnercode' );
			if ( $partner_code ) {
				$secret_code  = get_option( 'ooyalavideo_secretcode' );
				$show_in_feed = get_option( 'ooyalavideo_showinfeed' );
				$video_width  = get_option( 'ooyalavideo_width' );

				$options = array(
					'partner_code' => $partner_code,
					'secret_code'  => $secret_code,
					'show_in_feed' => $show_in_feed,
					'video_width'  => $video_width
				);
				update_option( 'ooyala', $options );
				delete_option( 'ooyalavideo_partnercode' );
				delete_option( 'ooyalavideo_secretcode' );
				delete_option( 'ooyalavideo_showinfeed' );
				delete_option( 'ooyalavideo_width' );
			} else {
				require_once( dirname( __FILE__ ) . '/OoyalaApi.php' );
				$options = get_option( 'ooyala', array( 'partner_code' => '', 'secret_code' => '' ) );
				$this->partner_code = $options['partner_code'];
				$this->secret_code  = $options['secret_code'];

				if ( !empty( $options['api_key'] ) && !empty( $options['api_secret'] ) && ! isset( $options['player_id'] ) ) {
					$api = new OoyalaApi( $options['api_key'], $options['api_secret'] );
					$options['player_id'] = '';

					try {
						$players = $api->get( "players" );
					} catch ( Exception $e ) {
						$players = false;
					}

					if ( $players && ! empty( $players->items ) ) {
						$options['players'] = array();
						foreach ( $players->items as $player )
							$options['players'][] = $player->id;

						if ( empty( $options['player_id'] ) )
							$options['player_id'] = $options['players'][0];

					}

					update_option( 'ooyala', $options );
				}

			}
*/

		}

		add_action( 'admin_menu',              array( &$this, 'add_media_page'  ) );
		add_action( 'admin_init',              array( &$this, 'register_script' ) );
		add_action( 'media_buttons',           array( &$this, 'media_button'    ), 999 );
		add_action( 'wp_ajax_ooyala_popup',    array( &$this, 'popup'           ) );
		add_action( 'wp_ajax_ooyala_set',      array( &$this, 'ooyala_set'      ) );
		add_action( 'wp_ajax_ooyala_request',  array( &$this, 'ooyala_request'  ) );
		add_action( 'wp_ajax_ooyala_uploader', array( &$this, 'ooyala_uploader' ) );
		add_action( 'wp_ajax_ooyala_uploader/assets', array( &$this, 'ooyala_uploader' ) );

		add_shortcode( 'ooyala', array(&$this, 'shortcode') );
	}

	function Ooyala_Video() {
		$this->__construct();
	}

	/**
	* Migrate the secret and partner code from the config.php file, if exists.
	* Only runs on plugin activation if option is not set.
	*/
	function migrate_config() {

		// Check no options are set yet
		if ( false === get_option( 'ooyalavideo_partnercode' ) && false === get_option( 'ooyalavideo_secretcode' ) ) {
			$config_file = dirname(__FILE__).'/config.php';

			if ( file_exists( $config_file ) ) {
				include_once( $config_file );
				$options = array(
					'partner_code'  => defined( 'OOYALA_PARTNER_CODE' ) ? esc_attr( 'OOYALA_PARTNER_CODE' ) : '',
					'parner_secret' => defined( 'OOYALA_SECRET_CODE'  ) ? esc_attr( 'OOYALA_SECRET_CODE'  ) : ''
				);
				update_option( 'ooyala', $options );
			}
		}
	}

	/**
	 * Registers and localizes the plugin javascript
	 */
	function register_script() {
		wp_register_script( 'ooyala', $this->plugin_url . 'js/ooyala.js', array( 'jquery' ), '1.4' );
		wp_register_script( 'ooyala-uploader', $this->plugin_url . 'js/ooyala_uploader.js', array( 'jquery' ), '20121205' );

		wp_localize_script( 'ooyala', 'ooyalaL10n', array(
			'latest_videos' => __( 'Latest Videos', 'ooyalavideo' ),
			'search_results' => __( 'Search Results', 'ooyalavideo' ),
			'done' => __( 'Done!', 'ooyalavideo' ),
			'upload_error' => __( 'Upload Error', 'ooyalavideo' ),
			'use_as_featured' => __( 'Use as featured image', 'ooyalavideo' ),
		) );

		wp_register_style( 'jquery-ui-progressbar', plugins_url( 'css/ooyala-uploader/jquery-ui-1.9.2.custom.min.css', __FILE__ ), array(), '1.9.2' );
	}

	/**
	 * Shortcode Callback
	 * @param array $atts Shortcode attributes
	 */
	function shortcode( $atts ) {

		/* Example shortcodes:
		  Legacy: [ooyala NtsSDByMjoSnp4x3NibMn32Aj640M8hbJ]
		  Updated: [ooyala code="NtsSDByMjoSnp4x3NibMn32Aj640M8hbJ" width="222" ]
		*/

		$options = get_option( 'ooyala' );
		extract(shortcode_atts( apply_filters( 'ooyala_default_query_args', array(
			'width' => '',
			'code' => '',
			'autoplay' => '',
			'callback' => 'recieveOoyalaEvent',
			'wmode' => 'opaque',
			'player_id' => $options['player_id'],
			'platform' => 'html5-fallback',
			'wrapper_class' => 'ooyala-video-wrapper',
			) ), $atts
		));
		if ( empty($width) )
			$width = $options['video_width'];
		if ( empty($width) )
			$width = $GLOBALS['content_width'];
		if ( empty($width) )
				$width = 500;

		$width = (int) $width;
		$height = floor( $width*9/16 );
		$autoplay = (bool) $autoplay ? '1' : '0';
		$sanitized_embed = sanitize_key( $code );
		$wmode = in_array( $wmode, array( 'window', 'transparent', 'opaque', 'gpu', 'direct' ) ) ? $wmode : 'opaque';
		$wrapper_class = sanitize_key( $wrapper_class );
		// V2 Callback
		$callback = preg_match( '/[^\w]/', $callback ) ? '' : sanitize_text_field( $callback ); // // sanitize a bit because we don't want nasty things
		// Check if platform is one of the accepted. If not, set to html5-fallback
		$platform = in_array( $platform, array( 'flash', 'flash-only', 'html5-fallback', 'html5-priority' ) ) ? $platform : 'html5-fallback';
		if ( empty( $code ) )
			if ( isset( $atts[0] ) )
				$code = $atts[0];
			else
				return '<!--Error: Ooyala shortcode is missing the code attribute -->';

		if( preg_match( "/[^a-z^A-Z^0-9^\-^\_]/i", $code ) )
			return '<!--Error: Ooyala shortcode attribute contains illegal characters -->';

		$output = '';

		if ( !is_feed() ) {
			$url = add_query_arg( array(
				'width' => $width,
				'height' => $height,
				'embedCode' => $code,
				'autoplay' => $autoplay,
				'callback' => $callback,
				'wmode' => $wmode,
				'version' => 2,
			) , 'http://player.ooyala.com/player.js' );

			if ( !empty( $player_id ) ) {
				$v3_url = "http://player.ooyala.com/v3/{$player_id}?platform={$platform}";
				$output .= '<script src="' . esc_url( $v3_url ) . '"></script>
<div id="playerContainer-' . esc_attr( $sanitized_embed ) . '" class="' . esc_attr( $wrapper_class ) . '"></div>
<script>
var myPlayer = OO.Player.create("playerContainer-' . esc_attr( $sanitized_embed ) . '", "' . esc_attr( $code ) .'", {
	width:' . absint( $width ) . ',
	height:' . absint( $height ) . ',
	autoplay: "' . esc_attr( $autoplay ) . '",
	wmode: "' . esc_attr( $wmode ) . '",
	onCreate: function(player) {
         window.messageBus = player.mb;  // save reference to message bus
         window.ooyalaPlayer = player;  // save reference to player itself
       }
});
</script>';
			} else {
				$output .= '<div class="' . esc_attr( $wrapper_class ) . '" id="ooyala-video-' . esc_attr( $sanitized_embed ) . '">';
				$output .= '<script src="'. esc_url( $url ) .'"></script>';
				$output .= '<noscript>';
				$output .= "<object classid='clsid:D27CDB6E-AE6D-11cf-96B8-444553540000' id='ooyalaPlayer_" . esc_attr( $sanitized_embed ) . "' width='{$width}' height='{$height}' codebase='http://fpdownload.macromedia.com/get/flashplayer/current/swflash.cab'>";
				$output .= "<param name='movie' value='http://player.ooyala.com/player.swf?embedCode={$code}&version=2' />";
				$output .= "<param name='bgcolor' value='#000000' />";
				$output .= "<param name='allowScriptAccess' value='always' />";
				$output .= "<param name='allowFullScreen' value='true' />";
				$output .= "<param name='wmode' value='$wmode' />";
				$output .= "<param name='flashvars' value='embedType=noscriptObjectTag&embedCode=###VID###' />";
				$output .= "<embed src='http://player.ooyala.com/player.swf?embedCode={$code}&version=2' bgcolor='#000000' width='{$width}' height='{$height}' name='ooyalaPlayer_" . esc_attr( $sanitized_embed ) . "' align='middle' play='true' loop='false' allowscriptaccess='always' allowfullscreen='true' type='application/x-shockwave-flash' flashvars='&embedCode={$code}' pluginspage='http://www.adobe.com/go/getflashplayer'>";
				$output .= "</embed>";
				$output .= "</object>";
				$output .= "</noscript>";
				$output .= "\n<!-- Shortcode generated by WordPress plugin Ooyala Video -->\n";
				$output .= '</div>';
			}
		} elseif ( $options['show_in_feed']  ) {
			$output = __('[There is a video that cannot be displayed in this feed. ', 'ooyalavideo').'<a href="'.get_permalink().'">'.__('Visit the blog entry to see the video.]','ooyalavideo').'</a>';
		}

		return $output;
	}

	/**
	 * Add options page
	 */
	function add_media_page() {
		add_media_page( __( 'Ooyala', 'ooyalavideo' ), __( 'Ooyala Video', 'ooyalavideo' ), 'upload_files', 'ooyala-browser', array( &$this, 'media_page' ) );
	}

	/**
	 * Adds the Ooyala button to the media upload
	 */
	function media_button() {

		global $post_ID, $temp_ID;
		$iframe_post_id = (int) ( 0 == $post_ID ? $temp_ID : $post_ID );

		$title = __( 'Embed Ooyala Video', 'ooyalavideo' );
		$plugin_url = $this->plugin_url;
		$site_url = admin_url( "/admin-ajax.php?post_id=$iframe_post_id&amp;ooyala=popup&amp;action=ooyala_popup&amp;TB_iframe=true&amp;width=768" );
		echo '<a href="' . esc_url( $site_url ) . '&id=add_form" class="thickbox" title="' . esc_attr( $title ) . '"><img src="' . esc_url( $plugin_url ) . 'img/ooyalavideo-button.png" alt="' . esc_attr( $title ) . '" width="13" height="12" /></a>';
	}


	/**
	 * Callback for ajax popup call. Outputs ooyala-popup.php
	 */
	function popup() {
		require_once( $this->plugin_dir . 'ooyala-popup.php' );
		die();
	}

	/**
	 * Adds a .jpg extension to the filename (for use with filenames retrieved from the thumbnail api)
	 * Called by set_thumbnail()
	 * @param string $filename
	 * @return filename with added jpg extension
	 */
	function add_extension( $filename ) {
		//beginning in 4.2, check for .tmp file extension and remove it.  Otherwise, unable to assign an image as a featured image
		$filename = str_replace('.tmp', '', $filename);
	    $info = pathinfo($filename);
	    $ext  = empty($info['extension']) ? '.jpg' : '.' . $info['extension'];
	    $name = basename($filename, $ext);
	    return $name . $ext;
	}

	/**
	 * Sets an external URL as post featured image ('thumbnail')
	 * Contains most of core media_sideload_image(), modified to allow fetching of files with no extension
	 *
	 * @param string $url
	 * @param int $_post_ID
	 * @return $thumbnail_id - id of the thumbnail attachment post id
	 */
	function set_thumbnail( $url,  $_post_id ) {

		if ( !current_user_can( 'edit_post', $_post_id ) )
			die( '-1' );

		if ( empty( $_post_id) )
			die( '0');

		add_filter('sanitize_file_name', array(&$this, 'add_extension' ) );

		// Download file to temp location
		$tmp = download_url( $url );
		remove_filter('sanitize_file_name', array(&$this, 'add_extension' ) );

		preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG)/', $tmp, $matches);
		$file_array['name'] = basename($matches[0]);
		$file_array['tmp_name'] = $tmp;

		// If error storing temporarily, unlink
		if ( is_wp_error( $tmp ) ) {
			@unlink($file_array['tmp_name']);
			$file_array['tmp_name'] = '';
		}

		// do the validation and storage stuff
		$thumbnail_id = media_handle_sideload( $file_array, $_post_id, '' );

		// If error storing permanently, unlink
		if ( is_wp_error($thumbnail_id) ) {
			@unlink($file_array['tmp_name']);
			return false;
		}

		return $thumbnail_id;
	}

	/**
	 * Ajax callback that sets a post thumbnail based on an ooyala embed id
	 *
	 * @uses OoyalaBacklotAPI::get_promo_thumbnail to get the thumbnail url
	 * @uses Ooyala_Video::set_thumbnail() to set fetch the image an set it
	 * @uses core's set_post_thumbnail() to set the link between post and thumbnail id
	 *
	 * output html block for the meta box (from _wp_post_thumbnail_html() )
	 */
	function ooyala_set() {
		global $post_ID;

		$nonce = isset( $_POST ['_wpnonce'] ) ?  $_POST['_wpnonce'] : '';

		if (! wp_verify_nonce($nonce, 'ooyala') )
		 	die('Security check');

		$_post_id = absint( $_POST['postid'] );

		// Make sure the global is set, otherwise the nonce check in set_post_thumbnail() will fail
		$post_ID = (int) $_post_id;

		//Let's set the thumbnails size
		if ( isset($_wp_additional_image_sizes['post-thumbnail']) ) {
			$thumbnail_width = $_wp_additional_image_sizes['post-thumbnail']['width'];
			$thumbnail_height = $_wp_additional_image_sizes['post-thumbnail']['height'];
		}
		else {
			$thumbnail_width = 640;
			$thumbnail_height = 640;
		}

		$url = isset( $_POST['img'] ) ? esc_attr( $_POST['img'] ) : '';
		$thumbnail_id = $this->set_thumbnail( $url, $_post_id );

		if ( false !== $thumbnail_id ) {
			set_post_thumbnail( $_post_id, $thumbnail_id );
			die( _wp_post_thumbnail_html( $thumbnail_id ) );
		}

	}

	/**
	 * Ajax callback that handles the request to Ooyala API from the Ooyala popup
	 *
	 * @uses OoyalaBacklotAPI::query() to run the queries
	 * @uses OoyalaBacklotAPI::print_results() to output the results
	 */

	function ooyala_request() {

		global $_wp_additional_image_sizes;

		if ( !isset( $_GET['ooyala'] ) )
			die('-1');

		$do = $_GET['ooyala'];

		$limit = Ooyala_Video::VIDEOS_PER_PAGE;

		$key_word = isset( $_GET['key_word'] ) ? esc_attr( $_GET['key_word'] ) : '';
		$field = isset( $_GET['search_field'] ) ? esc_attr( $_GET['search_field'] ) : 'description';
		$pageid = isset( $_GET['pageid'] ) ? $_GET['pageid'] : '';
		$backlot = new WP_Ooyala_Backlot( get_option( 'ooyala' ) );
		switch( $do ) {
			case 'search':
				if ( !empty( $pageid ) &&  '' != $key_word ) {
					$backlot->query( array(
						'where'        => $field . "='" . $key_word . "' AND status='live'",
						'orderby'      => 'created_at descending',
						'limit'        => $limit,
						'page_token' => absint( $pageid )
					) );
				} else if ( '' != $key_word ) {
					$backlot->query( array(
						'where'   => $field . "='" . $key_word . "' AND status='live'",
						'orderby' => 'created_at descending',
						'limit'   => $limit,
					) );
				}
				else {
					echo 'Please enter a search term!';
					die();
				}
			break;
	 		case 'last_few':
				if ( !empty( $pageid) ) {
					$backlot->query( array(
						'where'      => "status='live'",
						'orderby'    => 'created_at descending',
						'limit'      => $limit,
						'page_token' => absint( $pageid )
					));
				} else {
					$backlot->query( array(
						'where'   => "status='live'",
						'orderby' => 'created_at descending',
						'limit'   => $limit
					) );
				}
			break;
		}
		die();
	}

	function media_page() {
		require_once( dirname( __FILE__ ) . '/ooyala-browser.php' );
	}

	function ooyala_uploader() {
		require_once( dirname( __FILE__ ) . '/api_proxy.php' );
		die();
	}
}

//Run option migration on activation
register_activation_hook( __FILE__ , array( 'Ooyala_Video', 'migrate_config' ) );

//Launch
add_action( 'init', array( 'Ooyala_Video', 'init' ) );
