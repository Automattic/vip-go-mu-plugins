<?php
/*
 * Plugin Name: Shopify for WordPress
 * Plugin URI: http://shopify.com
 * Description: Embed Shopify products into your WordPress blog
 * Version: 0.2
 * Author: Wesley Ellis
 * Author URI: http://shopify.com
 * License: MIT
 * */

require_once( dirname( __FILE__ ) . '/class-widget.php' );

require_once( dirname( __FILE__ ) . '/class-shortcode.php');
new Shopify_Shortcode();

require_once( dirname( __FILE__ ) . '/class-settings.php');
new Shopify_Settings();

require_once( dirname( __FILE__ ) . '/class-assets.php');
new Shopify_Assets();
