<?php

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Class PushUp_Notifications_Core
 *
 * This class is responsible for enabling the WordPress user to setup access to our 10up push notification service,
 * configure their push package, and facilitate pushing notifications when posts are transitioned from not published to
 * published. It also handles enqueueing a script that will allow safari users to enable their push notifications.
 */
class PushUp_Notifications_Core {

	/**
	 * The name / id of the submenu settings page
	 *
	 * @var string
	 */
	protected static $menu_page = 'pushup-settings';

	/**
	 *
	 * @var string
	 */
	protected static $option_key = 'pushup';

	/**
	 * Database version, for database upgrades
	 *
	 * @var int
	 */
	protected static $database_version = 1060;

	/**
	 * Script version, for CSS/JS caching
	 *
	 * @var string
	 */
	protected static $script_version = '20140616a';

	/**
	 * Handles initializing this class and returning the singleton instance after it's been cached.
	 *
	 * @return null|PushUp_Notifications_Core
	 */
	public static function instance() {

		// Store the instance locally to avoid private static replication
		static $instance = null;

		if ( null === $instance ) {
			$instance = new self();
			self::_add_actions();
		}

		return $instance;
	}

	/**
	 * An empty constructor
	 */
	public function __construct() { /* Purposely do nothing here */ }

	/**
	 * Handles registering hooks that initialize this plugin.
	 */
	public static function _add_actions() {

		// Bail if not in admin or doing admin ajax
		if ( ! is_admin() ) {
			return;
		}

		// Add our methods to some actions
		add_action( 'admin_init',                         array( __CLASS__, 'database_upgrade'         ), 2  ); // Early to upgrade old options before authentication
		add_action( 'admin_init',                         array( __CLASS__, 'register_settings'        ), 6  ); // Early so settings could be conditionally de-registered
		add_action( 'admin_menu',                         array( __CLASS__, 'admin_menu'               )     );
		add_action( 'admin_notices',                      array( __CLASS__, 'activation_notice'        )     );
		add_action( 'admin_enqueue_scripts',              array( __CLASS__, 'admin_enqueue_scripts'    )     );
		add_action( 'wp_ajax_update-push-package-icon',   array( __CLASS__, 'update_push_package_icon' )     );
		add_action( 'load-settings_page_pushup-settings', array( __CLASS__, 'settings_fields'          )     );
		add_action( 'load-settings_page_pushup-settings', array( __CLASS__, 'settings_help'            )     );

		// add filters
		add_filter( 'pushup_get_shortlink',               array( __CLASS__, 'add_pushup_query_arg'     )     );
	}

	/**
	 * Filter the default `pushup_get_shortlink` so that it returns the URL with
	 * "pushup=1" appended to it.
	 *
	 * @param string $shortlink A shortlink URL that will be filtered to add
	 *                          "pushup=1" for analytics.
	 *
	 * @return string           A shortlink or an empty string if no shortlink
	 *                          exists for the requested resource or if
	 *                          shortlinks are not enabled.
	 */
	public static function add_pushup_query_arg( $shortlink = '' ) {
		return add_query_arg( 'pushup', '1', $shortlink );
	}

	/** Admin Init ************************************************************/

	/**
	 * Handles updating an icon via AJAX and the JSON API.
	 */
	public static function update_push_package_icon() {
		check_ajax_referer( 'pushup-notification-settings' );
		$white_listed_icon_ids = array( '16x16', '16x16@2x', '32x32', '32x32@2x', '128x128', '128x128@2x' );

		if ( ! isset( $_POST[ 'actionData' ] ) || ! is_array( $_POST[ 'actionData' ] ) ) {
			wp_send_json( array( 'error' => true ) );
		}

		$data = $_POST[ 'actionData' ];
		if ( ! isset( $data[ 'iconID' ] ) || ! in_array( $data[ 'iconID' ], $white_listed_icon_ids ) || ! isset( $data[ 'iconURL' ] ) ) {
			wp_send_json( array( 'error' => true ) );
		} elseif ( ! isset( $data[ 'currentMode' ] ) || ! in_array( $data[ 'currentMode' ], array( 'basic', 'advanced' ) ) ) {
			wp_send_json( array( 'error' => true ) );
		}

		$result = PushUp_Notifications_JSON_API::update_icon( $data[ 'iconURL' ], $data[ 'iconID' ], $data[ 'currentMode' ] );
		wp_send_json( $result );
	}

	/**
	 * Register the settings
	 */
	public static function register_settings() {
		// Register the settings array
		register_setting( 'pushup', self::$option_key, array( __CLASS__, 'sanitize_settings' ) );
	}

	/**
	 * Register settings sections and fields before loading the settings page.
	 *
	 * Happens after authenticate() so some settings sections can be skipped if
	 * authentication is broken. Also only happens for our settings page, to avoid
	 * admin_init overhead.
	 */
	public static function settings_fields() {
		// Register the settings sections
		foreach ( self::get_settings_sections() as $section_id => $section_attributes ) {
			add_settings_section( $section_id, $section_attributes['title'], $section_attributes['callback'], self::$menu_page );
		}

		// Register the settings sections
		foreach ( self::get_settings_fields() as $field_id => $field_attributes ) {
			$args = isset( $field_attributes['args'] ) ? $field_attributes['args'] : null;
			add_settings_field( $field_id, $field_attributes['title'], $field_attributes['callback'], self::$menu_page, $field_attributes['section'], $args );
		}
	}

