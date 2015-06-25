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
 * Validate the allow/omit dropdown options
 * @param array $input Passed by Wordpress, an Array of upload/metadata options
 * @return array A clean copy of the array, invalid values will be returned as "omit"
 */
function theplatform_dropdown_options_validate( $input ) {
	foreach ( $input as $key => $value ) {
		if ( !in_array( $value, array( 'read', 'write', 'hide' ) ) ) {
			$input[$key] = "hide";
		}
	}
	return $input;
}

/**
 * Validate MPX Account Settings for invalid input
 * @param array $input Passed by Wordpress, an Array of MPX options
 * @return array A cleaned up copy of the array, invalid values will be cleared.
 */
function theplatform_account_options_validate ( $input ) {	
	$tp_api = new ThePlatform_API;
	$defaults = TP_ACCOUNT_OPTIONS_DEFAULTS();
	
	if ( !is_array( $input ) || $input['mpx_username'] === 'mpx/' ) {
		return $defaults;
	}
			
	$account_is_verified = $tp_api->internal_verify_account_settings();
	if ( $account_is_verified ) {		
		
		if ( strpos( $input['mpx_account_id'], '|' ) !== FALSE ) {
			$ids = explode( '|', $input['mpx_account_id'] );			
			$input['mpx_account_id'] = $ids[0];
			$input['mpx_account_pid'] = $ids[1];
		}

		if ( strpos( $input['mpx_region'], '|' ) !== FALSE ) {
			$ids = explode( '|', $input['mpx_region'] );
			$input['mpx_region'] = $ids[0];
		}
	}
	
	foreach ($input as $key => $value) {
		$input[$key] = sanitize_text_field($value);
	}
	
	// If username, account id, or region have changed, reset settings to default	
	$old_preferences = get_option( TP_ACCOUNT_OPTIONS_KEY );			
	if ( $old_preferences ) {		
		$updates = false;
		// If the username changes, reset all preferences except user/pass
		if ( theplatform_setting_changed( 'mpx_username', $old_preferences, $input ) ) {
			$input['mpx_region'] = $defaults['mpx_region'];
			$input['mpx_account_pid'] = $defaults['mpx_account_pid'];
			$input['mpx_account_id'] = $defaults['mpx_account_id'];
			$updates = true;
		}

		// If the region changed, reset all preferences, but keep the new account settings
		if ( theplatform_setting_changed( 'mpx_region', $old_preferences, $input ) ) {		
			$updates = true;
		}

		// If the account changed, reset all preferences, but keep the new account settings
		if ( theplatform_setting_changed( 'mpx_account_id', $old_preferences, $input ) ) {
			$updates = true;
		}
		// Clear old options
		if ( $updates ) {			
			delete_option( TP_PREFERENCES_OPTIONS_KEY );
			delete_option( TP_METADATA_OPTIONS_KEY );
			delete_option( TP_UPLOAD_OPTIONS_KEY );
			delete_option( TP_TOKEN_OPTIONS_KEY );
		}
	}
		
	return $input;
}

/**
 * Compare a key between the old settings array and current settings array
 * @param string $key	The key of the setting to compare
 * @param array $oldArray Current option array
 * @param array $newArray New option array
 * @return boolean False if the value is not set or unchanged, True if changed
 */
function theplatform_setting_changed( $key, $oldArray, $newArray ) {
	if ( !isset( $oldArray[$key] ) && !isset( $newArray[$key] ) ){
		return FALSE;
	}
	
	if ( empty( $oldArray[$key] ) && empty( $newArray[$key] ) ){
		return FALSE;
	}
	
	if ( $oldArray[$key] !== $newArray[$key] ) {
		return TRUE;
	}
	
	return FALSE;
}

/**
 * Validate MPX Settings for invalid input
 * @param array $input Passed by Wordpress, an Array of MPX options
 * @return array A cleaned up copy of the array, invalid values will be cleared.
 */
