<?php
/*
 * Security check
 * Exit if file accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}



/*
 * Playbuzz Scripts and Styles
 * Displays playbuzz menus in WordPress dashboard.
 *
 * @since 0.9.0
 */
class PlaybuzzScriptsStyles {

	/*
	 * Constructor
	 */
	public function __construct() {

		// Load scripts and styles, in admin only
		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'playbuzz_styles'  ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'playbuzz_scripts' ) );
		}

	}

	/*
	 * Playbuzz Styles
	 */
	public function playbuzz_styles() {

		// Register Styles
		wp_register_style( 'playbuzz-admin',     plugins_url( 'css/admin.css',     __FILE__ ), false, '0.9.0' );
		wp_register_style( 'playbuzz-admin-rtl', plugins_url( 'css/admin-rtl.css', __FILE__ ), false, '0.9.0' );

		// Enqueue Styles
		wp_enqueue_style( 'playbuzz-admin' );
		if ( is_rtl() ) wp_enqueue_style( 'playbuzz-admin-rtl' );

	}

	/*
	 * Playbuzz Scripts
	 */
	public function playbuzz_scripts() {

		// Load settings
		$options = get_option( 'playbuzz' );

		// Set settings for JS files
		$js_settings = array(
			'key'     => isset( $options['key'] ) ? $options['key'] : '',
			'pb_user' => isset( $options['pb_user'] ) ? str_replace( ' ', '', $options['pb_user'] ) : '',
		);

		// Set localized translation strings for JS files
		$js_translations = array(
			'playbuzz' => __( 'Playbuzz', 'playbuzz' ),
			'playbuzz_content' => __( 'Playbuzz Content', 'playbuzz' ),
			'my_items' => __( 'My Items', 'playbuzz' ),
			'create_your_own' => __( '+ Create Your Own', 'playbuzz' ),
			'search_term' => __( 'Search Term', 'playbuzz' ),
			'search_my_items' => __( 'Search my items', 'playbuzz' ),
			'results_for' => __( 'Results for', 'playbuzz' ),
			'no_results_found' => __( 'No results found', 'playbuzz' ),
			'try_different_search' => __( 'Please try again with a different search.', 'playbuzz' ),
			'change_user' => __( 'Change User', 'playbuzz' ),
			'server_error' => __( 'Server error', 'playbuzz' ),
			'try_in_a_few_minutes' => __( 'Please try again in a few minutes.', 'playbuzz' ),
			'no_user' => __( 'No user', 'playbuzz' ),
			'set_user' => __( 'Set User', 'playbuzz' ),
			'set_your_username' => __( 'Go to the <a href="options-general.php?page=playbuzz&tab=embed" target="_blank">Settings</a> and set your Playbuzz.com username.', 'playbuzz' ),
			'you_dont_have_any_items_yet' => __( 'You don\'t have any items (yet!).', 'playbuzz' ),
			'go_to_playbuzz_to_create_your_own_playful_content' => __( 'Go to <a href="https://www.playbuzz.com/" target="_blank">Playbuzz.com</a> to create your own playful content and embed on your site.', 'playbuzz' ),
			'page' => __( 'page', 'playbuzz' ),
			'jan' => __( 'Jan', 'playbuzz' ),
			'feb' => __( 'Feb', 'playbuzz' ),
			'mar' => __( 'Mar', 'playbuzz' ),
			'apr' => __( 'Apr', 'playbuzz' ),
			'may' => __( 'May', 'playbuzz' ),
			'jun' => __( 'Jun', 'playbuzz' ),
			'jul' => __( 'Jul', 'playbuzz' ),
			'aug' => __( 'Aug', 'playbuzz' ),
			'sep' => __( 'Sep', 'playbuzz' ),
			'oct' => __( 'Oct', 'playbuzz' ),
			'nov' => __( 'Nov', 'playbuzz' ),
			'dec' => __( 'Dec', 'playbuzz' ),
			'show' => __( 'Show', 'playbuzz' ),
			'all_types' => __( 'All Types', 'playbuzz' ),
			'personality_quiz' => __( 'Personality Quiz', 'playbuzz' ),
			'list' => __( 'List', 'playbuzz' ),
			'trivia' => __( 'Trivia', 'playbuzz' ),
			'poll' => __( 'Poll', 'playbuzz' ),
			'ranked_list' => __( 'Ranked List', 'playbuzz' ),
			'gallery_quiz' => __( 'Gallery Quiz', 'playbuzz' ),
			'sort_by' => __( 'Sort By', 'playbuzz' ),
			'relevance' => __( 'Relevance', 'playbuzz' ),
			'views' => __( 'Views', 'playbuzz' ),
			'date' => __( 'Date', 'playbuzz' ),
			'discover_playful_content' => __( 'Discover Playful Content', 'playbuzz' ),
			'featured_items' => __( 'Featured Items', 'playbuzz' ),
			'created_by' => __( 'Created by', 'playbuzz' ),
			'by_user' => __( 'by', 'playbuzz' ),
			'by' => __( 'By', 'playbuzz' ),
			'on' => __( 'on', 'playbuzz' ),
			'items' => __( 'items', 'playbuzz' ),
			'view' => __( 'View', 'playbuzz' ),
			'embed' => __( 'Embed', 'playbuzz' ),
			'preview_item' => __( 'Preview item', 'playbuzz' ),
			'item_doesnt_exist' => __( 'Playbuzz item does not exist', 'playbuzz' ),
			'check_shortcode_url' => __( 'Check shortcode URL in the text editor.', 'playbuzz' ),
			'your_item_will_be_embedded_here' => __( 'Your item will be embedded here', 'playbuzz' ),
			'playbuzz_item_settings' => __( 'Playbuzz Item Settings', 'playbuzz' ),
			'item_settings' => __( 'Item Settings', 'playbuzz' ),
			'embedded_item_appearance' => __( 'Embedded Item Appearance', 'playbuzz' ),
			'use_site_default_settings' => __( 'Use site default settings', 'playbuzz' ),
			'configure_default_settings' => __( 'Configure default settings', 'playbuzz' ),
			'custom' => __( 'Custom', 'playbuzz' ),
			'display_item_information' => __( 'Display item information', 'playbuzz' ),
			'show_item_thumbnail_name_description_creator' => __( 'Show item thumbnail, name, description, creator.', 'playbuzz' ),
			'display_share_buttons' => __( 'Display share buttons', 'playbuzz' ),
			'show_share_buttons_with_links_to_your_site' => __( 'Show share buttons with links to YOUR site.', 'playbuzz' ),
			'display_more_recommendations' => __( 'Display more recommendations', 'playbuzz' ),
			'show_recommendations_for_more_items' => __( 'Show recommendations for more items.', 'playbuzz' ),
			'display_facebook_comments' => __( 'Display Facebook comments', 'playbuzz' ),
			'show_facebook_comments_in_your_items' => __( 'Show Facebook comments in your items.', 'playbuzz' ),
			'site_has_fixed_sticky_top_header' => __( 'Site has fixed (sticky) top header', 'playbuzz' ),
			'height' => __( 'Height', 'playbuzz' ),
			'px' => __( 'px', 'playbuzz' ),
			'use_this_if_your_website_has_top_header_thats_always_visible_even_while_scrolling_down' => __( 'Use this if your website has top header that\'s always visible, even while scrolling down.', 'playbuzz' ),
			'cancel' => __( 'Cancel', 'playbuzz' ),
			'update_item' => __( 'Update Item', 'playbuzz' ),
			'feedback_sent' => __( 'Feedback sent, thank you!', 'playbuzz' ),
			'feedback_error' => __( 'Something went wrong please try again...', 'playbuzz' ),
			'feedback_missing_required_fields' => __( 'Some required fields are missing.', 'playbuzz' ),
		);

		// Register Scripts
		wp_register_script( 'playbuzz-admin', plugins_url( 'js/playbuzz-admin.js', __FILE__ ), array( 'jquery' ), '0.9.0' );

		// Register Localized Scripts
		wp_localize_script( 'playbuzz-admin', 'site_settings', $js_settings );
		wp_localize_script( 'playbuzz-admin', 'translation',   $js_translations );

		// Enqueue Scripts
		wp_enqueue_script( 'playbuzz-admin' );

	}

}
new PlaybuzzScriptsStyles();
