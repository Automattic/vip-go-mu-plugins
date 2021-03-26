<?php

/**
 * Plugin Name: Gutenberg Ramp
 * Description: Allows theme authors to control the circumstances under which the Gutenberg editor loads. Options include "load" (1 loads all the time, 0 loads never) "post_ids" (load for particular posts) "post_types" (load for particular posts types.)
 * Version:     1.1.0
 * Author:      Automattic, Inc.
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: gutenberg-ramp
 */

// This file loads Ramp, and modifies behaviors for Gutenberg on VIP Go

if ( defined( 'VIP_GO_DISABLE_RAMP' ) && true === VIP_GO_DISABLE_RAMP ) {
	return;
}

/** Effectively remove Gutenberg Ramp plugin for some sites */
if ( \Automattic\VIP\Feature::is_enabled( 'remove-gutenberg-ramp' ) ) {
	return;
}

/** load Gutenberg Ramp **/
if ( file_exists( __DIR__ . '/gutenberg-ramp/gutenberg-ramp.php' ) ) {
	require_once( __DIR__ . '/gutenberg-ramp/gutenberg-ramp.php' );
}

/** Turn off the UI for Ramp **/
add_action( 'plugins_loaded', function() {
	remove_action( 'admin_init', 'gutenberg_ramp_initialize_admin_ui' );
} );

/**
 * Remove Try Gutenberg callout introduced as part of 4.9.8
 */
remove_action( 'try_gutenberg_panel', 'wp_try_gutenberg_panel' );

/**
 * Load Gutenberg via the Gutenberg Ramp plugin.
 */
function wpcom_vip_load_gutenberg( $criteria = true ) {
	if ( ! function_exists( 'gutenberg_ramp_load_gutenberg' ) ) {
		return;
	}

	gutenberg_ramp_load_gutenberg( $criteria );
}

// Modify the the post-upgrade screen (about.php) to be more VIP-specific
// Don't mind that hacky CSS, please :)
add_action( 'all_admin_notices', function() {
	global $pagenow;
	if ( 'about.php' !== $pagenow ) {
		return;
	}

	// Only on 5.0+
	$db_version = absint( get_option( 'db_version' ) );
	if ( $db_version < 43764 ) {
		return;
	}

	$is_using_gutenberg = false;
	if ( class_exists( 'Gutenberg_Ramp' ) ) {
		$is_using_gutenberg = Gutenberg_Ramp::get_instance()->active;
	}

?>
<style>
#classic-editor,
#classic-editor + div.full-width,
#classic-editor + div.full-width + div.feature-section {
	display: none;
}

.about-text {
	display: none;
}

#vip-upgrade-message {
	margin: 20px 200px 20px 0;
	padding: 0;

	background: none;
	border: none;
	box-shadow: none;
}

#vip-upgrade-message p {
	font-weight: 400;
    line-height: 1.6em;
    font-size: 19px;
}
</style>
<div id="vip-upgrade-message" class="notice notice-success" style="display: block !important">
	<p>Thank you for updating to the latest version! WordPress 5.0 introduces a robust new content creation experience.</p>

	<p><strong>Your editor of choice has not been changed</strong>.</p>

	<p>Read more about the new editor below, learn about <a href="https://docs.wpvip.com/technical-references/plugins/loading-gutenberg-on-vip/" rel="noopener noreferrer" target="_blank">how it's configured on VIP</a>, or <a href="https://testgutenberg.com/" rel="noopener noreferrer" target="_blank">try it out in your browser</a>.</p>

	<?php if ( ! $is_using_gutenberg ) : ?>
		<p>Need help planning your transition? The <a href="<?php echo admin_url( 'admin.php?page=vip-dashboard' ); ?>">VIP Support team is ready to help</a>!</p>
	<?php endif; ?>
</div>
<?php
} );
