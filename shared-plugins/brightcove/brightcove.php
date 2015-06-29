<?php
/**
 * @package Brightcove
 * @version 1.0
 */
/*
Plugin Name: Brightcove Video Cloud
Plugin URL: 
Description: An easy to use plugin that inserts Brightcove Video into your Wordpress site. 
Author: Brightcove
Version: 1.0
Author URI: 
*/

require __DIR__  . '/admin/brightcove_admin.php';
require __DIR__  . '/brightcove_shortcode.php';

/************************Upload Media Tab ***************************/

function brightcove_media_menu($tabs) {
	//TODO Check for isset or empty instead
	$api_key = get_option('bc_api_key');
  if ( ! empty( $api_key ) ) {
    $tabs['brightcove_api']='Brightcove'; 
  } else {
    $tabs['brightcove']='Brightcove';
  }
  return $tabs;
}


add_filter('media_upload_tabs', 'brightcove_media_menu');
add_action('media_upload_brightcove', 'brightcove_menu_handle');
add_action('media_upload_brightcove_api', 'brightcove_api_menu_handle');

function brightcove_menu_handle() {
	//TODO check to see what $errors is being used for
	//TODO check to see if parameters can be passed in here
	//if not then have bc_media_upload_form call function
	return wp_iframe('brightcove_media_upload_form');
}

function brightcove_api_menu_handle() {
  return wp_iframe('brightcove_media_api_upload_form');
}

add_action( 'wp_enqueue_scripts', 'brightcove_register_scripts', 1 ); // register our scripts early
add_action( 'admin_enqueue_scripts', 'brightcove_register_scripts', 1 );

function brightcove_register_scripts() {
	$brightcove_script = is_ssl() ? 'https://sadmin.brightcove.com/js/BrightcoveExperiences.js' : 'http://admin.brightcove.com/js/BrightcoveExperiences.js';
	wp_register_script( 'brightcove_script', $brightcove_script );
	wp_register_script( 'jqueryPlaceholder', plugins_url( '/jQueryPlaceholder/jQueryPlaceholder.js', __FILE__ ) );
	wp_register_script( 'jquery-validate', plugins_url( '/jQueryValidation/jquery.validate.min.js', __FILE__ ) );
	wp_register_script( 'jquery-validate-additional', plugins_url( '/jQueryValidation/additional-methods.min.js', __FILE__ ) );	
}

//Adds all the scripts nessesary for plugin to work
function brightcove_enqueue_admin_media_scripts() {

	wp_enqueue_script('media-upload');

	wp_enqueue_script( 'brightcove_script' );

	wp_enqueue_script( 'dynamic_brightcove_script', plugins_url( '/dynamic_brightcove.js', __FILE__ ), array( 'jquery-ui-tabs', 'jqueryPlaceholder', 'jquery-validate', 'jquery-validate-additional' ) );
	
	wp_enqueue_script( 'mapi_script', plugins_url( '/bc-mapi.js', __FILE__ ) );

	wp_enqueue_style( 'brightcove-styles', plugins_url( '/brightcove.css', __FILE__ ) );
	wp_enqueue_style( 'brightove-jquery-ui-style', ( is_ssl() ? 'https' : 'http' ) . '://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/smoothness/jquery-ui.css' );
}

//Adds all the scripts nessesary for plugin to work
function brightcove_enqueue_frontend_scripts() {
	wp_enqueue_script( 'brightcove_script' );
}


add_action( 'init', 'brightcove_init_global_vars' );

