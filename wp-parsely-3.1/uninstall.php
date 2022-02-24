<?php
/**
 * Uninstall script for wp-parsely. It deletes the `parsely` option on the database.
 *
 * @package      Parsely\wp-parsely
 * @since 3.0.0
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
