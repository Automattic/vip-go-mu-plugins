<?php

/* thePlatform Video Manager Wordpress Plugin
  Copyright (C) 2013-2015 thePlatform LLC.

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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tp_admin_cap = apply_filters( TP_ADMIN_CAP, TP_ADMIN_DEFAULT_CAP );

if ( current_user_can( $tp_admin_cap ) ) {

	if ( isset( $_POST['delete'] ) ) {
		check_admin_referer( 'theplatform_delete_settings_nonce' );

		delete_option( TP_ACCOUNT_OPTIONS_KEY );
		delete_option( TP_PREFERENCES_OPTIONS_KEY );
		delete_option( TP_CUSTOM_METADATA_OPTIONS_KEY );
		delete_option( TP_BASIC_METADATA_OPTIONS_KEY );
		delete_option( TP_TOKEN_OPTIONS_KEY );

		echo '<div id="message" class="updated"><p>All plugin settings have been reset</p></div>';
	}
}

?>
<div class="wrap">
	<h2>thePlatform Video Manager</h2>

	<p>Version <?php echo TP_PLUGIN_VERSION; ?><br>
		Copyright (C) 2013-<?php echo date( "Y" ); ?> thePlatform LLC.<br>
	</p>

	<p>The latest version of the plugin can be found at our GitHub repository:
		<a href="https://github.com/thePlatform/thePlatform-video-manager">thePlatform-video-manager</a>
	</p>

	<p>The following libraries are used under their respective licenses:<br>
		Holder - 2.3.1 - client side image placeholders<br>
		(c) 2012-<?php echo date( "Y" ); ?> <a href="http://imsky.co">Ivan Malopinsky</a>
	</p>

	<p>
		NProgress<br>
		Copyright (c) 2013-<?php echo date( "Y" ); ?> <a href="http://ricostacruz.com/nprogress/">Rico Sta. Cruz</a>
	</p>

	<?php

	// Administrators only should be able to delete all of the plugin settings
	if ( current_user_can( $tp_admin_cap ) ) {
		echo '<form name="delete_settings" action="' . esc_url( admin_url( "admin.php?page=theplatform-about" ) ) . '" method="post"><input type="hidden" name="delete" value="delete">';
		wp_nonce_field( 'theplatform_delete_settings_nonce' );
		submit_button( 'Reset Plugin Settings' );
		echo '</form>';
	}
	?>

	<script type="text/javascript">
		var clicked = false;
		jQuery('#submit').click(function (e) {
			if (!clicked) {
				e.preventDefault();
				clicked = true;
				jQuery(this).val('Click Again to Confirm');
			}
		});
	</script>
</div>
