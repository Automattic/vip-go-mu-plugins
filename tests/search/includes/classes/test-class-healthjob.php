<?php

namespace Automattic\VIP\Search;

use WP_UnitTestCase;
use Automattic\Test\Constant_Mocker;

require_once __DIR__ . '/../../../../search/search.php';
require_once __DIR__ . '/../../../../search/includes/classes/class-healthjob.php';
require_once __DIR__ . '/../../../../search/elasticpress/elasticpress.php';

class HealthJob_Test extends WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();

		Constant_Mocker::define( 'VIP_GO_ENV', 'test' );

		// Enable the current environment so each test's condition is the only thing disabling the job
		add_filter( 'vip_search_healthchecks_enabled_environments', fn() => [ 'test' ] );
	}

	public function test_vip_search_healthjob_is_disabled_when_constant_is_set() {
		$job = new HealthJob( $this->createMock( Search::class ) );

		$this->assertTrue( $job->is_enabled(), 'Precondition failed: health job should be enabled before setting the constant' );

		Constant_Mocker::define( 'DISABLE_VIP_SEARCH_HEALTHCHECKS', true );

		$enabled = $job->is_enabled();

		$this->assertFalse( $enabled );
	}

	public function test_vip_search_healthjob_is_disabled_when_app_id_matches_disabled_list() {
		Constant_Mocker::define( 'VIP_GO_APP_ID', 2341 );

		$job = new HealthJob( $this->createMock( Search::class ) );

		$this->assertTrue( $job->is_enabled(), 'Precondition failed: health job should be enabled before disabling the app ID' );

		$job->health_check_disabled_sites[] = Constant_Mocker::constant( 'VIP_GO_APP_ID' );

		$enabled = $job->is_enabled();

		$this->assertFalse( $enabled );
	}
}
