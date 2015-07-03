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

define( 'TP_PLUGIN_VERSION', '1.3.4' );
define( 'TP_PREFERENCES_OPTIONS_KEY', 'theplatform_preferences_options' );
define( 'TP_ACCOUNT_OPTIONS_KEY', 'theplatform_account_options' );
define( 'TP_METADATA_OPTIONS_KEY', 'theplatform_metadata_options' );
define( 'TP_UPLOAD_OPTIONS_KEY', 'theplatform_upload_options' );
define( 'TP_TOKEN_OPTIONS_KEY', 'theplatform_token_options' );
define( 'TP_ADMIN_CAP', 'tp_admin_cap' );
define( 'TP_VIEWER_CAP', 'tp_viewer_cap' );
define( 'TP_EMBEDDER_CAP', 'tp_embedder_cap' );
define( 'TP_UPLOADER_CAP', 'tp_uploader_cap' );
define( 'TP_ADMIN_DEFAULT_CAP', 'manage_options' );
define( 'TP_VIEWER_DEFAULT_CAP', 'edit_posts' );
define( 'TP_EMBEDDER_DEFAULT_CAP', 'edit_posts' );
define( 'TP_UPLOADER_DEFAULT_CAP', 'upload_files' );

function TP_ACCOUNT_OPTIONS_DEFAULTS() {
	return array(
					'mpx_account_id' => '',
					'mpx_username' => 'mpx/',
					'mpx_password' => '',
					'mpx_account_pid' => '',
					'mpx_region' => 'us'
				);
}

function TP_PREFERENCES_OPTIONS_DEFAULTS() {
	return array(
					'plugin_version' => TP_PLUGIN_VERSION,
					'embed_tag_type' => 'iframe',
					'default_player_name' => '',
					'default_player_pid' => '',
					'mpx_server_id' => 'DEFAULT_SERVER',
					'default_publish_id' => 'tp_wp_none',
					'user_id_customfield' => '(None)',
					'transform_user_id_to' => 'nickname',
					'filter_by_user_id' => 'false',
					'autoplay' => 'true',
					'rss_embed_type' => 'article',
					'default_width' => intval ( $GLOBALS['content_width'] ),
					'default_height' => intval( ($GLOBALS['content_width'] / 16) * 9 ),
					'player_embed_type' => 'embed',
			        'embed_hook' => 'tinymce',
			        'media_embed_type' => 'release'
				);
}

function TP_UPLOAD_FIELDS_DEFAULTS() {
	return array(
					'title' => "write",
					'description' => "write",
					'categories' => "write",
					'author' => "write",
					'keywords' => "write",
					'link' => "write",
					'guid' => "read",
          'pid' => "hide"
				);
}

function TP_CUSTOM_FIELDS_TYPES() {
	return array( 
					'String', 
					'Time',
					'Date', 
					'DateTime', 
					'Integer', 
					'Decimal', 
					'Duration', 
					'Boolean', 
					'URI', 
					'Link' 
				);
}

function TP_PREFERENCES_OPTIONS_FIELDS() {
    return  array(               
                array( 'id' => 'section_embed_options', 'title' => 'Embedding Preferences', 'callback' => 'section_embed_desc', 'fields' => array (             
                        array( 'id' => 'default_player_name',   'title' => 'Default Player',        'type' => 'callback' ),
                        array( 'id' => 'default_player_pid',    'title' => 'Default Player PID',    'type' => 'hidden' ),
                        array( 'id' => 'embed_tag_type',        'title' => 'Embed Tag Type',        'type' => 'select', 'values' => array( 'IFrame' => 'iframe', 'Script' => 'script' ),  ),
                        array( 'id' => 'media_embed_type',      'title' => 'Media Embed Type',      'type' => 'select', 'values' => array( 'Release' => 'release', 'Media PID' => 'pid', 'Media GUID' => 'guid' ) ),
                        array( 'id' => 'player_embed_type',     'title' => 'Player Embed Type',     'type' => 'select', 'values' => array( 'Video Only' => 'true', 'Full Player' => 'false' ) ),
                        array( 'id' => 'rss_embed_type',        'title' => 'RSS Embed Type',        'type' => 'select', 'values' => array( 'IFrame' => 'iframe', 'Script' => 'script', 'Article' => 'article' ) ),
                        array( 'id' => 'autoplay',              'title' => 'Force Autoplay',        'type' => 'boolean' ),
                        array( 'id' => 'default_width',         'title' => 'Default Player Width',  'type' => 'string' ),
                        array( 'id' => 'default_height',        'title' => 'Default Player Height', 'type' => 'string' )                        
                    )
                ),
                array( 'id' => 'section_preferences_options', 'title' => 'General Preferences', 'callback' => 'section_preferences_desc', 'fields' => array (
                        array( 'id' => 'filter_by_user_id',     'title' => 'Filter Users Own Videos',       'type' => 'boolean' ),
                        array( 'id' => 'user_id_customfield',   'title' => 'User ID Custom Field',          'type' => 'callback' ),
                        array( 'id' => 'transform_user_id_to',  'title' => 'Show User ID as',               'type' => 'select',     'values' => array( 'Email' => 'email', 'Full Name' => 'full_name', 'Nickname' => 'nickname', 'Username' => 'username' ) ),                        
                        array( 'id' => 'embed_hook', 			'title' => 'Plugin Embed button location', 	'type' => 'select', 	'values' => array( 'Media Button' => 'mediabutton', 'Editor Button' => 'tinymce', 'Both' => 'both' ) ),
                        array( 'id' => 'mpx_server_id',         'title' => 'MPX Upload Server',             'type' => 'callback' ),
                        array( 'id' => 'default_publish_id',    'title' => 'Default Publishing Profile',    'type' => 'callback' )
                    )
                )           
            );  
}

function TP_ACCOUNT_OPTIONS_FIELDS() {
    return  array(
                array( 'id' => 'section_mpx_account_options', 'title' => 'MPX Account Options', 'callback' => 'section_mpx_account_desc', 'fields' => array (             
                    array( 'id' => 'mpx_username',      'title' => 'MPX Username',      'type' => 'string' ),
                    array( 'id' => 'mpx_password',      'title' => 'MPX Password',      'type' => 'password' ),
                    array( 'id' => 'mpx_region',        'title' => 'MPX Region',        'type' => 'callback' ),
                    array( 'id' => 'mpx_account_id',    'title' => 'MPX Account',       'type' => 'callback' ),
                    array( 'id' => 'mpx_account_pid',   'title' => 'MPX Account PID',   'type' => 'hidden' )                    
                    )
                )    
            );  
}

function TP_PLUGIN_VERSION( $version = TP_PLUGIN_VERSION) {
	return array_combine( array( 'major', 'minor', 'patch' ), explode( '.', $version ) );	
}

function TP_REGIONS() {
	return array( 'us', 'eu' );
}
