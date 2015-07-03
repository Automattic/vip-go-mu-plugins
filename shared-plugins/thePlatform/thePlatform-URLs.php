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

/**
 * Class ThePlatform_URLs
 * This was added to support regions other than the US.
 * Constants are already used throughout the plugin, so rather than
 * change that, this will define them once when the plugin is started
 * but use the region to determine the base URLs.
 */
class ThePlatform_URLs {

	/**
	 * Define MPX endpoints and associated parameters
	 */
	function __construct( $preference_key ) {
		$region = $this->getRegion( $preference_key );
		if ( !in_array( $region, TP_REGIONS(), TRUE) ) {
			$region = 'us';
		}
		// Set the base URLs based on the region
		switch ( $region ) {
			case 'us':
				define( 'TP_API_ADMIN_IDENTITY_BASE_URL', 'https://identity.auth.theplatform.com/idm/web/Authentication/' );
				define( 'TP_API_MEDIA_DATA_BASE_URL', 'http://data.media.theplatform.com/media/data/' );
				define( 'TP_API_PLAYER_BASE_URL', 'http://data.player.theplatform.com/player/data/' );
				define( 'TP_API_ACCESS_BASE_URL', 'http://access.auth.theplatform.com/' );
				define( 'TP_API_WORKFLOW_BASE_URL', 'http://data.workflow.theplatform.com/workflow/data/' );
				define( 'TP_API_PUBLISH_BASE_URL', 'http://publish.theplatform.com/web/Publish/publish?schema=1.2&form=json' );
				define( 'TP_API_PUBLISH_DATA_BASE_URL', 'http://data.publish.theplatform.com/publish/data/' );
				define( 'TP_API_FMS_BASE_URL', 'http://fms.theplatform.com/web/FileManagement/' );
				define( 'TP_API_PLAYER_EMBED_BASE_URL', '//player.theplatform.com/p/' );
				break;
			case 'eu':
				define( 'TP_API_ADMIN_IDENTITY_BASE_URL', 'https://identity.auth.theplatform.eu/idm/web/Authentication/' );
				define( 'TP_API_MEDIA_DATA_BASE_URL', 'http://data.media.theplatform.eu/media/data/' );
				define( 'TP_API_PLAYER_BASE_URL', 'http://data.player.theplatform.eu/player/data/' );
				define( 'TP_API_ACCESS_BASE_URL', 'http://access.auth.theplatform.eu/' );
				define( 'TP_API_WORKFLOW_BASE_URL', 'http://data.workflow.theplatform.eu/workflow/data/' );
				define( 'TP_API_PUBLISH_BASE_URL', 'http://publish.theplatform.eu/web/Publish/publish?schema=1.2&form=json' );
				define( 'TP_API_PUBLISH_DATA_BASE_URL', 'http://data.publish.theplatform.eu/publish/data/' );
				define( 'TP_API_FMS_BASE_URL', 'http://fms.theplatform.eu/web/FileManagement/' );
				define( 'TP_API_PLAYER_EMBED_BASE_URL', '//player.theplatform.eu/p/' );
				break;
			default:
				wp_die( 'Invalid Region. Cannot match on region: ' . $region );
				break;
		}

		// XML File containing format definitions
		define( 'TP_API_FORMATS_XML_URL', 'http://web.theplatform.com/descriptors/enums/format.xml' );

		// Identity Management Service URLs
		define( 'TP_API_SIGNIN_URL', TP_API_ADMIN_IDENTITY_BASE_URL . 'signIn?schema=1.0&form=json&_duration=86400000&_idleTimeout=3600000&wpVersion=' . TP_PLUGIN_VERSION );
		define( 'TP_API_SIGNOUT_URL', TP_API_ADMIN_IDENTITY_BASE_URL . 'signOut?schema=1.0&form=json&_token=' );

		// Media Data Service URLs
		define( 'TP_API_MEDIA_ENDPOINT', TP_API_MEDIA_DATA_BASE_URL . 'Media?schema=1.7.0&form=cjson' );
		define( 'TP_API_MEDIA_FIELD_ENDPOINT', TP_API_MEDIA_DATA_BASE_URL . 'Media/Field?schema=1.7.0&form=cjson' );
		define( 'TP_API_MEDIA_SERVER_ENDPOINT', TP_API_MEDIA_DATA_BASE_URL . 'Server?schema=1.7.0&form=cjson' );
		define( 'TP_API_MEDIA_RELEASE_ENDPOINT', TP_API_MEDIA_DATA_BASE_URL . 'Release?schema=1.7.0&form=cjson' );
		define( 'TP_API_MEDIA_CATEGORY_ENDPOINT', TP_API_MEDIA_DATA_BASE_URL . 'Category?schema=1.7.0&form=cjson' );
		define( 'TP_API_MEDIA_ACCOUNTSETTINGS_ENDPOINT', TP_API_MEDIA_DATA_BASE_URL . 'AccountSettings?schema=1.7.0&form=cjson' );

		// Player Data Service URLs
		define( 'TP_API_PLAYER_PLAYER_ENDPOINT', TP_API_PLAYER_BASE_URL . 'Player?schema=1.3.0&form=cjson' );

		// Access Data Service URLs
		define( 'TP_API_ACCESS_ACCOUNT_ENDPOINT', TP_API_ACCESS_BASE_URL . 'data/Account?schema=1.3.0&form=cjson' );

		// Authorization Service URLs
		define( 'TP_API_ACCESS_AUTH_ENDPOINT', TP_API_ACCESS_BASE_URL . 'web/Authorization/authorize?schema=1.3&form=json' );

		// Workflow Data Service URLs
		define( 'TP_API_WORKFLOW_PROFILE_RESULT_ENDPOINT', TP_API_WORKFLOW_BASE_URL . 'ProfileResult?schema=1.0&form=cjson' );

		// Publish Data Service URLs
		define( 'TP_API_PUBLISH_PROFILE_ENDPOINT', TP_API_PUBLISH_DATA_BASE_URL . 'PublishProfile?schema=1.5.0&form=json' );

		// FMS URLs
		define( 'TP_API_FMS_GET_UPLOAD_URLS_ENDPOINT', TP_API_FMS_BASE_URL . 'getUploadUrls?schema=1.4&form=json' );
	}

	/**
	 * Determine the region based the plugin's preferences
	 * @param  string $preference_key Our plugin's preference key
	 * @return string                 The current region, either us or eu
	 */
	private function getRegion( $preference_key ) {
		$preferences = get_option( $preference_key );
		if ( $preferences && isset( $preferences['mpx_region'] ) && strlen( $preferences['mpx_region'] ) ) {
			$region = $preferences['mpx_region'];
			$region = explode( '|', $region );
			$region = $region[0];
		} else {
			$region = 'us';
		}
		return $region;
	}
}

new ThePlatform_URLs( TP_ACCOUNT_OPTIONS_KEY );