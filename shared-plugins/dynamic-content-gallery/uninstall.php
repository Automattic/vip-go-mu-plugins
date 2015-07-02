<?php
/* This file is part of the DYNAMIC CONTENT GALLERY Plugin Version 2.2
**********************************************************************
Copyright 2008  Ade WALKER  (email : info@studiograsshopper.ch)

Check that we are using 2.7+ before running
deleting options */
if ( !defined('WP_UNINSTALL_PLUGIN') ) {
    exit();
}
delete_option('dfcg_plugin_settings');
?>