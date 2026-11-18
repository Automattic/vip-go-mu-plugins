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
		// dbDelta() commits schema changes outside the test transaction.
		if ( ! $this->had_postmeta_index && $this->has_postmeta_index() ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder -- Restore the pre-import schema for subsequent tests.
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i DROP INDEX vip_meta_key_value', $wpdb->postmeta ) );
		}
		parent::tearDown();
	}

	public function test_sql_import_sanitizes_inactive_subsites_without_running_their_hooks(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}
		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'local' );
		require_once __DIR__ . '/../vip-helpers/vip-wp-cli.php';
		require_once __DIR__ . '/../wp-cli/vip-data-cleanup.php';
		// Create one retained physical options table, without a temporary table shadowing it.
		remove_action( 'wp_initialize_site', 'wp_initialize_site', 10 );
		$id = $this->factory()->blog->create();
		add_action( 'wp_initialize_site', 'wp_initialize_site', 10, 2 );
		global $wpdb;
		remove_filter( 'query', [ $this, '_create_temporary_tables' ] );
		remove_filter( 'query', [ $this, '_drop_temporary_tables' ] );
		$table = $wpdb->get_blog_prefix( $id ) . 'options';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder -- Retained import table on WP 6.2+.
		$wpdb->query( $wpdb->prepare( 'CREATE TABLE %i LIKE %i', $table, $wpdb->options ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder -- switch_to_blog() needs normal WordPress options.
		$wpdb->query( $wpdb->prepare( 'INSERT INTO %i SELECT * FROM %i', $table, $wpdb->options ) );
		$this->assertTrue( update_blog_option( $id, 'jetpack_private_options', [ 'blog_token' => 'test-credential' ] ) );
		wp_update_site( $id, [ 'archived' => 1 ] );
		$cleaned = [];
		add_action( 'vip_sqlimport_cleanup', function () use ( &$cleaned ) {
			$cleaned[] = get_current_blog_id();
		} );
		$original_blog = get_current_blog_id();
		try {
			( new \VIP_Data_Cleanup_Command() )->sql_import();
			$this->assertFalse( get_blog_option( $id, 'jetpack_private_options' ) );
			$this->assertNotContains( $id, $cleaned );
			$this->assertSame( $original_blog, get_current_blog_id() );
		} finally {
			wp_delete_site( $id );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder -- Uninitialized sites retain options-only tables.
			$wpdb->query( $wpdb->prepare( 'DROP TABLE %i', $table ) );
		}
	}

	public function test_preserves_hosted_credentials(): void {
		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'production' );
		update_option( 'jetpack_private_options', [ 'blog_token' => 'test-credential' ] );
		cleanup_local_imported_credentials();
		$this->assertSame( [ 'blog_token' => 'test-credential' ], get_option( 'jetpack_private_options' ) );
	}
}