function theplatform_preferences_options_validate( $input ) {	
	$tp_api = new ThePlatform_API;	

	$account_is_verified = $tp_api->internal_verify_account_settings();	
	if ( $account_is_verified ) {
		$region_is_verified = $tp_api->internal_verify_account_region();		
		
		if ( isset( $input['default_player_name'] ) && strpos( $input['default_player_name'], '|' ) !== FALSE ) {
			$ids = explode( '|', $input['default_player_name'] );
			$input['default_player_name'] = $ids[0];
			$input['default_player_pid'] = $ids[1];
		}
		
		// If the account is selected, but no player has been set, use the first
		// returned as the default.
		if (  !isset( $input['default_player_name'] ) || empty( $input['default_player_name'] ) )  {			
			if ( $region_is_verified ) {				
				$players = $tp_api->get_players();
				$player = $players[0];				
				$input['default_player_name'] = $player['title'];
				$input['default_player_pid'] = $player['pid'];
			} else {
				$input['default_player_name'] = '';
				$input['default_player_pid'] = '';
			}
		}
		
		// If the account is selected, but no upload server has been set, use the first
		// returned as the default.
		if ( !isset( $input['mpx_server_id'] ) || empty ( $input['mpx_server_id'] ) ) {			
				$input['mpx_server_id'] = 'DEFAULT_SERVER';			
		}

		foreach ( $input as $key => $value ) {
			if ( $key == 'videos_per_page' || $key === 'default_width' || $key === 'default_height' ) {
				$input[$key] = intval( $value );
			} else {
				$input[$key] = sanitize_text_field( $value );
			}
		}
	}	

	return $input;
}

/**
 * 	AJAX callback for account verification button
 */
function theplatform_verify_account_settings() {
	//User capability check
	check_admin_referer( 'theplatform-ajax-nonce-verify_account' );
	$hash = $_POST['auth_hash'];

	$response = ThePlatform_API_HTTP::get( TP_API_SIGNIN_URL, array( 'headers' => array( 'Authorization' => 'Basic ' . $hash ) ) );

	$payload = theplatform_decode_json_from_server( $response, TRUE );

	if ( !array_key_exists( 'isException', $payload ) ) {
		$account_is_verified = TRUE;
		wp_send_json_success();
	} 
	
	$account_is_verified = FALSE;
	wp_send_json_error();	
}

function theplatform_remove_utf8_bom($text)
{
    $bom = pack('H*','EFBBBF');
    $text = preg_replace("/^$bom/", '', $text);
    return $text;
}

/**
 * 	Catch JSON decode errors
 */
function theplatform_decode_json_from_server( $input, $assoc, $die_on_error = TRUE ) {

	$body = theplatform_remove_utf8_bom( wp_remote_retrieve_body( $input ) );
	$response = json_decode( $body, $assoc );

	// VIP: Don't die if the service is unreachable. This can take down all of wp-admin. 
	return $response;

	if ( FALSE === $die_on_error ) {
		return $response;
	}

	if ( is_null( $response ) && wp_remote_retrieve_response_code( $input ) != "200" ) {
		wp_die( '<p>There was an error getting data from MPX, if the error persists please contact thePlatform.</p>' );
	}

	if ( is_null( $response ) && wp_remote_retrieve_response_code( $input ) == "200" ) {
		return $response;
	}

	if ( is_wp_error( $response ) ) {
		wp_die( '<p>There was an error getting data from MPX, if the error persists please contact thePlatform. ' . esc_html( $response->get_error_message() ) . '</p>' );
	}

	if ( is_array( $response ) && array_key_exists( 'isException', $response ) ) {
		wp_die( '<p>There was an error getting data from MPX, if the error persists please contact thePlatform. ' . esc_html( $response['description'] ) . '</p>' );
	}

	return $response;
}

/**
 * Parse Custom Fileds and Upload Fields and returns a query string for MPX API calls
 * @param array $metadata Custom Fields
 * @return string MPX fields in query form
 */
function theplatform_get_query_fields( $metadata ) {
	$metadata_options = get_option( TP_METADATA_OPTIONS_KEY );
	$upload_options = get_option( TP_UPLOAD_OPTIONS_KEY );

	$fields = 'id,defaultThumbnailUrl,content';

	foreach ( $upload_options as $upload_field => $val ) {
		if ( $val == 'hide' ) {
			continue;
		}

		$field_title = (strstr( $upload_field, '$' ) !== false) ? substr( strstr( $upload_field, '$' ), 1 ) : $upload_field;
		if ( !empty( $fields ) ) {
			$fields .= ',';
		}
		$fields .= $field_title;
	}

	foreach ( $metadata_options as $custom_field => $val ) {
		if ( $val == 'hide' ) {
			continue;
		}

		$metadata_info = NULL;
		foreach ( $metadata as $entry ) {
			if ( array_search( $custom_field, $entry ) ) {
				$metadata_info = $entry;
				break;
			}
		}

		if ( is_null( $metadata_info ) ) {
			continue;
		}

		$field_title = $metadata_info['fieldName'];

		if ( empty( $fields ) ) {
			$fields .= ':';
		} else {
			$fields .= ',:';
		}

		$fields .= $field_title;
	}

	return $fields;
}

