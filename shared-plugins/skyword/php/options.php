<?php

add_action('admin_menu', 'skyword_plugin_menu');
/**
* Adds Skyword Admin settings page to side menu
*/
function skyword_plugin_menu() {
	add_options_page('Skyword', 'Skyword', 'manage_options', __FILE__, 'skyword_plugin_options');
	add_action('admin_init', 'skyword_register_settings');
}
/**
* Outputs Admin settings page
*/
function skyword_plugin_options() {
	wp_enqueue_style( 'styles', plugins_url( 'css/styles.css' , dirname(__FILE__) ) );
?>

	
	
	
	<div class="wrap skyword-settings">
		<div class="icon32" id="icon-options-general"><br></div>
		<h2><span>Skyword</span></h2>
		<p>The Skyword Plugin allows content created with the Skyword platform to be published in Wordpress.</p><p>To learn more about how Skyword helps you reach and engage your audience, please contact us at <a href="mailto:learnmore@skyword.com">learnmore@skyword.com</a> or visit <a target="_blank" href="www.skyword.com">www.skyword.com</a>.</p>
		<p>Please contact Skyword Support (<a href="mailto:support@skyword.com">support@skyword.com</a>) if you have any questions.</p>

		<form action="options.php" method="post">
		<?php settings_fields('skyword_plugin_options'); ?>
		<?php do_settings_sections(__FILE__); ?>
		<p class="submit">
			<input name="Submit" type="submit" class="button-primary"
            value="<?php esc_attr_e('Save Changes'); ?>" />
		</p>
		</form>
	</div>
<?php
}
/**
* Adds input fields to Admin settings page
*/
function skyword_register_settings() {
	register_setting('skyword_plugin_options', 'skyword_plugin_options', 'skyword_plugin_options_validate');
	add_settings_section('skyword_ogtags_section', 'Facebook OpenGraph', null, __FILE__);
	add_settings_field('skyword_enable_ogtags', '', 'skyword_enable_ogtags_input', __FILE__, 'skyword_ogtags_section');
	add_settings_section('skyword_titletag_section', 'SEO Page Title', null, __FILE__);
	add_settings_section('skyword_metatags_section', 'Meta Description', null, __FILE__);
	add_settings_field('skyword_enable_metatags', '', 'skyword_enable_metatags_input', __FILE__, 'skyword_metatags_section');
	add_settings_section('skyword_googlenewstags_section', 'Google News Keywords', null, __FILE__);
	add_settings_field('skyword_enable_googlenewstag', '', 'skyword_enable_googlenewstag_input', __FILE__, 'skyword_googlenewstags_section');
	add_settings_field('skyword_enable_pagetitle', ' ', 'skyword_enable_pagetitle_input', __FILE__, 'skyword_titletag_section');
}

/**
* Input fields for settings page
*/
function skyword_enable_ogtags_input() {
	$options = get_option('skyword_plugin_options');
	echo '<input type="checkbox" id="ogmeta_tag" name="skyword_plugin_options[skyword_enable_ogtags]" value="1" ' . checked( 1, $options['skyword_enable_ogtags'], false ) . '/> Include the Facebook OpenGraph tags on the post. <p>The OpenGraph tags are used to properly send information to Facebook when a page is recommended, liked, or shared by Facebook users.</p>';
}

function skyword_enable_metatags_input() {
	$options = get_option('skyword_plugin_options');
	echo '<input type="checkbox" id="meta_tag" name="skyword_plugin_options[skyword_enable_metatags]" value="1" ' . checked( 1, $options['skyword_enable_metatags'], false ) . '/> Include the meta description tag on the post. <p>The meta description tag provides additional information for search engines to properly index the web page. </p>';
}

function skyword_enable_googlenewstag_input() {
	$options = get_option('skyword_plugin_options');
	echo '<input type="checkbox" id="google_tag" name="skyword_plugin_options[skyword_enable_googlenewstag]" value="1" ' . checked( 1, $options['skyword_enable_googlenewstag'], false ) . '/> Include the Google News Keyword tag on the post. <p>The Google News Keyword tag provides additional information for Google to properly index the web page. Useful for sites accepted as news providers by the Google News Team.</p>';
}

function skyword_enable_pagetitle_input() {
	$options = get_option('skyword_plugin_options');
	echo '<input type="checkbox" id="page_title" name="skyword_plugin_options[skyword_enable_pagetitle]" value="1" ' . checked( 1, $options['skyword_enable_pagetitle'], false ) . '/> Include the search engine optimized page title.<p>The page title will use the search engine optimized title provided by Skyword.</p>';
}

/**
* Validation method for all input fields
*/
function skyword_plugin_options_validate($input) {
	$options = get_option('skyword_plugin_options');
	$options['skyword_enable_ogtags'] = ( empty ( $input['skyword_enable_ogtags'] ) ? false : true );
	$options['skyword_enable_metatags'] = ( empty ( $input['skyword_enable_metatags'] ) ? false : true );
	$options['skyword_enable_googlenewstag'] = ( empty ( $input['skyword_enable_googlenewstag'] ) ? false : true );
	$options['skyword_enable_pagetitle'] = ( empty ( $input['skyword_enable_pagetitle']) ? false : true );

	return $options;
}