// Initialize global variables 
function brightcove_init_global_vars() {

	GLOBAL $bcGlobalVariables;
	
	$bcGlobalVariables = Array('playerID'=>null, 
	'defaultHeight' => null, 
	'defaultWidth' => null, 
	'defaultKeyPlaylist' => null, 
	'defaultHeightPlaylist' => null, 
	'defaultWidthPlaylist' => null,
	'defaultSet' => null, 
	'defaultSetErrorMessage' => null, 
	'defaultsSection' => null, 
	'loadingImg' => null, 
	'publisherID' => null);
	
	//Get Post ID
 	$post_id = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0;
	
	//Publisher ID 
	$bcGlobalVariables['publisherID'] = get_option('bc_pub_id');
	
	//Player ID for single videos
	$bcGlobalVariables['playerID'] = get_option('bc_player_id');
	//Default height & width for single video players
	$bcGlobalVariables['defaultHeight']= get_option('bc_default_height');
	if ($bcGlobalVariables['defaultHeight'] == '') {
	  $bcGlobalVariables['defaultHeight']='270';
	}
	$bcGlobalVariables['defaultWidth']= get_option('bc_default_width');
	if ($bcGlobalVariables['defaultWidth'] == '') {
	  $bcGlobalVariables['defaultWidth']='480';
	}
	//Player ID for playlists
	$bcGlobalVariables['playerKeyPlaylist']= get_option('bc_player_key_playlist');
	
	$bcGlobalVariables['playerIDPlaylist']= get_option('bc_player_id_playlist');
	
	
	//Default height & width for playlist players
	$bcGlobalVariables['defaultHeightPlaylist']= get_option('bc_default_height_playlist');
	if ($bcGlobalVariables['defaultHeightPlaylist'] == '') {
	  $bcGlobalVariables['defaultHeightPlaylist']='400';
	}
	$bcGlobalVariables['defaultWidthPlaylist']= get_option('bc_default_width_playlist');
	if ($bcGlobalVariables['defaultWidthPlaylist'] == '') {
	  $bcGlobalVariables['defaultWidthPlaylist']='940';
	}
	//Checks to see if both those variables are set
	if ($bcGlobalVariables['playerID'] == '' || $bcGlobalVariables['playerKeyPlaylist'] == '' || $bcGlobalVariables['publisherID'] == '') {
	  $bcGlobalVariables['defaultSet']=false;
	} else  {
	  $bcGlobalVariables['defaultSet']=true;
	}
	
	if ( current_user_can('manage_options') ) {
		$bcGlobalVariables['defaultSetErrorMessage'] = "<div class='hidden error' id='defaults-not-set' data-defaultsSet='". esc_attr( $bcGlobalVariables['defaultSet'] ) ."'>
		 You have not set up your defaults for this plugin. Please click on the link to set your defaults.
	  <a target='_top' href='admin.php?page=brightcove_menu'>Brightcove Settings</a>
	  </div>";
	} else  {
		 $bcGlobalVariables['defaultSetErrorMessage'] = "<div class='hidden error' id='defaults-not-set' data-defaultsSet='". esc_attr( $bcGlobalVariables['defaultSet'] )."'>
		You have not set up your defaults for the Brightcove plugin. Please contact your site administrator to set these defaults.
	  </div>";	
	}
	
	$bcGlobalVariables['defaultsSection'] = 
		"<div class='defaults'>
		<input type='hidden' id='bc-default-player' name='bc-default-player' value='". esc_attr( $bcGlobalVariables['playerID'] ) ."' />
		<input type='hidden' id='bc-default-width' name='bc-default-width' value='". esc_attr( $bcGlobalVariables['defaultWidth'] ) ."' />
		<input type='hidden' id='bc-default-height' name='bc-default-height' value='". esc_attr( $bcGlobalVariables['defaultHeight'] ) ."' />
		<input type='hidden' id='bc-default-player-playlist' name='bc-default-player-playlist' value='". esc_attr( $bcGlobalVariables['playerIDPlaylist'] ) ."' />
		<input type='hidden' id='bc-default-player-playlist-key' name='bc-default-player-playlist-key' value='". esc_attr( $bcGlobalVariables['playerKeyPlaylist'] ) ."' />
		<input type='hidden' id='bc-default-width-playlist' name='bc-default-width-playlist' value='". esc_attr( $bcGlobalVariables['defaultWidthPlaylist'] ) ."' />
		<input type='hidden' id='bc-default-height-playlist' name='bc-default-height-playlist' value='". esc_attr( $bcGlobalVariables['defaultHeightPlaylist'] ) ."' />
		<input type='hidden' id='bc-default-link' name='bc-default-link' value='". esc_attr( get_permalink($post_id) ) ."' />

		</div>";
	
	$bcGlobalVariables['loadingImg'] = "<img class='loading-img' src='". includes_url( '/js/thickbox/loadingAnimation.gif' ) . "' />";

}


function brightcove_set_shortcode_button ($playlistOrVideo, $buttonText) {

if ($playlistOrVideo == 'playlist') {
	$id='playlist-shortcode-button';
} else {
	$id='video-shortcode-button';
}

?>
	<div class='media-item no-border insert-button-container'>
      <button disabled='disabled' id='<?php echo esc_attr($id); ?>' class='aligncenter button'/><?php echo esc_html( $buttonText ); ?></button>
    </div> <?php
	
} 

