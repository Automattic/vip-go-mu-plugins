<?php

namespace Automattic\VIP\Migration;

use Automattic\Test\Constant_Mocker;
use WP_UnitTestCase;

class Local_Import_Cleanup_Test extends WP_UnitTestCase {
	private bool $had_postmeta_index;

	public function setUp(): void {
		parent::setUp();
		$this->had_postmeta_index = $this->has_postmeta_index();
	}

	private function has_postmeta_index(): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder -- Inspect schema changed by the real import command.
		return null !== $wpdb->get_var( $wpdb->prepare( 'SHOW INDEX FROM %i WHERE Key_name = %s', $wpdb->postmeta, 'vip_meta_key_value' ) );
	}

	public function tearDown(): void {
		// DDL commits survive the test transaction and separate PHP processes.
		if ( ! $this->had_postmeta_index && $this->has_postmeta_index() ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder -- Restore the pre-import schema for subsequent tests.
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i DROP INDEX vip_meta_key_value', $wpdb->postmeta ) );
		}
		Constant_Mocker::clear();
		parent::tearDown();
	}

	public function test_removes_imported_credentials_without_jetpack(): void {
		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'local' );
		Constant_Mocker::define( 'VIP_JETPACK_SKIP_LOAD', true );
		global $wpdb;
		$names = [ 'jetpack_options', 'jetpack_private_options', 'jetpack_secrets', 'vaultpress', 'wordpress_api_key', 'vip_jetpack_connection_pilot_heartbeat' ];
		foreach ( $names as $name ) {
			$value = 'wordpress_api_key' === $name ? 'testcredential' : [ 'imported' => 'test-credential' ];
			$this->assertTrue( update_option( $name, $value ), $name );
		}
		update_option( 'unrelated_customer_option', 'keep' );

		cleanup_local_imported_credentials();

		foreach ( $names as $name ) {
			// Registered defaults can make get_option() return an empty string after deletion.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Verify removal of the stored credential, independent of plugin defaults.
			$this->assertNull( $wpdb->get_var( $wpdb->prepare( "SELECT option_id FROM $wpdb->options WHERE option_name = %s", $name ) ), $name );
		}
		$this->assertSame( 'keep', get_option( 'unrelated_customer_option' ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_sql_import_cleans_credentials_before_customer_hooks(): void {
		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'local' );
		if ( ! \defined( 'VIP_JETPACK_SKIP_LOAD' ) ) {
			\define( 'VIP_JETPACK_SKIP_LOAD', true );
		}
		require_once __DIR__ . '/fixtures/wp-cli/class-cleanup-cli.php';
		require_once __DIR__ . '/../wp-cli/vip-data-cleanup.php';
		update_option( 'jetpack_private_options', [ 'blog_token' => 'test-credential' ] );
		$seen = null;
		add_action( 'vip_sqlimport_cleanup', function () use ( &$seen ) {
			$seen = get_option( 'jetpack_private_options' );
		} );
		( new \VIP_Data_Cleanup_Command() )->sql_import();
		$this->assertFalse( $seen );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_sql_import_sanitizes_inactive_subsites_without_running_their_hooks(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}
		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'local' );
		if ( ! \defined( 'VIP_JETPACK_SKIP_LOAD' ) ) {
			\define( 'VIP_JETPACK_SKIP_LOAD', true );
		}
		require_once __DIR__ . '/fixtures/wp-cli/class-cleanup-cli.php';
		require_once __DIR__ . '/../wp-cli/vip-data-cleanup.php';
		// Create only site records; the retained options tables below must be physical, without temporary shadows.
		remove_action( 'wp_initialize_site', 'wp_initialize_site', 10 );
		$archived = $this->factory()->blog->create();
		$spam     = $this->factory()->blog->create();
		$deleted  = $this->factory()->blog->create();
		add_action( 'wp_initialize_site', 'wp_initialize_site', 10, 2 );
		global $wpdb;
		remove_filter( 'query', [ $this, '_create_temporary_tables' ] );
		remove_filter( 'query', [ $this, '_drop_temporary_tables' ] );
		foreach ( [ $archived, $spam, $deleted ] as $id ) {
			// Model a retained import table with the normal options needed by switch_to_blog().
			$table = $wpdb->get_blog_prefix( $id ) . 'options';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder -- Retained table fixture on WP 6.2+.
			$wpdb->query( $wpdb->prepare( 'CREATE TABLE %i LIKE %i', $table, $wpdb->options ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder -- Populate the retained table with real WordPress options.
			$wpdb->query( $wpdb->prepare( 'INSERT INTO %i SELECT * FROM %i', $table, $wpdb->options ) );
			update_blog_option( $id, 'jetpack_private_options', [ 'blog_token' => 'test-credential' ] );
		}
		wp_update_site( $archived, [ 'archived' => 1 ] );
		wp_update_site( $spam, [ 'spam' => 1 ] );
		wp_update_site( $deleted, [ 'deleted' => 1 ] );
		$cleaned = [];
		add_action( 'vip_sqlimport_cleanup', function () use ( &$cleaned ) {
			$cleaned[] = get_current_blog_id();
		} );
		$original_blog = get_current_blog_id();
		( new \VIP_Data_Cleanup_Command() )->sql_import();
		foreach ( [ $archived, $spam, $deleted ] as $id ) {
			$this->assertFalse( get_blog_option( $id, 'jetpack_private_options' ), 'Subsite ' . $id );
			$this->assertNotContains( $id, $cleaned );
			wp_delete_site( $id );
		}
		$this->assertSame( $original_blog, get_current_blog_id() );
	}

	public function test_preserves_hosted_credentials(): void {
		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'production' );
		update_option( 'jetpack_private_options', [ 'blog_token' => 'test-credential' ] );
		cleanup_local_imported_credentials();
		$this->assertSame( [ 'blog_token' => 'test-credential' ], get_option( 'jetpack_private_options' ) );
	}
}
