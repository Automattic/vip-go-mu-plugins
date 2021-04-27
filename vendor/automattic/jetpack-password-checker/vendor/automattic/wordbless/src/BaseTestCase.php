<?php

namespace WorDBless;

use PHPUnit\Framework\TestCase;

abstract class BaseTestCase extends TestCase {

	protected static $hooks_saved = array();

	/**
	 * Runs the routine before each test is executed.
	 *
	 * @before
	 */
	public function set_up_wordbless() {
		if ( ! self::$hooks_saved ) {
			$this->_backup_hooks();
		}
	}

	/**
	 * After a test method runs, reset any state in WordPress the test method might have changed.
	 *
	 * @after
	 */
	public function tear_down_wordbless() {
		$this->_restore_hooks();
		Options::init()->clear_options();
		Posts::init()->clear_all_posts();
		PostMeta::init()->clear_all_meta();
		Users::init()->clear_all_users();
		$this->clear_uploads();
	}

	/**
	 * Deletes everything from the uploads folder
	 *
	 * @return void
	 */
	public function clear_uploads() {
		$uploads_folder = ABSPATH . '/wp-content/uploads';
		$scan           = glob( rtrim( $uploads_folder, '/' ) . '/*' );

		foreach ( $scan as $path ) {
			$this->recursive_delete( $path );
		}
	}

	/**
	 * Recursive deletes a file or folder
	 *
	 * @param string $file File or folder name
	 * @return boolean
	 */
	protected function recursive_delete( $file ) {

		if ( is_file( $file ) ) {
			return unlink( $file );
		}

		if ( is_dir( $file ) ) {

			$scan = glob( rtrim( $file, '/' ) . '/*' );

			foreach ( $scan as $path ) {
				$this->recursive_delete( $path );
			}

			return rmdir( $file );
		}

		return false;
	}

	/**
	 * Saves the action and filter-related globals so they can be restored later.
	 *
	 * Stores $wp_actions, $wp_current_filter, and $wp_filter on a class variable
	 * so they can be restored on tearDown() using _restore_hooks().
	 *
	 * Original code from wp-phpunit
	 *
	 * @global array $wp_actions
	 * @global array $wp_current_filter
	 * @global array $wp_filter
	 */
	protected function _backup_hooks() {
		$globals = array( 'wp_actions', 'wp_current_filter' );
		foreach ( $globals as $key ) {
			self::$hooks_saved[ $key ] = $GLOBALS[ $key ];
		}
		self::$hooks_saved['wp_filter'] = array();
		foreach ( $GLOBALS['wp_filter'] as $hook_name => $hook_object ) {
			self::$hooks_saved['wp_filter'][ $hook_name ] = clone $hook_object;
		}
	}

	/**
	 * Restores the hook-related globals to their state at setUp()
	 * so that future tests aren't affected by hooks set during this last test.
	 *
	 * Original code from wp-phpunit
	 *
	 * @global array $wp_actions
	 * @global array $wp_current_filter
	 * @global array $wp_filter
	 */
	protected function _restore_hooks() {
		$globals = array( 'wp_actions', 'wp_current_filter' );
		foreach ( $globals as $key ) {
			if ( isset( self::$hooks_saved[ $key ] ) ) {
				$GLOBALS[ $key ] = self::$hooks_saved[ $key ];
			}
		}
		if ( isset( self::$hooks_saved['wp_filter'] ) ) {
			$GLOBALS['wp_filter'] = array();
			foreach ( self::$hooks_saved['wp_filter'] as $hook_name => $hook_object ) {
				$GLOBALS['wp_filter'][ $hook_name ] = clone $hook_object;
			}
		}
	}

}