//TODO Pass in as map
function brightcove_add_player_settings($playlistOrVideo, $buttonText) { 
	GLOBAL $bcGlobalVariables;
	if ($playlistOrVideo == 'playlist') {
		$setting = '-playlist';
		$height = $bcGlobalVariables['defaultHeightPlaylist'];
		$width = $bcGlobalVariables['defaultWidthPlaylist'];
		$player = $bcGlobalVariables['playerIDPlaylist'];
		$playerKey = $bcGlobalVariables['playerKeyPlaylist'];
		$id='playlist-settings';
		$class='playlist-hide';
		$playerHTML="<tr class='bc-width-row'>
            <th valign='top' scope='row' class='label'>
              <span class=;alignleft;><label for=bcPlaylistKey'>Playlist Key</label></span>
              <span class='alignright'></span>
            </th>
            <td>
             <input class='player-data' type='text' name='bcPlaylistKey' id='bc-player-playlist-key' placeholder='Default is ". esc_attr( $playerKey ) . "' />
            </td>
          </tr>";
	} else {
		$setting = 'video';
		$height = $bcGlobalVariables['defaultHeight'];
		$width = $bcGlobalVariables['defaultWidth'];
		$player = $bcGlobalVariables['playerID'];
		$id='video-settings';
		$class='video-hide';
		$playerKey='';
		$playerHTML="<tr class='bc-player-row'>
            <th valign='top' scope='row' class='label'>
              <span class='alignleft'><label for='bcPlayer'>Player ID:</label></span>
              <span class='alignright'></span>
            </th>
            <td>
             <input class='digits player-data' type='text' name='bcPlayer' id='bc-player-$setting' placeholder='Default ID is ". esc_attr( $player ) . "'/>
            </td>
          </tr>";
	}

	?>
	<form class='<?php echo esc_attr( $class );?>' id='<?php echo esc_attr( $id ); ?>'>
        <table>
          <tbody>
          <?php echo $playerHTML; ?>
          <tr class='bc-width-row'>
            <th valign='top' scope='row' class='label'>
              <span class="alignleft"><label for="bcWidth">Width:</label></span>
              <span class="alignright"></span>
            </th>
            <td>
             <input class='digits player-data' type='text' name='bcWidth' id='bc-width<?php echo esc_attr( $setting ); ?>' placeholder='Default is <?php echo esc_attr( $width ); ?> px' />
            </td>
          </tr>
          <tr class='bc-height-row'>
            <th valign='top' scope='row' class='label'>
              <span class="alignleft"><label for="bcHeight">Height:</label></span>
              <span class="alignright"></span>
            </th>
            <td>
             <input class='digits player-data'  type='text' name='bcHeight' id='bc-height<?php echo esc_attr( $setting ); ?>' placeholder='Default is <?php echo esc_attr( $height ); ?> px' />
            </td>
          </tr>
          </tbody>
        </table>
        <?php brightcove_set_shortcode_button($playlistOrVideo, $buttonText); ?>
      </form> 
      <?php
}

function brightcove_add_preview_area($playlistOrVideo) {

	if ($playlistOrVideo == 'playlist') {
		$id='dynamic-bc-placeholder-playlist';
		$class='playlist-hide';
		$otherClass='playlist';
	} else {
		$id='dynamic-bc-placeholder-video';
		$class='video-hide';
		$otherClass='video';
	}

?>
	<div class='<?php echo esc_attr( $class ); ?> media-item no-border player-preview preview-container hidden'>
      <h3 class='preview-header'>Video Preview</h3>
      <table>
        <tbody>
          <tr>
            <td>
				<div class='alignleft'>
					<h4 id='bc-title-<?php echo esc_attr( $otherClass ); ?>' class='bc-title'></h4>
					<p id='bc-description-<?php echo esc_attr( $otherClass ); ?>' class='bc-description'></p>
					<div id="<?php echo esc_attr( $id ); ?>"></div>
				</div>
				<div class='alignleft'>
				</div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <?php
}

