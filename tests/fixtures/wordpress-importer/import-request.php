<?php
/**
 * Load the importer through VIP's entry point as an import request does, then parse a WXR file
 * and a URL with the bundled php-toolkit. Run with run_php(): the importer declares functions,
 * so it can only be loaded once per process.
 */

use WordPress\DataLiberation\URL\WPURL;

define( 'ABSPATH', $argv[1] );
define( 'WPINC', 'wp-includes' );
define( 'WP_LOAD_IMPORTERS', true );
define( 'IMPORT_DEBUG', false );
// Skip the SimpleXML attempt: reporting its parse error needs the full translation stack.
define( 'PREFERRED_WXR_PARSER', 'xmlprocessor' );

require ABSPATH . WPINC . '/load.php';
require ABSPATH . WPINC . '/plugin.php';
require dirname( __DIR__, 3 ) . '/wordpress-importer.php';

$result = ( new WXR_Parser() )->parse( __DIR__ . '/malformed.xml' );

// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- wp_json_encode() isn't loaded.
echo json_encode( array(
	'title' => $result['posts'][0]['post_title'],
	'host'  => WPURL::parse( 'https://example.com/blog/' )->hostname,
) );
