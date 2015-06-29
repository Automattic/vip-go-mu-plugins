<?php

class Daylife_Options {
	public static $instance;

	public function __construct() {
		self::$instance = $this;
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'settings_init' ) );
		add_action( 'daylife-supported-post-types', array( $this, 'daylife_supported_post_types' ) );
	}

	public function add_menu_page() {
		$options_page = add_options_page( __( 'Daylife Options', 'daylife' ), __( 'Daylife', 'daylife' ), 'manage_options', 'daylife-options', array( $this, 'render_options_page' ) );
		add_action( "load-$options_page", array( $this, 'help' ) );
	}

	public function help() {
		$screen = get_current_screen();
		if ( ! method_exists( $screen, 'add_help_tab' ) )
			return;

		$screen->add_help_tab( array(
			'id'      => 'daylife-about',
			'title'   => __( 'About', 'daylife' ),
			'content' => __( '<p>The Daylife Images Plugin helps you find images related to your posts, and add them as a single image or to a gallery to your post. The plugin will recommend images based on the text of your post. You can also type in keywords in the Search box to find images relevant to your post.</p>', 'dayflife' )
		) );
		$screen->add_help_tab( array(
			'id'      => 'daylife-licenses',
			'title'   => __( 'Licenses & Pricing', 'daylife' ),
			'content' => __( '<p>This plugin helps you find licensed images from sources like Getty, AP, Reuters and more. See the complete list of all the content partners <a href="http://www.daylife.com/about-us/our-partnerships/">here</a>. To learn more about these Image Licenses and their pricing, drop an email to <a href="mailto:getdaylife@daylife.com">getdaylife@daylife.com</a>.</p>', 'dayflife' )
		) );
		$screen->add_help_tab( array(
			'id'      => 'daylife-getting-started',
			'title'   => __( 'Getting Started', 'daylife' ),
			'content' => __( '<p>Please drop an email to <a href="mailto:getdaylife@daylife.com">getdaylife@daylife.com</a>, and they will set your Plugin Settings - your Accesskey, SharedSecret and a Source Filter with access to your licensed sources.</p>', 'dayflife' )
		) );
		$screen->set_help_sidebar( __( '<p><strong>For more information:</strong></p><p><a href="http://www.daylife.com/">Daylife</a></p>', 'daylife' ) );
	}

	public function settings_init() {
		register_setting( 'daylife_options', 'daylife', array( $this, 'sanitize_settings' ) );
		add_settings_section( 'daylife-general', '', '__return_false', 'daylife-options' );
		add_settings_field( 'daylife-access-key', __( 'Access Key', 'daylife' ), array( $this, 'text_box' ), 'daylife-options', 'daylife-general', array( 'id' => 'daylife-access-key', 'name' => 'access_key' ) );
		add_settings_field( 'daylife-shared-secret', __( 'Shared Secret', 'daylife' ), array( $this, 'text_box' ), 'daylife-options', 'daylife-general', array( 'id' => 'daylife-shared-secret', 'name' => 'shared_secret' ) );
		add_settings_field( 'daylife-source-filter-id', __( 'Source Filter ID', 'daylife' ), array( $this, 'text_box' ), 'daylife-options', 'daylife-general', array( 'id' => 'daylife-source-filter-id', 'name' => 'source_filter_id' ) );
		add_settings_field( 'daylife-api-endpoint', __( 'Daylife API Endpoint', 'daylife' ), array( $this, 'text_box' ), 'daylife-options', 'daylife-general', array( 'id' => 'daylife-api-endpoint', 'name' => 'api_endpoint' ) );
		add_settings_field( 'daylife-start-time', __( 'Search Images Within', 'daylife' ), array( $this, 'start_time_radio' ), 'daylife-options', 'daylife-general', array( 'id' => 'daylife-start-time', 'name' => 'start_time' ) );
		add_settings_field( 'daylife-post-types', __( 'Post Types Supported', 'daylife' ), array( $this, 'post_types_checkboxes' ), 'daylife-options', 'daylife-general', array( 'id' => 'daylife-post-types', 'name' => 'post_types' ) );
		add_settings_field( 'daylife-galleries-endpoint', __( 'Smart Galleries Endpoint', 'daylife' ), array( $this, 'text_box' ), 'daylife-options', 'daylife-general', array( 'id' => 'daylife-galleries-endpoint', 'name' => 'galleries_endpoint' ) );
	}

	public function text_box( $args ) {
		$options = get_option( 'daylife', array() );
		if ( ! isset( $options[ $args['name'] ] ) )
			$options[ $args['name'] ] = '';
		?><input type="text" id="<?php echo esc_attr( $args['id'] ); ?>" name="daylife[<?php echo esc_attr( $args['name'] ); ?>]" value="<?php echo esc_attr( $options[ $args['name'] ] ); ?>" class="regular-text" /><?php
	}

	public function start_time_radio( $args ) {
		$start_time_options = apply_filters( 'daylife-start-time-options', array(
			'-2 years'  => __( 'Last Two Years', 'daylife' ),
			'-1 year'   => __( 'Last Year', 'daylife' ),
			'-6 months' => __( 'Last Six Months', 'daylife' ),
			'-3 months' => __( 'Last Three Months', 'daylife' ),
			'-1 months' => __( 'Last Month', 'daylife' ),
			'-1 week'   => __( 'Last Week', 'daylife' ),
		) );
		$options = get_option( 'daylife', array() );
		if ( ! isset( $options[ $args['name'] ] ) )
			$options[ $args['name'] ] = '-1 year';
		foreach ( $start_time_options as $opt_val => $opt_name ) {
			?>
			<input type="radio" id="<?php echo esc_attr( $args['id'] . '-' . sanitize_title_with_dashes( $opt_name ) ); ?>" name="daylife[<?php echo esc_attr( $args['name'] ); ?>]" value="<?php echo esc_attr( $opt_val ); ?>"<?php checked( $opt_val, $options[ $args['name'] ] ); ?> />
			<label for=""><?php echo esc_html( $opt_name ); ?></label><br />
			<?php
		}
	}

	public function post_types_checkboxes( $args ) {
		$post_types = apply_filters( 'daylife-supported-post-types', get_post_types( array(), 'objects' ) );

		$options = get_option( 'daylife', array() );
		if ( ! isset( $options[ $args['name'] ] ) )
			$options[ $args['name'] ] = array( 'post' );

		foreach ( $post_types as $post_type ) {
			?>
			<input type="checkbox" id="<?php echo esc_attr( $args['id'] . '-' . sanitize_title_with_dashes( $post_type->name ) ); ?>" name="daylife[<?php echo esc_attr( $args['name'] ); ?>][]" value="<?php echo esc_attr( $post_type->name ); ?>"<?php checked( in_array( $post_type->name, $options[ $args['name'] ] ) ); ?> />
			<label for=""><?php echo esc_html( $post_type->labels->name ); ?></label><br />
			<?php
		}
	}

	public function daylife_supported_post_types( $post_types ) {
		foreach( $post_types as $key => $post_type ) {
			if ( in_array( $post_type->name, array( 'nav_menu_item', 'revision', 'attachment' ) ) )
				unset( $post_types[$key] );
		}
		return $post_types;
	}

	public function sanitize_settings( $options ) {
		foreach ( $options as $option_key => &$option_value ) {
			switch ( $option_key ) {
				case 'post_types':
					foreach ( $option_value as &$val ) {
						$val = esc_attr( $val );
					}
					break;
				default:
					$option_value = esc_attr( $option_value );
					break;
			}
		}
		return $options;
	}

	public function render_options_page() {
		?>
		<style type="text/css" media="screen">
			#icon-daylife {
				background: transparent url(<?php echo plugins_url( 'images/daylife32.png', dirname( __FILE__ ) ); ?>) no-repeat;
			}
		</style>

		<div class="wrap">
			<?php screen_icon( 'daylife' ); ?>
			<h2><?php _e( 'Daylife Settings', 'daylife' ); ?></h2>
			 <p><?php _e( 'This plugin helps you find licensed images from sources like Getty, AP, Reuters and more. See the complete list of all the content partners <a href="http://www.daylife.com/about-us/our-partnerships/">here</a>. To learn more about these Image Licenses and their pricing, drop an email to <a href="mailto:getdaylife@daylife.com">getdaylife@daylife.com</a>.', 'dayflife' ); ?></p>
			<form method="post" action="options.php">
				<?php
					settings_fields( 'daylife_options' );
					do_settings_sections( 'daylife-options' );
					submit_button();
				?>
			</form>
		</div><?php
	}
}

new Daylife_Options;
