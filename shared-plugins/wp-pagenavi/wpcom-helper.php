<?php
/*
 * Just some notes on changes from the .org version:
 *    -- removed scb modules: cron, table, widget, hooks, boxespage because they're usless but can be included since most are unused (only one dangerous is table)
 */

// scbFramework inits the plugin at plugins_loaded, which is too early
if ( class_exists( 'scbLoad4' ) )
	add_action( 'after_setup_theme', array( 'scbLoad4', 'load' ), 9, 0 );