	/**
	 * Run a database upgrade routine on admin init
	 *
	 * Happens immediately on admin init so data can be upgraded before any
	 * other actions take place.
	 */
	public static function database_upgrade() {

		// Get the DB version
		$raw_db_version = self::get_raw_db_version();
		$needs_update   = false;

		// Upgrade from before 1.0.0 - consolidate options into 1 array
		if ( empty( $raw_db_version ) ) {

			// upgrade old beta non-serialized settings if applicable @todo test this
			if ( get_option( 'pushup-username' ) || get_option( 'pushup-website-push-id' ) || get_option( 'pushup-api-key' ) ) {

				// move into the new serialized option
				update_option( self::$option_key, array(
					'username' 			=> get_option( 'pushup-username',        '' ),
					'api-key'			=> get_option( 'pushup-api-key',         '' ),
					'website-push-id'	=> get_option( 'pushup-website-push-id', '' ),
					'user-id'           => PushUp_Notifications_JSON_API::get_user_id(),
					'post-title'        => __( 'Just published...', 'pushup' ),
					'db-version'        => self::$database_version
				) );

				// delete old options
				delete_option( 'pushup-username' );
				delete_option( 'pushup-website-push-id' );
				delete_option( 'pushup-api-key' );

				// Option needs updating
				$needs_update = true;
			}
		}

		// Get any existing settings
		$settings = get_option( self::$option_key );

		// Override the 'db-version' to current
		if ( isset( $settings['db-version'] ) && $settings['db-version'] !== self::$database_version ) {
			$needs_update = true;
		}

		// Maybe add 'post-title' to settings
		if ( ( $raw_db_version < 1030 ) && empty( $settings['post-title'] ) ) {
			$settings['post-title'] = __( 'Just published...', 'pushup' );
			$needs_update           = true;
		}

		// Maybe add 'user-id' to settings
		if ( ( $raw_db_version < 1040 ) && empty( $settings['user-id'] ) ) {
			$settings['user-id'] = PushUp_Notifications_JSON_API::get_user_id();
			$needs_update        = true;
		}

		// Update the option if it needs it
		if ( true === $needs_update ) {

			// Always update to the current database version. If this doesn't
			// happen, exil and mysterious bugs appear out of no-where.
			$settings['db-version'] = self::$database_version;

			// Update the option
			update_option( self::$option_key, $settings );
		}
	}

	/** Other Actions *********************************************************/

	/**
	 * Handles registering menus for our settings page
	 */
	public static function admin_menu() {
		add_submenu_page( 'options-general.php', __( 'PushUp Settings', 'pushup' ), __( 'PushUp', 'pushup' ), 'manage_options', self::$menu_page, array( __CLASS__, 'render_settings_page' ) );
	}

	/**
	 * Gets the user ID that will be used for communications with our push notification platform.
	 *
	 * @return mixed|void
	 */
	public static function get_user_id() {
		$options = get_option( self::$option_key );
		return !empty( $options['user-id'] ) ? $options['user-id'] : '';
	}

	/**
	 * Gets the username that will be used for communications with our push notification platform.
	 *
	 * @return mixed|void
	 */
	public static function get_username() {
		$options = get_option( self::$option_key );
		return !empty( $options['username'] ) ? $options['username'] : '';
	}

	/**
	 * Gets the previously set website push ID. This will be sent to each API request.
	 *
	 * @return mixed|void
	 */
	public static function get_website_push_id() {
		$options = get_option( self::$option_key );
		return !empty( $options['website-push-id'] ) ? $options['website-push-id'] : '';
	}

	/**
	 * Gets the API key that will be used for communications with our push notification platform.
	 *
	 * @return mixed|void
	 */
	public static function get_api_key() {
		$options = get_option( self::$option_key );
		return !empty( $options['api-key'] ) ? $options['api-key'] : '';
	}

	/**
	 * Gets the primary domain for the site.
	 *
	 * This function is necessary for MU sites where the URL for the backend is different than the URL for the frontend.
	 *
	 * See https://github.com/10up/PushUp/issues/9 for more information.
	 *
	 * @return string Represents the primary domain name for the domain name being used.
	 */
	public static function get_site_url() {
		$url = '';

		// Support for WordPress MU Domain Mapping
		if ( function_exists( 'domain_mapping_siteurl' ) ) {
			$url = domain_mapping_siteurl('');
		}

		if ( empty( $url ) ) {
			$url = site_url();
		}

		return apply_filters( 'pushup_site_url', $url );
	}

	/**
	 * Gets the API key that will be used for communications with our push notification platform.
	 *
	 * @return mixed|void
	 */
	public static function get_raw_db_version() {
		$options = get_option( self::$option_key );
		return !empty( $options['db-version'] ) ? $options['db-version'] : '';
	}

	/**
	 * Return the protected script version variable
	 *
	 * @return string
	 */
	public static function get_script_version() {
		return self::$script_version;
	}

	/**
	 * Gets the generic post title
	 *
	 * Currently can never be empty; we'll likely replace this with the post
	 * title in a future version.
	 *
	 * @return mixed|void
	 */
	public static function get_post_title() {
		$options = get_option( self::$option_key );
		return !empty( $options['post_title'] ) ? $options['post_title'] : __( 'Just published...', 'pushup' );
	}

	/**
	 * Gets the notification prompt configuration (when to prompt visitors for notifications)
	 *
	 * Must be an integer; 0 implies a custom configuration; default if unset should be 1 page view
	 *
	 * @return array
	 */
	public static function get_prompt_setting() {
		$options = get_option( self::$option_key );
		return array(
			'prompt'        => ( empty( $options['prompt'] ) || $options['prompt'] != 'custom' ) ? 'visits' : 'custom',
			'prompt_views'  => empty( $options['prompt_views'] ) ? 1 : (int) $options['prompt_views']
		);
	}

