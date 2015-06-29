<?php /*

**************************************************************************

Plugin Name:  Local Time
Plugin URI:   http://www.viper007bond.com/wordpress-plugins/localtime/
Version:      1.2.1
Description:  Displays post and comment date and times in the visitor's timezone using Javascript. Heavily based on code from the <a href="http://p2theme.com/">P2 theme</a> by <a href="http://automattic.com/">Automattic</a>.
Author:       Alex Mills (Viper007Bond)
Author URI:   http://www.viper007bond.com/

Text Domain:  localtime
Domain Path:  /localization/

**************************************************************************/

class ViperLocalTime {

	public $version = '1.2.1';

	// Class init
	function __construct() {
		global $wp_locale;

		if (
			! function_exists( 'esc_html' ) // Old yucky versions of WordPress
			|| is_admin() // Don't fiddle with stuff in the admin area
			|| function_exists( 'p2_date_time_with_microformat' ) // P2 theme being used? Don't duplicate
			|| ( function_exists( 'bnc_is_iphone' ) && bnc_is_iphone() ) // Disable if WPTouch being used
		)
			return;

		load_plugin_textdomain( 'localtime', false, dirname( plugin_basename( __FILE__ ) ) . '/localization/' );

		// Inject HTML into the various date/time functions
		add_filter( 'get_the_date',     array( &$this, 'add_html' ), 1, 2 );
		add_filter( 'get_post_time',    array( &$this, 'add_html' ), 1, 2 );
		add_filter( 'get_comment_date', array( &$this, 'add_html' ), 1, 2 );
		add_filter( 'get_comment_time', array( &$this, 'add_html' ), 1, 2 );

		// Enqueue the script
		wp_enqueue_script( 'localtime', plugins_url( 'localtime.js', __FILE__ ) , array( 'jquery', 'utils' ), $this->version );

		$localization = array();
		$localization['translated_title'] = __( 'This date and/or time has been adjusted to match your timezone', 'localtime' );

		// The localization functionality can't handle objects, that's why
		// we are using poor man's hash maps here -- using prefixes of the variable names
		foreach( $wp_locale->month as $key => $month )
			$localization['locale']["month_$key"] = $month;
		$i = 1;
		foreach( $wp_locale->month_abbrev as $key => $month )
			$localization['locale']["monthabbrev_".sprintf('%02d', $i++)] = $month;
		foreach( $wp_locale->weekday as $key => $day )
			$localization['locale']["weekday_$key"] = $day;
		$i = 1;
		foreach( $wp_locale->weekday_abbrev as $key => $day )
			$localization['locale']["weekdayabbrev_".sprintf('%02d', $i++)] = $day;

		wp_localize_script( 'localtime', 'localtime', $localization );
	}


	// Wraps the output of the passed string provided by a date or time function
	// in a <span> containing extra information for the Javascript to make use of
	public function add_html( $string, $format ) {
		// If a Unix timestamp was requested, then don't modify it as it's most likely being used for PHP and not display
		// Also don't do anything for feeds
		if ( 'U' === $format || is_feed() )
			return $string;

		// Populate the format if missing
		if ( empty( $format ) ) {
			switch ( current_filter() ) {
				case 'get_the_date':
				case 'get_comment_date':
					$format = get_option( 'date_format' );
					break;

				case 'get_post_time':
				case 'get_comment_time':
					$format = get_option( 'time_format' );
					break;

				default;
					return $string;
			}
		}

		// Get the GMT unfiltered value
		remove_filter( current_filter(), array( &$this, 'add_html' ), 1, 2 );
		switch ( current_filter() ) {
			case 'get_the_date':
			case 'get_post_time':
				$gmttime = get_post_time( 'c', true );
				break;

			case 'get_comment_date':
			case 'get_comment_time':
				$gmttime = get_comment_time( 'c', true );
				break;

			default;
				$gmttime = false; // Gotta add the filter back
		}
		add_filter( current_filter(), array( &$this, 'add_html' ), 1, 2 );

		if ( ! $gmttime )
			return $string;

		return '<span class="localtime" data-ltformat="' . esc_attr( $format ) . '" data-lttime="' . esc_attr( $gmttime ) . '">' . $string . '</span>';
	}
}

// Start this plugin
add_action( 'init', 'ViperLocalTime', 7 );
function ViperLocalTime() {
	global $ViperLocalTime;
	$ViperLocalTime = new ViperLocalTime();
}

?>