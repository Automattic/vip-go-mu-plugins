<?php
/* thePlatform Video Manager Wordpress Plugin
  Copyright (C) 2013-2015 thePlatform, LLC

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License along
  with this program; if not, write to the Free Software Foundation, Inc.,
  51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA. */


// TODO:
// Add Publish Update

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$account = get_option( TP_ACCOUNT_OPTIONS_KEY );
if ( $account == false || empty( $account['mpx_account_id'] ) ) {
	wp_die( '<div class="error"><p>mpx Account ID is not set, please configure the plugin before attempting to manage media</p></div>' );
}

// Detect IE 9 and below which doesn't support HTML 5 File API
preg_match( '/MSIE (.*?);/', $_SERVER['HTTP_USER_AGENT'], $matches );

if ( count( $matches ) > 1 ) {
	//Then we're using IE
	$version = $matches[1];
	if ( $version <= 9 ) {
		wp_die( '<div class="error"><p>Internet Explorer ' . esc_html( $version ) . ' is not supported</p></div>' );
	}
}

$tp_uploader_cap = apply_filters( TP_UPLOADER_CAP, TP_UPLOADER_DEFAULT_CAP );
if ( ! defined( 'TP_MEDIA_BROWSER' ) && ! current_user_can( $tp_uploader_cap ) ) {
	wp_die( '<div class="error"><p>You do not have sufficient permissions to upload video to mpx</p></div>' );
}

$preferences = get_option( TP_PREFERENCES_OPTIONS_KEY );

require_once( dirname( __FILE__ ) . '/thePlatform-HTML.php' );
$tp_html = new ThePlatform_HTML();

if ( ! defined( 'TP_MEDIA_BROWSER' ) ) { ?>
	<div class="wrap">
	<h2>Upload Video to mpx</h2> <?php
} else {
	$tp_html->edit_tabs_header(); ?>

	<div class="tab-content">
	<div class="tab-pane active" id="edit_content"> <?php
} ?>

	<div id="responsive-form" class="clearfix">
		<form role="form">
			<?php
			wp_nonce_field( 'theplatform_upload_nonce' );

			// Output a hidden WP User ID field if the plugin is configured to store it.
			$tp_html->user_id_field();

			// Output rows of all our writable metadata
			$tp_html->metadata_fields();

			if ( ! defined( 'TP_MEDIA_BROWSER' ) ) {
				$tp_html->profiles_and_servers( "upload" );
			} else {
				?>
				<div class="form-row" style="margin-top: 10px;">
					<div class="column-half">
						<button id="theplatform_edit_button" class="tp-input button button-primary" type="button"
						        name="theplatform-edit-button">Submit
						</button>
					</div>
				</div>
			<?php } ?>
		</form>
	</div> <!-- end of responsive-form div -->
<?php
if ( defined( 'TP_MEDIA_BROWSER' ) ) {
	// Write all of our edit dialog tabs
	$tp_html->edit_tabs_content();
} else { ?>
	</div> <!-- end of wrap div --> <?php
}