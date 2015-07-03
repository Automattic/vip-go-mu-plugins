<?php 

if( !defined( 'ABSPATH') && !defined('WP_UNINSTALL_PLUGIN') )
    exit();

	delete_option('bc_pub_id');
	delete_option('bc_player_id');
	delete_option('bc_player_id_playlist');
	delete_option('bc_player_key_playlist');
	delete_option('bc_api_key');
	delete_option('bc_default_height');
	delete_option('bc_default_width');
	delete_option('bc_default_height_playlist');
	delete_option('bc_default_width_playlist');

?>