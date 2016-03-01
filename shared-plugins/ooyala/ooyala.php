<?php
/*
Plugin name: Ooyala
Plugin URI: http://www.oomphinc.com/work/ooyala-wordpress-plugin/
Description: Easy Embedding of Ooyala Videos based off an Ooyala Account as defined in media settings.
Author: ooyala
Author URI: http://oomphinc.com/
Version: 2.1.1
*/

/*  Copyright 2015  Ooyala

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

/***
 ** Ooyala: The WordPress plugin!
 ***/
class Ooyala {
	// Define and register singleton
	private static $instance = false;
	public static function instance() {
		if( !self::$instance )
			self::$instance = new Ooyala;

		return self::$instance;
	}

	private function __clone() { }

	const shortcode = 'ooyala';
	const settings_key = 'ooyala';
	const capability = 'edit_posts';
	const api_base = 'https://api.ooyala.com';
	const chunk_size = 200000; //bytes per chunk for upload
	const per_page = 50; // #of assets to load per api request
	const polling_delay = 5000; // ms to wait before polling the API for status changes after an upload
	const polling_frequency = 5000; //ms to wait before polling again each time after the first try

	// defaults for player display options
	// some of these field names differ from the player API bc WP lowercases shortcode params, they are mapped below
	public $playerDefaults = array(
		'code' => '',
		'player_id' => '',
		'platform' => 'html5-fallback',
		'width' => '500', //if none are provided by the asset's streams
		'height' => '400',
		'enable_channels' => false,
		'wmode' => 'opaque',
		'initial_time' => '0',
		'auto' => false,
		'autoplay' => '',
		'chromeless' => false,
		'wrapper_class' => 'ooyala-video-wrapper',
		'callback' => 'recieveOoyalaEvent',
		'locale' => '', //equivalent to "User Default" aka providing no locale
		'additional_params' => '', //these will come through as the shortcode content, if supplied
	);

	// mapping of shortcode param => API param
	public $paramMapping = array(
		'enable_channels' => 'enableChannels',
		'initial_time' => 'initialTime',
	);

	public $allowed_values = array(
		'wmode' => array( 'window', 'transparent', 'opaque', 'gpu', 'direct' ),
		'platform' => array( 'flash', 'flash-only', 'html5-fallback', 'html5-priority' ),
	);

	var $settings_default = array(
		'api_key' => '',
		'api_secret' => '',
		'video_width' => '',
		'player_id' => '', //default player ID
	);

	/**
	 * Register actions and filters
	 *
	 * @uses add_action, add_filter
	 * @return null
	 */
	private function __construct() {
		// Enqueue essential assets
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue' ) );

		// Add the Ooyala media button
		add_action( 'media_buttons', array( $this, 'media_buttons' ), 20 );

		// Emit configuration nag
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );

		// Create view templates used by the Ooyala media manager
		add_action( 'print_media_templates', array( $this, 'print_media_templates' ) );

		// Register shorcodes
		add_action( 'init', array( $this, 'action_init' ) );

		// Do not texturize our shortcode content!
		add_filter( 'no_texturize_shortcodes', function( $codes ) { $codes[] = Ooyala::shortcode; return $codes; } );

		// Register settings screen
		add_action( 'admin_init', array( $this, 'admin_init' ) );

		// Handle signing requests
		add_action( 'wp_ajax_ooyala_sign_request', array( $this, 'ajax_sign_request' ) );

		// Handle image downloads
		add_action( 'wp_ajax_ooyala_download', array( $this, 'ajax_download' ) );

