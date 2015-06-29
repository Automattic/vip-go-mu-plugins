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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once( dirname( __FILE__ ) . '/thePlatform-proxy.php' );
?>

<script type="text/javascript">

	window.opener.postMessage('theplatform_uploader_ready', '*');

	window.onmessage = function (e) {
		if (e.data.source == 'theplatform_upload_data') {
			var uploaderData = e.data;
			var theplatformUploader = new TheplatformUploader(uploaderData.files, uploaderData.params, uploaderData.custom_params, uploaderData.profile, uploaderData.server);
		}
	};
</script>