	/**
	 * Return a shortlink for a post, page, attachment, or blog.
	 *
	 * Default shortlink support is limited to providing ?p= style
	 * links for posts. Plugins can short-circuit this function via the
	 * 'pushup_pre_get_shortlink' filter or filter the output via the
	 * 'pushup_get_shortlink' filter.
	 *
	 * This is mostly a fork of WordPress's `wp_get_shortlink` function to allow
	 * PushUp to not interfere with existing shortlink plugins (Jetpack, etc...)
	 *
	 * @param int    $id          A post or blog id. Default is 0, which means
	 *                            the current post or blog.
	 * @param string $context     Whether the id is a 'blog' id, 'post' id, or
	 *                            'media' id. If 'post', the post_type of the
	 *                            post is consulted. If 'query', the current
	 *                            query is consulted to determine the id and
	 *                            context. Default is 'post'.
	 * @param bool   $allow_slugs Whether to allow post slugs in the shortlink.
	 *                            It is up to the plugin how and whether to
	 *                            honor this.
	 *
	 * @return string             A shortlink or an empty string if no shortlink
	 *                            exists for the requested resource or if
	 *                            shortlinks are not enabled.
	 */
	public static function get_shortlink( $id = 0, $context = 'post', $allow_slugs = true ) {

		// Use this to short-circuit our function completely
		$shortlink = apply_filters( 'pushup_pre_get_shortlink', false, $id, $context, $allow_slugs );
		if ( false !== $shortlink ) {
			return $shortlink;
		}

		// Define some basic variables
		global $wp_query;
		$post_id = 0;

		// Querying posts
		if ( ( 'query' === $context ) && is_singular() ) {
			$post_id = $wp_query->get_queried_object_id();
			$post    = get_post( $post_id );

		// Querying single post
		} elseif ( 'post' === $context ) {
			$post = get_post( $id );
			if ( ! empty( $post->ID ) ) {
				$post_id = $post->ID;
			}
		}

		// Return p= link for all public post types.
		if ( ! empty( $post_id ) ) {
			$post_type = get_post_type_object( $post->post_type );

			// If page is the home page, use the root of home_url()
			if ( ( 'page' === $post->post_type ) && ( $post->ID === (int) get_option( 'page_on_front' ) ) && ( 'page' == get_option( 'show_on_front' ) ) ) {
				$shortlink = home_url( '/' );

			// If post is of a public type
			} elseif ( true === $post_type->public ) {
				$shortlink = home_url( '?p=' . $post_id );
			}
		}

		return apply_filters( 'pushup_get_shortlink', (string) $shortlink, $id, $context, $allow_slugs );
	}

	/**
	 * Renders the push notification settings form for our WP settings page
	 */
	public static function render_settings_page() {
	?>

		<div class="wrap pushup-notifications-settings">
			<h2><?php echo get_admin_page_title(); ?></h2>
			<form action="options.php" method="post" autocomplete="off">

				<?php settings_errors( 'pushup-settings' ); ?>

				<?php settings_fields( 'pushup' ); ?>

				<?php do_settings_sections( self::$menu_page ); ?>

				<?php

					// If data needed to offer notifications isn't set, let's
					// actually give the button a more clear name: activate!
					if ( ! PushUp_Notifications_Authentication::get_authentication_data() ) {
						$submit_text = __( 'Activate PushUp', 'pushup' );
					} else {
						$submit_text = __( 'Save Changes' ); // No text domain here
					}

					submit_button( $submit_text );
				?>

			</form>
		</div>

	<?php
	}

	/**
	 * render a account settings section
	 */
	public static function _render_account_settings_section() {
	?>
		<p><?php esc_html_e( 'Before you can use PushUp, we need to make sure you have a valid PushUp account.', 'pushup' ); ?></p>
	<?php
	}

	/**
	 * render a analytics settings section
	 */
	public static function _render_analytics_settings_section() {
	?>
		<script type="text/javascript">
			var pushup_chart_options = {
				segmentShowStroke : true,
				segmentStrokeColor : "rgba(0,0,0,.1)",
				segmentStrokeWidth : 2,
				animation : true,
				animationSteps : 120,
				animationEasing : "easeOutQuart",
				animateRotate : true,
				animateScale : false
			};
		</script>
		<p><?php esc_html_e( 'Some quick statistics about your audience.', 'pushup' ); ?></p>
	<?php
	}

	/**
	 * render notification display settings section
	 */
	public static function _render_display_settings_section() {
	?>
		<p><?php esc_html_e( 'Customize your notification title and icon to better match your site. (Changes may not appear immediately for existing subscribers.)', 'pushup' ); ?></p>
	<?php
	}

	/**
	 * account connectivity field
	 */
 	public static function _render_account_connectivity_field() {
		// Check the last request for connectivity
		$request = PushUp_Notifications_JSON_API::_get_last_request();
		$code    = wp_remote_retrieve_response_code( $request );
		$message = wp_remote_retrieve_response_message( $request );

		// Set some default variable data
		$connection_class    = $authenticate_class = 'status-error';
		$connection_status   = sprintf( __( 'Error: %d - The PushUp API could not be reached.', 'pushup' ), $code );
		$authenticate_status = __( 'Your PushUp username and/or API key are invalid.', 'pushup' );

		// Check the connection and provide some feedback
		if ( ( 200 === $code ) && ( 'OK' === $message ) ) {
			$connection_class  = 'status-success';
			$connection_status = __( 'The PushUp API was contacted successfully.', 'pushup' );
		}

		// Check whether the users username and API key are valid
		if ( PushUp_Notifications_Authentication::is_authenticated() ) {
			$authenticate_class  = 'status-success';
			$authenticate_status = __( 'Your PushUp username and API key are valid.', 'pushup' );

			if ( PushUp_Notifications_JSON_API::is_domain_enabled() ) {
				$domain_class = 'status-success';
				$domain_status = __( 'PushUp has been provisioned for this domain.', 'pushup' );
			} else {
				$domain_class = 'status-error';
				$domain_status = __( 'PushUp has not been provisioned for this domain.', 'pushup' );
			}
		} ?>

		<p class="pushup-connection-status <?php echo esc_attr( $connection_class ); ?>"><?php echo esc_html( $connection_status ); ?></p>

		<?php if ( self::get_username() || self::get_api_key() ) : ?>
			<p class="pushup-authentication-status <?php echo esc_attr( $authenticate_class ); ?>"><?php echo esc_html( $authenticate_status ); ?></p>
		<?php endif; ?>

		<?php if ( isset( $domain_status ) && isset( $domain_class ) ) : ?>
			<p class="pushup-domain-status <?php echo esc_attr( $domain_class ); ?>"><?php echo esc_html( $domain_status ); ?></p>
		<?php endif; ?>

	<?php
	}

