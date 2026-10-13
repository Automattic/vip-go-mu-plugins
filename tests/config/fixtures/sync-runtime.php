<?php
/**
 * Boot an isolated WordPress runtime without the test-library sync guard.
 */

use Automattic\VIP\Config\Site_Details_Index;
use Automattic\VIP\Config\Sync;

/**
 * Substitute the FastCGI response-completion boundary in this CLI runtime.
 */
function fastcgi_finish_request() {
	$GLOBALS['sync_fastcgi_completions'] = ( $GLOBALS['sync_fastcgi_completions'] ?? 0 ) + 1;
	return true;
}

$config = json_decode( stream_get_contents( STDIN ), true );
foreach ( $config['constants'] as $name => $value ) {
	define( $name, $value );
}
// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Configure the child WordPress database prefix.
$table_prefix = $config['prefix'];
$repo         = dirname( __DIR__, 3 );
$requests     = array();
require ABSPATH . 'wp-includes/plugin.php';
add_filter( 'pre_http_request', static function ( $response, $args, $url ) use ( &$requests ) {
	if ( str_starts_with( $url, 'https://sync.example.test/' ) ) {
		$requests[] = array(
			'url'  => $url,
			'args' => $args,
		);
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => '{"updated":true}',
			'headers'  => array(),
		);
	}
	return array(
		'response' => array( 'code' => 418 ),
		'body'     => '',
		'headers'  => array(),
	);
}, PHP_INT_MAX, 3 );
require ABSPATH . 'wp-settings.php';
require $repo . '/vendor/autoload.php';
require $repo . '/vip-helpers/vip-utils.php';
require $repo . '/vip-plugins/vip-plugins.php';
require $repo . '/wp-parsely.php';
require $repo . '/lib/utils/class-context.php';
require $repo . '/config/class-site-details-index.php';
require $repo . '/config/class-sync.php';

$sync    = Sync::instance();
$details = Site_Details_Index::instance();
$now     = (int) $details->get_current_timestamp();
$details->set_current_timestamp( $now );
update_option( Site_Details_Index::SYNC_DATA_OPTION, array(
	'last_synced'      => 1,
	'last_full_synced' => 1,
) );
update_option( 'home', 'https://sync-runtime.example.test/' );
$secondary = wp_insert_site( array(
	'domain' => DOMAIN_CURRENT_SITE,
	'path'   => '/sync-secondary/',
) );
switch_to_blog( $secondary );
update_option( 'home', 'https://sync-runtime-secondary.example.test/' );
restore_current_blog();
$sync->run_sync_checks();
$full                = get_option( Site_Details_Index::SYNC_DATA_OPTION );
$full['last_synced'] = $now - 26 * Site_Details_Index::MINUTE_IN_MS;
update_option( Site_Details_Index::SYNC_DATA_OPTION, $full );
$sync->queue_sync_for_blog();
$sync->run_sync_checks();
$heartbeat = get_option( Site_Details_Index::SYNC_DATA_OPTION );
switch_to_blog( $secondary );
$scheduled = wp_next_scheduled( Sync::CRON_EVENT_NAME, array( 'is_faster_cron' => true ) );
restore_current_blog();
remove_all_actions( 'shutdown' );
echo wp_json_encode( array(
	'guard'      => defined( 'WP_TESTS_DOMAIN' ),
	'requests'   => $requests,
	'full'       => $full,
	'heartbeat'  => $heartbeat,
	'secondary'  => $secondary,
	'scheduled'  => $scheduled,
	'now'        => $now,
	'fastcgi'    => $GLOBALS['sync_fastcgi_completions'] ?? 0,
	'queued'     => $sync->get_blogs_to_sync(),
	'rate_count' => wp_cache_get( 'sync_ratelimit', 'vip_config' ),
) );