		// Handle thumbnail lookups
		add_action( 'wp_ajax_ooyala_get_image_id', array( $this, 'ajax_get_image_id' ) );
	}

	/**
	* Register shortcodes
	*/
	function action_init() {
		add_shortcode( self::shortcode, array( $this, 'shortcode' ) );
	}

	/**
	 * Register settings screen and validation callback
	 */
	function admin_init() {
		register_setting( 'media', self::settings_key, array( $this, 'validate_settings' ) );
		add_settings_section( 'ooyala-general', "Ooyala", array( $this, 'settings_fields' ), 'media' );
	}

	/**
	 * Emit settings fields
	 */
	function settings_fields() {
		$option = $this->get_settings(); ?>
		<table class="form-table" id="ooyala">
			<tr>
				<th scope="row"><label for="ooyala-apikey"><?php esc_html_e( "API Key", 'ooyala' ); ?></label></th>
				<td scope="row"><input type="text" name="ooyala[api_key]" class="widefat" id="ooyala-apikey" value="<?php echo esc_attr( $option['api_key'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="ooyala-apisecret"><?php esc_html_e( "API Secret", 'ooyala' ); ?></label></th>
				<td scope="row"><input type="text" name="ooyala[api_secret]" class="widefat" id="ooyala-apisecret" value="<?php echo esc_attr( $option['api_secret'] ); ?>" /></td>
			</tr>
			<tr>
				<td colspan="2">
					<p class="description"><?php esc_html_e( "You can obtain these values in the Ooyala Backlot administration area under 'Account > Settings'", 'ooyala' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ooyala-playerid"><?php esc_html_e( 'Default Player ID', 'ooyala' ); ?></label></th>
				<td scope="row"><input type="text" name="ooyala[player_id]" class="widefat" id="ooyala-playerid" value="<?php echo esc_attr( $option['player_id'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="ooyala-videowidth"><?php esc_html_e( 'Default Video Width', 'ooyala' ); ?></label></th>
				<td scope="row"><input type="text" name="ooyala[video_width]" id="ooyala-videowidth" value="<?php echo esc_attr( $option['video_width'] ); ?>" />px</td>
			</tr>
		</table>
	<?php
	}

	/**
	 * Validate option value
	 */
	function validate_settings( $settings ) {
		$validated = $this->settings_default;

		foreach ( $this->settings_default as $key => $value ) {
			if( isset( $settings[ $key ] ) && is_string( $settings[ $key ] ) ) {
				$validated[ $key ] = sanitize_text_field( $settings[ $key ] );
			}
		}

		return $validated;
	}

	/**
	 * Get the user's saved settings for this plugin, filled in with default values.
	 * @return array settings or defaults
	 */
	function get_settings() {
		return get_option( self::settings_key, $this->settings_default );
	}

	/**
	 * Look up an attachment ID based on a given Ooyala thumbnail URL
	 *
	 * @param string $url
	 * @return int
	 */
	function get_attachment_id( $url ) {
		// Though this is a query on postmeta, it's only invoked by administrative
		// users on a relatively infrequent basis
		$query = new WP_Query( array(
			'post_type' => 'attachment',
			'meta_query' => array( array(
				'key' => 'ooyala_source',
				'value' => $url
			) ),
			'post_status' => 'any',
			'fields' => 'ids',
			'posts_per_page' => 1
		) );

		return $query->posts ? $query->posts[0] : 0;
	}

	/**
	 * Process signing request
	 *
	 * @action wp_ajax_ooyala_sign_request
	 */
	function ajax_sign_request() {
		$settings = $this->get_settings();

		if( !$this->configured() ) {
			$this->ajax_error( __( "Plugin not configured", 'ooyala' ) );
		}

		// check nonce
		$this->ajax_check();

		$request = json_decode( file_get_contents( 'php://input' ), true );

		if( !isset( $request ) || !is_array( $request ) ) {
			$this->ajax_error( __( "Invalid request", 'ooyala' ) );
		}

		$request = wp_parse_args( $request, array(
			'method' => '',
			'path' => '',
			'body' => '',
			'params' => array()
		) );

		$request['params']['api_key'] = $settings['api_key'];
		$request['params']['expires'] = time() + 300;

		$to_sign = $settings['api_secret'] . $request['method'] . $request['path'];

		$param_sorted = array_keys( $request['params'] );
		sort( $param_sorted );

		foreach( $param_sorted as $key ) {
			$to_sign .= $key . '=' . $request['params'][$key];
		}

		$to_sign .= $request['body'];
		// Sign the payload in $to_sign
		$hash = hash( "sha256", $to_sign, true );

		$base64_hash = base64_encode( $hash );
		$request['params']['signature'] = rtrim( substr( $base64_hash, 0, 43 ), '=' );

		$url = self::api_base . $request['path'] . '?' . http_build_query( $request['params'] );

		$this->ajax_success( null, array(
			'url' => $url
		) );
	}

	/**
	 * Process download, return image ID to use as featured image.
	 *
	 * @action wp_ajax_ooyala_download
	 */
	function ajax_download() {
		if( !$this->configured() ) {
			$this->ajax_error( __( 'Plugin not configured', 'ooyala' ) );
		}

		// check nonce
		$this->ajax_check();

		$post_id = (int) filter_input( INPUT_POST, 'post_id', FILTER_VALIDATE_INT );
		$url = filter_input( INPUT_POST, 'image_url', FILTER_SANITIZE_URL );

		// sanity check inputs
		if( empty( $url ) ) {
			$this->ajax_error( __( 'No image URL given', 'ooyala' ) );
		}

		// First check that we haven't already downloaded this image.
		$existing_id = $this->get_attachment_id( $url );

		if( $existing_id ) {
			$this->ajax_success( __( 'Attachment already exists', 'ooyala' ), array( 'id' => $existing_id ) );
		}

		// The following code is copied and modified from media_sideload_image to
		// handle downloading of thumbnail assets from Ooyala.
		$image_name = basename( $url );

		// Assume JPEG by default for Ooyala-downloaded thumbnails
		if( !preg_match( $image_name, '/\.(jpe?g|png|gif)$/i', $image_name ) ) {
			$image_name .= '.jpg';
		}

		$file_array = array(
			'name' => $image_name
		);

		// Download file to temp location.
		$file_array['tmp_name'] = download_url( $url );

		// If error storing temporarily, return the error.
		if( is_wp_error( $file_array['tmp_name'] ) ) {
			$this->ajax_error( sprintf( __( 'Failed to download image at %s', 'ooyala' ), $url ) );
		}

		// Do the validation and storage stuff.
		$id = media_handle_sideload( $file_array, $post_id );

		// If error storing permanently, unlink.
		if( is_wp_error( $id ) ) {
			@unlink( $file_array['tmp_name'] );

			$this->ajax_error( __( 'Failed to store downloaded image', 'ooyala' ) );
		}

		update_post_meta( $id, 'ooyala_source', $url );

		$this->ajax_success( __( 'Successfully downloaded image', 'ooyala' ), array( 'id' => $id ) );
	}

	/**
	 * Look up an attachment ID from a preview URL
	 *
	 * @action wp_ajax_ooyala_get_image_id
	 */
	function ajax_get_image_id() {
		if( !$this->configured() ) {
			$this->ajax_error( __( 'Plugin not configured', 'ooyala' ) );
		}

		// check nonce
		$this->ajax_check();

		$post_id = (int) filter_input( INPUT_POST, 'post_id', FILTER_VALIDATE_INT );
		$url = filter_input( INPUT_POST, 'image_url', FILTER_SANITIZE_URL );

		// sanity check inputs
		if( empty( $url ) ) {
			$this->ajax_error( __( 'No image URL given', 'ooyala' ) );
		}

		// First check that we haven't already downloaded this image.
		$existing_id = $this->get_attachment_id( $url );

		$this->ajax_success( __( 'Found attachment ID', 'ooyala' ), array( 'id' => $existing_id ) );
	}

	/**
	 * Emit an error result via AJAX
	 */
	function ajax_error( $message = null, $data = array() ) {
		if( !is_null( $message ) ) {
			$data['message'] = $message;
		}

		wp_send_json_error( $data );
	}

	/**
	 * Emit a success message via AJAX
	 */
	function ajax_success( $message = null, $data = array() ) {
		if( !is_null( $message ) ) {
			$data['message'] = $message;
		}

		wp_send_json_success( $data );
	}

	/**
	 * Check against a nonce to limit exposure, all AJAX handlers must use this
	 */
	function ajax_check() {
		if( !isset( $_GET['nonce'] ) || !wp_verify_nonce( $_GET['nonce'], 'ooyala' ) ) {
			$this->ajax_error( __( 'Invalid nonce', 'ooyala' ) );
		}
	}

	/**
	 * Include all of the templates used by Backbone views
	 */
	function print_media_templates() {
		include( __DIR__ . '/ooyala-templates.php' );
	}

	/**
	 * Enqueue all assets used for admin view. Localize scripts.
	 */
	function admin_enqueue() {
		global $pagenow;

		// Only operate on edit post pages
		if( $pagenow != 'post.php' && $pagenow != 'post-new.php' )
			return;

		// Ensure all the files required by the media manager are present
		wp_enqueue_media();

		wp_enqueue_script( 'spin-js', plugins_url( '/js/spin.js', __FILE__ ), array(), 1, true );
		wp_enqueue_script( 'ooyala-views', plugins_url( '/js/ooyala-views.js', __FILE__ ), array( 'spin-js' ), 1, true );
		wp_enqueue_script( 'ooyala-models', plugins_url( '/js/ooyala-models.js', __FILE__ ), array(), 1, true );
		// load up our special edition of plupload which is catered to ooyala's API needs
		// the API requires unique URLs per chunk which cannot be fulfilled by the current version of plupload as of this writing
		wp_enqueue_script( 'ooyala-plupload', plugins_url( '/js/plupload.js', __FILE__ ), array(), 1, true );
		wp_enqueue_script( 'ooyala', plugins_url( '/js/ooyala.js', __FILE__ ), array( 'ooyala-views', 'ooyala-models', 'ooyala-plupload' ), 1, true );

		wp_enqueue_style( 'ooyala', plugins_url( '/ooyala.css', __FILE__ ) );

		// Nonce 'n' localize!
		wp_localize_script( 'ooyala-views', 'ooyala',
			array(
				'model' => array(), // Backbone models
				'view' => array(), // Backbone views
				'api' => $this->get_settings(),
				'sign' => admin_url( 'admin-ajax.php?action=ooyala_sign_request&nonce=' . wp_create_nonce( 'ooyala' ) ),
				'download' => admin_url( 'admin-ajax.php?action=ooyala_download&nonce=' . wp_create_nonce( 'ooyala' ) ),
				'imageId' => admin_url( 'admin-ajax.php?action=ooyala_get_image_id&nonce=' . wp_create_nonce( 'ooyala' ) ),

				'playerDefaults' => apply_filters( 'ooyala_default_query_args_js', $this->playerDefaults ),
				'tag' => self::shortcode,
				'chunk_size' => self::chunk_size,
				'perPage' => self::per_page,
				'pollingDelay' => self::polling_delay,
				'pollingFrequency' => self::polling_frequency,
				'text' => array(
					// Ooyala search field placeholder
					'searchPlaceholder' => __( "Search...", 'ooyala' ),
					// Search button text
					'search' => __( "Search", 'ooyala' ),
					// This will be used as the default button text
					'title'  => __( "Ooyala", 'ooyala' ),
					// this warning is shown when a user tries to navigate while an upload is in progress
					'uploadWarning' => __( 'WARNING: You have an upload in progress.', 'ooyala' ),
					// alert for success or failure upon upload
					'successMsg' => __( 'Your asset "%s" has finished processing and is now ready to be embedded.', 'ooyala' ),
					'errorMsg' => __( 'Your asset "%s" encountered an error during processing.', 'ooyala' ),

					// Results
					'oneResult' => __( "%d result", 'ooyala' ),
					'results' => __( "%d results", 'ooyala' ),
					'noResults' => __( "Sorry, we found zero results matching your search.", 'ooyala' ),
					'recentlyViewed' => __( "Recently Viewed", 'ooyala' ),

					// Button for inserting the embed code
					'insertAsset' => __( "Embed Asset", 'ooyala' ),
				)
			)
		);
	}

	/**
	 * Add "Ooyala..." button to edit screen
	 *
	 * @action media_buttons
	 */
	function media_buttons( $editor_id = 'content' ) {
		$classes = 'button ooyala-activate add_media';

		if( !$this->configured() ) {
			$classes .= ' disabled';
		} ?>
		<a href="#" id="insert-ooyala-button" class="<?php echo esc_attr( $classes ); ?>"
			data-editor="<?php echo esc_attr( $editor_id ); ?>"
			title="<?php if ( $this->configured() ) esc_attr_e( "Embed assets from your Ooyala account.", 'ooyala' ); else esc_attr_e( "This button is disabled because your Ooyala API credentials are not configured in Media Settings.", 'ooyala' ); ?>">
			<span class="ooyala-buttons-icon"></span><?php esc_html_e( "Embed Ooyala...", 'ooyala' ); ?></a>
	<?php
	}

	/**
	 * Is this module configured?
	 *
	 * @return bool
	 */
	function configured() {
		$settings = $this->get_settings();

		return !empty( $settings['api_key'] ) && !empty( $settings['api_secret'] );
	}

	/**
	 * Notify the user if the API credentials have not been entered
	 *
	 * @action admin_notices
	 */
	function admin_notices() {
		$url = admin_url( 'options-media.php#ooyala' );
		if ( $this->configured() || !current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="update-nag">
			<?php echo wp_kses_post( sprintf( __( 'Your Ooyala API credentials are not configured in <a href="%s">Media Settings</a>.', 'ooyala' ), esc_url( $url ) ) ); ?>
		</div>
		<?php
	}

	/**
	 * Determine if the supplied shortcode param is the default for the player
	 * @param  string  $field shortcode field name
	 * @param  mixed  $value
	 * @return boolean   determination
	 */
	function is_default( $field, $value ) {
		return isset( $this->playerDefaults[ $field ] ) && $this->playerDefaults[ $field ] == $value;
	}

	/**
	 * Render the Ooyala shortcode
	 */
	function shortcode( $atts, $content = null ) {
		static $num;
		// do not display markup in feeds
		if ( is_feed() ) {
			return;
		}
		// handle the 'legacy' shortcode format: [ooyala code12345]
		if ( empty( $atts['code'] ) ) {
			if ( isset( $atts[0] ) ) {
				$atts['code'] = $atts[0];
			} else {
				// we need a code!
				return;
			}
		}
		$num++;
		$settings = $this->get_settings();
		// fill in defaults saved in user settings
		if ( empty( $atts['player_id'] ) && !empty( $settings['player_id'] ) ) {
			$atts['player_id'] = $settings['player_id'];
		}
		// set a width from some defaults
		if ( empty( $atts['width'] ) ) {
			if ( !empty( $settings['video_width'] ) ) {
				$atts['width'] = $settings['video_width'];
			} elseif ( !empty( $GLOBALS['content_width'] ) ) {
				$atts['width'] = $GLOBALS['content_width'];
			}
		}
		// fill in remaining player defaults
		$atts = shortcode_atts( apply_filters( 'ooyala_default_query_args', $this->playerDefaults ), $atts );
		//coerce string true and false to their respective boolean counterparts
		$atts = array_map( function($value) { if ($value==='true') return true; elseif ($value==='false') return false; else return $value; }, $atts );

		// match against allowed values
		foreach ( array( 'wmode', 'platform' ) as $att ) {
			$atts[ $att ] = in_array( $atts[ $att ], $this->allowed_values[ $att ] ) ? $atts[ $att ] : $this->playerDefaults[ $att ];
		}

		$width = (int) $atts['width'];
		$height = (int) $atts['height'];

		$player_style = '';

		ob_start();

		if ( $atts['auto'] ) {
			// Auto-size the player by stretching it into a fixed-ratio container
			$container_style = 'max-width:' . $width . 'px;';

			$player_style = 'position:absolute;top:0;right:0;bottom:0;left:0';

			$sizer_style =
				'width:auto;' .
				'padding-top:' . ($height / $width * 100) . '%;' .
				'position:relative';

	?>
	<div class="ooyala-container" style="<?php echo esc_attr( $container_style ); ?>">
		<div class="ooyala-sizer" style="<?php echo esc_attr( $sizer_style ); ?>">
	<?php
		}

		if ( !empty( $atts['player_id'] ) ) {
			// player query string parameters
			$query_params = array(
				'namespace' => 'OoyalaPlayer' . $num // each player has its own namespace to avoid collisions
			);
			// JS parameters - start with passed json, if any
			if ( $content
				&& ( $json = json_decode( $content, true ) )
				&& is_array( $json )
				&& count( array_filter( array_keys( $json ), 'is_string' ) ) //only if assoc array
			) {
				$js_params = $json;
			} else {
				$js_params = array();
			}

			// pick out all other params
			foreach ( $atts as $key => $value ) {
				switch ( $key ) {
					// no-op bc these have special placement in the embed code
					case 'width':
					case 'height':
						if( ! $atts['auto'] ) {
							$js_params[$key] = (int) $value;
						}

						break;

					case 'code':
					case 'player_id':
						break;

					// these are query params and are appended to the player script URL
					case 'platform':
						$query_params[$key] = $value;
						break;

					case 'chromeless':
						if ( !$this->is_default( $key, $value ) ) {
							$js_params['layout'] = 'chromeless';
						}
						break;

					// all other params become JS parameters
					// these will override values of the same name supplied from the JSON content block
					default:
						if ( !$this->is_default( $key, $value ) ) {
							$js_params[ isset( $this->paramMapping[ $key ] ) ? $this->paramMapping[ $key ] : $key ] = $value;
						}
					break;
				}
			}
		?>
			<script src="<?php echo esc_url( '//player.ooyala.com/v3/' . $atts['player_id'] . '?' . http_build_query( $query_params ) ); ?>"></script>
			<div id="ooyalaplayer-<?php echo (int) $num; ?>" class="<?php echo esc_attr( $atts['wrapper_class'] ); ?>" style="<?php echo esc_attr( $player_style ); ?>" ></div>
			<script>
				var ooyalaplayers = ooyalaplayers || [];
				<?php
				$player = 'OoyalaPlayer' . $num;
				$params = array( "ooyalaplayer-$num", $atts['code'] );
				$js_params = apply_filters( 'ooyala_js_params', $js_params );
				if ( count( $js_params ) ) {
					$params[] = $js_params;
				}
				?>
					var config<?php echo (int)$num ?> = <?php echo json_encode( $params ); ?>;
					if(config<?php echo (int)$num ?>[config<?php echo (int)$num ?>.length - 1]["amazon-ads-manager"] && window[config<?php echo (int)$num ?>[config<?php echo (int)$num ?>.length - 1]["amazon-ads-manager"].adTag]){
						config<?php echo (int) $num ?>[config<?php echo (int) $num ?>.length - 1]["google-ima-ads-manager"] = {'adTagUrl' : ''} ;
						config<?php echo (int) $num ?>[config<?php echo (int) $num ?>.length - 1]["google-ima-ads-manager"].adTagUrl = window[config<?php echo (int) $num ?>[config<?php echo (int) $num ?>.length - 1]["amazon-ads-manager"].adTag];
						delete config<?php echo (int)$num; ?>[config<?php echo (int)$num; ?>.length - 1]["amazon-ads-manager"];
					}
				<?php
				echo esc_js( $player ) . '.ready(function() { ooyalaplayers.push(' . esc_js( $player ) . '.Player.create.apply(this, config'.$num.') ); });';
				?>
			</script>
			<noscript><div><?php esc_html_e( 'Please enable Javascript to watch this video', 'ooyala' ); ?></div></noscript>
		<?php
		// no player id, use the v2 player
		} else {
			if( !$atts['auto'] ) {
				$player_style = '';
			}

			$script_url = add_query_arg( array(
				'width' => $atts['width'],
				'height' => $atts['height'],
				'embedCode' => $atts['code'],
				'autoplay' => $atts['autoplay'] ? '1' : '0',
				'callback' => $atts['callback'],
				'wmode' => $atts['wmode'],
				'version' => 2,
			), 'https://player.ooyala.com/player.js' );
			?>
			<div id="ooyalaplayer-<?php echo (int) $num; ?>" class="<?php echo esc_attr( $atts['wrapper_class'] ); ?>" style="<?php echo esc_attr( $player_style ); ?>">
				<script src="<?php echo esc_url( $script_url ); ?>"></script>
				<noscript>
					<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" width="<?php echo (int) $atts['width']; ?>" height="<?php echo (int) $atts['height']; ?>" codebase="http://fpdownload.macromedia.com/get/flashplayer/current/swflash.cab">
						<param name="movie" value="<?php echo esc_url( 'http://player.ooyala.com/player.swf?embedCode=' . $atts['code'] . '&version=2' ); ?>">
						<param name="bgcolor" value="#000000">
						<param name="allowScriptAccess" value="always">
						<param name="allowFullScreen" value="true">
						<param name="wmode" value="<?php echo esc_attr( $atts['wmode'] ); ?>">
						<param name="flashvars" value="embedType=noscriptObjectTag&amp;embedCode=###VID###">
						<embed src="<?php echo esc_url( 'http://player.ooyala.com/player.swf?embedCode=' . $atts['code'] . '&version=2' ); ?>" bgcolor="#000000" width="<?php echo (int) $atts['width']; ?>" height="<?php echo (int) $atts['height']; ?>" align="middle" play="true" loop="false" allowscriptaccess="always" allowfullscreen="true" type="application/x-shockwave-flash" flashvars="&amp;embedCode=<?php echo esc_attr( $atts['code'] ); ?>" pluginspage="http://www.adobe.com/go/getflashplayer">
						</embed>
					</object>
				</noscript>
			</div>
			<?php
		}

		if ( $atts['auto'] ) { ?>
			</div>
		</div>
		<?php
		}

		return ob_get_clean();
	}
}

Ooyala::instance();
