<?php
/* thePlatform Video Manager Wordpress Plugin
  Copyright (C) 2013-2014  thePlatform for Media Inc.

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

if ( !defined( 'ABSPATH' ) ) {
	exit;
}
$tp_viewer_cap = apply_filters( TP_VIEWER_CAP, TP_VIEWER_DEFAULT_CAP );
if ( !current_user_can( $tp_viewer_cap ) ) {
	wp_die( '<p>You do not have sufficient permissions to browse MPX Media</p>' );
}
?>

<style type="text/css">
    #tp-iframe {
		height: 100%;
		width: 100%;
    }

    #tp-container {
		height: 100%;
		width: 100%;	
		overflow-y: hidden;	
    }
</style>

<div id="tp-container">		
	<?php
	$site_url = wp_nonce_url( admin_url( "/admin-ajax.php?action=theplatform_media" ), 'theplatform-ajax-nonce-theplatform_media');
	echo '<iframe id="tp-iframe" src="' . esc_url( $site_url ) . '"></iframe>'
	?>		
</div>

<script type="text/javascript">
	jQuery( document ).ready( function() {
		jQuery( '#tp-iframe' ).css( 'height', window.innerHeight - 101 );

		jQuery( window ).resize( function() {
			jQuery( '#tp-iframe' ).css( 'height', window.innerHeight - 101 );
		} );
	} );
</script>