	/**
	 * account username field
	 */
	public static function _render_account_username_field() {
	?>
		<input type="text" name="pushup[username]" value="<?php echo esc_attr( self::get_username() ); ?>" class="short-text" />
		<p class="description"><?php printf( __( 'Enter your PushUp Username here. (%s)', 'pushup' ), '<a href="http://pushupnotifications.com">Need a username?</a>' ); ?></p>
	<?php
	}

	/**
	 * api key field
	 */
	public static function _render_account_api_key_field() {
	?>
		<input type="text" name="pushup[api-key]" value="<?php echo esc_attr( self::get_api_key() ); ?>" class="short-text" />
		<p class="description"><?php printf( esc_html__( 'Enter a valid PushUp API key here. (%s)', 'pushup' ), '<a href="http://pushupnotifications.com">Need an API key?</a>' ); ?></p>
	<?php
	}

	/**
	 * account user ID field
	 */
	public static function _render_account_user_id_field() {
	?>
		<p><strong><?php echo esc_html( self::get_user_id() ); ?></strong></p>
		<p class="description"><?php esc_html_e( 'This helps us identify you when contacting us for support.', 'pushup' ); ?></p>
	<?php
	}

	/**
	 * pie chart statistics field
	 */
 	public static function _render_analytics_pie_field( $args ) {
	?>

		<div class="pushup-analytics">
			<canvas id="pushup-chart-<?php echo esc_attr( $args['canvas_id'] ); ?>" width="100" height="100"></canvas>
			<div class="description">

				<?php

				// Get the analytics
				$analytics = PushUp_Notifications_JSON_API::get_analytics();

				// Loop through the analytics
				foreach( $args['data_points'] as $point_name => $point ) :
					$args['data_points'][$point_name]['value'] = $value = self::_get_value_from_array_path( $analytics, $point['path'] ); ?>

					<p class="pushup <?php echo sanitize_html_class( $point_name ); ?>"><?php printf( __( '%d ' . $point_name, 'pushup' ), $value ); ?></p>

				<?php endforeach; ?>

			</div>
			<p class="description"><?php echo esc_html( $args['desc'] ); ?></p>

			<script type="text/javascript">
				new Chart( jQuery( document.querySelector( "#pushup-chart-<?php echo esc_attr( $args['canvas_id'] ); ?>" ) ).get(0).getContext( "2d" ) ).Pie( [
				<?php foreach( $args['data_points'] as $point_name => $point ) : ?>
					{
						color : "<?php echo esc_js( $point['color'] ); ?>",
						value : <?php echo esc_js( $point['value'] ); ?>
					}<?php if ( end( $args['data_points'] ) !== $point ) echo ','; ?>
				<?php endforeach; ?>
				], pushup_chart_options );
			</script>
		</div>

	<?php
	}

	/**
	 * Get the value of a specific path in an array; used for storing paths to analytics data for fields
	 *
	 * @param array $arr Array with the data
	 * @param array $path An array with the path to the final key
	 * @return mixed Value at that path
	 */
	private static function _get_value_from_array_path( $arr = array(), $path = array() ) {

		// Bail if incorrect data was passed
		if ( empty( $arr ) || empty( $path ) || ! is_array( $arr ) || ! is_array( $path ) ) {
			return 0;
		}

		$dest      = $arr;
		$final_key = array_pop( $path );
		foreach ( $path as $key ) {
			if ( ! array_key_exists( $key, $dest ) ) {
				return 0;
			}
			$dest = $dest[ $key ];
		}

		return intval( $dest[ $final_key ] );
	}

	/**
	 * Website name field
	 */
	public static function _render_display_website_name_field() {

		// Get the website name
		$website_name = PushUp_Notifications_JSON_API::get_website_name();

		// Set website name to blog info name if none exists
		if ( empty( $website_name ) ) {
			$website_name = get_bloginfo( 'name' );
			PushUp_Notifications_JSON_API::set_website_name( $website_name );
		} ?>

		<input type="text" name="pushup[website_name]" value="<?php echo esc_attr( $website_name ); ?>" class="regular-text" />
		<p class="description"><?php esc_html_e( 'The website name for your notification messages, typically the name of your website or your URL.', 'pushup' ); ?></p>

		<?php
	}

	/**
	 * Post title field
	 */
	public static function _render_display_post_title_field() {
	?>

		<input type="text" name="pushup[post_title]" value="<?php echo esc_attr( self::get_post_title() ); ?>" class="regular-text" />
		<p class="description"><?php esc_html_e( 'Title text to help motivate your audience to click their notification. ("Just published..." is the default.)', 'pushup' ); ?></p>

		<?php
	}

