<?php
/**
 * Parse.ly plugin uninstaller
 *
 * Deletes the `parsely` option from the database.
 *
 * @package Parsely
 * @since   3.0.0
 */

declare(strict_types=1);

namespace Parsely;

// If uninstall.php is not called by WordPress, die.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

$parsely_option_name = 'parsely';

delete_option( $parsely_option_name );

// For site options in Multisite.
delete_site_option( $parsely_option_name );

// Delete page options.
delete_metadata( 'user', -1, 'wp_parsely_page', '', true );
