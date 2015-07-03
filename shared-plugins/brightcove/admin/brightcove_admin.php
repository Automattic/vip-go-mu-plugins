<?php 

/* Sets up an admin notice notifying the user that they have not registered their brightcove settings */
add_action('admin_notices', 'brightcove_settings_notice');

/*Checks to see if defaults are set, displays error if not set*/
function brightcove_settings_notice() {
  global $bcGlobalVariables;

  if ($bcGlobalVariables['defaultSet'] == false) {
    if (current_user_can('manage_options')) {
      echo "<div class='error'><p>You have not entered your settings for the Brightcove Plugin. Please set them up at <a href='". menu_page_url( 'brightcove_menu', false ) ."'>Brightcove Settings</a></p></div>";
    } else {
      echo "<div class='error'><p>  You have not set up your defaults for the Brightcove plugin. Please contact your site administrator to set these defaults.</p></div>";
    } 
    
  }
}

add_action( 'admin_menu', 'brightcove_add_admin_menu' );
add_action( 'admin_init', 'brightcove_register_settings' );
add_action( 'admin_enqueue_scripts', 'brightcove_enqueue_admin_settings_scripts' );

function brightcove_add_admin_menu()
{
	add_options_page(__('Brightcove Settings'), __('Brightcove'), 'manage_options', 'brightcove_menu', 'brightcove_menu_render' ); 
}

function brightcove_enqueue_admin_settings_scripts() {
	global $plugin_page;

	if ( 'brightcove_menu' != $plugin_page )
	  return;

	wp_enqueue_script( 'brightcove_admin_script', plugins_url( '/brightcove_admin.js', __FILE__ ), array( 'jqueryPlaceholder', 'jquery-validate', 'jquery-validate-additional' ) );
	wp_enqueue_style( 'brightcove_menu_style', plugins_url( '/brightcove_admin.css', __FILE__ ) );
}