	/**
	 * Website icons
	 */
	public static function _render_display_icon_field() {

		// Get Icon data
		$icon_data = PushUp_Notifications_JSON_API::get_icon_data();
		if ( empty( $icon_data ) ) {
			return;
		}

		// Whether or not to use advanced icon mode
		$show_advanced_mode = apply_filters( 'pushup_icon_mode', false ); ?>

		<?php if ( false === $show_advanced_mode ) : ?>

			<div class="basic-mode">
				<div class="icon-preview alignleft" id="icon-128x128-2x">
					<div class="thumbnail" data-icon-id="128x128@2x">
						<img data-icon-id="128x128@2x" src="<?php echo esc_url( add_query_arg( 'time', time(), $icon_data[ 'icon_128x128@2x.png' ] ) ); ?>"/>
						<div class="loader"></div>
					</div>
					<input type="button" data-icon-id="128x128@2x" class="button" value="<?php esc_html_e( 'Change', 'pushup' ); ?>" />
				</div>
				<div class="clear"></div>
				<p class="description"><?php printf( esc_html__( 'For best results, upload a %s %s image. (Transparency is allowed.)', 'pushup' ), '256x256', '<b>.PNG</b>' ); ?></p>
			</div>

		<?php else : ?>

			<div class="advanced-mode">
				<div class="icon-preview alignleft" id="icon-16x16">
					<div class="title">16 x 16</div>
					<div class="thumbnail" data-icon-id="16x16" >
						<img data-icon-id="16x16" src="<?php echo esc_url( add_query_arg( 'time', time(), $icon_data[ 'icon_16x16.png' ] ) ); ?>"/>
						<div class="loader"></div>
					</div>
					<input type="button" data-icon-id="16x16" class="button" value="<?php esc_html_e( 'Change', 'pushup' ); ?>" />
				</div>
				<div class="icon-preview alignleft" id="icon-16x16-2x">
					<div class="title">16 x 16 @ 2x</div>
					<div class="thumbnail" data-icon-id="16x16@2x" >
						<img data-icon-id="16x16@2x" src="<?php echo esc_url( add_query_arg( 'time', time(), $icon_data[ 'icon_16x16@2x.png' ] ) ); ?>"/>
						<div class="loader"></div>
					</div>
					<input type="button" data-icon-id="16x16@2x" class="button" value="<?php esc_html_e( 'Change', 'pushup' ); ?>" />
				</div>
				<div class="icon-preview alignleft" id="icon-32x32">
					<div class="title">32 x 32</div>
					<div class="thumbnail" data-icon-id="32x32">
						<img data-icon-id="32x32" src="<?php echo esc_url( add_query_arg( 'time', time(), $icon_data[ 'icon_32x32.png' ] ) ); ?>"/>
						<div class="loader"></div>
					</div>
					<input type="button" data-icon-id="32x32" class="button" value="<?php esc_html_e( 'Change', 'pushup' ); ?>" />
				</div>
				<div class="icon-preview alignleft" id="icon-32x32-2x">
					<div class="title">32 x 32 @ 2x</div>
					<div class="thumbnail" data-icon-id="32x32@2x">
						<img data-icon-id="32x32@2x" src="<?php echo esc_url( add_query_arg( 'time', time(), $icon_data[ 'icon_32x32@2x.png' ] ) ); ?>"/>
						<div class="loader"></div>
					</div>
					<input type="button" data-icon-id="32x32@2x" class="button" value="<?php esc_html_e( 'Change', 'pushup' ); ?>" />
				</div>
				<div class="icon-preview alignleft" id="icon-128x128">
					<div class="title">128 x 128</div>
					<div class="thumbnail" data-icon-id="128x128">
						<img data-icon-id="128x128" src="<?php echo esc_url( add_query_arg( 'time', time(), $icon_data[ 'icon_128x128.png' ] ) ); ?>"/>
						<div class="loader"></div>
					</div>
					<input type="button" data-icon-id="128x128" class="button" value="<?php esc_html_e( 'Change', 'pushup' ); ?>" />
				</div>
				<div class="icon-preview alignleft" id="icon-128x128-2x">
					<div class="title">128 x 128 @ 2x</div>
					<div class="thumbnail" data-icon-id="128x128@2x">
						<img data-icon-id="128x128@2x" src="<?php echo esc_url( add_query_arg( 'time', time(), $icon_data[ 'icon_128x128@2x.png' ] ) ); ?>"/>
						<div class="loader"></div>
					</div>
					<input type="button" data-icon-id="128x128@2x" class="button" value="<?php esc_html_e( 'Change', 'pushup' ); ?>" />
				</div>
				<div class="clear"></div>
				<p class="description"><?php printf( esc_html__( 'Notifications require %s image type for best results. (Transparency is allowed.)', 'pushup' ), '<b>.PNG</b>' ); ?></p>
			</div>

		<?php endif; ?>
	<?php
	}

	/**
	 * Control prompt for notifications
	 */
	public static function _render_display_prompt_field() {

		// 0 = custom, otherwise this contains the number of page views
		$prompt = self::get_prompt_setting(); ?>

		<fieldset><legend class="screen-reader-text"><span>Prompt Visitor for Notifications</span></legend>
			<label title="<?php esc_attr_e( 'After a specified number of page views.', 'pushup' ); ?>">
				<input type="radio" name="pushup[prompt]" value="visits" <?php checked( $prompt['prompt'], 'visits' ); ?>> <?php esc_html_e( 'After', 'pushup' ); ?></label> <label for="pushup[prompt_views]"><input name="pushup[prompt_views]" type="number" min="1" step="1" id="pushup[prompt_views]" value="<?php echo (int) $prompt['prompt_views']; ?>" class="small-text"> <?php esc_html_e( 'page view(s)', 'pushup' ); ?>
			</label>
			<br>
			<label title="<?php esc_attr_e( 'On a custom event.', 'pushup' ); ?>">
				<input type="radio" name="pushup[prompt]" value="custom" <?php checked( $prompt['prompt'], 'custom' ); ?>> <?php esc_html_e( 'On a custom event', 'pushup' ); ?>
			</label>
			
			<div id="pushup_custom_prompt_help" class="postbox"<?php if ( $prompt['prompt'] !== 'custom' ) :?>style="display: none;"<?php endif; ?>>
				<div class="inside">
					<ul>
						<li>
							<p><?php printf( esc_html__( 'Assign the class %s to any element to prompt the visitor on-click.', 'pushup' ), '<code>"pushup_button"</code>' ); ?></p>
							<p><?php printf( esc_html__( 'Example: %s', 'pushup' ), '<code>&lt;a href="#" class="pushup_button"&gt;' . esc_html__( 'Receive Desktop Notifications', 'pushup' ) . '&lt;/a&gt;</code>' ); ?></p>
						</li>

						<?php if ( current_theme_supports( 'menus' ) ) : ?>
							<li><p><?php printf( esc_html__( '%s can be configured to prompt on-click by enabling CSS Classes under Screen Options.', 'pushup' ), '<a href="' . admin_url( 'nav-menus.php' ) . '">' . esc_html__( 'Menu items', 'pushup' ) . '</a>' ); ?></p></li>
						<?php endif; ?>

						<li><p class="description"><?php esc_html_e( 'Elements with this special class are hidden from visitors who already accepted/denied notifications or are using unsupported browsers.', 'pushup' ); ?></p></li>
					</ul>
				</div>
			</div>
			
			<p class="description"><?php esc_html_e( 'Customize when visitors see the prompt offering notifications in supported browsers.', 'pushup' ); ?></p>
		</fieldset>

	<?php
	}

