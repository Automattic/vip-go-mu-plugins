<?php
/**
 * Holds the SocialFlow Admin Message settings class
 *
 * @package SocialFlow
 */
class SocialFlow_Admin_Settings_General extends SocialFlow_Admin_Settings_Page {

	/**
	 * Add general menu item
	 */
	function __construct() {
		global $socialflow;

		$this->slug = 'socialflow';

		// Store current page object
		$socialflow->pages[ $this->slug ] = $this;

		// General menu page
		add_action( 'admin_menu', array( $this, 'admin_menu' ) ); 

		add_filter( 'sf_save_settings', array( $this, 'save_settings' ) );

		// Add update notice
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * This is callback for admin_menu action fired in construct
	 *
	 * @since 2.1
	 * @access public
	 */
	function admin_menu() {

		add_menu_page(
			esc_attr__( 'SocialFlow', 'socialflow' ),
			esc_attr__( 'SocialFlow', 'socialflow' ),
			'manage_options',
			$this->slug,
			array( $this, 'page' ),
			plugin_dir_url( SF_FILE ) . 'assets/images/menu-icon.png'
		);
		
		add_submenu_page(
			'socialflow',
			esc_attr__( 'Default Settings', 'socialflow' ),
			esc_attr__( 'Default Settings', 'socialflow' ),
			'manage_options',
			$this->slug,
			array( $this, 'page' )
		);

		// General section is both for authorized and for non authorized users
		add_settings_section(
			'general_settings_section',
			null,
			'__return_false',
			$this->slug
		);

		add_settings_field( 
			'publish_option',
			esc_attr__( 'Default Publishing Option:', 'socialflow' ),
			array( &$this, 'settings_field_publish_option' ),
			$this->slug,
			'general_settings_section',
			$this->get_publish_options() // The array of arguments to pass to the callback.
		);

		add_settings_field( 
			'optimize_publish_option_advanced',
			null,
			array( &$this, 'settings_field_optimize_publish_option' ),
			$this->slug,
			'general_settings_section',
			$this->get_publish_options() // The array of arguments to pass to the callback.
		);

		add_settings_field( 
			'compose_now',
			esc_attr__( 'Send to SocialFlow when the post is published:', 'socialflow' ),
			array( &$this, 'settings_field_compose_now_option' ),
			$this->slug,
			'general_settings_section'
		);

		add_settings_field( 
			'shorten_links',
			esc_attr__( 'Shorten Links:', 'socialflow' ),
			array( &$this, 'settings_field_shorten_links_option' ),
			$this->slug,
			'general_settings_section'
		);

		add_settings_field( 
			'post_type',
			esc_attr__( 'Enable plugin for this post types:', 'socialflow' ),
			array( &$this, 'settings_field_post_type_option' ),
			$this->slug,
			'general_settings_section'
		);
	}

	/**
	 * Default Publishing options
	 * @param  array  $args Available publishing optoins
	 * @return void
	 */
	public function settings_field_publish_option( $args = array() ) {
		global $socialflow;
		$current = $socialflow->options->get('publish_option');
		?>
		<select name="<?php echo $this->slug; ?>[publish_option]" id="js-publish-options">
			<?php foreach ( $args as $value => $label ): ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $current ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach ?>
		</select>
		<?php
	}

	public function settings_field_optimize_publish_option() {
		global $socialflow;

		// grouped options
		$must_send = $socialflow->options->get('must_send');
		$optimize_period = $socialflow->options->get('optimize_period');
		$optimize_start_date = $socialflow->options->get('optimize_start_date');
		$optimize_end_date = $socialflow->options->get('optimize_end_date');

		?><div class="optimize" <?php if ( 'optimize' != $socialflow->options->get( 'publish_option' ) ) echo 'style="display:none;"' ?> id="js-optimize-options">
			<input id="sf_must_send" type="checkbox" value="1" name="socialflow[must_send]" <?php checked( 1, $must_send ); ?> />
			<label for="sf_must_send"><?php esc_html_e( 'Must Send', 'socialflow' ); ?></label>

			<select name="socialflow[optimize_period]" id="js-optimize-period">
				<?php foreach ( self::get_optimize_periods() as $value => $label ): ?>
					<option <?php selected( $optimize_period, $value ); ?> value="<?php echo esc_attr( $value ) ?>" ><?php echo esc_html( $label ); ?></option>
				<?php endforeach ?>
			</select>

			<span class="range" <?php if ( 'range' != $optimize_period ) echo 'style="display:none;"' ?> id="js-optimize-range">
				<label for="optimize-from">from</label> <input type="text" value="<?php echo esc_attr( $optimize_start_date ); ?>" class="datetimepicker" name="socialflow[optimize_start_date]" data-tz-offset="<?php echo absint( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) ?>" />
				<label for="optimize-from">to</label> <input type="text" value="<?php echo esc_attr( $optimize_end_date ); ?>" class="datetimepicker" name="socialflow[optimize_end_date]" data-tz-offset="<?php echo absint( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) ?>" />
			</span>
		</div><?php
	}

	/**
	 * Select if upon publication post by default will be send to SocialFlow or not
	 * @return void
	 */
	public function settings_field_compose_now_option() {
		global $socialflow;
		?><input id="sf_compose_now" type="checkbox" value="1" name="socialflow[compose_now]" <?php checked( 1, $socialflow->options->get( 'compose_now' ) ) ?> /><?php
	}

	/**
	 * Should shorten links checkbox be checked by default 
	 * @return void
	 */
	public function settings_field_shorten_links_option() {
		global $socialflow;
		?><input id="sf_shorten_links" type="checkbox" value="1" name="socialflow[shorten_links]" <?php checked( 1, $socialflow->options->get( 'shorten_links' ) ) ?> /><?php
	}

	/**
	 * Enable SocialFlow compose form for specific post types
	 * @return void
	 */
	public function settings_field_post_type_option() {
		global $socialflow;
		$checked = $socialflow->options->get( 'post_type', array() );

		$types = get_post_types( 
			array(
				'public'  => true,
				'show_ui' => true
			),
			'objects'
		);

		foreach ( $types as $type => $post_type ) : ?>
			<input type="checkbox" value="<?php echo esc_attr( $type ); ?>" name="socialflow[post_type][]" <?php checked( true, in_array( $type, $checked ) ) ?> id="sf_post_types-<?php echo esc_attr( $type ); ?>" />
			<label for="sf_post_types-<?php echo esc_attr( $type ); ?>"><?php echo esc_attr( $post_type->labels->name ); ?></label>
			<br>
		<?php endforeach;
	}

	/**
	 * Render admin page with all accounts
	 */
	function page() {
		global $socialflow; ?>
		<div class="wrap socialflow">
			<h2><?php esc_html_e( 'Default Settings', 'socialflow' ); ?></h2>

			<?php settings_errors( $this->slug ); ?>

			<form method="post" action="options.php">
				<?php settings_fields( $this->slug ); ?>
				<?php
				if ( $socialflow->options->get( 'access_token' ) ) {
					do_settings_sections( $this->slug );
					submit_button();
				}
				else
					$this->authorize_settings();
				?>
			</form>

			<?php if ( $socialflow->options->get( 'access_token' ) ) : ?>
			<br />
			<p>
				<?php // Add temp token to check with ?>
				<a id="toggle-disconnect" class="clickable"><?php esc_html_e( 'Disconnect from SocialFlow.', 'socialflow' ) ?></a> &nbsp;&nbsp;&nbsp;&nbsp;
				<a id="disconnect-link" style="display:none" href="<?php echo admin_url( 'options-general.php?page=socialflow&sf_unauthorize=1' ) ?>"><?php esc_html_e( 'All plugin options will be removed.', 'socialflow' ) ?></a>
			</p>
			<?php endif; ?>
		</div>

		<?php
	}

	/**
	 * Outputs HTML for authorize Settings
	 *
	 * @since 2.0
	 * @access public
	 */
	function authorize_settings() {
		global $socialflow;

		$api = $socialflow->get_api();

		if ( ! $request_token = $api->get_request_token( add_query_arg( 'sf_oauth', true, admin_url( 'options-general.php?page=socialflow' ) ) ) ) {
			?><div class="misc-pub-section"><p><span class="sf-error"><?php esc_html_e( 'There was a problem communicating with the SocialFlow API. Please Try again later. If this problem persists, please email support@socialflow.com', 'sfp' ); ?></p></div><?php
			return;
		}

		$signup = 'http://socialflow.com/signup';
		if ( $links = $api->get_account_links( SF_KEY ) )
			$signup = $links->signup;

		// Store Oauth token and secret
		$socialflow->options->set( 'oauth_token', $request_token['oauth_token'] );
		$socialflow->options->set( 'oauth_token_secret', $request_token['oauth_token_secret'] );
		$socialflow->options->save();
		?>
		<div class="socialflow-authorize">
			<p><?php esc_html_e('Optimize publishing to Twitter and Facebook using', 'socialflow'); ?> <a href="http://socialflow.com/"><?php esc_html_e( 'SocialFlow.', 'socialflow' ); ?></a></p>
			<p><?php esc_html_e('Don’t have a SocialFlow account?', 'socialflow'); ?> <a href="<?php echo esc_url($signup); ?>"><?php esc_html_e( ' Sign Up', 'socialflow' ); ?></a></p>
			<p><a href="http://support.socialflow.com/entries/20573086-wordpress-plugin-faq-help"><?php esc_html_e( 'Help/FAQ', 'socialflow' ); ?></a></p>

			<p><a class="button-primary" href="<?php echo esc_url( $api->get_authorize_url( $request_token ) ); ?>"><?php esc_html_e( 'Connect to SocialFlow', 'socialflow' ); ?></a></p>
		</div>

		<?php
	}

	/**
	 * Sanitizes settings
	 *
	 * Callback for "sf_save_settings" hook in method SocialFlow_Admin::save_settings()
	 *
	 * @see SocialFlow_Admin::save_settings()
	 * @since 2.0
	 * @access public
	 *
	 * @param string|array $settings Settings passed in from filter
	 * @return string|array Sanitized settings
	 */
	function save_settings( $settings = array() ) {
		global $socialflow;

		// check is current page default (main) option page or is subpage
		if ( isset( $_POST['socialflow-page'] ) )
			return $settings;

		if ( !isset( $_POST['socialflow'] ) )
			return $settings;

		$data = $_POST['socialflow'];

		if ( isset( $_POST['option_page'] ) AND ( $this->slug == $_POST['option_page'] ) ) {

			// Whitelist validation
			if ( isset( $data['publish_option'] ) && array_key_exists( $data['publish_option'], self::get_publish_options() ) )
				$settings['publish_option'] = $data['publish_option'];

			if ( isset( $data['optimize_period'] ) && array_key_exists( $data['optimize_period'], self::get_optimize_periods() ) )
				$settings['optimize_period'] = $data['optimize_period'];

			$settings['optimize_range_from'] = isset( $data['optimize_range_from'] ) ? sanitize_text_field( $data['optimize_range_from'] ) : null;
			$settings['optimize_range_to'] = isset( $data['optimize_range_to'] ) ? sanitize_text_field( $data['optimize_range_to'] ) : null;

			$settings['post_type'] = isset( $data['post_type'] ) ? array_map( 'sanitize_text_field', $data['post_type'] ) : array();

			$settings['shorten_links'] = isset( $data['shorten_links'] ) ? absint( $data['shorten_links']) : 0;
			$settings['must_send'] = isset( $data['must_send'] ) ? absint( $data['must_send'] ) : 0;
			$settings['compose_now'] = isset( $data['compose_now'] ) ? absint( $data['compose_now'] ) : 0;
		}

		return $settings;
	}

	/**
	 * Available publish options
	 * @return array Publish options with labels
	 */
	public function get_publish_options() {
		return array(
			'optimize' => esc_attr__( 'Optimize', 'socialflow' ),
			'publish now' => esc_attr__( 'Publish Now', 'socialflow' ),
			'hold' => esc_attr__( 'Hold', 'socialflow' ),
			'schedule' => esc_attr__( 'Schedule', 'socialflow' ),
		);
	}

	/**
	 * Available optimize periods
	 * @return array Optimize perfiods with labels
	 */
	public function get_optimize_periods() {
		return array(
			'10 minutes' => esc_attr__( '10 minutes', 'socialflow' ),
			'1 hour' => esc_attr__( '1 hour', 'socialflow' ),
			'1 day' => esc_attr__( '1 day', 'socialflow' ),
			'1 week' => esc_attr__( '1 week', 'socialflow' ),
			'anytime' => esc_attr__( 'Anytime', 'socialflow' ),
			'range' => esc_attr__( 'Pick a range', 'socialflow' )
		);
	}

}