function brightcove_media_upload_form () {
	media_upload_header();
	brightcove_enqueue_admin_media_scripts();
?>
<div class="bc-container">
	<?php
	GLOBAL $bcGlobalVariables;
		echo wp_kses_post( $bcGlobalVariables['defaultSetErrorMessage'] ); 
		echo wp_kses( $bcGlobalVariables['defaultsSection'], array( 'input' => array( 'id' => array(), 'type' => array(), 'value' => array(), 'name' => array() ), 'div' => array( 'class' => array() ) ) );
		echo wp_kses( $bcGlobalVariables['loadingImg'], array( 'img' => array( 'class' => array(), 'src' => array() ) ) );
	?>

	<div class='no-error'>
	    <div id='tabs'>
	      <ul>
	        <li ><a class='video-tab' href="#tabs-1">Videos</a></li>
	        <li><a class='playlist-tab' href="#tabs-2">Playlists</a></li>
	      </ul>
	    <div class='tab clearfix video-tab' id='tabs-1'>
	        <div class='media-item no-border'>
	          <form id='validate-video'>
	            <table>
	              <tbody>
	                <tr>
	                  <th valign='top' scope='row' class='label'>
	                    <span class="alignleft"><label for="bc-video">Video:</label></span>
	                    <span class="alignright"></span>
	                  </th>
	                  <td>
	                    <input class='id-field player-data' placeholder='Video ID' aria-required="true" type='text' name='bcVideo' id='bc-video' placeholder='Video ID or URL'>
	                  </td>
	                </tr>
	                <tr>
	                  <th valign='top' scope='row' class='label'>
	                  </th>
	                  <td class='bc-check'>
	                     <input class='player-data alignleft' type='checkbox' name='bc-video-ref' id='bc-video-ref' />
	                     <span class="alignleft"><label for='bc-video-ref'>This is a reference ID, not a video ID </label></span>
	                  </td>
	                </tr>
	              </tbody>
	            </table>
	          </form>
	        </div>
	      </div>
	      <div class='tab clearfix playlist-tab' id='tabs-2'>
		       <div class='media-item no-border'>
		          <form id='validate-playlist'>
		            <table> 
		              <tbody>
		                <tr>
		                  <th valign='top' scope='row' class='label' >
		                    <span class="alignleft"><label for="bcPlaylist">Playlist:</label></span>
		                    <span class="alignright"></span>
		                  </th>
		                  <td>
		                   <input class='id-field player-data' type='text' name='bcPlaylist' id='bc-playlist' placeholder='Playlist ID(s) separated by commas or spaces' />
		                  </td>
		                </tr>
		                <tr>
		                  <th valign='top' scope='row' class='label'>
		                  </th>
		                  <td class='bc-check'>
		                   <input class='alignleft player-data' type='checkbox' name='bc-playlist-ref' id='bc-playlist-ref'/>
		                   <span class="alignleft"><label for='bc-playlist-ref'>These are reference IDs, not playlist IDs </label></span>
		                  </td>
		                </tr>
		              </tbody>
		            </table>
		          </form>
		        </div>
		      </div>
		    </div><!-- End of tabs --> 
		    <div id='bc-error' class='hidden error'>An error has occured, please check to make sure that you have a valid video or playlist ID</div>

<?php
	//TODO pass in map of defaults
	brightcove_add_player_settings('video', 'Insert Shortcode');?> 
	
<?php
	brightcove_add_preview_area('video');
	brightcove_add_player_settings('playlist', 'Insert Shortcode');
	brightcove_add_preview_area('playlist');

?>
</div> <?php	
}

function brightcove_media_api_upload_form () {
	GLOBAL $bcGlobalVariables;
	media_upload_header();
	brightcove_enqueue_admin_media_scripts();
	$apiKey = get_option('bc_api_key');
?>
	<div class="bc-container">
	<?php
		echo wp_kses_post( $bcGlobalVariables['defaultSetErrorMessage'] ); 
		echo wp_kses( $bcGlobalVariables['defaultsSection'], array( 'input' => array( 'id' => array(), 'type' => array(), 'value' => array(), 'name' => array() ), 'div' => array( 'class' => array() ) ) );
		echo wp_kses( $bcGlobalVariables['loadingImg'], array( 'img' => array( 'class' => array(), 'src' => array() ) ) );

	?>
<input type='hidden' id='bc-api-key' name='bc-api-key' value='<?php echo esc_attr( $apiKey ); ?>'>
<div class='no-error'>
	<div id='tabs-api'>
		<ul>
			<li ><a class='video-tab-api' href="#tabs-1">Videos</a></li>
			<li><a class='playlist-tab-api' href="#tabs-2">Playlists</a></li>
		</ul>
		<div id='tabs-1' class='tabs clearfix video-tabs'>
			<form class='clearfix' id='search-form'>
				<div class='alignleft'>
				  <input placeholder=' Search by name, description, tag or custom field' id='bc-search-field' type='text'>
				</div>
				<div class='alignright'>
				  <button class='button' type='submit' id='bc-search'>Search</button>
				</div>
			</form>
			<div class='bc-video-search clearfix' id='bc-video-search-video'></div>
			<?php brightcove_add_player_settings('video', 'Insert Video'); ?>
		</div>
		<div id='tabs-2' class='tabs clearfix playlist-tab'>
			<div class='bc-video-search clearfix' id='bc-video-search-playlist'></div>
			<?php brightcove_add_player_settings('playlist', 'Insert Playlists');?>
		</div>
	</div>
</div>
<?php
}