	/** Help & Notices ********************************************************/

	/**
	 * Contextual help for settings page
	 *
	 * @uses get_current_screen()
	 */
	public static function settings_help() {

		// Bail if no current screen
		if ( ! $current_screen = get_current_screen() ) {
			return;
		}

		// Overview
		$current_screen->add_help_tab( array(
			'id'      => 'overview',
			'title'   => esc_html__( 'Overview', 'pushup' ),
			'content' => '<p>' . __( 'This screen provides access to all of the PushUp settings.', 'pushup' ) . '</p>' .
						 '<p>' . __( 'Please see the additional help tabs for more information on each indiviual section.', 'pushup' ) . '</p>'
		) );

		// Main Settings
		$current_screen->add_help_tab( array(
			'id'      => 'account_information',
			'title'   => esc_html__( 'Account Information', 'pushup' ),
			'content' => '<p>' . esc_html__( 'The Account Information section has two fields:', 'pushup' ) . '</p>' .
						 '<p>' .
							'<ul>' .
								'<li>' . __( '<strong>PushUp Username</strong> - The PushUp service uses your username to authenticate you, and so PushUp knows what to push where.',  'pushup' ) . '</li>' .
								'<li>' . __( '<strong>PushUp API Key</strong> - Your PushUp API key is a unique set of characters assigned to you by the PushUp service, and is used to help verify your identity.', 'pushup' ) . '</li>' .
							'</ul>' .
						'</p>' .
						'<p>' . __( 'You must click the Save Changes button at the bottom of the screen for new settings to take effect.', 'pushup' ) . '</p>'
		) );

		// Per Page
		$current_screen->add_help_tab( array(
			'id'      => 'analytics',
			'title'   => esc_html__( 'Analytics', 'pushup' ),
			'content' => '<p>' . esc_html__( 'The Analytics section currently provides three key pieces of information:', 'pushup' ) . '</p>' .
						 '<p>' .
							'<ul>' .
								'<li>' . __( '<strong>Subscribers</strong> - Total number of subscribers that clicked "Allow" when prompted.',        'pushup' ) . '</li>' .
								'<li>' . __( '<strong>Pushes</strong> - Total number of unique pieces of content were pushed to your subscribers.',   'pushup' ) . '</li>' .
								'<li>' . __( '<strong>Notifications</strong> - Total number of notifications sent to your subscribers.',              'pushup' ) . '</li>' .
							'</ul>' .
						'</p>'
		) );

		// Slugs
		$current_screen->add_help_tab( array(
			'id'      => 'notifications_settings',
			'title'   => esc_html__( 'Notification Settings', 'pushup' ),
			'content' => '<p>' . esc_html__( 'The Notification Settings section has four fields:', 'pushup' ) . '</p>' .
						 '<p>' .
							'<ul>' .
								'<li>' . __( '<strong>Website Name</strong> - The name of your website that readers will see as the source of the push notification. You can leave this blank, and PushUp will use the name of your site from General Settings.',  'pushup' ) . '</li>' .
								'<li>' . __( '<strong>Notification Title</strong> - A very short title that appears before the unique title of your content. Only fits about 4 or 5 short words, and defaults to "Just published...".', 'pushup' ) . '</li>' .
								'<li>' . __( '<strong>Icon(s)</strong> - You can upload several different size icons for use in OS X Mavericks and Safari. For the best results, create icons for each specific dimension available.', 'pushup' ) . '</li>' .
								'<li>' . __( '<strong>Offer Notifications</strong> - Prompt visitors in supported browsers after they visit this site a set number of times (defaults to the first time), and/or upon an event like clicking a link. Although presented as alternatives, a custom event link will work in in concert with a set number of page views.', 'pushup' ) . '</li>' .
							'</ul>' .
						'</p>' .
						'<p>' . __( 'You must click the Save Changes button at the bottom of the screen for new settings to take effect.', 'pushup' ) . '</p>'
		) );

		// Help Sidebar
		$current_screen->set_help_sidebar(
			'<p><strong>' . esc_html__( 'For more information:', 'pushup' ) . '</strong></p>' .
			'<p>' . __( '<a href="http://pushupnotifications.com/documentation/" target="_blank">PushUp Documentation</a>',  'pushup' ) . '</p>' .
			'<p>' . __( '<a href="http://pushupnotifications.com/faq/" target="_blank">PushUp FAQ</a>',                      'pushup' ) . '</p>'
		);
	}

