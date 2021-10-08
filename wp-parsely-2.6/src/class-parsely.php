<?php
/**
 * Parsely class
 *
 * @package Parsely
 * @since 2.5.0
 */

/**
 * Holds most of the logic for the plugin.
 *
 * @internal This needs splitting up in the future.
 *
 * @since 1.0.0
 * @since 2.5.0 Moved from plugin root file to this file.
 */
class Parsely {
	/**
	 * Declare our constants
	 *
	 * @codeCoverageIgnoreStart
	 */
	const VERSION     = PARSELY_VERSION;
	const MENU_SLUG   = 'parsely';             // Defines the page param passed to options-general.php.
	const OPTIONS_KEY = 'parsely';             // Defines the key used to store options in the WP database.
	const CAPABILITY  = 'manage_options';      // The capability required for the user to administer settings.

	/**
	 * Declare some class properties
	 *
	 * @var array $option_defaults The defaults we need for the class.
	 */
	private $option_defaults = array(
		'apikey'                      => '',
		'content_id_prefix'           => '',
		'api_secret'                  => '',
		'use_top_level_cats'          => false,
		'custom_taxonomy_section'     => 'category',
		'cats_as_tags'                => false,
		'track_authenticated_users'   => true,
		'lowercase_tags'              => true,
		'force_https_canonicals'      => false,
		'track_post_types'            => array( 'post' ),
		'track_page_types'            => array( 'page' ),
		'disable_javascript'          => false,
		'disable_amp'                 => false,
		'meta_type'                   => 'json_ld',
		'logo'                        => '',
		'metadata_secret'             => '',
		'parsely_wipe_metadata_cache' => false,
	);

	/**
	 * Declare post types that Parse.ly will process as "posts".
	 *
	 * @link https://www.parse.ly/help/integration/jsonld#distinguishing-between-posts-and-pages
	 *
	 * @since 2.5.0
	 * @var string[]
	 */
	private $supported_jsonld_post_types = array(
		'NewsArticle',
		'Article',
		'TechArticle',
		'BlogPosting',
		'LiveBlogPosting',
		'Report',
		'Review',
		'CreativeWork',
	);

	/**
	 * Declare post types that Parse.ly will process as "non-posts".
	 *
	 * @link https://www.parse.ly/help/integration/jsonld#distinguishing-between-posts-and-pages
	 *
	 * @since 2.5.0
	 * @var string[]
	 */
	private $supported_jsonld_non_post_types = array(
		'WebPage',
		'Event',
		'Hotel',
		'Restaurant',
		'Movie',
	);

	/**
	 * Register action and filter hook callbacks.
	 *
	 * Also immediately upgrade options if needed.
	 */
	public function run() {
		// Run upgrade options if they exist for the version currently defined.
		$options = $this->get_options();
		if ( empty( $options['plugin_version'] ) || self::VERSION !== $options['plugin_version'] ) {
			$method = 'upgrade_plugin_to_version_' . str_replace( '.', '_', self::VERSION );
			if ( method_exists( $this, $method ) ) {
				call_user_func_array( array( $this, $method ), array( $options ) );
			}
			// Update our version info.
			$options['plugin_version'] = self::VERSION;
			update_option( self::OPTIONS_KEY, $options );
		}

		// admin_menu and a settings link.
		add_action( 'admin_head-settings_page_parsely', array( $this, 'add_admin_header' ) );
		add_action( 'admin_menu', array( $this, 'add_settings_sub_menu' ) );
		add_action( 'admin_init', array( $this, 'initialize_settings' ) );

		// display warning when plugin hasn't been configured.
		add_action( 'admin_footer', array( $this, 'display_admin_warning' ) );

		// TODO: Remove this warning when version 3.0 is released.
		add_action( 'admin_notices', array( $this, 'display_admin_upgrade_warning' ) );

		// phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval
		add_filter( 'cron_schedules', array( $this, 'wpparsely_add_cron_interval' ) );

		add_action( 'parsely_bulk_metas_update', array( $this, 'bulk_update_posts' ) );
		// inserting parsely code.
		add_action( 'wp_head', array( $this, 'insert_parsely_page' ) );
		add_action( 'init', array( $this, 'register_js' ) );

		// load_js_api should be called prior to load_js_tracker so the relevant scripts are enqueued in order.
		add_action( 'wp_footer', array( $this, 'load_js_api' ) );
		add_action( 'wp_footer', array( $this, 'load_js_tracker' ) );

		add_action( 'save_post', array( $this, 'update_metadata_endpoint' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'wp_parsely_style_init' ) );
	}

	/**
	 * Adds 10 minute cron interval.
	 *
	 * @param array $schedules WP schedules array.
	 */
	public function wpparsely_add_cron_interval( $schedules ) {
		$schedules['everytenminutes'] = array(
			'interval' => 600, // time in seconds.
			'display'  => __( 'Every 10 Minutes', 'wp-parsely' ),
		);
		return $schedules;
	}

	/**
	 * Initialize parsely WordPress style
	 */
	public function wp_parsely_style_init() {
		wp_register_style( 'wp-parsely-style', plugin_dir_url( PARSELY_FILE ) . 'wp-parsely.css', array(), self::VERSION );
	}

	/**
	 * Include the parsely admin header
	 *
	 * @category   Function
	 * @package    Parsely
	 */
	public function add_admin_header() {
		echo '
<style>
#wp-parsely_version { color: #777; font-size: 12px; margin-left: 1em; }
.help-text { width: 75%; }
</style>
';

		$admin_script_asset = require plugin_dir_path( PARSELY_FILE ) . 'build/admin-page.asset.php';
		wp_enqueue_script(
			'wp-parsely-admin',
			plugin_dir_url( PARSELY_FILE ) . 'build/admin-page.js',
			$admin_script_asset['dependencies'],
			self::get_asset_cache_buster(),
			true
		);
	}

	/**
	 * Parsely settings page in WordPress settings menu.
	 *
	 * @category   Function
	 * @package    Parsely
	 */
	public function add_settings_sub_menu() {
		add_options_page(
			__( 'Parse.ly Settings', 'wp-parsely' ),
			__( 'Parse.ly', 'wp-parsely' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'display_settings' )
		);
	}

