<?php

fieldmanager_set_baseurl( wpcom_vip_themes_root_uri() . '/plugins/fieldmanager/' );

// Replace user meta functions with user attributes.
add_filter( 'fm_user_context_get_data', function() { return 'get_user_attribute'; } );
add_filter( 'fm_user_context_add_data', function() { return 'add_user_attribute'; } );
add_filter( 'fm_user_context_update_data', function() { return 'update_user_attribute'; } );
add_filter( 'fm_user_context_delete_data', function() { return 'delete_user_attribute'; } );