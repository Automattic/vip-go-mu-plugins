<?php

declare(strict_types=1);

namespace Automattic\VIP\Telemetry\Tracks;

use WP_UnitTestCase;

class Tracks_Event_DTO_Test extends WP_UnitTestCase {

	public function test_should_create_event_dto() {
		$event = new Tracks_Event_DTO();

		$this->assertInstanceOf( Tracks_Event_DTO::class, $event );
	}
}
