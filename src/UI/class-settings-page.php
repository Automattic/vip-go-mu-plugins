<?php
/**
 * Parsely settings page class
 *
 * @package Parsely
 * @since 3.0.0
 */

declare(strict_types=1);

namespace Parsely\UI;

use Parsely\Parsely;

use WP_Screen;
use const Parsely\PARSELY_FILE;

/**
 * Render the wp-admin Parse.ly plugin settings page
 *
 * @since 3.0.0
 */
final class Settings_Page {
	/**
	 * Instance of Parsely class.
	 *
	 * @var Parsely
	 */
	private $parsely;

	/**
	 * Admin page name used for hook suffixes.
	 *
	 * @since 3.2.0
	 *
	 * @var string
	 */
	private $hook_suffix;

	/**
	 * Screen options name.
	 *
	 * Name must end in `_page` so that set-screen-option hook is triggered for WP < 5.4.2.
	 *
	 * @since 3.2.0
	 *
	 * @var string
	 */
	private $screen_options_name = 'wp_parsely_page';

	/**
	 * Constructor.
	 *
	 * @param Parsely $parsely Instance of Parsely class.
	 */
	public function __construct( Parsely $parsely ) {
		$this->parsely = $parsely;
	}

	/**
	 * Register settings page.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function run(): void {
		add_action( 'admin_menu', array( $this, 'add_settings_sub_menu' ) );
		add_action( 'admin_init', array( $this, 'initialize_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_settings_assets' ) );

		// Handle saving of screen options.
		add_filter( 'set-screen-option', array( $this, 'set_screen_option' ), 11, 3 );
		// Render screen options.
		add_filter( 'screen_settings', array( $this, 'screen_settings' ), 10, 2 );
	}

	/**
	 * Enqueue all needed scripts and styles for Parse.ly plugin settings page.
	 *
	 * @param string $hook_suffix The current page being loaded.
	 * @return void
	 */
	public function enqueue_settings_assets( string $hook_suffix ): void {
		if ( 'settings_page_parsely' === $hook_suffix ) {
			wp_enqueue_media();

			$admin_settings_asset = require plugin_dir_path( PARSELY_FILE ) . 'build/admin-settings.asset.php';
			$built_assets_url     = plugin_dir_url( PARSELY_FILE ) . '/build/';

			wp_enqueue_script(
				'parsely-admin-settings',
				$built_assets_url . 'admin-settings.js',
				$admin_settings_asset['dependencies'],
				$admin_settings_asset['version'],
				true
			);

			wp_enqueue_style(
				'parsely-admin-settings',
				$built_assets_url . 'admin-settings.css',
				$admin_settings_asset['dependencies'],
				$admin_settings_asset['version']
			);
		}
	}

	/**
	 * Parse.ly settings page in WordPress settings menu.
	 *
	 * @return void
	 */
	public function add_settings_sub_menu(): void {
		$suffix = add_options_page(
			__( 'Parse.ly Settings', 'wp-parsely' ),
			__( 'Parse.ly', 'wp-parsely' ),
			Parsely::CAPABILITY,
			Parsely::MENU_SLUG,
			array( $this, 'display_settings' )
		);

		if ( is_string( $suffix ) ) {
			$this->hook_suffix = $suffix;

			// Adds help text when admin page loads.
			add_action( 'load-' . $this->hook_suffix, array( $this, 'add_help_text' ) );
			// Adds screen options when admin page loads.
			add_action( 'load-' . $this->hook_suffix, array( $this, 'add_screen_options' ) );
		}
	}

