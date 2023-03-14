<?php

namespace Automattic\WP\Cron_Control\Tests;

use Automattic\WP\Cron_Control\CLI;
use WP_Site;

class Orchestrate_Sites_Tests extends \WP_UnitTestCase {
	function setUp(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Skipping tests that only run on multisites.' );
		}

		parent::setUp();
	}

	function tearDown(): void {
		parent::tearDown();
	}

	function test_list_sites_removes_inactive_subsites() {
		add_filter( 'sites_pre_query', [ $this, 'mock_get_sites' ], 10, 2 );

		// The archived/spam/deleted subsites should not be returned.
		$expected = wp_json_encode( [ [ 'url' => 'site1.com' ], [ 'url' => 'site2.com/two' ], [ 'url' => 'site3.com/three' ], [ 'url' => 'site7.com/seven' ] ] );
		$this->expectOutputString( $expected );
		( new CLI\Orchestrate_Sites() )->list();

		remove_filter( 'sites_pre_query', [ $this, 'mock_get_sites' ], 10, 2 );
	}

	function test_list_sites_2_hosts() {
		add_filter( 'sites_pre_query', [ $this, 'mock_get_sites' ], 10, 2 );

		// With two hosts, all active sites should still be returned.
		$this->mock_hosts_list( 2 );
		$expected = wp_json_encode( [ [ 'url' => 'site1.com' ], [ 'url' => 'site2.com/two' ], [ 'url' => 'site3.com/three' ], [ 'url' => 'site7.com/seven' ] ] );
		$this->expectOutputString( $expected );
		( new CLI\Orchestrate_Sites() )->list();

		remove_filter( 'sites_pre_query', [ $this, 'mock_get_sites' ], 10, 2 );
	}

	function test_list_sites_7_hosts() {
		add_filter( 'sites_pre_query', [ $this, 'mock_get_sites' ], 10, 2 );

		// With seven hosts, our current request should only be given two of the active sites.
		$this->mock_hosts_list( 7 );
		$expected = wp_json_encode( [ [ 'url' => 'site1.com' ], [ 'url' => 'site7.com/seven' ] ] );
		$this->expectOutputString( $expected );
		( new CLI\Orchestrate_Sites() )->list();

		remove_filter( 'sites_pre_query', [ $this, 'mock_get_sites' ], 10, 2 );
	}

	function mock_hosts_list( $number_of_hosts ) {
		// Always have the "current" host.
		$heartbeats = [ gethostname() => time() ];

		if ( $number_of_hosts > 1 ) {
			for ( $i = 1; $i < $number_of_hosts; $i++ ) {
				$heartbeats[ "test_$i" ] = time();
			}
		}

		wp_cache_set( CLI\Orchestrate_Sites::RUNNER_HOST_HEARTBEAT_KEY, $heartbeats );
	}

	function mock_get_sites( $site_data, $query_class ) {
		if ( $query_class->query_vars['count'] ) {
			return 7;
		}

		return [
			new WP_Site( (object) [ 'domain' => 'site1.com', 'path' => '/' ] ),
			new WP_Site( (object) [ 'domain' => 'site2.com', 'path' => '/two' ] ),
			new WP_Site( (object) [ 'domain' => 'site3.com', 'path' => '/three' ] ),
			new WP_Site( (object) [ 'domain' => 'site4.com', 'path' => '/four', 'archived' => '1' ] ),
			new WP_Site( (object) [ 'domain' => 'site5.com', 'path' => '/five', 'spam' => '1' ] ),
			new WP_Site( (object) [ 'domain' => 'site6.com', 'path' => '/six', 'deleted' => '1' ] ),
			new WP_Site( (object) [ 'domain' => 'site7.com', 'path' => '/seven' ] ),
		];
	}
}