	/**
	 * A quick activation notice
	 *
	 * @global string $hook_suffix
	 * @return null If on the wrong page
	 */
	public static function activation_notice() {
		global $hook_suffix;

		// Bail if submitting a form of some kind
		if ( isset( $_POST['submit'] ) ) {
			return;
		}

		// Bail if API key is already saved
		if ( self::get_api_key() ) {
			return;
		}

		if ( $hook_suffix !== 'plugins.php' ) {
			return;
		}

		// The Settings Page URL
		$settings_page = add_query_arg( array( 'page' => self::$menu_page ), admin_url( 'options-general.php' ) ); ?>

		<div class="updated" style="padding: 0; margin: 0; border: none; background: none;">
			<style type="text/css">
				.pushup-activate {
					min-width: 325px;
					border:1px solid #2e82ac;
					padding: 10px;
					margin:15px 0;
					background: #2ea2cc;
					position: relative;
					overflow: hidden
				}

				.pushup-activate .pushup-button {
					display: inline-block;
					padding: 10px 29px;
					margin: 6px;
					text-align: center;
					background-color: #4ac68f;
					font-size: 14px;
					text-decoration: none;
					color: #f2f2f2;
					font-weight: bold;
					text-shadow: none;
					box-shadow: 1px 1px 1px rgba(0,0,0,.2);
					-moz-transition: background-color 300ms ease-out;
					-webkit-transition: background-color 300ms ease-out;
					-ms-transition: background-color 300ms ease-out;
					transition: background-color 300ms ease-out;
				}

				.pushup-activate .pushup-button:hover {
					color: #F0F8FB;
					background: #47ba87;
				}

				.pushup-activate .pushup-button-wrapper {
					display: inline-block;
					margin-right: 15px;
				}

				.pushup-activate .pushup-description {
					display: inline-block;
					color: #f2f2f2;
					font-size: 15px;
					z-index: 1000;
				}

				.pushup-activate .pushup-description strong {
					color: #fff;
					font-weight: normal
				}
			</style>
			<div class="pushup-activate">
				<div class="pushup-button-wrapper">
					<a href="<?php echo esc_url( $settings_page ); ?>" class="pushup-button"><?php _e( 'Activate your PushUp account', 'pushup' ); ?></a>
				</div>
				<div class="pushup-description"><?php _e( 'You are <strong>almost ready</strong> to start sending push notifications', 'pushup' ); ?></div>
			</div>
		</div>

		<?php
	}