	/**
	 * Save the screen option setting.
	 *
	 * Nonce is already checked in set_screen_options() - no need to check here.
	 *
	 * @since 3.2.0
	 *
	 * @param mixed  $screen_option The value to save instead of the option value. Default false (to skip saving the current option).
	 * @param string $option        The option name.
	 * @param mixed  $value         The option value.
	 * @return mixed Updated option value.
	 */
	public function set_screen_option( $screen_option, string $option, $value ) {
		if ( $this->screen_options_name === $option ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( isset( $_POST[ $this->screen_options_name ] ) && is_array( $_POST[ $this->screen_options_name ] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$unslashed = wp_unslash( $_POST[ $this->screen_options_name ] );
				$data      = array_map( 'sanitize_text_field', $unslashed );
			}
			$value = $data ?? array();
		}
		return $value;
	}

	/**
	 * Register screen options.
	 *
	 * @since 3.2.0
	 *
	 * @return void
	 */
	public function add_screen_options(): void {
		add_screen_option(
			'wp_parsely_screen_options_requires_recrawl',
			array(
				'label'  => __( 'Requires Recrawl Settings', 'wp-parsely' ),
				'option' => 'requires-recrawl',
			)
		);
		add_screen_option(
			'wp_parsely_screen_options_advanced',
			array(
				'label'  => __( 'Advanced Settings', 'wp-parsely' ),
				'option' => 'advanced',
			)
		);
	}

	/**
	 * Render the screen options block.
	 *
	 * @since 3.2.0
	 *
	 * @param string    $screen_settings Screen settings.
	 * @param WP_Screen $screen          WP_Screen object.
	 *
	 * @return string The filtered screen settings.
	 */
	public function screen_settings( string $screen_settings, WP_Screen $screen ): string {
		if ( $this->hook_suffix !== $screen->base ) {
			return $screen_settings;
		}

		$current_screen = get_current_screen();
		if ( null === $current_screen ) {
			return $screen_settings;
		}

		$user_meta = get_user_meta( get_current_user_id(), $this->screen_options_name, true );

		ob_start();
		?>
		<fieldset>
		<legend><?php esc_html_e( 'Show on screen', 'wp-parsely' ); ?></legend>
		<input type="hidden" name="wp_screen_options[option]" value="<?php echo esc_attr( $this->screen_options_name ); ?>" />
		<input type="hidden" name="wp_screen_options[value]" value="yes" />
		<?php
		foreach ( $current_screen->get_options() as $option ) {
			$checked = isset( $user_meta[ $option['option'] ] );
			$name    = $this->screen_options_name . '[' . $option['option'] . ']';
			?>
			<label><input class="hide-section-tog" name="<?php echo esc_attr( $name ); ?>" type="checkbox" id="<?php echo esc_attr( $option['option'] ); ?>" value="true"<?php checked( $checked ); ?>><?php echo esc_html( $option['label'] ); ?></label>
			<?php
		}
		?>
		</fieldset>
		<?php
		submit_button( __( 'Apply', 'wp-parsely' ), 'primary', 'screen-options-apply' );

		return ob_get_clean();
	}

	/**
	 * Add help tab to the settings page.
	 *
	 * @since 3.1.0
	 */
	public function add_help_text(): void {
		$screen = get_current_screen();
		$screen->add_help_tab(
			array(
				'id'      => 'overview',
				'title'   => __( 'Overview', 'wp-parsely' ),
				'content' => '<p>' . __( 'The only required setting on this page is the Site ID. All of the other settings are optional.', 'wp-parsely' ) . '</p>' .
					'<p>' . __( 'You must click the Save Changes button at the bottom of the screen for new settings to take effect.', 'wp-parsely' ) . '</p>' .
					'<p>' . __( 'This plugin does not currently support dynamic tracking (the tracking of multiple pageviews on a single page). Some common use-cases for dynamic tracking are slideshows or articles loaded via AJAX calls in single-page applications — situations in which new content is loaded without a full page refresh. Tracking these events requires manually implementing additional JavaScript above <a href="https://www.parsely.com/help/integration/basic/">the standard Parse.ly include</a> that the plugin injects into your page source. Please consult <a href="https://www.parsely.com/help/integration/dynamic/">the Parse.ly documentation on dynamic tracking</a> for instructions on implementing dynamic tracking, or contact Parse.ly support (<a href="mailto:support@parsely.com">support@parsely.com</a>) for additional assistance.', 'wp-parsely' ) . '</p>',
			)
		);
		$screen->add_help_tab(
			array(
				'id'      => 'requires_recrawl',
				'title'   => __( 'Requires Recrawl', 'wp-parsely' ),
				'content' => '<p>' . __(
					'Important: changing any of the values in the Requires Recrawl section on a site currently tracked with Parse.ly will require reprocessing of your Parse.ly data.
Once you have changed a value and saved, please contact support@parsely.com to request a recrawl.',
					'wp-parsely'
				) . '</p>' .
					'<p>' . __(
						'If you can\'t see these settings, you will need to enable the Requires Recrawl Settings in the Screen Options.',
						'wp-parsely'
					) . '</p>',
			)
		);
	}

	/**
	 * Parse.ly settings screen ( options-general.php?page=[MENU_SLUG] ).
	 *
	 * @return void
	 */
	public function display_settings(): void {
		if ( ! current_user_can( Parsely::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-parsely' ) );
		}

		include plugin_dir_path( PARSELY_FILE ) . 'views/parsely-settings.php';
	}

	/**
	 * Initialize the settings for Parsely.
	 *
	 * @return void
	 */
	public function initialize_settings(): void {
		// All our options are actually stored in one single array to reduce DB queries.
		register_setting(
			Parsely::OPTIONS_KEY,
			Parsely::OPTIONS_KEY,
			array( $this, 'validate_options' )
		);

		$user_meta = array_keys( (array) get_user_meta( get_current_user_id(), $this->screen_options_name, true ) );

		$this->initialize_basic_section();

		if ( in_array( 'requires-recrawl', $user_meta, true ) ) {
			$this->initialize_requires_recrawl_section();
		}

		if ( in_array( 'advanced', $user_meta, true ) ) {
			$this->initialize_advanced_section();
		}
	}

	/**
	 * Register section and settings for Basic section.
	 *
	 * @since 3.2.0
	 *
	 * @return void
	 */
	private function initialize_basic_section(): void {
		add_settings_section(
			'basic_settings',
			__( 'Basic Settings', 'wp-parsely' ),
			'__return_null',
			Parsely::MENU_SLUG
		);

		// Get the API Key.
		$h          = __( 'Your Site ID is your own site domain (e.g. `mydomain.com`).', 'wp-parsely' );
		$field_id   = 'apikey';
		$field_args = array(
			'option_key'    => $field_id,
			'help_text'     => $h,
			'label_for'     => $field_id,
			'optional_args' => array(
				'required' => 'required',
			),

		);
		add_settings_field(
			$field_id,
			__( 'Parse.ly Site ID <em>(required)</em>', 'wp-parsely' ),
			array( $this, 'print_text_tag' ),
			Parsely::MENU_SLUG,
			'basic_settings',
			$field_args
		);

		/* translators: 1: Opening anchor tag markup, 2: Documentation URL, 3: Opening anchor tag markup continued, 4: Closing anchor tag */
		$h          = __( 'Your API secret is your secret code to <a href="https://www.parse.ly/help/api/analytics/">access our API</a>. It can be found at <code>dash.parsely.com/<var>yoursitedomain</var>/settings/api</code> (replace <var>yoursitedomain</var> with your domain name, e.g. <samp>mydomain.com</samp>).<br />If you haven\'t purchased access to the API and would like to do so, email your account manager or <a href="mailto:support@parsely.com">support@parsely.com</a>.', 'wp-parsely' );
		$field_id   = 'api_secret';
		$field_args = array(
			'option_key' => $field_id,
			'help_text'  => $h,
			'label_for'  => $field_id,
		);
		add_settings_field(
			$field_id,
			__( 'Parse.ly API Secret', 'wp-parsely' ),
			array( $this, 'print_text_tag' ),
			Parsely::MENU_SLUG,
			'basic_settings',
			$field_args
		);

		$h          = __( 'Your metadata secret is given to you by Parse.ly support. DO NOT enter anything here unless given to you by Parse.ly support!', 'wp-parsely' );
		$field_id   = 'metadata_secret';
		$field_args = array(
			'option_key' => $field_id,
			'help_text'  => $h,
			'label_for'  => $field_id,
		);
		add_settings_field(
			$field_id,
			__( 'Parse.ly Metadata Secret', 'wp-parsely' ),
			array( $this, 'print_text_tag' ),
			Parsely::MENU_SLUG,
			'basic_settings',
			$field_args
		);

		// Logo.
		$field_help = __( 'Here you can specify your logo\'s URL by using the "Browse" button or typing the URL manually.', 'wp-parsely' );
		$field_id   = 'logo';
		add_settings_field(
			$field_id,
			__( 'Logo', 'wp-parsely' ),
			array( $this, 'print_media_single_image' ),
			Parsely::MENU_SLUG,
			'basic_settings',
			array(
				'title'      => __( 'Logo', 'wp-parsely' ), // Passed for legend element.
				'option_key' => $field_id,
				'label_for'  => $field_id,
				'help_text'  => $field_help,
			)
		);

		// Track logged-in users.
		$h = __( 'By default, the plugin will track the activity of users that are logged into this site. You can change this setting to only track the activity of anonymous visitors. Note: You will no longer see the Parse.ly tracking code on your site if you browse while logged in.', 'wp-parsely' );
		add_settings_field(
			'track_authenticated_users',
			__( 'Track Logged-in Users', 'wp-parsely' ),
			array( $this, 'print_binary_radio_tag' ),
			Parsely::MENU_SLUG,
			'basic_settings',
			array(
				'title'      => __( 'Track Logged-in Users', 'wp-parsely' ), // Passed for legend element.
				'option_key' => 'track_authenticated_users',
				'help_text'  => $h,
			)
		);

		// Disable JavaScript.
		$h = __( 'If you use a separate system for JavaScript tracking (Tealium / Segment / Google Tag Manager / other tag manager solution) you may want to use that instead of having the plugin load the tracker. <span style="color:#d63638">WARNING:</span> disabling this option will also disable the "Personalize Results" section of the recommended widget! We highly recommend leaving this option set to "No".', 'wp-parsely' );
		add_settings_field(
			'disable_javascript',
			__( 'Disable JavaScript', 'wp-parsely' ),
			array( $this, 'print_binary_radio_tag' ),
			Parsely::MENU_SLUG,
			'basic_settings',
			array(
				'title'      => __( 'Disable JavaScript', 'wp-parsely' ), // Passed for legend element.
				'option_key' => 'disable_javascript',
				'help_text'  => $h,
			)
		);

		if ( defined( 'AMP__VERSION' ) ) {
			// Disable AMP tracking.
			$h = __( 'If you use a separate system for JavaScript tracking on AMP pages (Tealium / Segment / Google Tag Manager / other tag manager solution) you may want to use that instead of having the plugin load the tracker.', 'wp-parsely' );
			add_settings_field(
				'disable_amp',
				__( 'Disable AMP Tracking', 'wp-parsely' ),
				array( $this, 'print_binary_radio_tag' ),
				Parsely::MENU_SLUG,
				'basic_settings',
				array(
					'title'      => __( 'Disable AMP Tracking', 'wp-parsely' ), // Passed for legend element.
					'option_key' => 'disable_amp',
					'help_text'  => $h,
				)
			);
		}
	}

	/**
	 * Register section and settings for Requires Recrawl section.
	 *
	 * @since 3.2.0
	 *
	 * @return void
	 */
	private function initialize_requires_recrawl_section(): void {
		add_settings_section(
			'requires_recrawl_settings',
			__( 'Requires Recrawl Settings', 'wp-parsely' ),
			function (): void {
				echo '<strong>' . wp_kses_post( __( '<span style="color:#d63638">Important:</span> Changing any of these values below on a site currently tracked with Parse.ly will require reprocessing of your Parse.ly data.', 'wp-parsely' ) ) . '</strong><br />';
				printf(
					/* translators: Mailto link  */
					esc_html__( 'Once you have changed a value and and saved, please contact %s to request a recrawl.', 'wp-parsely' ),
					wp_kses_post( '<a href="mailto:support@parsely.com?subject=' . rawurlencode( 'Please reprocess ' . $this->parsely->get_api_key() ) . '">support@parsely.com</a>' )
				);
			},
			Parsely::MENU_SLUG
		);

		// Allow use of custom taxonomy to populate articleSection in parselyPage; defaults to category.
		$field_id   = 'track_post_types_as';
		$field_help = __( 'By default, Parse.ly only tracks posts and pages. If you want to track other post types, select how you want to track them here.', 'wp-parsely' );
		add_settings_field(
			$field_id,
			__( 'Track Post Types as', 'wp-parsely' ),
			array( $this, 'print_track_post_types_table' ),
			Parsely::MENU_SLUG,
			'requires_recrawl_settings',
			array(
				'title'      => __( 'Track Post Types as', 'wp-parsely' ),
				'option_key' => $field_id,
				'help_text'  => $field_help,
			)
		);

		// Metadata Format.
		/* translators: 1: Opening anchor tag markup, 2: Documentation URL, 3: Opening anchor tag markup continued, 4: Closing anchor tag */
		$h          = __( 'Choose the metadata format for our crawlers to access. Most publishers are fine with <a href="https://www.parse.ly/help/integration/jsonld/">JSON-LD</a>, but if you prefer to use our proprietary metadata format then you can do so here.', 'wp-parsely' );
		$field_id   = 'meta_type';
		$field_args = array(
			'option_key'     => $field_id,
			'help_text'      => $h,
			// filter WordPress taxonomies under the hood that should not appear in dropdown.
			'select_options' => array(
				'json_ld'        => 'json_ld',
				'repeated_metas' => 'repeated_metas',
			),
			'label_for'      => Parsely::OPTIONS_KEY . "[$field_id]",
		);
		add_settings_field(
			$field_id,
			__( 'Metadata Format', 'wp-parsely' ),
			array( $this, 'print_select_tag' ),
			Parsely::MENU_SLUG,
			'requires_recrawl_settings',
			$field_args
		);

		// Content ID Prefix.
		$h          = __( 'If you use more than one content management system (e.g. WordPress and Drupal), you may end up with duplicate content IDs. Adding a Content ID Prefix will ensure the content IDs from WordPress will not conflict with other content management systems. We recommend using "WP-" for your prefix.', 'wp-parsely' );
		$field_id   = 'content_id_prefix';
		$field_args = array(
			'option_key'    => $field_id,
			'optional_args' => array(
				'placeholder' => 'WP-',
			),
			'help_text'     => $h,
			'label_for'     => $field_id,
		);
		add_settings_field(
			$field_id,
			__( 'Content ID Prefix', 'wp-parsely' ),
			array( $this, 'print_text_tag' ),
			Parsely::MENU_SLUG,
			'requires_recrawl_settings',
			$field_args
		);

		// Use top-level categories.
		$h = __( 'The plugin will use the first category assigned to a post. With this option selected, if you post a story to News > National > Florida, the plugin will use the "News" for the section name in your dashboard instead of "Florida".', 'wp-parsely' );
		add_settings_field(
			'use_top_level_cats',
			__( 'Use Top-Level Categories for Section', 'wp-parsely' ),
			array( $this, 'print_binary_radio_tag' ),
			Parsely::MENU_SLUG,
			'requires_recrawl_settings',
			array(
				'title'      => __( 'Use Top-Level Categories for Section', 'wp-parsely' ), // Passed for legend element.
				'option_key' => 'use_top_level_cats',
				'help_text'  => $h,
			)
		);

		// Allow use of custom taxonomy to populate articleSection in parselyPage; defaults to category.
		$h          = __( 'By default, the section value in your Parse.ly dashboard maps to a post\'s category. You can optionally choose a custom taxonomy, if you\'ve created one, to populate the section value instead.', 'wp-parsely' );
		$field_id   = 'custom_taxonomy_section';
		$field_args = array(
			'option_key'     => $field_id,
			'help_text'      => $h,
			// filter WordPress taxonomies under the hood that should not appear in dropdown.
			'select_options' => array_diff(
				get_taxonomies(),
				array(
					'post_tag',
					'nav_menu',
					'author',
					'link_category',
					'post_format',
				)
			),
			'label_for'      => Parsely::OPTIONS_KEY . "[$field_id]",
		);
		add_settings_field(
			$field_id,
			__( 'Use Custom Taxonomy for Section', 'wp-parsely' ),
			array( $this, 'print_select_tag' ),
			Parsely::MENU_SLUG,
			'requires_recrawl_settings',
			$field_args
		);

		// Use categories and custom taxonomies as tags.
		$h = __( 'You can use this option to add all assigned categories and taxonomies to your tags. For example, if you had a post assigned to the categories: "Business/Tech", "Business/Social", your tags would include "Business/Tech" and "Business/Social" in addition to your other tags.', 'wp-parsely' );
		add_settings_field(
			'cats_as_tags',
			__( 'Add Categories to Tags', 'wp-parsely' ),
			array( $this, 'print_binary_radio_tag' ),
			Parsely::MENU_SLUG,
			'requires_recrawl_settings',
			array(
				'title'      => __( 'Add Categories to Tags', 'wp-parsely' ), // Passed for legend element.
				'option_key' => 'cats_as_tags',
				'help_text'  => $h,
			)
		);

		// Lowercase all tags.
		$h = __( 'By default, the plugin will use lowercase versions of your tags to correct for potential misspellings. You can change this setting to ensure that tag names are used verbatim.', 'wp-parsely' );
		add_settings_field(
			'lowercase_tags',
			__( 'Lowercase All Tags', 'wp-parsely' ),
			array( $this, 'print_binary_radio_tag' ),
			Parsely::MENU_SLUG,
			'requires_recrawl_settings',
			array(
				'title'      => __( 'Lowercase All Tags', 'wp-parsely' ), // Passed for legend element.
				'option_key' => 'lowercase_tags',
				'help_text'  => $h,
			)
		);

		$h = __( 'The plugin uses <code>http</code> canonical URLs by default. If this needs to be forced to use <code>https</code>, set this option to true. Note: the default is fine for almost all publishers, it\'s unlikely you\'ll have to change this unless directed to do so by a Parse.ly support rep.', 'wp-parsely' );
		add_settings_field(
			'force_https_canonicals',
			__( 'Force HTTPS Canonicals', 'wp-parsely' ),
			array( $this, 'print_binary_radio_tag' ),
			Parsely::MENU_SLUG,
			'requires_recrawl_settings',
			array(
				'title'      => __( 'Force HTTPS Canonicals', 'wp-parsely' ), // Passed for legend element.
				'option_key' => 'force_https_canonicals',
				'help_text'  => $h,
			)
		);
	}

	/**
	 * Register section and settings for Advanced section.
	 *
	 * @since 3.2.0
	 *
	 * @return void
	 */
	private function initialize_advanced_section(): void {
		// These are Advanced Settings.
		add_settings_section(
			'advanced_settings',
			__( 'Advanced Settings', 'wp-parsely' ),
			'__return_null',
			Parsely::MENU_SLUG
		);

		// Clear metadata.
		$h = __( 'Check this radio button and hit "Save Changes" to clear all metadata information for Parse.ly posts and re-send all metadata to Parse.ly.<br /><span style="color:#d63638">WARNING:</span> Do not do this unless explicitly instructed by Parse.ly Staff!', 'wp-parsely' );
		add_settings_field(
			'parsely_wipe_metadata_cache',
			__( 'Wipe Parse.ly Metadata Info', 'wp-parsely' ),
			array( $this, 'print_checkbox_tag' ),
			Parsely::MENU_SLUG,
			'advanced_settings',
			array(
				'option_key' => 'parsely_wipe_metadata_cache',
				'help_text'  => $h,
			)
		);
	}

	/**
	 * Print out the description text, if there is any.
	 *
	 * @since 3.1.0
	 *
	 * @param array $args The arguments for the form field. May contain 'help_text'.
	 */
	public function print_description_text( $args ) {
		echo isset( $args['help_text'] ) ? '<p class="description" id="' . esc_attr( $args['option_key'] ) . '-description">' . wp_kses_post( $args['help_text'] ) . '</p>' : '';
	}

	/**
	 * Print out an input text tag.
	 *
	 * @param array $args The arguments for text tag.
	 * @return void
	 */
	public function print_text_tag( array $args ): void {
		$options       = $this->parsely->get_options();
		$name          = $args['option_key'];
		$value         = $options[ $name ] ?? '';
		$optional_args = $args['optional_args'] ?? array();
		$id            = esc_attr( $name );
		$name          = Parsely::OPTIONS_KEY . "[$id]";
		$value         = esc_attr( $value );
		$accepted_args = array( 'placeholder', 'required' );

		echo sprintf( "<input type='text' name='%s' id='%s' value='%s'", esc_attr( $name ), esc_attr( $id ), esc_attr( $value ) );
		if ( $args['help_text'] ) {
			echo ' aria-describedby="' . esc_attr( $id ) . '-description"';
		}
		foreach ( $optional_args as $key => $val ) {
			if ( \in_array( $key, $accepted_args, true ) ) {
				echo ' ' . esc_attr( $key ) . '="' . esc_attr( $val ) . '"';
			}
		}
		echo ' />';

		$this->print_description_text( $args );
	}

	/**
	 * Prints a checkbox tag in the settings page.
	 *
	 * @param array $args Arguments to print to checkbox tag.
	 * @return void
	 */
	public function print_checkbox_tag( array $args ): void {
		$options = $this->parsely->get_options();
		$name    = $args['option_key'];
		$value   = $options[ $name ];
		$id      = esc_attr( $name );
		$name    = Parsely::OPTIONS_KEY . "[$id]";

		echo sprintf( "<input type='checkbox' name='%s' id='%s_true' value='true' ", esc_attr( $name ), esc_attr( $id ) );
		if ( $args['help_text'] ) {
			echo ' aria-describedby="' . esc_attr( $id ) . '-description"';
		}
		echo checked( true === $value, true, false );
		echo sprintf( " /> <label for='%s_true'>%s</label>", esc_attr( $id ), esc_html__( 'Yes', 'wp-parsely' ) );

		$this->print_description_text( $args );
	}

	/**
	 * Print out the select tags
	 *
	 * @param array $args The arguments for the select dropdowns.
	 * @return void
	 */
	public function print_select_tag( array $args ): void {
		$options        = $this->parsely->get_options();
		$name           = $args['option_key'];
		$select_options = $args['select_options'];
		$selected       = $options[ $name ] ?? null;
		$id             = esc_attr( $name );
		$name           = Parsely::OPTIONS_KEY . "[$id]";

		echo sprintf( "<select name='%s' id='%s'", esc_attr( $name ), esc_attr( $name ) );
		if ( $args['help_text'] ) {
			echo ' aria-describedby="' . esc_attr( $id ) . '-description"';
		}
		echo '>';

		foreach ( $select_options as $key => $val ) {
			echo '<option value="' . esc_attr( $key ) . '" ';
			echo selected( $selected, $key, false ) . '>';
			echo esc_html( $val );
			echo '</option>';
		}
		echo '</select>';

		$this->print_description_text( $args );
	}

	/**
	 * Print out the radio buttons
	 *
	 * @param array $args The arguments for the radio buttons.
	 * @return void
	 */
	public function print_binary_radio_tag( array $args ): void {
		$options = $this->parsely->get_options();
		$name    = $args['option_key'];
		$value   = $options[ $name ];
		$id      = esc_attr( $name );
		$name    = Parsely::OPTIONS_KEY . "[$id]";
		?>
		<fieldset>
			<legend class="screen-reader-text"><span><?php echo esc_html( $args['title'] ); ?></span></legend>
			<p>
				<label for="<?php echo esc_attr( "{$id}_true" ); ?>">
					<input type="radio" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( "{$id}_true" ); ?>" value="true"<?php checked( $value ); ?> /><?php echo esc_html__( 'Yes', 'wp-parsely' ); ?>
				</label>
				<br />
				<label for="<?php echo esc_attr( "{$id}_false" ); ?>">
					<input type="radio" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( "{$id}_false" ); ?>" value="false"<?php checked( $value, false ); ?> /><?php echo esc_html__( 'No', 'wp-parsely' ); ?>
				</label>
			</p>
		</fieldset>
		<?php
		$this->print_description_text( $args );
	}

	/**
	 * Prints out multiple selection in the form of checkboxes
	 *
	 * @param array $args The arguments for the checkboxes.
	 * @return void
	 */
	public function print_multiple_checkboxes( array $args ): void {
		$options        = $this->parsely->get_options();
		$select_options = $args['select_options'];
		$id             = esc_attr( $args['option_key'] );
		$name           = Parsely::OPTIONS_KEY . "[$id]";

		?>
		<fieldset>
			<legend class="screen-reader-text"><span><?php echo esc_html( $args['title'] ); ?></span></legend>
		<?php
		foreach ( $select_options as $key => $val ) {
			$selected = in_array( $val, $options[ $args['option_key'] ], true );
			printf(
				'<label for="%1$s-%2$s"><input type="checkbox" name="%1$s[]" id="%1$s-%2$s" value="%2$s" ',
				esc_attr( $name ),
				esc_attr( $key )
			);
			echo checked( $selected, true, false );
			echo sprintf( ' /> %s</label><br />', esc_attr( $val ) );
		}

		$this->print_description_text( $args );
	}

	/**
	 * Print out a "single-image browse control" which includes a text
	 * input to store image path and a button to browse for images.
	 *
	 * @param array $args The arguments for the control.
	 * @return void
	 */
	public function print_media_single_image( array $args ) {
		$key         = $args['option_key'];
		$input_value = $this->parsely->get_options()[ $key ];
		$input_name  = Parsely::OPTIONS_KEY . "[$key]";
		$button_text = __( 'Browse', 'wp-parsely' );
		?>

		<fieldset class="media-single-image" id="media-single-image-<?php echo esc_attr( $key ); ?>">
			<legend class="screen-reader-text"><span><?php echo esc_html( $args['title'] ); ?></span></legend>
			<input class="file-path" type="text" name="<?php echo esc_attr( $input_name ); ?>" value="<?php echo esc_attr( $input_value ); ?>" />
			<p>
				<button data-option="<?php echo esc_attr( $key ); ?>" class="browse button" type="button"><?php echo esc_html( $button_text ); ?></button>
			</p>
		</fieldset>

		<?php
		$this->print_description_text( $args );
	}

	/**
	 * Print out the post tracking options table.
	 *
	 * @since 3.2.0
	 *
	 * @param array<string, string> $args The arguments used in the output HTML elements.
	 * @return void
	 */
	public function print_track_post_types_table( array $args ): void {
		$option_key = esc_attr( $args['option_key'] );
		$post_types = get_post_types( array( 'public' => true ) );
		$values     = $this->get_tracking_values_for_display();
		?>
		<fieldset>
			<legend class="screen-reader-text"><span><?php echo esc_html( $args['title'] ); ?></span></legend>
			<table class="form-table widefat striped" id="track-post-types">
				<caption class="screen-reader-text"><?php echo esc_html( $args['title'] ); ?></caption>
				<thead>
					<tr>
						<th scope="col"><?php echo esc_html__( 'Post Type', 'wp-parsely' ); ?></th>
						<th id="track-post-types--post" scope="col"><?php echo esc_html__( 'Track as Post', 'wp-parsely' ); ?></th>
						<th id="track-post-types--page" scope="col"><?php echo esc_html__( 'Track as Page', 'wp-parsely' ); ?></th>
						<th id="track-post-types--none" scope="col"><?php echo esc_html__( 'Do not track', 'wp-parsely' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $post_types as $post_type ) {
						$group_name = "parsely[{$option_key}][{$post_type}]";
						$id_post    = "{$option_key}_{$post_type}_post";
						$id_page    = "{$option_key}_{$post_type}_page";
						$id_none    = "{$option_key}_{$post_type}_none";
						$value      = $values[ $post_type ] ?? 'none';
						?>
						<tr>
							<th scope="row"><?php echo esc_html( $post_type ); ?></th>
							<td>
								<label aria-labelledby="track-post-types--post" for="<?php echo esc_attr( $id_post ); ?>">
									<input id="<?php echo esc_attr( $id_post ); ?>" name="<?php echo esc_attr( $group_name ); ?>" type="radio" value="post" <?php checked( $value, 'post' ); ?> />
								</label>
							</td>
							<td>
								<label aria-labelledby="track-post-types--page" for="<?php echo esc_attr( $id_page ); ?>">
									<input id="<?php echo esc_attr( $id_page ); ?>" name="<?php echo esc_attr( $group_name ); ?>" type="radio" value="page" <?php checked( $value, 'page' ); ?> />
								</label>
							</td>
							<td>
								<label aria-labelledby="track-post-types--none" for="<?php echo esc_attr( $id_none ); ?>">
									<input id="<?php echo esc_attr( $id_none ); ?>" name="<?php echo esc_attr( $group_name ); ?>" type="radio" value="none" <?php checked( $value, 'none' ); ?> />
								</label>
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		</fieldset>
		<?php
		$this->print_description_text( $args );
	}

	/**
	 * Return the custom post type tracking values in a format that is easily
	 * consumable by the print_track_post_types_table() function.
	 *
	 * @since 3.2.0
	 *
	 * @return array<string, string> Key-value pairs with post type and their 'track as' value.
	 */
	public function get_tracking_values_for_display(): array {
		$options = $this->parsely->get_options();
		$types   = array( 'post', 'page' );
		$result  = array();

		foreach ( $types as $type ) {
			$array_key = "track_{$type}_types";
			if ( array_key_exists( $array_key, $options ) ) {
				foreach ( $options[ $array_key ] as $post_type ) {
					$result[ $post_type ] = $type;
				}
			}
		}

		return $result;
	}

	/**
	 * Validate the options provided by the user
	 *
	 * @param array $input Options from the settings page.
	 * @return array List of validated input settings.
	 */
	public function validate_options( array $input ): array {
		$options = $this->parsely->get_options();

		if ( empty( $input['apikey'] ) ) {
			add_settings_error(
				Parsely::OPTIONS_KEY,
				'apikey',
				__( 'Please specify the Site ID', 'wp-parsely' )
			);
		} else {
			$input['apikey'] = strtolower( $input['apikey'] );
			$input['apikey'] = sanitize_text_field( $input['apikey'] );
			if ( strpos( $input['apikey'], '.' ) === false || strpos( $input['apikey'], ' ' ) !== false ) {
				add_settings_error(
					Parsely::OPTIONS_KEY,
					'apikey',
					__( 'Your Parse.ly Site ID looks incorrect, it should look like "example.com".', 'wp-parsely' )
				);
			}
		}

		$input['api_secret'] = sanitize_text_field( $input['api_secret'] );

		if ( ! empty( $input['metadata_secret'] ) ) {
			if ( strlen( $input['metadata_secret'] ) !== 10 ) {
				add_settings_error(
					Parsely::OPTIONS_KEY,
					'metadata_secret',
					__( 'Metadata secret is incorrect. Please contact Parse.ly support!', 'wp-parsely' )
				);
			} elseif ( isset( $input['parsely_wipe_metadata_cache'] ) && 'true' === $input['parsely_wipe_metadata_cache'] ) {
				delete_post_meta_by_key( 'parsely_metadata_last_updated' );

				wp_schedule_event( time() + 100, 'everytenminutes', 'parsely_bulk_metas_update' );
				$input['parsely_wipe_metadata_cache'] = false;
			}
		}

		if ( empty( $input['logo'] ) ) {
			$input['logo'] = self::get_logo_default();
		}

		// Validate 'Track post type as'.
		$this->validate_options_post_type_tracking( $input );

		// Track authenticated users.
		if ( 'true' !== $input['track_authenticated_users'] && 'false' !== $input['track_authenticated_users'] ) {
			add_settings_error(
				Parsely::OPTIONS_KEY,
				'track_authenticated_users',
				__( 'Value passed for track_authenticated_users must be either "true" or "false".', 'wp-parsely' )
			);
		} else {
			$input['track_authenticated_users'] = 'true' === $input['track_authenticated_users'];
		}

		if ( 'true' !== $input['disable_javascript'] && 'false' !== $input['disable_javascript'] ) {
			add_settings_error(
				Parsely::OPTIONS_KEY,
				'disable_javascript',
				__( 'Value passed for disable_javascript must be either "true" or "false".', 'wp-parsely' )
			);
		} else {
			$input['disable_javascript'] = 'true' === $input['disable_javascript'];
		}

		// Allow for Disable AMP setting to be conditionally included on the page.
		// If it's not shown, then set the value as what was previously saved.
		if ( ! isset( $input['disable_amp'] ) ) {
			$input['disable_amp'] = 'true';
			if ( false === $options['disable_amp'] ) {
				$input['disable_amp'] = 'false';
			}
		}

		if ( 'true' !== $input['disable_amp'] && 'false' !== $input['disable_amp'] ) {
			add_settings_error(
				Parsely::OPTIONS_KEY,
				'disable_amp',
				__( 'Value passed for disable_amp must be either "true" or "false".', 'wp-parsely' )
			);
		} else {
			$input['disable_amp'] = 'true' === $input['disable_amp'];
		}

		$input['api_secret'] = sanitize_text_field( $input['api_secret'] );

		// Custom taxonomy as section.
		if ( ! isset( $input['meta_type'] ) ) {
			$input['meta_type'] = $options['meta_type'];
		} else {
			$input['meta_type'] = sanitize_text_field( $input['meta_type'] );
		}

		// Content ID prefix.
		if ( ! isset( $input['content_id_prefix'] ) ) {
			$input['content_id_prefix'] = $options['content_id_prefix'];
		} else {
			$input['content_id_prefix'] = sanitize_text_field( $input['content_id_prefix'] );
		}

		// Allow for Top-level categories setting to be conditionally included on the page.
		// If it's not shown, then set the value as what was previously saved.
		if ( ! isset( $input['use_top_level_cats'] ) ) {
			$input['use_top_level_cats'] = 'true';
			if ( false === $options['use_top_level_cats'] ) {
				$input['use_top_level_cats'] = 'false';
			}
		}

		// Top-level categories.
		if ( 'true' !== $input['use_top_level_cats'] && 'false' !== $input['use_top_level_cats'] ) {
			add_settings_error(
				Parsely::OPTIONS_KEY,
				'use_top_level_cats',
				__( 'Value passed for use_top_level_cats must be either "true" or "false".', 'wp-parsely' )
			);
		} else {
			$input['use_top_level_cats'] = 'true' === $input['use_top_level_cats'];
		}

		// Custom taxonomy as section.
		if ( ! isset( $input['custom_taxonomy_section'] ) ) {
			$input['custom_taxonomy_section'] = $options['custom_taxonomy_section'];
		} else {
			$input['custom_taxonomy_section'] = sanitize_text_field( $input['custom_taxonomy_section'] );
		}

		// Allow for Categories as Tags setting to be conditionally included on the page.
		// If it's not shown, then set the value as what was previously saved.
		if ( ! isset( $input['cats_as_tags'] ) ) {
			$input['cats_as_tags'] = 'true';
			if ( false === $options['cats_as_tags'] ) {
				$input['cats_as_tags'] = 'false';
			}
		}

		// Child categories as tags.
		if ( 'true' !== $input['cats_as_tags'] && 'false' !== $input['cats_as_tags'] ) {
			add_settings_error(
				Parsely::OPTIONS_KEY,
				'cats_as_tags',
				__( 'Value passed for cats_as_tags must be either "true" or "false".', 'wp-parsely' )
			);
		} else {
			$input['cats_as_tags'] = 'true' === $input['cats_as_tags'];
		}

		// Allow for Lowercase Tags setting to be conditionally included on the page.
		// If it's not shown, then set the value as what was previously saved.
		if ( ! isset( $input['lowercase_tags'] ) ) {
			$input['lowercase_tags'] = 'true';
			if ( false === $options['lowercase_tags'] ) {
				$input['lowercase_tags'] = 'false';
			}
		}

		// Lowercase tags.
		if ( 'true' !== $input['lowercase_tags'] && 'false' !== $input['lowercase_tags'] ) {
			add_settings_error(
				Parsely::OPTIONS_KEY,
				'lowercase_tags',
				__( 'Value passed for lowercase_tags must be either "true" or "false".', 'wp-parsely' )
			);
		} else {
			$input['lowercase_tags'] = 'true' === $input['lowercase_tags'];
		}

		// Allow for Force HTTPS Canonical setting to be conditionally included on the page.
		// If it's not shown, then set the value as what was previously saved.
		if ( ! isset( $input['force_https_canonicals'] ) ) {
			$input['force_https_canonicals'] = 'true';
			if ( false === $options['force_https_canonicals'] ) {
				$input['force_https_canonicals'] = 'false';
			}
		}

		if ( 'true' !== $input['force_https_canonicals'] && 'false' !== $input['force_https_canonicals'] ) {
			add_settings_error(
				Parsely::OPTIONS_KEY,
				'force_https_canonicals',
				__( 'Value passed for force_https_canonicals must be either "true" or "false".', 'wp-parsely' )
			);
		} else {
			$input['force_https_canonicals'] = 'true' === $input['force_https_canonicals'];
		}

		return $input;
	}

	/**
	 * Receive the $input array from the validate_options() function and validate post tracking options.
	 * This function will mutate the $input array.
	 *
	 * @since 3.2.0
	 *
	 * @param array $input Array passed to validate_options() function.
	 * @return void
	 */
	private function validate_options_post_type_tracking( array &$input ): void {
		$options         = $this->parsely->get_options();
		$posts           = 'track_post_types';
		$pages           = 'track_page_types';
		$track_as        = 'track_post_types_as';
		$input[ $posts ] = $options[ $posts ];
		$input[ $pages ] = $options[ $pages ];

		if ( isset( $input[ $track_as ] ) && is_array( $input[ $track_as ] ) && 0 < count( $input[ $track_as ] ) ) {
			$post_types = get_post_types( array( 'public' => true ) );
			$temp_posts = array();
			$temp_pages = array();

			// Create temporary Post and Page arrays, disallowing non-existent post types.
			foreach ( $input[ $track_as ] as $key => $value ) {
				if ( false === in_array( $key, $post_types, true ) ) {
					continue;
				}

				if ( 'post' === $value ) {
					$temp_posts[] = $key;
				} elseif ( 'page' === $value ) {
					$temp_pages[] = $key;
				}
			}

			// Cleanup and sanitized values assignment.
			$input [ $posts ] = self::sanitize_option_array( $temp_posts );
			$input [ $pages ] = self::sanitize_option_array( $temp_pages );
		}

		if ( isset( $input[ $track_as ] ) ) {
			unset( $input[ $track_as ] );
		}
	}

	/**
	 * Show our note about dynamic tracking.
	 *
	 * @return void
	 */
	public function print_dynamic_tracking_note(): void {
		printf(
		/* translators: 1: Documentation URL 2: Documentation URL */
			wp_kses_post( __( 'This plugin does not currently support dynamic tracking ( the tracking of multiple pageviews on a single page). Some common use-cases for dynamic tracking are slideshows or articles loaded via AJAX calls in single-page applications -- situations in which new content is loaded without a full page refresh. Tracking these events requires manually implementing additional JavaScript above <a href="%1$s">the standard Parse.ly include</a> that the plugin injects into your page source. Please consult <a href="%2$s">the Parse.ly documentation on dynamic tracking</a> for instructions on implementing dynamic tracking, or contact Parse.ly support (<a href="%3$s">support@parsely.com</a> ) for additional assistance.', 'wp-parsely' ) ),
			esc_url( 'http://www.parsely.com/help/integration/basic/' ),
			esc_url( 'https://www.parsely.com/help/integration/dynamic/' ),
			esc_url( 'mailto:support@parsely.com' )
		);
	}

	/**
	 * Returns default logo if one can be found.
	 *
	 * @return string
	 */
	private static function get_logo_default(): string {
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
	 * Sanitize all elements in an option array.
	 *
	 * @param array $array Array of options to be sanitized.
	 * @return array
	 */
	private static function sanitize_option_array( array $array ): array {
		$new_array = $array;
		foreach ( $array as $key => $val ) {
			$new_array[ $key ] = sanitize_text_field( $val );
		}
		return $new_array;
	}
}
