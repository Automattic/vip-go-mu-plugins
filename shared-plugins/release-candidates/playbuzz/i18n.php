<?php
/*
 * Security check
 * Exit if file accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}



/*
 * Internationalization
 * Define and load the internationalization PO/MO files.
 *
 * @since 0.9.0
 */
class Playbuzzi18n {

	/*
	 * Constructor
	 */
	public function __construct() {

		// Load textdomain
		add_action( 'plugins_loaded',  array( $this, 'load_textdomain' ) );

	}

	/*
	 * Load the text domain for translation
	 */
	public function load_textdomain() {

		try {

			load_plugin_textdomain(
				'playbuzz',
				false,
				dirname( plugin_basename( __FILE__ ) ) . '/lang/'
			);

		} catch (Exception $e) {

			// Nothing

		}

	}

}
new Playbuzzi18n();
