<?php
/**
 * Breadcrumb NavXT - uninstall script
 *
 * uninstall script based on WordPress Uninstall Plugin API
 * 
 * 
 * Because bcn_admin->uninstall() does not work with WPMU, 
 * an uninstaller class has been written, that encapsulates 
 * the uninstall logic and calls bcn_admin->uninstall() 
 * when applicable.
 * 
 * @see http://codex.wordpress.org/Migrating_Plugins_and_Themes_to_2.7#Uninstall_Plugin_API
 * @see http://trac.mu.wordpress.org/ticket/967
 *
 * this uninstall.php file was executed multiple times because 
 * breadcrumb navxt (until 3.3) constsisted of two plugins:
 *
 *	1.) breadcrumb_navxt_class.php / Core
 *  2.) breadcrumb_navxt_admin.php / Adminstration Interface
 *  
 * @author Tom Klingenberg
 */


/*
 * @see bcn_uninstaller
 */
require_once(dirname(__FILE__) . '/breadcrumb_navxt_uninstaller.php');

/*
 * main
 */
new bcn_uninstaller( array('plugin' => $plugin) );
