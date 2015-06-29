<?php

class Shopify_Widget
{
	static function generate( $settings ) {
		$widget_id = "widget" . uniqid();
		$widget_id = str_replace( "-", "_", $widget_id );

		$product_handle = self::extract_handle( $settings['product'] );

		ob_start();
		include 'views/widget.php';
		return ob_get_clean();
	}

	static function extract_handle( $product ) {
		$_temp = explode( '/', parse_url( $product, PHP_URL_PATH ) );
		if( $_temp[count( $_temp ) -1] == '' ) {
			return $_temp[count( $_temp ) -2];
		} else {
			return $_temp[count( $_temp ) -1];
		}
	}
}
