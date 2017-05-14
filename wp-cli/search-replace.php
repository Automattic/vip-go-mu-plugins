<?php

/**
 * Adapted from https://github.com/wp-cli/search-replace-command
 */

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

require_once( __DIR__ . '/search-replace/command.php' );
require_once( __DIR__ . '/search-replace/search-replacer.php' );

WP_CLI::add_command( 'vip search-replace', 'VIP_Search_Replace_Command' );
