<?php
/**
 * Abstract class for admin settings pages
 *
 * @package SocialFlow
 * @since 2.1
 */
class SocialFlow_Admin_Settings_Page {

	/**
	 * Holds current page slug
	 *
	 * @since 2.1
	 * @access public
	 * @var string
	 */
	public $slug;

	/**
	 * Add actions to manipulate messages
	 * add menu page on creation
	 */
	function __construct() {}

	/**
	 * Render current page content here
	 */
	function page() {}

	/**
	 * Save page settings
	 * @param array settings to filter
	 * @return array filtered settings
	 */
	function save( $settings ) {
		return $settings;
	}

	/**
	 * Output success or failure admin notice when updating options page
	 */
	function admin_notices() {
		global $socialflow;

		if ( isset( $_GET['page'] ) AND $this->slug == $_GET['page'] AND isset( $_GET['settings-updated'] ) AND $_GET['settings-updated'] ) {
			$socialflow->render_view( 'notice/options-updated' );
		}
	}
}