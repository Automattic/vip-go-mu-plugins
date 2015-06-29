<?php
//Redirect to the main plugin options page if form has been submitted
if ( isset( $_GET['updated'], $_GET['action'] ) && 'add' == $_GET['action'] ) {
	wp_redirect( admin_url( 'options-general.php?page=' . GCE_PLUGIN_NAME . '.php&updated=deleted' ) );
	exit;
}

add_settings_section('gce_delete', __('Delete Feed', GCE_TEXT_DOMAIN), 'gce_delete_main_text', 'delete_feed');
//Unique ID                                  //Title                            //Function                //Page         //Section ID
add_settings_field('gce_delete_id_field',    __('Feed ID', GCE_TEXT_DOMAIN),    'gce_delete_id_field',    'delete_feed', 'gce_delete');
add_settings_field('gce_delete_title_field', __('Feed Title', GCE_TEXT_DOMAIN), 'gce_delete_title_field', 'delete_feed', 'gce_delete');

//Main text
function gce_delete_main_text(){
	?>
	<p><?php _e('Are you want you want to delete this feed? (Remember to remove / adjust any widgets or shortcodes associated with this feed).', GCE_TEXT_DOMAIN); ?></p>
	<?php
}

//ID
function gce_delete_id_field(){
	$options = get_option(GCE_OPTIONS_NAME);
	$options = $options[$_GET['id']];
	?>
	<input type="text" disabled="disabled" value="<?php echo esc_attr( $options['id'] ); ?>" size="3" />
	<input type="hidden" name="gce_options[id]" value="<?php echo esc_attr( $options['id'] ); ?>" />
	<?php
}

//Title
function gce_delete_title_field(){
	$options = get_option(GCE_OPTIONS_NAME);
	$options = $options[$_GET['id']];
	?>
	<input type="text" name="gce_options[title]" disabled="disabled" value="<?php echo esc_attr( $options['title'] ); ?>" size="50" />
	<?php
}
?>