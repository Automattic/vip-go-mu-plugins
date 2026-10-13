<?php
/**
 * Tests SDI data syncing hook
 *
 * @phpcs:disable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
 * @phpcs:disable WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
 */

namespace Automattic\VIP\Config;

use Automattic\Test\Constant_Mocker;
use WP_UnitTestCase;

use function Automattic\Test\Utils\block_http_requests;
use function Automattic\Test\Utils\get_class_property_as_public;
use function Automattic\Test\Utils\reset_site_details_index;
use function Automattic\Test\Utils\run_php;

require_once __DIR__ . '/../../config/class-site-details-index.php';
require_once __DIR__ . '/../../config/class-sync.php';

class Sync_Test extends WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();

		// Sync is a singleton created on `init` during bootstrap; clear the queue other tests may have filled.
		get_class_property_as_public( Sync::class, 'blogs_to_sync' )->setValue( Sync::instance(), [] );
		reset_site_details_index();
		block_http_requests();
	}

	/**
	 * Cross the real runtime sync path in a subprocess without WP_TESTS_DOMAIN.
	 */
	// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Isolated test-runtime database and process setup.
	public function test_runtime_sync_payload_persistence_and_secondary_schedule() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'The isolated runtime requires multisite for secondary-blog scheduling.' );
		}
		global $wpdb;
		$prefix = 'sync_runtime_' . strtolower( wp_generate_password( 8, false ) ) . '_';
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		try {
			foreach ( $wpdb->tables( 'all', true ) as $source ) {
				$target = $prefix . substr( $source, strlen( $wpdb->base_prefix ) );
				$this->assertNotFalse( $wpdb->query( $wpdb->prepare( 'CREATE TABLE %i LIKE %i', $target, $source ) ) );
				$wpdb->query( $wpdb->prepare( 'INSERT INTO %i SELECT * FROM %i', $target, $source ) );
			}
			$config = array(
				'prefix' => $prefix,
				'constants' => array(
					'ABSPATH' => ABSPATH,
					'DB_HOST' => DB_HOST,
					'DB_NAME' => DB_NAME,
					'DB_USER' => DB_USER,
					'DB_PASSWORD' => DB_PASSWORD,
					'DB_CHARSET' => 'utf8',
					'DB_COLLATE' => '',
					'MULTISITE' => true,
					'SUBDOMAIN_INSTALL' => false,
					'DOMAIN_CURRENT_SITE' => get_network()->domain,
					'PATH_CURRENT_SITE' => '/',
					'SITE_ID_CURRENT_SITE' => 1,
					'BLOG_ID_CURRENT_SITE' => 1,
					'WP_ADMIN' => true,
					'WPMU_PLUGIN_DIR' => sys_get_temp_dir() . '/no-sync-mu-plugins',
					'VIP_SERVICES_AUTH_TOKENS' => base64_encode( wp_json_encode( array( 'site' => array( 'vip-site-details' => array( 'url' => 'https://sync.example.test', 'token' => 'fake-token' ) ) ) ) ),
				),
			);
			$child  = run_php( array( __DIR__ . '/fixtures/sync-runtime.php' ), wp_json_encode( $config ) );
			$this->assertSame( 0, $child['exit'], $child['stderr'] . $child['stdout'] );
			$result = json_decode( $child['stdout'], true );
			$this->assertIsArray( $result, $child['stderr'] . $child['stdout'] );
			$this->assertFalse( $result['guard'] );
			$this->assertCount( 2, $result['requests'] );
			$this->assertSame( 'https://sync.example.test/sites', $result['requests'][0]['url'] );
			$this->assertSame( 'PUT', $result['requests'][0]['args']['method'] );
			$full_body = json_decode( $result['requests'][0]['args']['body'], true );
			$this->assertSame( 'https://sync-runtime.example.test', $full_body['core']['home_url'] );
			$this->assertSame( $result['now'], $result['full']['last_full_synced'] );
			$this->assertNotEmpty( $result['full']['last_sync_hash'] );
			$this->assertSame( 'https://sync.example.test/sites/heartbeat', $result['requests'][1]['url'] );
			$this->assertSame( array( 'client_site_id' => 0, 'blog_id' => 1, 'timestamp' => $result['heartbeat']['last_synced'] ), json_decode( $result['requests'][1]['args']['body'], true ) );
			$this->assertGreaterThan( 0, $result['scheduled'] );
			$this->assertSame( 2, $result['fastcgi'] );
			$this->assertSame( array(), $result['queued'] );
			$this->assertSame( 2, $result['rate_count'] );
			$this->assertSame( $result['full']['last_full_synced'], $result['heartbeat']['last_full_synced'] );
		} finally {
			$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $prefix ) . '%' ) );
			foreach ( $tables as $table ) {
				$wpdb->query( $wpdb->prepare( 'DROP TABLE %i', $table ) );
			}
			add_filter( 'query', array( $this, '_create_temporary_tables' ) );
			add_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		}
	}

	// phpcs:enable WordPress.DB.DirectDatabaseQuery

	public function tearDown(): void {
		reset_site_details_index();
		parent::tearDown();
	}

	/**
	 * It checks that the action is active and that the blog is queued for a sync
	 * once we call `update_option` with the correct option name.
	 *
	 * @dataProvider data_update_hook_options
	 */
	public function test__vip_site_details_update_hook( string $option_name, string $sds_core_field, string $option_value ) {
		Constant_Mocker::define( 'WP_CLI', true );
		$sync_instance = Sync::instance();

		Site_Details_Index::instance( 100 );

		$this->assertIsInt( has_action( "update_option_{$option_name}", array(
			$sync_instance,
			'queue_sync_for_blog',
		) ) );
		$this->assertIsInt( has_action( 'shutdown', array( $sync_instance, 'run_sync_checks' ) ) );

		$this->assertEmpty( $sync_instance->get_blogs_to_sync() );

		update_option( $option_name, $option_value );

		$this->assertNotEmpty( $sync_instance->get_blogs_to_sync() );

		$site_details = apply_filters( 'vip_site_details_index_data', array() );
		$this->assertSame( $option_value, $site_details['core'][ $sds_core_field ], "$sds_core_field should be equal to the updated value" );
	}

	public function data_update_hook_options(): array {
		return [
			'siteurl' => [ 'siteurl', 'site_url', 'http://change-site-url.com' ],
			'home'    => [ 'home', 'home_url', 'http://change-home-url.com' ],
		];
	}

	/**
	 * Won't queue the change if we are not in the CLI/Admin
	 */
	public function test__vip_site_details_not_queuing_on_frontend() {
		$sync_instance = Sync::instance();
		Site_Details_Index::instance( 100 );

		$this->assertEmpty( $sync_instance->get_blogs_to_sync() );

		update_option( 'home', 'https://wontsync-data.com' );

		$this->assertEmpty( $sync_instance->get_blogs_to_sync() );
	}

	/**
	 * Test that we don't queue more than BLOGS_TO_SYNC_LIMIT sites to sync.
	 * We don't run it in a separate process to avoid https://core.trac.wordpress.org/ticket/51773
	 */
	public function test__vip_site_details_not_queuing_after_limit() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Not relevant on single-site' );
		}
		//we need this for the queue to work.
		Constant_Mocker::define( 'WP_CLI', true );

		$sync_instance = Sync::instance();
		Site_Details_Index::instance( 100 );

		$this->assertEmpty( $sync_instance->get_blogs_to_sync() );

		// One site more than the limit is enough to show the queue stops growing.
		for ( $i = 1; $i <= Sync::BLOGS_TO_SYNC_LIMIT + 1; $i++ ) {
			$network_site_id = self::factory()->blog->create( [
				'domain' => 'source-domain.com',
				'path'   => '/' . $i . '/',
			] );
			switch_to_blog( $network_site_id );
			update_option( 'home', 'https://wontsync-data' . $i . '.com' );
			restore_current_blog();
		}

		$this->assertCount( Sync::BLOGS_TO_SYNC_LIMIT, $sync_instance->get_blogs_to_sync() );
	}
}
