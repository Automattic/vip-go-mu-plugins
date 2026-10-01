<?php

namespace Automattic\VIP\Migration;

use Automattic\Test\Constant_Mocker;
use WP_UnitTestCase;

class Local_Import_Cleanup_Test extends WP_UnitTestCase {
	public function tearDown(): void {
		Constant_Mocker::clear();
		parent::tearDown();
	}

	public function test_removes_imported_credentials_without_jetpack(): void {
		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'local' );
		Constant_Mocker::define( 'VIP_JETPACK_SKIP_LOAD', true );
		$names = [ 'jetpack_options', 'jetpack_private_options', 'jetpack_secrets', 'vaultpress', 'wordpress_api_key', 'vip_jetpack_connection_pilot_heartbeat' ];
		foreach ( $names as $name ) {
			update_option( $name, [ 'imported' => 'test-credential' ] );
		}
		update_option( 'unrelated_customer_option', 'keep' );

		cleanup_local_imported_credentials();

		foreach ( $names as $name ) {
			$this->assertFalse( get_option( $name ), $name );
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
		$archived = $this->factory()->blog->create();
		$spam     = $this->factory()->blog->create();
		$deleted  = $this->factory()->blog->create();
		global $wpdb;
		remove_filter( 'query', [ $this, '_create_temporary_tables' ] );
		remove_filter( 'query', [ $this, '_drop_temporary_tables' ] );
		foreach ( [ $archived, $spam, $deleted ] as $id ) {
			// Site factories do not install tables in this runner. Model retained import tables.
			$table = $wpdb->get_blog_prefix( $id ) . 'options';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder -- Retained table fixture on WP 6.2+.
			$wpdb->query( $wpdb->prepare( 'CREATE TABLE %i LIKE %i', $table, $wpdb->options ) );
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
			$table = $wpdb->get_blog_prefix( $id ) . 'options';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder -- Retained table fixture on WP 6.2+.
			$wpdb->query( $wpdb->prepare( 'DROP TABLE %i', $table ) );
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
