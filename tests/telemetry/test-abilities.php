<?php

class Test_Telemetry_Abilities extends WP_UnitTestCase {
	public function set_up() {
		parent::set_up();

		// The production hook registration in telemetry/abilities.php is gated behind
		// WPCOM_IS_VIP_ENV / WPCOM_SANDBOXED / the Feature flag, evaluated once at file
		// load time — same constraint test-stats.php works around for stats.php. So we
		// add the filter directly here and test the handler functions on their own,
		// same as Test_Stats does for XML_RPC_Auth_Tracker.
		add_filter( 'wp_register_ability_args', 'Automattic\\VIP\\Telemetry\\Abilities\\track_ability_execution', PHP_INT_MAX, 2 );

		\Automattic\VIP\Telemetry\Abilities\Ability_Invocation_Tracker::$tracks_instance = null;
	}

	public function tear_down() {
		remove_filter( 'wp_register_ability_args', 'Automattic\\VIP\\Telemetry\\Abilities\\track_ability_execution', PHP_INT_MAX );

		\Automattic\VIP\Telemetry\Abilities\Ability_Invocation_Tracker::$tracks_instance = null;

		parent::tear_down();
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_args_without_execute_callback_are_untouched() {
		$args = [ 'label' => 'No callback here' ];

		$filtered = apply_filters( 'wp_register_ability_args', $args, 'test/no-callback' );

		$this->assertSame( $args, $filtered );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_execute_callback_is_wrapped_and_still_returns_original_result() {
		$args = [
			'execute_callback' => function ( $input ) {
				return [ 'echoed' => $input ];
			},
		];

		$filtered = apply_filters( 'wp_register_ability_args', $args, 'test/echo' );

		$this->assertNotSame( $args['execute_callback'], $filtered['execute_callback'] );
		$this->assertSame( [ 'echoed' => 'hello' ], call_user_func( $filtered['execute_callback'], 'hello' ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_successful_invocation_is_recorded() {
		$mock_tracks = $this->getMockBuilder( 'Automattic\\VIP\\Telemetry\\Tracks' )
			->disableOriginalConstructor()
			->onlyMethods( [ 'record_event' ] )
			->getMock();

		\Automattic\VIP\Telemetry\Abilities\Ability_Invocation_Tracker::$tracks_instance = $mock_tracks;

		$mock_tracks->expects( $this->once() )
			->method( 'record_event' )
			->with(
				'abilities_api_invoked',
				$this->callback( function ( $properties ) {
					return 'test/echo' === $properties['ability_name'] &&
						true === $properties['success'];
				} )
			);

		$args     = [
			'execute_callback' => function ( $input ) {
				return [ 'echoed' => $input ];
			},
		];
		$filtered = apply_filters( 'wp_register_ability_args', $args, 'test/echo' );

		call_user_func( $filtered['execute_callback'], 'hello' );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_wp_error_result_is_recorded_as_unsuccessful() {
		$mock_tracks = $this->getMockBuilder( 'Automattic\\VIP\\Telemetry\\Tracks' )
			->disableOriginalConstructor()
			->onlyMethods( [ 'record_event' ] )
			->getMock();

		\Automattic\VIP\Telemetry\Abilities\Ability_Invocation_Tracker::$tracks_instance = $mock_tracks;

		$mock_tracks->expects( $this->once() )
			->method( 'record_event' )
			->with(
				'abilities_api_invoked',
				$this->callback( function ( $properties ) {
					return 'test/failing' === $properties['ability_name'] &&
						false === $properties['success'];
				} )
			);

		$args     = [
			'execute_callback' => function () {
				return new WP_Error( 'test_error', 'Something went wrong.' );
			},
		];
		$filtered = apply_filters( 'wp_register_ability_args', $args, 'test/failing' );

		call_user_func( $filtered['execute_callback'] );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_thrown_exception_is_recorded_as_unsuccessful_and_rethrown() {
		$mock_tracks = $this->getMockBuilder( 'Automattic\\VIP\\Telemetry\\Tracks' )
			->disableOriginalConstructor()
			->onlyMethods( [ 'record_event' ] )
			->getMock();

		\Automattic\VIP\Telemetry\Abilities\Ability_Invocation_Tracker::$tracks_instance = $mock_tracks;

		$mock_tracks->expects( $this->once() )
			->method( 'record_event' )
			->with(
				'abilities_api_invoked',
				$this->callback( function ( $properties ) {
					return 'test/throws' === $properties['ability_name'] &&
						false === $properties['success'];
				} )
			);

		$args     = [
			'execute_callback' => function () {
				throw new \RuntimeException( 'Something went very wrong.' );
			},
		];
		$filtered = apply_filters( 'wp_register_ability_args', $args, 'test/throws' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Something went very wrong.' );

		call_user_func( $filtered['execute_callback'] );
	}
}