function brightcove_menu_render() {
$playerID = get_option('bc_player_id');
$playerKey_playlist = get_option('bc_player_key_playlist');

$publisherID = get_option('bc_pub_id');

if (isset($_GET['settings-updated'])) {
  $isTrue = sanitize_key( $_GET['settings-updated'] );
  if ($isTrue == true) {
    echo '<div class="updated"><p> Your settings have been saved </p></div>';
  }
}

?>

<input id='dataSet' type='hidden' data-defaultPlayer="<?php echo esc_attr( $playerID ); ?>" data-publisherID="<?php echo esc_attr( $publisherID ); ?>" data-defaultplayerplaylistkey="<?php echo esc_attr( $playerKey_playlist ); ?>" />
  <div class='wrap'>
    <h2>Brightcove Settings </h2> 
    <form id='brightcove_menu' method='post' action='options.php'>
    <?php settings_fields( 'brightcove-settings-group' ); ?>
      <h3> Required Settings </h3><a href='#brightcove-settings-help'> Where do I find these? </a>
      <table class='form-table required-settings'> 
        <tbody>
         <tr valign="top">
          <th scope="row">
            <label for="bc_pub_id">Publisher ID</label>
          </th>
          <td>
            <input class='digits required regular-text' value = "<?php echo esc_attr( $publisherID ); ?>" name="bc_pub_id" type="text" id="bc_pub_id" placeholder='Publisher ID' class="regular-text">
            <span class='description'>Publisher ID</span>
          </td>
        </tr>
         <tr valign="top">
            <th scope="row">
              <label for="bc_player_id">Default Player ID - Single Video</label>
            </th>
            <td>
              <input value ="<?php echo esc_attr( $playerID ); ?>" name="bc_player_id" type="text" id="bc_player_id" class="required regular-text digits" placeholder='Default player ID'>
              <span class='description'>Default player ID for setting a custom player template across the site.</span>
            </td>
          </tr>
          <!--<tr valign="top">
            <th scope="row">
              <label for="bc_player_id_playlist">Default Player ID - Playlist</label>
            </th>
            <td>
              <input value ="<?php echo esc_attr( $playerID_playlist ); ?>"  name="bc_player_id_playlist" type="text" id="bc_player_id_playlist" class="required digits regular-text" placeholder='Default player ID for Playlists'>
              <span class='description'>Default player ID for setting a custom player template across the site for playlists.</span>
            </td>
          </tr> -->
          <tr valign="top">
            <th scope="row">
              <label for="bc_player_key_playlist">Default Player Key - Playlist</label>
            </th>
            <td>
              <input value ="<?php echo esc_attr( $playerKey_playlist ); ?>"  name="bc_player_key_playlist" type="text" id="bc_player_key_playlist" class="required regular-text" placeholder='Default player key for Playlists'>
              <span class='description'>Default player key for setting a custom player template across the site for playlists.<a href='#player-key-help'>?</a></span>
            </td>
          </tr>
        </tbody>
      </table>
      <h3> Required Only for Accessing Media API</h3>
      <table class='form-table'> 
        <tbody>
          <tr valign="top">
            <th scope="row">
              <label for="bc_api_key">API Read Token </label> 
            </th>
            <td>
              <input value='<?php echo esc_attr( get_option('bc_api_key') ); ?>' name="bc_api_key" type="text" id="bc_api_key" placeholder='API Key' class="regular-text">
              <span class='description'>API Read Token <a href='#read-key-help'>?</a></span>
            </td>
          </tr>
        </tbody>
      </table>
      <h3> Optional Settings </h3>
      <table class='form-table'> 
        <tbody>
          <tr valign="top">
            <th scope="row">
              <label for="bc_default_width">Default Player Width</label>
            </th>
            <td>
              <input value='<?php echo esc_attr( get_option('bc_default_width') ); ?>' name="bc_default_width" type="text" id="bc_default_width" class="digits regular-text" placeholder='Default player width'>
              <span class='description'>Default width for video players</span>
            </td>
          </tr>
          <tr valign="top">
            <th scope="row">
              <label for="bc_default_height">Default Player Height </label>
            </th>
            <td>
              <input value='<?php echo esc_attr( get_option('bc_default_height') ); ?>' name="bc_default_height" type="text" id="bc_default_height" class="digits regular-text" placeholder='Default player height'>
              <span class='description'>Default height for video players</span>
            </td>
          </tr>
          <tr valign="top">
            <th scope="row">
              <label for="bc_default_width_playlist">Default Playlist Player Width </label>
            </th>
            <td>
              <input value='<?php echo esc_attr( get_option('bc_default_width_playlist') ); ?>' name="bc_default_width_playlist" type="text" id="bc_default_width_playlist" class="digits regular-text" placeholder='Default playlist player width'>
              <span class='description'>Default width for playlist players</span>
            </td>
          </tr>
          <tr valign="top">
            <th scope="row">
              <label for="bc_default_height">Default Playlist Player Height </label>
            </th>
            <td>
              <input value='<?php echo esc_attr( get_option('bc_default_height_playlist') ); ?>' name="bc_default_height_playlist" type="text" id="bc_default_height_playlist" class="digits regular-text" placeholder='Default playlist player height'>
              <span class='description'>Default height for playlist players</span>
            </td>
          </tr>
        </tbody>
      </table>
      <p class="submit">
        <input type="submit" name="submit" id="submit" class="button-primary" value="Save Changes">
      </p>
    </form>

    <div id="brightcove-settings-help" class="brightcove-settings-help">
      <h2>Getting Your Brightcove Settings</h2>
      <p>Each of the following settings can be retrieved by <a href="https://my.brightcove.com/" target="_blank">logging in to your Brightcove Video Cloud account</a>.</p>

      <h3>Publisher ID</h3>
      <p>To retrieve your Publisher ID, go to <strong>Home &gt; Profile</strong>. It is located under the account name.</p>

      <h3>Player ID(s)</h3>
      <p>To retrieve the ID for a player: 
        <ol>
          <li>Open the <strong>Publishing</strong> tab</li>
          <li>Click a player</li>
          <li>Copy the <strong>Player ID</strong> under the player preview in the right hand panel.</li>
        </ol>
      </p>

      <p id='player-key-help' >To retrieve the Key for a player: 
        <ol>
          <li>Open the <strong>Publishing</strong> tab</li>
          <li>Click the same player that you used for the playlist player</li>
          <li>Click on the <strong> Get Code </strong> button on the bottom of the screen</li>
          <li> A code window should appear, check to see it's in Javascript mode </li>
          <li> In Javascript mode scroll down until you see <strong>param name = "playerKey"</strong> </li>
          <li> Copy the value from playerKey </li>
          <h4>Example</h4> 
          <img src='<?php echo plugins_url( '/playerKey.png', __FILE__ ); ?>' title='Player Key Example' />
        </ol>
      </p>

      <h3 id='read-key-help'>API Read Key</h3>
      <p>For Video Cloud Professional and Video Cloud Enterprise Brightcove customers, the API Read Key is required for enhanced functionality such as searching of videos and playlists via the Brightcove Media API. To retrieve your API Read Key, go to <strong>Home &gt; Account Settings &gt; API Management</strong>. See <a href="http://support.brightcove.com/en/docs/managing-media-api-tokens" target="_blank">this support article</a> for detailed information on API Key management.</p>
    </div>

  </div>
  <?php
}

function brightcove_register_settings() { // whitelist options
  register_setting( 'brightcove-settings-group', 'bc_pub_id' , 'sanitize_key');
  register_setting( 'brightcove-settings-group', 'bc_player_id', 'sanitize_key' );
  register_setting( 'brightcove-settings-group', 'bc_player_key_playlist', 'sanitize_text_field' );
  register_setting( 'brightcove-settings-group', 'bc_api_key', 'sanitize_text_field' );
  register_setting( 'brightcove-settings-group', 'bc_default_height', 'sanitize_key' );
  register_setting( 'brightcove-settings-group', 'bc_default_width', 'sanitize_key' );
  register_setting( 'brightcove-settings-group', 'bc_default_height_playlist', 'sanitize_key' );
  register_setting( 'brightcove-settings-group', 'bc_default_width_playlist', 'sanitize_key' );
}