<?php
if( !defined( 'ABSPATH') && !defined('WP_UNINSTALL_PLUGIN') )
    exit();

require_once( dirname(__FILE__) . "/mediapass_plugin.php");

MediaPass_Plugin::delete_all_options();
?>