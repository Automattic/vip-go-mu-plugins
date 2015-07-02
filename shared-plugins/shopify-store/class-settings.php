<?php

class Shopify_Settings
{
	protected $options = array(
		#setting => { label => label_text, type => (text_field | select | hidden | color), (values => [one, two, three])}
		'myshopify_domain' => array(
			'label' =>'Store address (<a id="shopify-edit-link" href="#" onClick="Shopify.toggleEditMyshopify();return false">edit</a>)',
			'type' => 'text',
			'section' => 'store'
		),
		'primary_shopify_domain' => array(
			'label' =>'',
			'type' => 'hidden',
			'section' => 'hidden_field_section'
		),
		'style' => array(
			'label' => 'Preset style',
			'type' => 'select',
			'values' => array( "simple", "centered" ),
			'select_labels' => array( "Simple", "Centered" ),
			'section' => 'widget'
		),
		'image_size' => array(
			'label' => 'Default image size',
			'type' => 'select',
			'values' => array( "small", "medium", "large", "grande" ),
			'select_labels' => array( "Small", "Medium", "Large", "Grande" ),
			'section' => 'widget'
		),
		'text_color' => array(
			'label' => 'Text color',
			'type' => 'color',
			'section' => 'widget'
		),
		'money_format' => array(
			'label' => '',
			'type' => 'hidden',
			'section' => 'hidden_field_section'
		),
		'background_color' => array(
			'label' => 'Background color',
			'type' => 'color',
			'section' => 'widget'
		),
		'border_color' => array(
			'label' => 'Border color',
			'type' => 'color',
			'section' => 'widget'
		),
		'border_padding' => array(
			'label' => ' Border padding',
			'type' => 'text',
			'section' => 'widget'
		),
		'button_text' => array(
			'label' => 'Button text',
			'type' => 'text',
			'section' => 'button'
		),
		'destination' => array(
			'label' => 'Point this button to',
			'type' => 'select',
			'select_labels' => array( "Checkout", "Cart page", "Product page" ),
			'values' => array( "checkout", "cart", "product" ),
			'section' => 'button'
		),
		'button_background' => array(
			'label' => 'Background color',
			'type' => 'color',
			'section' => 'button'
		),
		'button_text_color' => array(
			'label' => 'Text color',
			'type' => 'color',
			'section' => 'button'
		),
		'setup' => array(
			'label' => '',
			'type' => 'hidden',
			'section' => 'hidden_field_section'
		)
	);

	protected $defaults = array(
		#setting => default
		'myshopify_domain' => '',
		'primary_shopify_domain' => '',
		'money_format' => '${{amount}}',
		'image_size' => 'medium',
		'text_color' => '#00000',
		'button_background' => '#222222',
		'button_text_color' => '#ffffff',
		'background_color' => '#ffffff',
		'border_color' => '#ffffff',
		'border_padding' => '0px',
		'button_text' => 'Buy now',
		'destination' => 'checkout',
		'style' => 'simple',
		'setup' => 'false'
	);

	protected $option_name = 'shopify';
	protected $option_group = 'shopify_settings';
	protected $menu_slug = 'shopify_menu';
	protected $option_section = 'shopify_options';

	public function __construct() {
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_filter( 'plugin_action_links_shopify-store/shopify-store.php', array( $this, 'plugin_action_links' ) );
	}

	public function admin_init() {
		register_setting( $this->option_group, $this->option_name, array( $this, 'sanitize_settings' ) );
		add_option( $this->option_name, $this->defaults );

		foreach ( $this->options as $setting => $options ) {
			if ( !isset( $options['values'] ) ) {
				$options['values'] = "";
			}
			if ( !isset( $options['select_labels'] ) ) {
				$options['select_labels'] = array();
			}

			if ( !isset( $options['section'] ) ) {
				$section = $this->option_section;
			} else {
				$section = $options['section'];
			}

			add_settings_field(
				$setting,
				$options['label'],
				array( $this, 'settings_field' ),
				$this->menu_slug,
				$section,
				array(
					'setting' => $setting,
					'label' => $options['label'],
					'type' => $options['type'],
					'values' => $options['values'],
					'select_labels' => $options['select_labels'],
				) #this array is passed to settings_field
			);
		}

		# $id, $title, $callback, $menu_slug
		add_settings_section( "store", 'Shopify account', array( $this, 'do_nothing' ), $this->menu_slug );
		add_settings_section( "widget", 'Widget style', array( $this, 'do_nothing' ), $this->menu_slug );
		add_settings_section( "button", 'Button style', array( $this, 'do_nothing' ), $this->menu_slug );
		add_settings_section( $this->option_section, '', array( $this, 'do_nothing' ), $this->menu_slug );
		add_settings_section( "hidden_field_section", '', array( $this, 'do_nothing' ), $this->menu_slug );
	}