	/**
	 * Handles enqueueing admin scripts for various aspects of the admin section
	 */
	public static function admin_enqueue_scripts( $hook ) {

		// Bail if this is not the droid we are looking for
		if ( $hook !== 'settings_page_pushup-settings' ) {
			return;
		}

		// Allow base path to be filtered
		$base = apply_filters( 'pushup-notification-base-script-path', plugins_url( '', dirname( __FILE__ ) ) );

		// Enqueue media for use with uploading icons
		wp_enqueue_media();

		// Enqueue our custom CSS and JS
		wp_enqueue_style( 'pushup-notification-settings',  $base . '/css/settings.css', array(),           self::get_script_version()       );
		wp_enqueue_script( 'pushup-notification-charts',   $base . '/js/chart.js',      array( 'jquery' ), self::get_script_version()       );
		wp_enqueue_script( 'pushup-notification-settings', $base . '/js/settings.js',   array( 'jquery' ), self::get_script_version(), true );

		// Localize the notifications settings
		wp_localize_script( 'pushup-notification-settings', 'pushNotificationSettings', array(
			'ajaxURL' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'pushup-notification-settings' ),
		) );
	}

	/** Settings Helpers ******************************************************/

	/**
	 * Sanitize saved settings fields
	 */
	public static function sanitize_settings( $input ) {
		$sanitized_input = array();

		// Nullify any previously cached authentications
		PushUp_Notifications_Authentication::_set_cached_authentication( null );

		// handle username field
		$sanitized_input['username'] = empty( $input['username'] ) ? '' : sanitize_text_field( $input['username'] );

		// set site push id to old value by default, to be cautious
		$sanitized_input['website-push-id'] = self::get_website_push_id();

		// handle api key and site push id
		if ( empty( $input['api-key'] ) ) {
			$sanitized_input['api-key'] = '';
		} else {
			$sanitized_input['api-key'] = sanitize_text_field( $input['api-key'] );

			// we need to cache the website push ID here because it's used elsewhere in the site like for generating a
			// push package on the frontend
			// @todo if this hasn't been stored, we need to attempt to get it in other places
			if ( ! empty( $sanitized_input['username'] ) ) {
				$settings_data = PushUp_Notifications_JSON_API::get_settings_data( $sanitized_input['username'], $sanitized_input['api-key'] );

				// grab the push ID
				$push_id = ( ! empty( $settings_data[ 'push.domain' ][ 'push_id' ] ) ) ? $settings_data[ 'push.domain' ][ 'push_id' ] : false;
				$sanitized_input['website-push-id'] = sanitize_text_field( $push_id );

				// we also need to cache the user ID here because it's used elsewhere in the site.
				$user_id = ( ! empty( $settings_data[ 'push.user.id' ] ) ) ? $settings_data[ 'push.user.id' ] : false;
				$sanitized_input['user-id'] = intval( $user_id );
			}
		}

		// update website name over PushUp API
		if ( ! empty( $input['website_name'] ) ) {
			PushUp_Notifications_JSON_API::set_website_name( $input['website_name'], $sanitized_input['username'], $sanitized_input['api-key'] );

		// if field appeared but was left empty or upon first authentication nothing was already set on server side
		} elseif ( isset( $input['website_name'] ) || !PushUp_Notifications_JSON_API::get_website_name() ) {
			PushUp_Notifications_JSON_API::set_website_name( get_bloginfo('name'), $sanitized_input['username'], $sanitized_input['api-key'] );
		}

		// update post title
		if ( ! empty( $input['post_title'] ) ) {
			$sanitized_input['post_title'] = sanitize_text_field( $input['post_title'] );

		// if field is empty, use default text
		} elseif ( isset( $input['post_title'] ) ) {
			$sanitized_input['post_title'] = __( 'Just published...', 'pushup' );
		}

		// save prompt configuration
		$sanitized_input['prompt'] = ( empty( $input['prompt'] ) || $input['prompt'] != 'custom' ) ? 'visits' : 'custom';
		$sanitized_input['prompt_views'] = empty( $input['prompt_views'] ) ? 1 : (int) $input['prompt_views'];

		return $sanitized_input;
	}

	/**
	 * Helper function to return the settings field sections, their descriptions,
	 * and help setup their callbacks for rendering.
	 *
	 * @return array
	 */
	public static function get_settings_sections() {

		// Account section
		$settings_sections = array(
			'default' => array(
				'title'    => esc_html__( 'Account Information','pushup' ),
				'callback' => array( __CLASS__, '_render_account_settings_section' ),
			)
		);

		// The following sections are only for authenticated users
		if ( PushUp_Notifications_Authentication::is_authenticated() && PushUp_Notifications_JSON_API::is_domain_enabled() ) {

			// Analytics
			if ( PushUp_Notifications_JSON_API::get_analytics() ) {
				$settings_sections['analytics'] = array(
					'title'    => esc_html__( 'Analytics', 'pushup' ),
					'callback' => array( __CLASS__, '_render_analytics_settings_section' ),
				);
			}

			// Display
			$settings_sections['display'] = array(
				'title'    => esc_html__( 'Notification Display', 'pushup' ),
				'callback' => array( __CLASS__, '_render_display_settings_section' ),
			);
		}

		return $settings_sections;
	}

	/**
	 * Helper function to return the settings fields, their descriptions, and
	 * help setup their callbacks for rendering.
	 *
	 * @return array
	 */
	public static function get_settings_fields() {

		// Authentication settings
		$settings_fields = array(
			'connectivity' => array(
				'title'    => esc_html__( 'Service Connectivity', 'pushup' ),
				'callback' => array( __CLASS__, '_render_account_connectivity_field' ),
				'section'  => 'default'
			),
			'username' => array(
				'title'    => esc_html__( 'PushUp Username', 'pushup' ),
				'callback' => array( __CLASS__, '_render_account_username_field' ),
				'section'  => 'default'
			),
			'api-key' => array(
				'title'    => esc_html__( 'PushUp API Key', 'pushup' ),
				'callback' => array( __CLASS__, '_render_account_api_key_field' ),
				'section'  => 'default'
			)
		);

		// The following fields are only for authenticated users
		if ( PushUp_Notifications_Authentication::is_authenticated() && PushUp_Notifications_JSON_API::is_domain_enabled() ) {

			/** Analytics *****************************************************/

			// Skip if we can't get any analytics
			if ( PushUp_Notifications_JSON_API::get_analytics() ) {

				// Subscribers chart
				$settings_fields['subscribers'] = array(
					'title'    => esc_html__( 'Subscribers', 'pushup' ),
					'callback' => array( __CLASS__, '_render_analytics_pie_field' ),
					'section'  => 'analytics',
					'args'     => array(
						'canvas_id'   => 'conversion',
						'desc'        => esc_html__( 'Number of Subscribers', 'pushup' ),
						'data_points' => array(
							esc_html( 'Declined', 'pushup' ) => array(
								'color' => '#63bde4',
								'path'	=> array( 'total_declined' ),
							),
							esc_html( 'Accepted', 'pushup' ) =>  array(
								'color' => 'rgba(74,198,143,1)',
								'path'  => array( 'total_granted' ),
							),
						)
					)
				);

				// Pushes chart
				$settings_fields['pushes'] = array(
					'title'    => esc_html__( 'Push Requests', 'pushup' ),
					'callback' => array( __CLASS__, '_render_analytics_pie_field' ),
					'section'  => 'analytics',
					'desc'     => '',
					'args'     => array(
						'canvas_id'  => 'requests',
						'desc'        => esc_html__( 'Total Number of Posted Items Pushed to Subscribers', 'pushup' ),
						'data_points' => array(
							esc_html( 'This Month', 'pushup' ) => array(
								'color' => '#63bde4',
								'path'	=> array( 'total_monthly_pushes', 'total_push_requests' ),
							),
							esc_html( 'All Time', 'pushup' ) => array(
								'color' => 'rgba(74,198,143,1)',
								'path'	=> array( 'total_all_time_pushes', 'total_push_requests' ),
							),
						),
					)
				);

				// Notifications chart
				$settings_fields['notifications'] = array(
					'title'    => esc_html__( 'Notifications', 'pushup' ),
					'callback' => array( __CLASS__, '_render_analytics_pie_field' ),
					'section'  => 'analytics',
					'args'     => array(
						'canvas_id'	  => 'recipients',
						'desc'        => esc_html__( 'Total Number of Notifications Sent to Subscribers', 'pushup' ),
						'data_points' => array(
							esc_html__( 'This Month', 'pushup' ) => array(
								'color' => '#63bde4',
								'path'	=> array( 'total_monthly_pushes', 'total_push_recipients' ),
							),
							esc_html__( 'All Time', 'pushup' ) => array(
								'color' => 'rgba(74,198,143,1)',
								'path'	=> array( 'total_all_time_pushes', 'total_push_recipients' ),
							),
						),
					)
				);
			}

			/** Display *******************************************************/

			// Website Name
			$settings_fields['website_name'] = array(
				'title'    => esc_html__( 'Website Name', 'pushup' ),
				'callback' => array( __CLASS__, '_render_display_website_name_field' ),
				'section'  => 'display'
			);

			// Notification Title
			$settings_fields['post_title'] = array(
				'title'    => esc_html__( 'Notification Title', 'pushup' ),
				'callback' => array( __CLASS__, '_render_display_post_title_field' ),
				'section'  => 'display'
			);

			// Icons
			if ( PushUp_Notifications_JSON_API::get_icon_data() ) {
				$settings_fields['icon'] = array(
					'title'    => esc_html__( 'Icon(s)', 'pushup' ),
					'callback' => array( __CLASS__, '_render_display_icon_field' ),
					'section'  => 'display'
				);
			}

			// Prompt Visitor
			$settings_fields['prompt'] = array(
				'title'    => esc_html__( 'Offer Notifications', 'pushup' ),
				'callback' => array( __CLASS__, '_render_display_prompt_field' ),
				'section'  => 'display'
			);
		}

		return $settings_fields;
	}
}
