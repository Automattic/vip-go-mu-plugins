<?php

/**
 * PushUp uninstaller
 *
 * Used when clicking "Delete" from inside of WordPress's plugins page, this
 * uninstaller currently only deletes options, and does not remove post meta
 * from posts that have been pushed, though it may do this in a future version,
 * when PushUp is able to backfill previously pushed posts more intelligently.
 *
 * (We've included a postmeta cleaning method, though it's commented out.)
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * PushUp Plugin Uninstaller Class
 */
class PushUp_Uninstaller {

	/**
	 * Perform some checks to make sure plugin can/should be uninstalled
	 */
	public function __construct() {

		// Not uninstalling
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			self::exit_uninstaller();
		}

		// Not uninstalling
		if ( ! WP_UNINSTALL_PLUGIN ) {
			self::exit_uninstaller();
		}

		// Not uninstalling this plugin
		if ( dirname( WP_UNINSTALL_PLUGIN ) !== dirname( plugin_basename( __FILE__ ) ) ) {
			self::exit_uninstaller();
		}

		// Bail on multisite (best cleaned up manually, via wp-cli, etc...)
		if ( is_multisite() ) {
			self::exit_uninstaller();
		}

		// Uninstall PushUp
		self::clean_options();
		//self::clean_postmeta();
	}

	/**
	 * Clean up after PushUp's settings & options
	 */
	protected static function clean_options() {
		delete_option( 'pushup' );
	}

	/**
	 * Clean up all post meta
	 */
	protected static function clean_postmeta() {
		delete_post_meta_by_key( '_pushup-notifications-push-setting' );
	}

	/**
	 * Gracefully exit the uninstaller if we should not be here
	 */
	protected static function exit_uninstaller() {
		status_header( 404 );
		exit;
	}
}
new PushUp_Uninstaller();
