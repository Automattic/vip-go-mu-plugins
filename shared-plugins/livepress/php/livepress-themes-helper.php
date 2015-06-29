<?php
/**
 * Try to automagically inject livepress widget into theme.
 * Magic can be removed by adding into functions.php of theme:
 * define('LIVEPRESS_THEME', true)
 *
 * @package livepress
 */

class LivePress_Themes_Helper {
	/**
	 * Instance.
	 *
	 * @static
	 * @access private
	 * @var null $instance LivePress_Themes_Helper instance.
	 */
	private static $instance = NULL;

	/**
	 * Instance.
	 *
	 * @static
	 *
	 * @return LivePress_Themes_Helper
	 */
	public static function instance() {
		if (self::$instance == NULL) {
			self::$instance = new LivePress_Themes_Helper();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'loop_start', array( $this, 'inject_updatebox' ) );
	}

	/**
	 * Inject update box into theme.
	 */
	function inject_updatebox() {
		static $did_output = false;
		if ( ! defined( 'LIVEPRESS_THEME' ) || ! constant( 'LIVEPRESS_THEME' ) ) {
			if ( ! $did_output && ( is_single() || is_home() ) ) {
				livepress_update_box();
				if ( is_single() )
					LivePress_Updater::instance()->inject_widget( true );
				$did_output = true;
			}
		}
	}

	/**
	 * Inject widget.
	 *
	 * @param     $content
	 * @param int $last_update
	 * @return string
	 */
	function inject_widget( $content, $last_update = 0 ) {
		static $did_output = false;
		if ( ! defined( 'LIVEPRESS_THEME' ) || ! constant( 'LIVEPRESS_THEME' ) ) {
			if ( ! $did_output ) {
				$livepress_template = livepress_template( true, $last_update );
				$content = $livepress_template . $content;
				$did_output = true;
			}
		}
		return $content;
	}
}
