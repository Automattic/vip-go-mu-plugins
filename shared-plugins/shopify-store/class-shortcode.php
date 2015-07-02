<?php

class Shopify_Shortcode
{

	public function __construct() {
		add_shortcode( 'shopify', array( $this, 'embed_product' ) );
		add_filter( 'the_content', array( $this, 'product_url_to_shortcode' ) );
	}

	public function embed_product( $atts ) {
		// Attributes
		$shortcode_atts = shortcode_atts(
			array(
				'product' => '',
				'image_size' => '',
				'style' => '',
				'text_color' => '',
				'button_background' => '',
				'button_text_color' => '',
				'background_color' => '',
				'border_color' => '',
				'border_padding' => '',
				'button_text' => '',
				'destination' => '',
				'money_format' => ''
			), $atts );

		$settings = get_option( 'shopify', array() );

		foreach ( $shortcode_atts as $key => $value ) {
			if ( $value != "" ) {
				$settings[$key] = $value;
			}
		}

		if ( !isset( $settings['product'] ) ) {
			return "product is a required attribute of the shopify shortcode";
		}

		if ( empty( $settings['myshopify_domain'] ) ) {
			return "<span style='color: red'>Please enter your myshopify domain in the shopify settings page</span>";
		}
		return Shopify_Widget::generate( $settings );
	}

	function product_url_to_shortcode( $content ) {
		$settings = get_option( 'shopify', array() );

		// No need to run a regex if there is nothing to replace
		if ( empty( $settings['myshopify_domain'] ) ) {
			return $content;
		}

		if ( false === strpos( $content, $settings['myshopify_domain'] ) )
			return $content;

		if ( ( false === strpos( $content, $settings['myshopify_domain'] ) ) && ( false === strpos( $content, $settings['primary_shopify_domain'] ) ) ) {
			return $content;
		}

		$content = preg_replace(
			'/^(?:<p>)?(https?:\/\/(?:' . preg_quote( $settings['myshopify_domain'] ) . '|' . preg_quote( $settings['primary_shopify_domain'] ) . ')(?:\/[\w-\d\/]+)?\/products\/[\w-\d]+)\/?(?:<\/p>)?$/m',
			esc_html( '[shopify product=\1]' ),
			$content );

		return $content;
	}

}