/**
 * Checks the current version against the last version stored in preferences to determine whether an update happened
 * @return boolean 
 */
function theplatform_plugin_version_changed() {
	$preferences = get_option( TP_PREFERENCES_OPTIONS_KEY );
	
	if ( !$preferences ) {
		return FALSE; //New installation
	}
	
	if ( !isset( $preferences['plugin_version'] ) ) {
		return TP_PLUGIN_VERSION('1.0.0'); //Old versions didn't have plugin_version stored
	}
	
	$version = TP_PLUGIN_VERSION( $preferences['plugin_version'] );
	$currentVersion = TP_PLUGIN_VERSION();
	if ( $version['major'] != $currentVersion['major']) {
		return $version;
	}
	
	if ( $version['minor'] != $currentVersion['minor']) {
		return $version;
	}
	
	if ( $version['patch'] != $currentVersion['patch']) {
		return $version;
	}	
	
	return FALSE;
}

/**
 * Checks if the plugin has been updated and performs any necessary updates.
 */
function theplatform_check_plugin_update() {		
	$oldVersion = theplatform_plugin_version_changed();	
	if ( FALSE === $oldVersion ) {
		return;
	}	

	$newVersion = TP_PLUGIN_VERSION();
	
	// On any version, update defaults that didn't previously exist and update the plugin version
	$newPreferences = array_merge( TP_PREFERENCES_OPTIONS_DEFAULTS(), get_option( TP_PREFERENCES_OPTIONS_KEY, array() ) );
	$newPreferences['plugin_version'] = TP_PLUGIN_VERSION;		
	update_option( TP_PREFERENCES_OPTIONS_KEY,  $newPreferences );
	update_option( TP_ACCOUNT_OPTIONS_KEY,      array_merge( TP_ACCOUNT_OPTIONS_DEFAULTS(),     get_option( TP_ACCOUNT_OPTIONS_KEY,     array() ) ) );
	update_option( TP_UPLOAD_OPTIONS_KEY,       array_merge( TP_UPLOAD_FIELDS_DEFAULTS(),       get_option( TP_UPLOAD_OPTIONS_KEY,	    array() ) ) );  
	

	// Set the default embed hook and media embed types (1.2.2 ~ 1.3.2)
	if ( ( $oldVersion['major'] == '1' && $oldVersion['minor'] == '2' && $oldVersion['patch'] == '2' ) ||
		 ( $oldVersion['major'] == '1' && $oldVersion['minor'] == '3' && $oldVersion['patch'] < '3' ) ) {
		if ( defined( 'WPCOM_IS_VIP_ENV' ) ) {
			$newPreferences['embed_hook'] = 'tinymce';	// VIP Only
		}		
		$newPreferences['media_embed_type'] = 'release';

		update_option( TP_PREFERENCES_OPTIONS_KEY,  $newPreferences );		
	}

	// We had a messy update with 1.2.2/1.3.0, let's clean up	
	if ( ( $oldVersion['major'] == '1' && $oldVersion['minor'] == '2' && $oldVersion['patch'] == '2' ) ||
		 ( $oldVersion['major'] == '1' && $oldVersion['minor'] == '3' && $oldVersion['patch'] == '0' ) ) {
		$basicMetadataFields = get_option( TP_UPLOAD_OPTIONS_KEY, array() );
		$customMetadataFields = get_option( TP_METADATA_OPTIONS_KEY, array() );
		update_option ( TP_UPLOAD_OPTIONS_KEY, array_diff_assoc( $basicMetadataFields, $customMetadataFields ) );
	}

	// Move account settings from preferences (1.2.0)	
	if ( ( $oldVersion['major'] == '1' && $oldVersion['minor'] <  '2' ) && 
		 ( $newVersion['major'] > '1' || ( $newVersion['major'] >= '1' && $newVersion['minor'] >= '2' ) )
		) {
		$preferences = get_option( TP_PREFERENCES_OPTIONS_KEY, array() );
		if ( array_key_exists( 'mpx_account_id', $preferences ) ) {
			$accountSettings = TP_ACCOUNT_OPTIONS_DEFAULTS();
			foreach ( $preferences as $key => $value ) {
				if ( array_key_exists( $key, $accountSettings ) ) {
					$accountSettings[$key] = $preferences[$key];				
				}
			}
			update_option( TP_ACCOUNT_OPTIONS_KEY, $accountSettings );
		}	
	}
}