	/**
	 * Parse.ly settings screen ( options-general.php?page=[MENU_SLUG] )
	 *
	 * @category   Function
	 * @package    Parsely
	 */
	public function display_settings() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-parsely' ) );
		}

		include plugin_dir_path( PARSELY_FILE ) . 'src/parsely-settings.php';
	}

	/**
	 * Initialize the settings for Parsely
	 *
	 * @category   Function
	 * @package    Parsely
	 */
	public function initialize_settings() {
		// All our options are actually stored in one single array to reduce
		// DB queries.
		register_setting(
			self::OPTIONS_KEY,
			self::OPTIONS_KEY,
			array( $this, 'validate_options' )
		);

		// These are the Required Settings.
		add_settings_section(
			'required_settings',
			__( 'Required Settings', 'wp-parsely' ),
			array( $this, 'print_required_settings' ),
			self::MENU_SLUG
		);

		// Get the API Key.
		$h = __( 'Your Site ID is your own site domain ( e.g. `mydomain.com` )', 'wp-parsely' );

		$field_args = array(
			'option_key' => 'apikey',
			'help_text'  => $h,
		);
		add_settings_field(
			'apikey',
			__( 'Parse.ly Site ID', 'wp-parsely' ),
			array( $this, 'print_text_tag' ),
			self::MENU_SLUG,
			'required_settings',
			$field_args
		);

		// These are the Optional Settings.
		add_settings_section(
			'optional_settings',
			__( 'Optional Settings', 'wp-parsely' ),
			array( $this, 'print_optional_settings' ),
			self::MENU_SLUG
		);

		/* translators: 1: Opening anchor tag markup, 2: Documentation URL, 3: Opening anchor tag markup continued, 4: Closing anchor tag */
		$h      = __( 'Your API secret is your secret code to %1$s%2$s%3$saccess our API.%4$s It can be found at dash.parsely.com/yoursitedomain/settings/api ( replace yoursitedomain with your domain name, e.g. `mydomain.com` ) If you haven\'t purchased access to the API, and would like to do so, email your account manager or support@parsely.com!', 'wp-parsely' );
		$h_link = 'https://www.parse.ly/help/api/analytics/';

		$field_args = array(
			'option_key' => 'api_secret',
			'help_text'  => $h,
			'help_link'  => $h_link,
		);
		add_settings_field(
			'api_secret',
			__( 'Parse.ly API Secret', 'wp-parsely' ),
			array( $this, 'print_text_tag' ),
			self::MENU_SLUG,
			'optional_settings',
			$field_args
		);

		$h      = __( 'Your metadata secret is given to you by Parse.ly support. DO NOT enter anything here unless given to you by Parse.ly support!', 'wp-parsely' );
		$h_link = 'https://www.parse.ly/help/api/analytics/';

		$field_args = array(
			'option_key' => 'metadata_secret',
			'help_text'  => $h,
			'help_link'  => $h_link,
		);
		add_settings_field(
			'metadata_secret',
			__( 'Parse.ly Metadata Secret', 'wp-parsely' ),
			array( $this, 'print_text_tag' ),
			self::MENU_SLUG,
			'optional_settings',
			$field_args
		);

		// Clear metadata.
		$h = __( 'Check this radio button and hit "Save Changes" to clear all metadata information for Parse.ly posts and re-send all metadata to Parse.ly. WARNING: do not do this unless explicitly instructed by Parse.ly Staff!', 'wp-parsely' );
		add_settings_field(
			'parsely_wipe_metadata_cache',
			__( 'Wipe Parse.ly Metadata Info', 'wp-parsely' ),
			array( $this, 'print_checkbox_tag' ),
			self::MENU_SLUG,
			'optional_settings',
			array(
				'option_key'       => 'parsely_wipe_metadata_cache',
				'help_text'        => $h,
				'requires_recrawl' => false,
			)
		);

		/* translators: 1: Opening anchor tag markup, 2: Documentation URL, 3: Opening anchor tag markup continued, 4: Closing anchor tag */
		$h      = __( 'Choose the metadata format for our crawlers to access. Most publishers are fine with JSON-LD ( %1$s%2$s%3$shttps://www.parse.ly/help/integration/jsonld/%4$s ), but if you prefer to use our proprietary metadata format then you can do so here.', 'wp-parsely' );
		$h_link = 'https://www.parse.ly/help/integration/jsonld/';

		add_settings_field(
			'meta_type',
			__( 'Metadata Format', 'wp-parsely' ),
			array( $this, 'print_select_tag' ),
			self::MENU_SLUG,
			'optional_settings',
			array(
				'option_key'       => 'meta_type',
				'help_text'        => $h,
				'help_link'        => $h_link,
				// filter WordPress taxonomies under the hood that should not appear in dropdown.
				'select_options'   => array(
					'json_ld'        => 'json_ld',
					'repeated_metas' => 'repeated_metas',
				),
				'requires_recrawl' => true,
				'multiple'         => false,
			)
		);

		$h = __( 'If you want to specify the url for your logo, you can do so here.', 'wp-parsely' );

		$field_args = array(
			'option_key' => 'logo',
			'help_text'  => $h,
		);

		add_settings_field(
			'logo',
			__( 'Logo', 'wp-parsely' ),
			array( $this, 'print_text_tag' ),
			self::MENU_SLUG,
			'optional_settings',
			$field_args
		);

		// Content ID Prefix.
		$h = __( 'If you use more than one content management system (e.g. WordPress and Drupal), you may end up with duplicate content IDs. Adding a Content ID Prefix will ensure the content IDs from WordPress will not conflict with other content management systems. We recommend using "WP-" for your prefix.', 'wp-parsely' );

		$field_args = array(
			'option_key'       => 'content_id_prefix',
			'optional_args'    => array(
				'placeholder' => 'WP-',
			),
			'help_text'        => $h,
			'requires_recrawl' => true,
		);
		add_settings_field(
			'content_id_prefix',
			__( 'Content ID Prefix', 'wp-parsely' ),
			array( $this, 'print_text_tag' ),
			self::MENU_SLUG,
			'optional_settings',
			$field_args
		);

		// Disable JavaScript.
		$h = __( 'If you use a separate system for JavaScript tracking ( Tealium / Segment / Google Tag Manager / other tag manager solution ) you may want to use that instead of having the plugin load the tracker. WARNING: disabling this option will also disable the "Personalize Results" section of the recommended widget! We highly recommend leaving this option set to "No"!', 'wp-parsely' );
		add_settings_field(
			'disable_javascript',
			__( 'Disable JavaScript', 'wp-parsely' ),
			array( $this, 'print_binary_radio_tag' ),
			self::MENU_SLUG,
			'optional_settings',
			array(
				'title'            => __( 'Disable JavaScript', 'wp-parsely' ), // Passed for legend element.
				'option_key'       => 'disable_javascript',
				'help_text'        => $h,
				'requires_recrawl' => false,
			)
		);

		// Disable amp tracking.
		$h = __( 'If you use a separate system for JavaScript tracking on AMP pages ( Tealium / Segment / Google Tag Manager / other tag manager solution ) you may want to use that instead of having the plugin load the tracker.', 'wp-parsely' );
		add_settings_field(
			'disable_amp',
			__( 'Disable AMP Tracking', 'wp-parsely' ),
			array( $this, 'print_binary_radio_tag' ),
			self::MENU_SLUG,
			'optional_settings',
			array(
				'title'            => __( 'Disable AMP Tracking', 'wp-parsely' ), // Passed for legend element.
				'option_key'       => 'disable_amp',
				'help_text'        => $h,
				'requires_recrawl' => false,
			)
		);

		// Use top-level categories.
		$h = __( 'The plugin will use the first category assigned to a post. With this option selected, if you post a story to News > National > Florida, the plugin will use the "News" for the section name in your dashboard instead of "Florida".', 'wp-parsely' );
		add_settings_field(
			'use_top_level_cats',
			__( 'Use Top-Level Categories for Section', 'wp-parsely' ),
			array( $this, 'print_binary_radio_tag' ),
			self::MENU_SLUG,
			'optional_settings',
			array(
				'title'            => __( 'Use Top-Level Categories for Section', 'wp-parsely' ), // Passed for legend element.
				'option_key'       => 'use_top_level_cats',
				'help_text'        => $h,
				'requires_recrawl' => true,
			)
		);

		// Allow use of custom taxonomy to populate articleSection in parselyPage; defaults to category.
		$h = __( 'By default, the section value in your Parse.ly dashboard maps to a post\'s category. You can optionally choose a custom taxonomy, if you\'ve created one, to populate the section value instead.', 'wp-parsely' );
		add_settings_field(
			'custom_taxonomy_section',
			__( 'Use Custom Taxonomy for Section', 'wp-parsely' ),
			array( $this, 'print_select_tag' ),
			self::MENU_SLUG,
			'optional_settings',
			array(
				'option_key'       => 'custom_taxonomy_section',
				'help_text'        => $h,
				// filter WordPress taxonomies under the hood that should not appear in dropdown.
				'select_options'   => array_diff( get_taxonomies(), array( 'post_tag', 'nav_menu', 'author', 'link_category', 'post_format' ) ),
				'requires_recrawl' => true,
			)
		);

		// Use categories and custom taxonomies as tags.
		$h = __( 'You can use this option to add all assigned categories and taxonomies to your tags.  For example, if you had a post assigned to the categories: "Business/Tech", "Business/Social", your tags would include "Business/Tech" and "Business/Social" in addition to your other tags.', 'wp-parsely' );
		add_settings_field(
			'cats_as_tags',
			__( 'Add Categories to Tags', 'wp-parsely' ),
			array( $this, 'print_binary_radio_tag' ),
			self::MENU_SLUG,
			'optional_settings',
			array(
				'title'            => __( 'Add Categories to Tags', 'wp-parsely' ), // Passed for legend element.
				'option_key'       => 'cats_as_tags',
				'help_text'        => $h,
				'requires_recrawl' => true,
			)
		);

		// Track logged-in users.
		$h = __( 'By default, the plugin will track the activity of users that are logged into this site. You can change this setting to only track the activity of anonymous visitors. Note: You will no longer see the Parse.ly tracking code on your site if you browse while logged in.', 'wp-parsely' );
		add_settings_field(
			'track_authenticated_users',
			__( 'Track Logged-in Users', 'wp-parsely' ),
			array( $this, 'print_binary_radio_tag' ),
			self::MENU_SLUG,
			'optional_settings',
			array(
				'title'            => __( 'Track Logged-in Users', 'wp-parsely' ), // Passed for legend element.
				'option_key'       => 'track_authenticated_users',
				'help_text'        => $h,
				'requires_recrawl' => false,
			)
		);

		// Lowercase all tags.
		$h = __( 'By default, the plugin will use lowercase versions of your tags to correct for potential misspellings. You can change this setting to ensure that tag names are used verbatim.', 'wp-parsely' );
		add_settings_field(
			'lowercase_tags',
			__( 'Lowercase All Tags', 'wp-parsely' ),
			array( $this, 'print_binary_radio_tag' ),
			self::MENU_SLUG,
			'optional_settings',
			array(
				'title'            => __( 'Lowercase All Tags', 'wp-parsely' ), // Passed for legend element.
				'option_key'       => 'lowercase_tags',
				'help_text'        => $h,
				'requires_recrawl' => true,
			)
		);

		$h = __( 'The plugin uses http canonical URLs by default. If this needs to be forced to use https, set this option to true. Note: the default is fine for almost all publishers, it\'s unlikely you\'ll have to change this unless directed to do so by a Parse.ly support rep.', 'wp-parsely' );
		add_settings_field(
			'force_https_canonicals',
			__( 'Force HTTPS canonicals', 'wp-parsely' ),
			array( $this, 'print_binary_radio_tag' ),
			self::MENU_SLUG,
			'optional_settings',
			array(
				'title'            => __( 'Force HTTPS canonicals', 'wp-parsely' ), // Passed for legend element.
				'option_key'       => 'force_https_canonicals',
				'help_text'        => $h,
				'requires_recrawl' => true,
			)
		);

		// Allow use of custom taxonomy to populate articleSection in parselyPage; defaults to category.
		$h = __( 'By default, Parse.ly only tracks the default post type as a post page. If you want to track custom post types, select them here!', 'wp-parsely' );
		add_settings_field(
			'track_post_types',
			__( 'Post Types To Track', 'wp-parsely' ),
			array( $this, 'print_select_tag' ),
			self::MENU_SLUG,
			'optional_settings',
			array(
				'option_key'       => 'track_post_types',
				'help_text'        => $h,
				// filter WordPress taxonomies under the hood that should not appear in dropdown.
				'select_options'   => get_post_types(),
				'requires_recrawl' => true,
				'multiple'         => true,
			)
		);

		// Allow use of custom taxonomy to populate articleSection in parselyPage; defaults to category.
		$h = __( 'By default, Parse.ly only tracks the default page type as a non-post page. If you want to track custom post types as non-post pages, select them here!', 'wp-parsely' );
		add_settings_field(
			'track_page_types',
			__( 'Page Types To Track', 'wp-parsely' ),
			array( $this, 'print_select_tag' ),
			self::MENU_SLUG,
			'optional_settings',
			array(
				'option_key'       => 'track_page_types',
				'help_text'        => $h,
				// filter WordPress taxonomies under the hood that should not appear in dropdown.
				'select_options'   => get_post_types(),
				'requires_recrawl' => true,
				'multiple'         => true,
			)
		);

		// Dynamic tracking note.
		add_settings_field(
			'dynamic_tracking_note',
			__( 'Note: ', 'wp-parsely' ),
			array( $this, 'print_dynamic_tracking_note' ),
			self::MENU_SLUG,
			'optional_settings'
		);
	}

	/**
	 * Validate options from an array
	 *
	 * @category   Function
	 * @package    Parsely
	 * @param array  $array Array of options to be sanitized.
	 * @param string $name Unused?.
	 */
	public function validate_option_array( $array, $name ) {
		$new_array = $array;
		foreach ( $array as $key => $val ) {
			$new_array[ $key ] = sanitize_text_field( $val );
		}
		return $new_array;
	}

	/**
	 * Validate the options provided by the user
	 *
	 * @category   Function
	 * @package    Parsely
	 * @param array $input Options from the settings page.
	 * @return array $input list of validated input settings.
	 */
	public function validate_options( $input ) {
		if ( empty( $input['apikey'] ) ) {
			add_settings_error(
				self::OPTIONS_KEY,
				'apikey',
				__( 'Please specify the Site ID', 'wp-parsely' )
			);
		} else {
			$input['apikey'] = strtolower( $input['apikey'] );
			$input['apikey'] = sanitize_text_field( $input['apikey'] );
			if ( strpos( $input['apikey'], '.' ) === false || strpos( $input['apikey'], ' ' ) !== false ) {
				add_settings_error(
					self::OPTIONS_KEY,
					'apikey',
					__( 'Your Parse.ly Site ID looks incorrect, it should look like "example.com".', 'wp-parsely' )
				);
			}
		}
		// these can't be null, if somebody accidentally deselected them just reset to default.
		if ( ! isset( $input['track_post_types'] ) ) {
			$input['track_post_types'] = array( 'post' );
		}
		if ( ! isset( $input['track_page_types'] ) ) {
			$input['track_page_types'] = array( 'page' );
		}

		if ( empty( $input['logo'] ) ) {
			$input['logo'] = $this->get_logo_default();
		}

		$input['track_post_types'] = $this->validate_option_array( $input['track_post_types'], 'track_post_types' );
		$input['track_page_types'] = $this->validate_option_array( $input['track_page_types'], 'track_page_types' );

		$input['api_secret'] = sanitize_text_field( $input['api_secret'] );
		// Content ID prefix.
		$input['content_id_prefix']       = sanitize_text_field( $input['content_id_prefix'] );
		$input['custom_taxonomy_section'] = sanitize_text_field( $input['custom_taxonomy_section'] );

		// Custom taxonomy as section.
		// Top-level categories.
		if ( 'true' !== $input['use_top_level_cats'] && 'false' !== $input['use_top_level_cats'] ) {
			add_settings_error(
				self::OPTIONS_KEY,
				'use_top_level_cats',
				__( 'Value passed for use_top_level_cats must be either "true" or "false".', 'wp-parsely' )
			);
		} else {
			$input['use_top_level_cats'] = 'true' === $input['use_top_level_cats'];
		}

		// Child categories as tags.
		if ( 'true' !== $input['cats_as_tags'] && 'false' !== $input['cats_as_tags'] ) {
			add_settings_error(
				self::OPTIONS_KEY,
				'cats_as_tags',
				__( 'Value passed for cats_as_tags must be either "true" or "false".', 'wp-parsely' )
			);
		} else {
			$input['cats_as_tags'] = 'true' === $input['cats_as_tags'];
		}

		// Track authenticated users.
		if ( 'true' !== $input['track_authenticated_users'] && 'false' !== $input['track_authenticated_users'] ) {
			add_settings_error(
				self::OPTIONS_KEY,
				'track_authenticated_users',
				__( 'Value passed for track_authenticated_users must be either "true" or "false".', 'wp-parsely' )
			);
		} else {
			$input['track_authenticated_users'] = 'true' === $input['track_authenticated_users'];
		}

		// Lowercase tags.
		if ( 'true' !== $input['lowercase_tags'] && 'false' !== $input['lowercase_tags'] ) {
			add_settings_error(
				self::OPTIONS_KEY,
				'lowercase_tags',
				__( 'Value passed for lowercase_tags must be either "true" or "false".', 'wp-parsely' )
			);
		} else {
			$input['lowercase_tags'] = 'true' === $input['lowercase_tags'];
		}

		if ( 'true' !== $input['force_https_canonicals'] && 'false' !== $input['force_https_canonicals'] ) {
			add_settings_error(
				self::OPTIONS_KEY,
				'force_https_canonicals',
				__( 'Value passed for force_https_canonicals must be either "true" or "false".', 'wp-parsely' )
			);
		} else {
			$input['force_https_canonicals'] = 'true' === $input['force_https_canonicals'];
		}

		if ( 'true' !== $input['disable_javascript'] && 'false' !== $input['disable_javascript'] ) {
			add_settings_error(
				self::OPTIONS_KEY,
				'disable_javascript',
				__( 'Value passed for disable_javascript must be either "true" or "false".', 'wp-parsely' )
			);
		} else {
			$input['disable_javascript'] = 'true' === $input['disable_javascript'];
		}

		if ( 'true' !== $input['disable_amp'] && 'false' !== $input['disable_amp'] ) {
			add_settings_error(
				self::OPTIONS_KEY,
				'disable_amp',
				__( 'Value passed for disable_amp must be either "true" or "false".', 'wp-parsely' )
			);
		} else {
			$input['disable_amp'] = 'true' === $input['disable_amp'];
		}

		if ( ! empty( $input['metadata_secret'] ) ) {
			if ( strlen( $input['metadata_secret'] ) !== 10 ) {
				add_settings_error(
					self::OPTIONS_KEY,
					'metadata_secret',
					__( 'Metadata secret is incorrect. Please contact Parse.ly support!', 'wp-parsely' )
				);
			} elseif ( 'true' === $input['parsely_wipe_metadata_cache'] ) {
				delete_post_meta_by_key( 'parsely_metadata_last_updated' );

				wp_schedule_event( time() + 100, 'everytenminutes', 'parsely_bulk_metas_update' );
				$input['parsely_wipe_metadata_cache'] = false;
			}
		}

		return $input;
	}

	/**
	 * Not doing anything here
	 *
	 * @category   Function
	 * @package    Parsely
	 */
	public function print_required_settings() {
		// We can optionally print some text here in the future, but we don't
		// need to now.
	}

	/**
	 * Not doing anything here
	 *
	 * @category   Function
	 * @package    Parsely
	 */
	public function print_optional_settings() {
		// We can optionally print some text here in the future, but we don't
		// need to now.
	}

	/**
	 * Display the admin warning if needed
	 *
	 * @category   Function
	 * @package    Parsely
	 */
	public function display_admin_warning() {
		if ( ! $this->should_display_admin_warning() ) {
			return;
		}

		$message = sprintf(
			/* translators: %s: Plugin settings page URL */
			__( '<strong>The Parse.ly plugin is not active.</strong> You need to <a href="%s">provide your Parse.ly Dash Site ID</a> before things get cooking.', 'wp-parsely' ),
			esc_url( self::get_settings_url() )
		);
		?>
		<div id="message" class="error"><p><?php echo wp_kses_post( $message ); ?></p></div>
		<?php
	}

	/**
	 * Display a dismissible admin warning if the current WordPress or PHP versions are below the required minimum for the 3.0 release of wp-parsely
	 * We should get rid of this warning when we release 3.0
	 *
	 * @since 2.6.0
	 */
	public function display_admin_upgrade_warning() {
		global $wp_version;
		if ( version_compare( PHP_VERSION, '7.1.0', '>=' ) && version_compare( $wp_version, '5.0', '>=' ) ) {
			return;
		}

		$message = sprintf(
			/* translators: %s: Plugin settings page URL */
			__( '<strong>The next version of the Parse.ly plugin will not work with the current setup.</strong> WordPress 5.0 and PHP 7.1 will be the <a href="%s">new required minimum versions</a>.', 'wp-parsely' ),
			esc_url( 'https://github.com/Parsely/wp-parsely/issues/390' )
		);
		?>
		<div id="message" class="notice notice-error is-dismissible"><p><?php echo wp_kses_post( $message ); ?></p></div>
		<?php
	}

	/**
	 * Decide whether the admin display warning should be displayed
	 *
	 * @category Function
	 * @package Parsely
	 *
	 * @return bool True if the admin warning should be displayed
	 */
	private function should_display_admin_warning() {
		if ( is_network_admin() ) {
			return false;
		}

		$options = $this->get_options();
		return empty( $options['apikey'] );
	}

	/**
	 * Show our note about dynamic tracking
	 *
	 * @category   Function
	 * @package    Parsely
	 */
	public function print_dynamic_tracking_note() {
		printf(
			/* translators: 1: Documentation URL 2: Documentation URL */
			wp_kses_post( __( 'This plugin does not currently support dynamic tracking ( the tracking of multiple pageviews on a single page). Some common use-cases for dynamic tracking are slideshows or articles loaded via AJAX calls in single-page applications -- situations in which new content is loaded without a full page refresh. Tracking these events requires manually implementing additional JavaScript above <a href="%1$s">the standard Parse.ly include</a> that the plugin injects into your page source. Please consult <a href="%2$s">the Parse.ly documentation on dynamic tracking</a> for instructions on implementing dynamic tracking, or contact Parse.ly support (<a href="%3$s">support@parsely.com</a> ) for additional assistance.', 'wp-parsely' ) ),
			esc_url( 'http://www.parsely.com/help/integration/basic/' ),
			esc_url( 'https://www.parsely.com/help/integration/dynamic/' ),
			esc_url( 'mailto:support@parsely.com' )
		);
	}

	/**
	 * End the code coverage ignore
	 *
	 * @codeCoverageIgnoreEnd
	 */

	/**
	 * Actually inserts the code for the <meta name='parsely-page'> parameter within the <head></head> tag.
	 */
	public function insert_parsely_page() {
		$parsely_options = $this->get_options();

		if (
			$this->api_key_is_missing() ||

			// Chosen not to track logged in users.
			( ! $parsely_options['track_authenticated_users'] && $this->parsely_is_user_logged_in() ) ||

			// 404 pages are not tracked.
			is_404() ||

			// Search pages are not tracked.
			is_search()
		) {
			return '';
		}

		global $post;
		// Assign default values for LD+JSON
		// TODO: Maping of an install's post types to Parse.ly post types (namely page/post).
		$parsely_page = $this->construct_parsely_metadata( $parsely_options, $post );

		// Something went wrong - abort.
		if ( empty( $parsely_page ) || ! isset( $parsely_page['headline'] ) ) {
			return;
		}

		echo "\n" . '<!-- BEGIN Parse.ly ' . esc_html( self::VERSION ) . ' -->' . "\n";

		// Insert JSON-LD or repeated metas.
		if ( 'json_ld' === $parsely_options['meta_type'] ) {
			include plugin_dir_path( PARSELY_FILE ) . 'views/json-ld.php';
		} else {
			// Assume `meta_type` is `repeated_metas`.
			$parsely_post_type = $this->convert_jsonld_to_parsely_type( $parsely_page['@type'] );
			if ( isset( $parsely_page['keywords'] ) && is_array( $parsely_page['keywords'] ) ) {
				$parsely_page['keywords'] = implode( ',', $parsely_page['keywords'] );
			}

			$parsely_metas = array(
				'title'     => isset( $parsely_page['headline'] ) ? $parsely_page['headline'] : null,
				'link'      => isset( $parsely_page['url'] ) ? $parsely_page['url'] : null,
				'type'      => $parsely_post_type,
				'image-url' => isset( $parsely_page['thumbnailUrl'] ) ? $parsely_page['thumbnailUrl'] : null,
				'pub-date'  => isset( $parsely_page['datePublished'] ) ? $parsely_page['datePublished'] : null,
				'section'   => isset( $parsely_page['articleSection'] ) ? $parsely_page['articleSection'] : null,
				'tags'      => isset( $parsely_page['keywords'] ) ? $parsely_page['keywords'] : null,
				'author'    => isset( $parsely_page['author'] ),
			);
			$parsely_metas = array_filter( $parsely_metas, array( $this, 'filter_empty_and_not_string_from_array' ) );

			if ( isset( $parsely_page['author'] ) ) {
				$parsely_page_authors = wp_list_pluck( $parsely_page['author'], 'name' );
				$parsely_page_authors = array_filter( $parsely_page_authors, array( $this, 'filter_empty_and_not_string_from_array' ) );
			}

			include plugin_dir_path( PARSELY_FILE ) . 'views/repeated-metas.php';
		}

		// Add any custom metadata.
		if ( isset( $parsely_page['custom_metadata'] ) ) {
			include plugin_dir_path( PARSELY_FILE ) . 'views/custom-metadata.php';
		}

		echo '<!-- END Parse.ly -->' . "\n\n";

		return $parsely_page;
	}

	/**
	 * Function to be used in `array_filter` to clean up repeated metas
	 *
	 * @param mixed $var Value to filter from the array.
	 * @return bool Returns true if the variable is not empty, and it's a string
	 */
	private static function filter_empty_and_not_string_from_array( $var ) {
		return ! empty( $var ) && is_string( $var );
	}

	/**
	 * Compare the post_status key against an allowed list (by default, only 'publish'ed content includes tracking data).
	 *
	 * @since 2.5.0
	 *
	 * @param int|WP_Post $post Which post object or ID to check.
	 * @return bool Should the post status be tracked for the provided post's post_type. By default, only 'publish' is allowed.
	 */
	public static function post_has_trackable_status( $post ) {
		static $cache = array();
		$post_id      = is_int( $post ) ? $post : $post->ID;
		if ( isset( $cache[ $post_id ] ) ) {
			return $cache[ $post_id ];
		}

		/**
		 * Filters the statuses that are permitted to be tracked.
		 *
		 * By default, the only status tracked is 'publish'. Use this filter if you have other published content that has a different (custom) status.
		 *
		 * @since 2.5.0
		 *
		 * @param string[]    $trackable_statuses The list of post statuses that are allowed to be tracked.
		 * @param int|WP_Post $post               Which post object or ID is being checked.
		 */
		$statuses          = apply_filters( 'wp_parsely_trackable_statuses', array( 'publish' ), $post );
		$cache[ $post_id ] = in_array( get_post_status( $post ), $statuses, true );
		return $cache[ $post_id ];
	}

	/**
	 * Check if the post's type is "publicly queryable."
	 *
	 * @since 2.6.0
	 *
	 * @param int|WP_Post $post Which post object or ID to check.
	 * @return bool Is the provided post's type considered "public."
	 */
	public static function post_has_viewable_type( $post ) {
		if ( function_exists( 'is_post_type_viewable' ) ) {
			return is_post_type_viewable( $post->post_type );
		}

		/**
		 * `is_post_type_viewable` was added in WordPress 4.4
		 * The rest of this function approximates it until we bump the plugin min. version above it.
		 */
		$post_type = get_post_type_object( $post->post_type );
		return $post_type->publicly_queryable || ( $post_type->_builtin && $post_type->public );
	}

	/**
	 * Creates parsely metadata object from post metadata.
	 *
	 * @param array   $parsely_options parsely_options array.
	 * @param WP_Post $post object.
	 * @return mixed|void
	 */
	public function construct_parsely_metadata( array $parsely_options, $post ) {
		$parsely_page      = array(
			'@context' => 'http://schema.org',
			'@type'    => 'WebPage',
		);
		$current_url       = $this->get_current_url();
		$queried_object_id = get_queried_object_id();

		if ( is_front_page() && ! is_paged() ) {
			$parsely_page['headline'] = $this->get_clean_parsely_page_value( get_bloginfo( 'name', 'raw' ) );
			$parsely_page['url']      = home_url();
		} elseif ( is_front_page() && is_paged() ) {
			$parsely_page['headline'] = $this->get_clean_parsely_page_value( get_bloginfo( 'name', 'raw' ) );
			$parsely_page['url']      = $current_url;
		} elseif (
			is_home() && (
				! ( 'page' === get_option( 'show_on_front' ) && ! get_option( 'page_on_front' ) ) ||
				$queried_object_id && (int) get_option( 'page_for_posts' ) === $queried_object_id
			)
		) {
			$parsely_page['headline'] = get_the_title( get_option( 'page_for_posts', true ) );
			$parsely_page['url']      = $current_url;
		} elseif ( is_author() ) {
			// TODO: why can't we have something like a WP_User object for all the other cases? Much nicer to deal with than functions.
			$author                   = ( get_query_var( 'author_name' ) ) ? get_user_by( 'slug', get_query_var( 'author_name' ) ) : get_userdata( get_query_var( 'author' ) );
			$parsely_page['headline'] = $this->get_clean_parsely_page_value( 'Author - ' . $author->data->display_name );
			$parsely_page['url']      = $current_url;
		} elseif ( is_category() || is_post_type_archive() || is_tax() ) {
			$category                 = get_queried_object();
			$parsely_page['headline'] = $this->get_clean_parsely_page_value( $category->name );
			$parsely_page['url']      = $current_url;
		} elseif ( is_date() ) {
			if ( is_year() ) {
				/* translators: %s: Archive year */
				$parsely_page['headline'] = sprintf( __( 'Yearly Archive - %s', 'wp-parsely' ), get_the_time( 'Y' ) );
			} elseif ( is_month() ) {
				/* translators: %s: Archive month, formatted as F, Y */
				$parsely_page['headline'] = sprintf( __( 'Monthly Archive - %s', 'wp-parsely' ), get_the_time( 'F, Y' ) );
			} elseif ( is_day() ) {
				/* translators: %s: Archive day, formatted as F jS, Y */
				$parsely_page['headline'] = sprintf( __( 'Daily Archive - %s', 'wp-parsely' ), get_the_time( 'F jS, Y' ) );
			} elseif ( is_time() ) {
				/* translators: %s: Archive time, formatted as F jS g:i:s A */
				$parsely_page['headline'] = sprintf( __( 'Hourly, Minutely, or Secondly Archive - %s', 'wp-parsely' ), get_the_time( 'F jS g:i:s A' ) );
			}
			$parsely_page['url'] = $current_url;
		} elseif ( is_tag() ) {
			$tag = single_tag_title( '', false );
			if ( empty( $tag ) ) {
				$tag = single_term_title( '', false );
			}
			/* translators: %s: Tag name */
			$parsely_page['headline'] = $this->get_clean_parsely_page_value( sprintf( __( 'Tagged - %s', 'wp-parsely' ), $tag ) );
			$parsely_page['url']      = $current_url;
		} elseif ( in_array( get_post_type( $post ), $parsely_options['track_post_types'], true ) && self::post_has_trackable_status( $post ) ) {
			$authors  = $this->get_author_names( $post );
			$category = $this->get_category_name( $post, $parsely_options );
			$post_id  = $parsely_options['content_id_prefix'] . get_the_ID();

			if ( has_post_thumbnail( $post ) ) {
				$image_id  = get_post_thumbnail_id( $post );
				$image_url = wp_get_attachment_image_src( $image_id );
				$image_url = $image_url[0];
			} else {
				$image_url = $this->get_first_image( $post );
			}

			$tags = $this->get_tags( $post->ID );
			if ( $parsely_options['cats_as_tags'] ) {
				$tags = array_merge( $tags, $this->get_categories( $post->ID ) );
				// add custom taxonomy values.
				$tags = array_merge( $tags, $this->get_custom_taxonomy_values( $post, $parsely_options ) );
			}
			// the function 'mb_strtolower' is not enabled by default in php, so this check
			// falls back to the native php function 'strtolower' if necessary.
			if ( function_exists( 'mb_strtolower' ) ) {
				$lowercase_callback = 'mb_strtolower';
			} else {
				$lowercase_callback = 'strtolower';
			}
			if ( $parsely_options['lowercase_tags'] ) {
				$tags = array_map( $lowercase_callback, $tags );
			}

			/**
			 * Filters the post tags that are used as metadata keywords.
			 *
			 * @since 1.8.0
			 *
			 * @param string[] $tags Post tags.
			 * @param int      $ID   Post ID.
			 */
			$tags = apply_filters( 'wp_parsely_post_tags', $tags, $post->ID );
			$tags = array_map( array( $this, 'get_clean_parsely_page_value' ), $tags );
			$tags = array_values( array_unique( $tags ) );

			/**
			 * Filters the JSON-LD @type.
			 *
			 * @since 2.5.0
			 *
			 * @param array   $jsonld_type  JSON-LD @type value, default is NewsArticle.
			 * @param integer $id           Post ID.
			 * @param string  $post_type    Post type in WordPress.
			 */
			$type            = (string) apply_filters( 'wp_parsely_post_type', 'NewsArticle', $post->ID, $post->post_type );
			$supported_types = array_merge( $this->supported_jsonld_post_types, $this->supported_jsonld_non_post_types );

			// Validate type before passing it further as an invalid type will not be recognized by Parse.ly.
			if ( ! in_array( $type, $supported_types ) ) {
				$error = sprintf(
					/* translators: 1: JSON @type like NewsArticle, 2: URL */
					__( '@type %1$s is not supported by Parse.ly. Please use a type mentioned in %2$s', 'wp-parsely' ),
					$type,
					'https://www.parse.ly/help/integration/jsonld#distinguishing-between-posts-and-pages'
				);
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
				trigger_error( esc_html( $error ), E_USER_WARNING );
				$type = 'NewsArticle';
			}

			$parsely_page['@type']            = $type;
			$parsely_page['mainEntityOfPage'] = array(
				'@type' => 'WebPage',
				'@id'   => $this->get_current_url( 'post' ),
			);
			$parsely_page['headline']         = $this->get_clean_parsely_page_value( get_the_title( $post ) );
			$parsely_page['url']              = $this->get_current_url( 'post', $post->ID );
			$parsely_page['thumbnailUrl']     = $image_url;
			$parsely_page['image']            = array(
				'@type' => 'ImageObject',
				'url'   => $image_url,
			);
			$parsely_page['dateCreated']      = gmdate( 'Y-m-d\TH:i:s\Z', get_post_time( 'U', true, $post ) );
			$parsely_page['datePublished']    = gmdate( 'Y-m-d\TH:i:s\Z', get_post_time( 'U', true, $post ) );
			if ( get_the_modified_date( 'U', true ) >= get_post_time( 'U', true, $post ) ) {
				$parsely_page['dateModified'] = gmdate( 'Y-m-d\TH:i:s\Z', get_the_modified_date( 'U', true ) );
			} else {
				// Use the post time as the earliest possible modification date.
				$parsely_page['dateModified'] = gmdate( 'Y-m-d\TH:i:s\Z', get_post_time( 'U', true, $post ) );
			}
			$parsely_page['articleSection'] = $category;
			$author_objects                 = array();
			foreach ( $authors as $author ) {
				$author_tag = array(
					'@type' => 'Person',
					'name'  => $author,
				);
				array_push( $author_objects, $author_tag );
			}
			$parsely_page['author']    = $author_objects;
			$parsely_page['creator']   = $authors;
			$parsely_page['publisher'] = array(
				'@type' => 'Organization',
				'name'  => get_bloginfo( 'name' ),
				'logo'  => $parsely_options['logo'],
			);
			$parsely_page['keywords']  = $tags;
		} elseif ( in_array( get_post_type(), $parsely_options['track_page_types'], true ) && self::post_has_trackable_status( $post ) ) {
			$parsely_page['headline'] = $this->get_clean_parsely_page_value( get_the_title( $post ) );
			$parsely_page['url']      = $this->get_current_url( 'post' );
		} elseif ( 'page' === get_option( 'show_on_front' ) && ! get_option( 'page_on_front' ) ) {
			$parsely_page['headline'] = $this->get_clean_parsely_page_value( get_bloginfo( 'name', 'raw' ) );
			$parsely_page['url']      = home_url();
		}

		/**
		 * Filters the structured metadata.
		 *
		 * @deprecated 2.5.0 Use `wp_parsely_metadata` filter instead.
		 * @since 1.10.0
		 *
		 * @param array   $parsely_page    Existing structured metadata for a page.
		 * @param WP_Post $post            Post object.
		 * @param array   $parsely_options The Parsely options.
		 */
		$parsely_page = apply_filters_deprecated(
			'after_set_parsely_page',
			array( $parsely_page, $post, $parsely_options ),
			'2.5.0',
			'wp_parsely_metadata'
		);

		/**
		 * Filters the structured metadata.
		 *
		 * @since 2.5.0
		 *
		 * @param array   $parsely_page    Existing structured metadata for a page.
		 * @param WP_Post $post            Post object.
		 * @param array   $parsely_options The Parsely options.
		 */
		$parsely_page = apply_filters( 'wp_parsely_metadata', $parsely_page, $post, $parsely_options );

		return $parsely_page;
	}


	/**
	 * Updates the Parsely metadata endpoint with the new metadata of the post.
	 *
	 * @param int $post_id id of the post to update.
	 * @return string
	 */
	public function update_metadata_endpoint( $post_id ) {
		$parsely_options = $this->get_options();

		if ( $this->api_key_is_missing() || empty( $parsely_options['metadata_secret'] ) ) {
			return '';
		}

		$post     = get_post( $post_id );
		$metadata = $this->construct_parsely_metadata( $parsely_options, $post );

		$endpoint_metadata = array(
			'canonical_url' => $metadata['url'],
			'page_type'     => $this->convert_jsonld_to_parsely_type( $metadata['@type'] ),
			'title'         => $metadata['headline'],
			'image_url'     => $metadata['thumbnailUrl'],
			'pub_date_tmsp' => $metadata['datePublished'],
			'section'       => $metadata['articleSection'],
			'authors'       => $metadata['creator'],
			'tags'          => $metadata['keywords'],
		);

		$parsely_api_endpoint    = 'https://api.parsely.com/v2/metadata/posts';
		$parsely_metadata_secret = $parsely_options['metadata_secret'];
		$headers                 = array(
			'Content-Type' => 'application/json',
		);
		$body                    = wp_json_encode(
			array(
				'secret'   => $parsely_metadata_secret,
				'apikey'   => $parsely_options['apikey'],
				'metadata' => $endpoint_metadata,
			)
		);
		$response                = wp_remote_post(
			$parsely_api_endpoint,
			array(
				'method'      => 'POST',
				'headers'     => $headers,
				'blocking'    => false,
				'body'        => $body,
				'data_format' => 'body',
			)
		);
		$current_timestamp       = time();
		$meta_update             = update_post_meta( $post_id, 'parsely_metadata_last_updated', $current_timestamp );
	}


	/**
	 * Updates posts with Parsely metadata api in bulk.
	 */
	public function bulk_update_posts() {
		global $wpdb;
		$parsely_options      = $this->get_options();
		$allowed_types        = array_merge( $parsely_options['track_post_types'], $parsely_options['track_page_types'] );
		$allowed_types_string = implode(
			', ',
			array_map(
				function( $v ) {
					return "'" . esc_sql( $v ) . "'";
				},
				$allowed_types
			)
		);
		$ids                  = wp_cache_get( 'parsely_post_ids_need_meta_updating' );
		if ( false === $ids ) {
			$ids = array();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$results = $wpdb->get_results(
				$wpdb->prepare( "SELECT DISTINCT(id) FROM {$wpdb->posts} WHERE post_type IN (\" . %s . \") AND id NOT IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'parsely_metadata_last_updated');", $allowed_types_string ),
				ARRAY_N
			);
			foreach ( $results as $result ) {
				array_push( $ids, $result[0] );
			}
			wp_cache_set( 'parsely_post_ids_need_meta_updating', $ids, '', 86400 );
		}

		for ( $i = 0; $i < 100; $i++ ) {
			$post_id = array_pop( $ids );
			if ( null === $post_id ) {
				wp_clear_scheduled_hook( 'parsely_bulk_metas_update' );
				break;
			}
			$this->update_metadata_endpoint( $post_id );
		}
	}

	/**
	 * Get the cache buster value for script and styles.
	 *
	 * If WP_DEBUG is defined and truthy and we're not running tests, then use a random number.
	 * Otherwise, use the plugin version.
	 *
	 * @since 2.5.0
	 *
	 * @return int|string Random number or plugin version string.
	 */
	public static function get_asset_cache_buster() {
		static $cache_buster;
		if ( isset( $cache_buster ) ) {
			return $cache_buster;
		}

		$cache_buster = defined( 'WP_DEBUG' ) && WP_DEBUG && empty( 'WP_TESTS_DOMAIN' ) ? wp_rand() : PARSELY_VERSION;

		/**
		 * Filters the cache buster value for linked scripts and styles.
		 *
		 * @since 2.5.0
		 *
		 * @param string $cache_buster Plugin version, unless WP_DEBUG is defined and truthy, and tests are not running.
		 */
		return apply_filters( 'wp_parsely_cache_buster', $cache_buster );
	}

	/**
	 * Register JavaScripts, if there's an API key value saved.
	 *
	 * @since 2.5.0
	 *
	 * @return void
	 */
	public function register_js() {
		$parsely_options = $this->get_options();

		if ( $this->api_key_is_missing() ) {
			return;
		}

		wp_register_script(
			'wp-parsely-tracker',
			'https://cdn.parsely.com/keys/' . $parsely_options['apikey'] . '/p.js',
			array(),
			self::get_asset_cache_buster(),
			true
		);

		$api_script_asset = require plugin_dir_path( PARSELY_FILE ) . 'build/init-api.asset.php';
		wp_register_script(
			'wp-parsely-api',
			plugin_dir_url( PARSELY_FILE ) . 'build/init-api.js',
			$api_script_asset['dependencies'],
			self::get_asset_cache_buster(),
			true
		);

		wp_localize_script(
			'wp-parsely-api',
			'wpParsely', // This globally-scoped object holds variables used to interact with the API.
			array(
				'apikey' => $parsely_options['apikey'],
			)
		);
	}

	/**
	 * Enqueues the JavaScript code required to send off beacon requests.
	 *
	 * @since 2.5.0 Rename from insert_parsely_javascript
	 */
	public function load_js_tracker() {
		$parsely_options = $this->get_options();
		if ( $this->api_key_is_missing() || $parsely_options['disable_javascript'] ) {
			return;
		}

		global $post;
		$display = true;
		if ( in_array( get_post_type(), $parsely_options['track_post_types'], true ) && ! self::post_has_trackable_status( $post ) ) {
			$display = false;
		}
		if ( ! $parsely_options['track_authenticated_users'] && $this->parsely_is_user_logged_in() ) {
			$display = false;
		}
		if ( ! in_array( get_post_type(), $parsely_options['track_post_types'], true ) && ! in_array( get_post_type(), $parsely_options['track_page_types'], true ) ) {
			$display = false;
		}

		/**
		 * Filters whether to include the Parsely JavaScript file.
		 *
		 * If true, the JavaScript files are sourced.
		 *
		 * @since 2.2.0
		 * @deprecated 2.5.0 Use `wp_parsely_load_js_tracker` filter instead.
		 *
		 * @param bool $display True if the JavaScript file should be included. False if not.
		 */
		if ( ! apply_filters_deprecated(
			'parsely_filter_insert_javascript',
			array( $display ),
			'2.5.0',
			'wp_parsely_load_js_tracker'
		) ) {
			return;
		}

		/**
		 * Filters whether to enqueue the Parsely JavaScript tracking script from the CDN.
		 *
		 * If true, the script is enqueued.
		 *
		 * @since 2.5.0
		 *
		 * @param bool $display True if the JavaScript file should be included. False if not.
		 */
		if ( ! apply_filters( 'wp_parsely_load_js_tracker', $display ) ) {
				return;
		}

		if ( ! has_filter( 'script_loader_tag', array( $this, 'script_loader_tag' ) ) ) {
			add_filter( 'script_loader_tag', array( $this, 'script_loader_tag' ), 10, 3 );
		}

		wp_enqueue_script( 'wp-parsely-tracker' );
	}

	/**
	 * Load JavaScript for Parse.ly API.
	 *
	 * @since 2.5.0
	 */
	public function load_js_api() {
		$parsely_options = $this->get_options();

		// If we don't have an API secret, there's no need to proceed.
		if ( empty( $parsely_options['api_secret'] ) ) {
			return;
		}

		if ( ! has_filter( 'script_loader_tag', array( $this, 'script_loader_tag' ) ) ) {
			add_filter( 'script_loader_tag', array( $this, 'script_loader_tag' ), 10, 3 );
		}

		wp_enqueue_script( 'wp-parsely-api' );
	}

	/**
	 * Filter the script tag for certain scripts to add needed attributes.
	 *
	 * @since 2.5.0
	 *
	 * @param string $tag    The `script` tag for the enqueued script.
	 * @param string $handle The script's registered handle.
	 * @param string $src    The script's source URL.
	 * @return string Amended `script` tag.
	 */
	public function script_loader_tag( $tag, $handle, $src ) {
		$parsely_options = $this->get_options();
		if ( in_array(
			$handle,
			array(
				'wp-parsely',
				'wp-parsely-api',
				'wp-parsely-tracker',
				'wp-parsely-recommended-widget',
			)
		) ) {
			// Have CloudFlare Rocket Loader ignore these scripts:
			// https://support.cloudflare.com/hc/en-us/articles/200169436-How-can-I-have-Rocket-Loader-ignore-specific-JavaScripts-.
			$tag = preg_replace( '/^<script /', '<script data-cfasync="false" ', $tag );
		}

		if ( 'wp-parsely-tracker' === $handle ) {
			$tag = preg_replace( '/ id=(\"|\')wp-parsely-tracker-js\1/', ' id="parsely-cfg"', $tag );
			$tag = preg_replace(
				'/ src=/',
				' data-parsely-site="' . esc_attr( $parsely_options['apikey'] ) . '" src=',
				$tag
			);
		}
		return $tag;
	}

	/**
	 * Print out the select tags
	 *
	 * @param array $args The arguments for the select drop downs.
	 */
	public function print_select_tag( $args ) {
		$options        = $this->get_options();
		$name           = $args['option_key'];
		$select_options = $args['select_options'];
		if ( isset( $args['multiple'] ) ) {
			$multiple = $args['multiple'];
		} else {
			$multiple = false;
		}
		$selected = isset( $options[ $name ] ) ? $options[ $name ] : null;
		$id       = esc_attr( $name );
		$name     = self::OPTIONS_KEY . "[$id]";

		if ( isset( $args['help_text'] ) ) {
			echo '<div class="parsely-form-controls" data-has-help-text="true">';
		}
		if ( isset( $args['requires_recrawl'] ) && true === $args['requires_recrawl'] ) {
			echo '<div class="parsely-form-controls" data-requires-recrawl="true">';
		}

		if ( $multiple ) {
			echo sprintf( "<select multiple='multiple' name='%s[]'id='%s'", esc_attr( $name ), esc_attr( $name ) );
		} else {
			echo sprintf( "<select name='%s' id='%s'", esc_attr( $name ), esc_attr( $name ) );
		}

		echo '>';

		foreach ( $select_options as $key => $val ) {
			echo '<option value="' . esc_attr( $key ) . '" ';

			if ( $multiple ) {
				$selected = in_array( $val, $options[ $args['option_key'] ], true );
				echo selected( $selected, true, false ) . '>';
			} else {
				echo selected( $selected, $key, false ) . '>';
			}
			echo esc_html( $val );
			echo '</option>';
		}
		echo '</select>';

		if ( isset( $args['help_text'] ) ) {
			if ( isset( $args['help_link'] ) ) {
				echo '<div class="help-text"> <p class="description">' .
				sprintf( esc_html( $args['help_text'] ), '<a href="', esc_url( $args['help_link'] ), '">', '</a>' ) .
				'</p></div>';
			} else {
				echo '<div class="help-text"> <p class="description">' . esc_html( $args['help_text'] ) . '</p></div>';
			}
		}
		echo '</div>';
	}

	/**
	 * Print out the radio buttons
	 *
	 * @param array $args The arguments for the radio buttons.
	 */
	public function print_binary_radio_tag( $args ) {
		$options = $this->get_options();
		$name    = $args['option_key'];
		$value   = $options[ $name ];
		$id      = esc_attr( $name );
		$name    = self::OPTIONS_KEY . "[$id]";

		$has_help_text    = isset( $args['help_text'] ) ? ' data-has-help-text="true"' : '';
		$requires_recrawl = isset( $args['requires_recrawl'] ) && true === $args['requires_recrawl'] ? ' data-requires-recrawl="true"' : '';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static text attribute key-value. ?>
		<fieldset class="parsely-form-controls" <?php echo $has_help_text . $requires_recrawl; ?>>
			<legend class="screen-reader-text"><span><?php echo esc_html( $args['title'] ); ?></span></legend>
			<p>
				<label for="<?php echo esc_attr( "{$id}_true" ); ?>">
					<input type="radio" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( "{$id}_true" ); ?>" value="true"<?php checked( $value ); ?> />Yes
				</label>
				<br />
				<label for="<?php echo esc_attr( "{$id}_false" ); ?>">
					<input type="radio" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( "{$id}_false" ); ?>" value="false"<?php checked( $value, false ); ?> />No
				</label>
			</p>
		<?php
		if ( isset( $args['help_text'] ) ) {
			?>
			<div class="help-text"><p class="description .help-text"><?php echo esc_html( $args['help_text'] ); ?></p></div>
			<?php
		}
		?>
		</fieldset>
		<?php
	}

	/**
	 * Prints a checkbox tag in the settings page.
	 *
	 * @param array $args Arguments to print to checkbox tag.
	 */
	public function print_checkbox_tag( $args ) {
		$options = $this->get_options();
		$name    = $args['option_key'];
		$value   = $options[ $name ];
		$id      = esc_attr( $name );
		$name    = self::OPTIONS_KEY . "[$id]";

		if ( isset( $args['help_text'] ) ) {
			echo '<div class="parsely-form-controls" data-has-help-text="true">';
		}
		if ( isset( $args['requires_recrawl'] ) && true === $args['requires_recrawl'] ) {
			echo '<div class="parsely-form-controls" data-requires-recrawl="true">';
		}

		echo sprintf( "<input type='checkbox' name='%s' id='%s_true' value='true' ", esc_attr( $name ), esc_attr( $id ) );
		echo checked( true === $value, true, false );
		echo sprintf( " /> <label for='%s_true'>Yes</label>", esc_attr( $id ) );

		if ( isset( $args['help_text'] ) ) {
			echo '<div class="help-text"><p class="description">' . esc_html( $args['help_text'] ) . '</p></div>';
		}
		echo '</div>';
	}

	/**
	 * Print out the radio buttons
	 *
	 * @param array $args The arguments for text tags.
	 */
	public function print_text_tag( $args ) {
		$options       = $this->get_options();
		$name          = $args['option_key'];
		$value         = isset( $options[ $name ] ) ? $options[ $name ] : '';
		$optional_args = isset( $args['optional_args'] ) ? $args['optional_args'] : array();
		$id            = esc_attr( $name );
		$name          = self::OPTIONS_KEY . "[$id]";
		$value         = esc_attr( $value );
		$accepted_args = array( 'placeholder' );

		if ( isset( $args['help_text'] ) ) {
			echo '<div class="parsely-form-controls" data-has-help-text="true">';
		}
		if ( isset( $args['requires_recrawl'] ) && true === $args['requires_recrawl'] ) {
			echo '<div class="parsely-form-controls" data-requires-recrawl="true">';
		}

		echo sprintf( "<input type='text' name='%s' id='%s' value='%s'", esc_attr( $name ), esc_attr( $id ), esc_attr( $value ) );
		foreach ( $optional_args as $key => $val ) {
			if ( in_array( $key, $accepted_args, true ) ) {
				echo ' ' . esc_attr( $key ) . '="' . esc_attr( $val ) . '"';
			}
		}
		echo ' />';

		if ( isset( $args['help_text'] ) ) {
			if ( isset( $args['help_link'] ) ) {
				echo ' <div class="help-text" id="' .
					esc_attr( $args['option_key'] ) .
					'_help_text"><p class="description">' .
					sprintf( esc_html( $args['help_text'] ), '<a href="', esc_url( $args['help_link'] ), '">', '</a>' ) .
					'</p>' .
					'</div>';
			} else {
				echo ' <div class="help-text" id="' .
					esc_attr( $args['option_key'] ) .
					'_help_text"><p class="description">' .
					esc_html( $args['help_text'] ) . '</p>' .
					'</div>';
			}
		}
	}

	/**
	 * Returns default logo if one can be found
	 */
	private function get_logo_default() {
		$custom_logo_id = get_theme_mod( 'custom_logo' );
		if ( $custom_logo_id ) {
			$logo_attrs = wp_get_attachment_image_src( $custom_logo_id, 'full' );
			if ( $logo_attrs ) {
				return $logo_attrs[0];
			}
		}

		// get_site_icon_url returns an empty string if one isn't found,
		// which is what we want to use as the default anyway.
		return get_site_icon_url();
	}

	/**
	 * Extracts a host ( not TLD ) from a URL
	 *
	 * @param string $url The url of the host.
	 * @return string $url The host of the url…
	 */
	private function get_host_from_url( $url ) {
		if ( preg_match( '/^https?:\/\/( [^\/]+ )\/.*$/', $url, $matches ) ) {
			return $matches[1];
		}

		return $url;
	}

	/**
	 * Returns the tags associated with this page or post
	 *
	 * @param string $post_id The id of the post you're trying to get tags for.
	 * @return array $tags The tags of the post represented by the post id.
	 */
	private function get_tags( $post_id ) {
		$tags    = array();
		$wp_tags = wp_get_post_tags( $post_id );
		foreach ( $wp_tags as $wp_tag ) {
			array_push( $tags, $wp_tag->name );
		}

		return $tags;
	}

	/**
	 * Returns an array of all the child categories for the current post
	 *
	 * @param string $post_id The id of the post you're trying to get categories for.
	 * @param string $delimiter What character will delimit the categories.
	 * @return array $tags all the child categories of the current post.
	 */
	private function get_categories( $post_id, $delimiter = '/' ) {
		$tags       = array();
		$categories = get_the_category( $post_id );
		foreach ( $categories as $category ) {
			$hierarchy = get_category_parents( $category, false, $delimiter );
			$hierarchy = rtrim( $hierarchy, '/' );
			array_push( $tags, $hierarchy );
		}
		// take last element in the hierarchy, a string representing the full parent->child tree,
		// and split it into individual category names.
		$tags = explode( '/', end( $tags ) );
		// remove uncategorized value from tags.
		$tags = array_diff( $tags, array( 'Uncategorized' ) );
		return $tags;
	}

	/**
	 * Safely returns options for the plugin by assigning defaults contained in optionDefaults.  As soon as actual
	 * options are saved, they override the defaults. This prevents us from having to do a lot of isset() checking
	 * on variables.
	 *
	 * @return array
	 */
	private function get_options() {
		$options = get_option( self::OPTIONS_KEY, $this->option_defaults );
		return array_merge( $this->option_defaults, $options );
	}

	/**
	 * Returns a properly cleaned category/taxonomy value and will optionally use the top-level category/taxonomy value
	 * if so instructed via the `use_top_level_cats` option.
	 *
	 * @param WP_Post $post_obj The object for the post.
	 * @param array   $parsely_options The parsely options.
	 * @return string $category Cleaned category name for for post in question.
	 */
	private function get_category_name( $post_obj, $parsely_options ) {
		$taxonomy_dropdown_choice = get_the_terms( $post_obj->ID, $parsely_options['custom_taxonomy_section'] );
		// Get top-level taxonomy name for chosen taxonomy and assign to $parent_name; it will be used
		// as the category value if 'use_top_level_cats' option is checked.
		// Assign as "Uncategorized" if no value is checked for the chosen taxonomy.
		$category = 'Uncategorized';
		if ( ! empty( $taxonomy_dropdown_choice ) ) {
			if ( $parsely_options['use_top_level_cats'] ) {
				$first_term = array_shift( $taxonomy_dropdown_choice );
				$term_name  = $this->get_top_level_term( $first_term->term_id, $first_term->taxonomy );
			} else {
				$term_name = $this->get_bottom_level_term( $post_obj->ID, $parsely_options['custom_taxonomy_section'] );
			}

			if ( $term_name ) {
				$category = $term_name;
			}
		}

		/**
		 * Filters the constructed category name that are used as metadata keywords.
		 *
		 * @since 1.8.0
		 *
		 * @param string  $category        Category name.
		 * @param WP_Post $post_obj        Post object.
		 * @param array   $parsely_options The Parsely options.
		 */
		$category = apply_filters( 'wp_parsely_post_category', $category, $post_obj, $parsely_options );
		$category = $this->get_clean_parsely_page_value( $category );
		return $category;
	}

	/**
	 * Return the top-most category/taxonomy value in a hierarcy given a taxonomy value's ID
	 * ( WordPress calls taxonomy values 'terms' ).
	 *
	 * @param string $term_id The id of the top level term.
	 * @param string $taxonomy_name The name of the taxonomy.
	 * @return string $parent The top level name of the category / taxonomy.
	 */
	private function get_top_level_term( $term_id, $taxonomy_name ) {
		$parent = get_term_by( 'id', $term_id, $taxonomy_name );
		while ( false !== $parent && 0 !== $parent->parent ) {
			$parent = get_term_by( 'id', $parent->parent, $taxonomy_name );
		}
		return $parent ? $parent->name : false;
	}

	/**
	 * Return the bottom-most category/taxonomy value in a hierarcy given a post ID
	 * ( WordPress calls taxonomy values 'terms' ).
	 *
	 * @param string $post_id The post id you're interested in.
	 * @param string $taxonomy_name The name of the taxonomy.
	 * @return string name of the custom taxonomy.
	 */
	private function get_bottom_level_term( $post_id, $taxonomy_name ) {
		$terms    = get_the_terms( $post_id, $taxonomy_name );
		$term_ids = is_array( $terms ) ? wp_list_pluck( $terms, 'term_id' ) : null;
		$parents  = is_array( $terms ) ? array_filter( wp_list_pluck( $terms, 'parent' ) ) : null;

		// Get array of IDs of terms which are not parents.
		$term_ids_not_parents = array_diff( $term_ids, $parents );
		// Get corresponding term objects, which are mapped to array index keys.
		$terms_not_parents = array_intersect_key( $terms, $term_ids_not_parents );
		// remove array index keys.
		$terms_not_parents_cleaned = array();
		foreach ( $terms_not_parents as $index => $value ) {
			array_push( $terms_not_parents_cleaned, $value );
		}
		// if you assign multiple child terms in a custom taxonomy, will only return the first.
		return $terms_not_parents_cleaned[0]->name;
	}

	/**
	 * Get all term values from custom taxonomies.
	 *
	 * @param WP_Post $post_obj The post object.
	 * @param array   $parsely_options The pparsely options.
	 */
	private function get_custom_taxonomy_values( $post_obj, $parsely_options ) {
		// filter out default WordPress taxonomies.
		$all_taxonomies = array_diff( get_taxonomies(), array( 'post_tag', 'nav_menu', 'author', 'link_category', 'post_format' ) );
		$all_values     = array();

		if ( is_array( $all_taxonomies ) ) {
			foreach ( $all_taxonomies as $taxonomy ) {
				$custom_taxonomy_objects = get_the_terms( $post_obj->ID, $taxonomy );
				if ( is_array( $custom_taxonomy_objects ) ) {
					foreach ( $custom_taxonomy_objects as $custom_taxonomy_object ) {
						array_push( $all_values, $custom_taxonomy_object->name );
					}
				}
			}
		}
		return $all_values;
	}

	/**
	 * Returns a list of coauthors for a post assuming the coauthors plugin is
	 * installed. Borrowed from
	 * https://github.com/Automattic/Co-Authors-Plus/blob/master/template-tags.php#L3-35
	 *
	 * @param string $post_id The id of the post.
	 */
	private function get_coauthor_names( $post_id ) {
		$coauthors = array();
		if ( class_exists( 'coauthors_plus' ) ) {
			global $post, $post_ID, $coauthors_plus, $wpdb;

			$post_id = (int) $post_id;
			if ( ! $post_id && $post_ID ) {
				$post_id = $post_ID;
			}

			if ( ! $post_id && $post ) {
				$post_id = $post->ID;
			}

			if ( $post_id ) {
				$coauthor_terms = get_the_terms( $post_id, $coauthors_plus->coauthor_taxonomy );

				if ( is_array( $coauthor_terms ) && ! empty( $coauthor_terms ) ) {
					foreach ( $coauthor_terms as $coauthor ) {
						$coauthor_slug = preg_replace( '#^cap\-#', '', $coauthor->slug );
						$post_author   = $coauthors_plus->get_coauthor_by( 'user_nicename', $coauthor_slug );
						// In case the user has been deleted while plugin was deactivated.
						if ( ! empty( $post_author ) ) {
							$coauthors[] = $post_author;
						}
					}
				} elseif ( ! $coauthors_plus->force_guest_authors ) {
					if ( $post && $post_id === $post->ID ) {
						$post_author = get_userdata( $post->post_author );
					}
					if ( ! empty( $post_author ) ) {
						$coauthors[] = $post_author;
					}
				} // the empty else case is because if we force guest authors, we don't ever care what value wp_posts.post_author has.
			}
		}
		return $coauthors;
	}

	/**
	 * Determine author name from display name, falling back to firstname
	 * lastname, then nickname and finally the nicename.
	 *
	 * @param WP_User $author The author of the post.
	 */
	private function get_author_name( $author ) {
		// gracefully handle situation where no author is available.
		if ( empty( $author ) || ! is_object( $author ) ) {
			return '';
		}
		$author_name = $author->display_name;
		if ( ! empty( $author_name ) ) {
			return $author_name;
		}

		$author_name = $author->user_firstname . ' ' . $author->user_lastname;
		if ( ' ' !== $author_name ) {
			return $author_name;
		}

		$author_name = $author->nickname;
		if ( ! empty( $author_name ) ) {
			return $author_name;
		}

		return $author->user_nicename;
	}

	/**
	 * Retrieve all the authors for a post as an array. Can include multiple
	 * authors if coauthors plugin is in use.
	 *
	 * @param WP_Post $post The post object.
	 * @return array
	 */
	private function get_author_names( $post ) {
		$authors = $this->get_coauthor_names( $post->ID );
		if ( empty( $authors ) ) {
			$authors = array( get_user_by( 'id', $post->post_author ) );
		}

		/**
		 * Filters the list of author WP_User objects for a post.
		 *
		 * @since 1.14.0
		 *
		 * @param array   $authors One or more authors as WP_User objects (may also be `false`).
		 * @param WP_Post $post    Post object.
		 */
		$authors = apply_filters( 'wp_parsely_pre_authors', $authors, $post );

		$authors = array_map( array( $this, 'get_author_name' ), $authors );

		/**
		 * Filters the list of author names for a post.
		 *
		 * @since 1.14.0
		 *
		 * @param string[] $authors One or more author names.
		 * @param WP_Post  $post    Post object.
		 */
		$authors = apply_filters( 'wp_parsely_post_authors', $authors, $post );
		$authors = array_map( array( $this, 'get_clean_parsely_page_value' ), $authors );
		return $authors;
	}

	/**
	 * Sanitize content
	 *
	 * @since 2.6.0
	 *
	 * @param string $val The content you'd like sanitized.
	 * @return string
	 */
	public function get_clean_parsely_page_value( $val ) {
		if ( is_string( $val ) ) {
			$val = str_replace( "\n", '', $val );
			$val = str_replace( "\r", '', $val );
			$val = wp_strip_all_tags( $val );
			$val = trim( $val );
			return $val;
		}

		return $val;
	}


	/**
	 * Get the URL of the plugin settings page
	 */
	public static function get_settings_url() {
		return admin_url( 'options-general.php?page=' . self::MENU_SLUG );
	}


	/**
	 * Get the URL of the current PHP script.
	 * A fall-back implementation to determine permalink
	 *
	 * @param string $parsely_type Optional. Parse.ly post type you're interested in, either 'post' or 'nonpost'. Default is 'nonpost'.
	 * @param int    $post_id      Optional. ID of the post you want to get the URL for. Default is 0, which means the global `$post` is used.
	 * @return string|void
	 */
	public function get_current_url( $parsely_type = 'nonpost', $post_id = 0 ) {
		if ( 'post' === $parsely_type ) {
			$permalink = get_permalink( $post_id );

			/**
			 * Filters the permalink for a post.
			 *
			 * @since 1.14.0
			 * @since 2.5.0  Added $post_id.
			 *
			 * @param string $permalink         The permalink URL or false if post does not exist.
			 * @param string $parsely_type      Parse.ly type ("post" or "nonpost").
			 * @param int    $post_id           ID of the post you want to get the URL for. May be 0, so $permalink will be
			 *                                  for the global $post.
			 */
			$url = apply_filters( 'wp_parsely_permalink', $permalink, $parsely_type, $post_id );
		} else {
			$request_uri = isset( $_SERVER['REQUEST_URI'] )
					? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
					: '';

			$url = home_url( $request_uri );
		}

		$options = $this->get_options();
		return $options['force_https_canonicals']
				? str_replace( 'http://', 'https://', $url )
				: str_replace( 'https://', 'http://', $url );
	}

	/**
	 * Get the first image from a post
	 * https://css-tricks.com/snippets/wordpress/get-the-first-image-from-a-post/
	 *
	 * @param WP_Post $post The post object you're interested in.
	 * @return mixed|string
	 */
	public function get_first_image( $post ) {
		ob_start();
		ob_end_clean();
		if ( preg_match_all( '/<img.+src=[\'"]( [^\'"]+ )[\'"].*>/i', $post->post_content, $matches ) ) {
			return $matches[1][0];
		}
		return '';
	}

	/**
	 * Check to see if parsely user is logged in
	 */
	public function parsely_is_user_logged_in() {
		// can't use $blog_id here because it futzes with the global $blog_id.
		$current_blog_id = get_current_blog_id();
		$current_user_id = get_current_user_id();
		return is_user_member_of_blog( $current_user_id, $current_blog_id );
	}

	/**
	 * Convert JSON-LD type to respective Parse.ly page type.
	 *
	 * If the JSON-LD type is one of the types Parse.ly supports as a "post", then "post" will be returned.
	 * Otherwise, for "non-posts" and unknown types, "index" is returned.
	 *
	 * @since 2.5.0
	 *
	 * @see https://www.parse.ly/help/integration/metatags#field-description
	 *
	 * @param string $type JSON-LD type.
	 * @return string "post" or "index".
	 */
	public function convert_jsonld_to_parsely_type( $type ) {
		return in_array( $type, $this->supported_jsonld_post_types ) ? 'post' : 'index';
	}

	/**
	 * Determine if an API key is saved in the options.
	 *
	 * @since 2.6.0
	 *
	 * @return bool True is API key is set, false if it is missing.
	 */
	public function api_key_is_set() {
		$options = $this->get_options();

		return (
				isset( $options['apikey'] ) &&
				is_string( $options['apikey'] ) &&
				'' !== $options['apikey']
		);
	}

	/**
	 * Determine if an API key is not saved in the options.
	 *
	 * @since 2.6.0
	 *
	 * @return bool True if API key is missing, false if it is set.
	 */
	public function api_key_is_missing() {
		return ! $this->api_key_is_set();
	}

	/**
	 * Get the API key if set.
	 *
	 * @since 2.6.0
	 *
	 * @return string API key if set, or empty string if not.
	 */
	public function get_api_key() {
		$options = $this->get_options();

		return $this->api_key_is_set() ? $options['apikey'] : '';
	}
}
