<?php
/*
Plugin Name: Printable Post
Description: Provides printable versions of posts and pages
Author: Alex Shiels
Version: 1.0
Author URI: http://automattic.com/
*/

// define these in wp-config.php or before this plugin is loaded to configure it

// are single posts printable?
if ( !defined('TW_PRINT_SINGLE') ) define('TW_PRINT_SINGLE', true);
// are pages printable?
if ( !defined('TW_PRINT_PAGE') ) define('TW_PRINT_PAGE', true);

define('TW_PRINT_VERSION', 1);


// guess the URL of the directory containing this plugin's files
function tw_print_plugin_root_uri() {
	$here = dirname(__FILE__);
	$rel = preg_replace('|^'.preg_quote(ABSPATH, '|').'|', '', realpath($here));
	return get_option('siteurl').'/'.trim($rel, '/');
}

function tw_print_template($template) {
	if ( !tw_is_print() )
		return $template;

	// use the theme's print.php if it exists
	$template = get_query_template('print');
	if ( !$template )
		$template = dirname( __FILE__ ) . '/print.php';
		
	return $template;
}

function tw_print_template_filter($f) {
	return tw_print_template($f);
}

function tw_print_stylesheet_uri($uri) {
	if ( !tw_is_print() )
		return $uri;

	// use the theme's print.css if it exists
	if ( file_exists(get_stylesheet_directory() . '/print.css') )
		$uri = get_stylesheet_directory_uri() . '/print.css';
	else
		$uri = tw_print_plugin_root_uri() . '/print.css';
	
	return $uri;
}

function tw_print_install() {
	if ( get_option('tw_print_version') < TW_PRINT_VERSION ) {
		update_option('tw_print_version', TW_PRINT_VERSION);
		global $wp_rewrite;
		$wp_rewrite->flush_rules();
	}
}

// add filters and any other necessary paraphernalia
function tw_print_init() {
	if ( TW_PRINT_SINGLE ) {
		add_filter('single_template', 'tw_print_template_filter');
		add_filter('stylesheet_uri', 'tw_print_stylesheet_uri');
	}
	if ( TW_PRINT_PAGE )
		add_filter('page_template', 'tw_print_template_filter');
	
	tw_print_install();
	
}

add_filter('init', 'tw_print_init');

function tw_rewrite_voodoo($wp_rewrite) {
	// the post trackback rewrite rule has the same structure as is required for the print rule, so find that and modify it
	$permalink_rules = $wp_rewrite->generate_rewrite_rule($wp_rewrite->permalink_structure);
	
	$our_regexp = $our_rewrite = null;
	foreach ( $permalink_rules as $regexp => $rewrite ) {
		if ( strpos($rewrite, 'attachment') !== false )
			continue;
		// stop when we find the trackback rule
		if ( strpos($regexp, '/trackback/') !== false ) {
			$our_regexp = $regexp;
			$our_rewrite = $rewrite;
			break;
		}
	}
	
	if ( !$our_regexp || !$our_rewrite )
		return $wp_rewrite;
		
	$our_regexp = str_replace('/trackback/', '/print/', $our_regexp);
	$our_rewrite = str_replace('&tb=1', '&print=1', $our_rewrite);
	
	$wp_rewrite->rules = array_merge( array($our_regexp => $our_rewrite), $wp_rewrite->rules );
	return $wp_rewrite;
}

add_filter('generate_rewrite_rules', 'tw_rewrite_voodoo');

function tw_print_query_vars($vars) {
	$vars[] = 'print';
	return $vars;
}

add_filter('query_vars', 'tw_print_query_vars');

function tw_print_parse_query(&$wp_query) {
	$wp_query->is_print = !empty( $wp_query->query['print'] );
}

add_action('parse_query', 'tw_print_parse_query');

function tw_is_print() {
	global $wp_query;
	return $wp_query->is_print;
}

// same as the_content() but pagination tags are ignored
function tw_print_content() {
	global $pages;
	$_pages = $pages;
	$pages = array(join("\n\n", $pages));
	the_content();
	$pages = $_pages;
}

function tw_print_permalink() {
	echo get_permalink() . 'print/';
}

function tw_print_body_class($c) {
	if ( tw_is_print() && is_array($c) ) {
		$c[] = 'tw-print';
	}
	return $c;
}

add_filter('body_class', 'tw_print_body_class');

?>