	public function admin_menu() {
		#$page_title, $menu_title, $capability, $menu_slug, $function
		add_options_page( 'Shopify', 'Shopify', 'administrator', $this->menu_slug, array( $this, 'settings_page' ) );
	}

	public function admin_notices() {
		$settings = get_option( $this->option_name, array() );
		if ( $settings['setup'] !== 'true' ) {
			echo "<div class='error' id='shopify-connect-banner'><p><a href='" . esc_url( $this->settings_page_url() ) . "'>Click here</a> to link your Shopify store to your WordPress blog.</p></div>";
		}
	}

	public function plugin_action_links( $links ) {
		$new_links = array(
			'<a href="' . esc_url( $this->settings_page_url() ) . '">Settings</a>',
		);
		return array_merge( $links, $new_links );
	}

	public function settings_page_url() {
		return admin_url( 'options-general.php?page=' . $this->menu_slug );
	}

	public function do_nothing(){}

	public function sanitize_settings( $input ){
		foreach ( $input as $key => $value) {
			$input[$key] = sanitize_text_field( $value );
		}
		return $input;
	}

	public function hidden_field( $name, $id, $value ) {
		return "<input type='hidden' name='" . esc_attr( $name ) . "' value='" . esc_attr( $value ) . "' id='" . esc_attr( $id ) . "' />";
	}

	public function text_field( $name, $id, $value ){
		return "<input type='text' name='" . esc_attr( $name ) . "' value='" . esc_attr( $value ) . "' id='" . esc_attr( $id ) . "' />";
	}

	public function color_field( $name, $id, $value ){
		return "<input type='text' name='" . esc_attr( $name ) . "' value='" . esc_attr( $value ) . "' id='" . esc_attr( $id ) . "' class='color-picker' />";
	}

	public function select_field( $name, $id, $values, $labels, $value ){
		$html = '';
		$html .= "<select name='" . esc_attr( $name ) . "' id='" . esc_attr( $id ) . "'>";
		foreach ( $values as $index => $option ) {
			if ( count( $labels ) > 0 ) {
				$select_label = $labels[$index];
			} else {
				$select_label = $option;
			}
			if ( $option == $value ) {
				$html .= "<option selected='selected' value='" . esc_attr( $option ) . "'>" . esc_html( $select_label ) . "</option>";
			} else {
				$html .= "<option value='" . esc_attr( $option ) . "'>" . esc_html( $select_label ) . "</option>";
			}
		}
		$html .= "</select>";

		return $html;
	}

	public function settings_field( array $args ) {
		$settings = get_option( $this->option_name, array() );
		$setting = $args['setting'];
		if ( array_key_exists( $setting, $settings ) ) {
			$value = $settings[$setting];
		} else {
			$value = '';
		}

		$name = $this->option_name . '[' . $setting . ']';
		$id = $this->option_name . '_' . $setting;

		if ( $args["type"] == "text" ) {
			echo $this->text_field( $name, $id, $value );
		} elseif ( $args["type"] == "select" ) {
			echo $this->select_field( $name, $id, $args["values"], $args["select_labels"], $value );
		} elseif ( $args["type"] == "hidden" ) {
			echo $this->hidden_field( $name, $id, $value );
		} elseif ( $args["type"] == "color" ) {
			echo $this->color_field( $name, $id, $value );
		}
	}

	public function settings_page() {
		$settings = get_option( $this->option_name, array() );
		$setup = $settings['setup'];
		include 'views/settings.php';
	}
}
