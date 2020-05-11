<?php

/*
Plugin Name: Akismet Anti-Spam
Plugin URI: https://akismet.com/
Description: Used by millions, Akismet is quite possibly the best way in the world to <strong>protect your blog from spam</strong>. It keeps your site protected even while you sleep. To get started: activate the Akismet plugin and then go to your Akismet Settings page to set up your API key.
Version: 4.1.5
Author: Automattic
Author URI: https://automattic.com/wordpress-plugins/
License: GPLv2 or later
Text Domain: akismet
*/

// Load the core Akismet plugin
require_once( __DIR__ . '/akismet/akismet.php' );

// By default, Akismet tries to delete batches of 10,000 at a time.
// That's way too high. Let's set a more reasonable limit.
function wpcom_vip_akismet_delete_limit( $limit ) {
	return 500;
}
add_filter( 'akismet_delete_comment_limit', 'wpcom_vip_akismet_delete_limit' );

// Identify outgoing requests as coming from VIP Go
function wpcom_vip_akismet_ua( $ua ) {
	return $ua . ' | platform:vipgo';
}
add_filter( 'akismet_ua', 'wpcom_vip_akismet_ua' );

// Limit cache/alloptions invalidations when getting inundated with spam comments.
function wpcom_vip_akismet_spam_count_incr( $val ) {
	// if the blog has a small number of comments, increment the counter by 1 every time
	$current = get_option( 'akismet_spam_count' );
	if ( $current < 150 ) {
		return $val;
	}

	// If it has a large number of comments, increment it by 3 one third of the time
	$random = mt_rand( 1, 3 );
	if ( 3 === $random ) {
		return 3;
	}

	return 0;
}
add_filter( 'akismet_spam_count_incr', 'wpcom_vip_akismet_spam_count_incr' );
