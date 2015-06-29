<?php
//Redirect to the main plugin options page if form has been submitted
if ( isset( $_GET['action'] ) ) {
	if ( 'refresh' == $_GET['action'] && isset( $_GET['updated'] ) ) {
		wp_redirect( admin_url( 'options-general.php?page=' . GCE_PLUGIN_NAME . '.php&updated=refreshed' ) );
		exit;
	}
}

add_settings_section( 'gce_refresh', __( 'Refresh Feed Cache', GCE_TEXT_DOMAIN ), 'gce_refresh_main_text', 'refresh_feed' );
//Unique ID                                  //Title                            //Function                //Page         //Section ID
add_settings_field( 'gce_refresh_id_field',    __( 'Feed ID', GCE_TEXT_DOMAIN ),    'gce_refresh_id_field',    'refresh_feed', 'gce_refresh' );
add_settings_field( 'gce_refresh_title_field', __( 'Feed Title', GCE_TEXT_DOMAIN ), 'gce_refresh_title_field', 'refresh_feed', 'gce_refresh' );

//Main text
function gce_refresh_main_text() {
	?>
	<p><?php _e( 'The plugin will automatically refresh the cache when it expires, but you can manually clear the cache now by clicking the button below.', GCE_TEXT_DOMAIN ); ?></p>
	<p><?php _e( 'Are you want you want to clear the cache data for this feed?', GCE_TEXT_DOMAIN ); ?></p>
	<?php
}

//ID
function gce_refresh_id_field() {
	$options = get_option( GCE_OPTIONS_NAME );
	$options = $options[$_GET['id']];
	?>
	<input type="text" disabled="disabled" value="<?php echo esc_attr( $options['id'] ); ?>" size="3" />
	<input type="hidden" name="gce_options[id]" value="<?php echo esc_attr( $options['id'] ); ?>" />
	<?php
}

//Title
function gce_refresh_title_field() {
	$options = get_option( GCE_OPTIONS_NAME );
	$options = $options[$_GET['id']];
	?>
	<input type="text" name="gce_options[title]" disabled="disabled" value="<?php echo esc_attr( $options['title'] ); ?>" size="50" />
	<?php
}
?>