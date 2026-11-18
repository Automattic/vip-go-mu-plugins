<?php

namespace Automattic\VIP\Admin_Notice;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../admin-notice/conditions/interface-condition.php';
require_once __DIR__ . '/../../../admin-notice/conditions/class-capability-condition.php';

class Capability_Condition_Test extends TestCase {

	public static $mock_global_functions;

	public function setUp(): void {
		parent::setUp();
		self::$mock_global_functions = $this->getMockBuilder( self::class )
			->addMethods( [ 'mock_current_user_can' ] )
			->getMock();
	}

	/**
	 * Reset the namespace boundary before other notice tests run.
	 */
	public function tearDown(): void {
		self::$mock_global_functions = null;
		parent::tearDown();
	}

	/**
	 * Named capabilities with independent per-capability decisions.
	 */
	public function evaluate_data(): array {
		return [
			'one allowed'   => [ [ 'manage_options' => true ], true ],
			'both allowed'  => [
				[
					'manage_options'    => true,
					'edit_others_posts' => true,
				],
				true,
			],
			'one denied'    => [ [ 'manage_options' => false ], false ],
			'both denied'   => [
				[
					'manage_options'    => false,
					'edit_others_posts' => false,
				],
				false,
			],
			'first denied'  => [
				[
					'manage_options'    => false,
					'edit_others_posts' => true,
				],
				false,
			],
			'second denied' => [
				[
					'manage_options'    => true,
					'edit_others_posts' => false,
				],
				false,
			],
			'no conditions' => [ [], true ],
		];
	}

	/**
	 * Require every actual capability argument and preserve short-circuit order.
	 *
	 * @dataProvider evaluate_data
	 */
	public function test__evaluate( array $decisions, bool $expected_result ): void {
		$expected_calls = [];
		foreach ( $decisions as $capability => $allowed ) {
			$expected_calls[] = $capability;
			if ( ! $allowed ) {
				break;
			}
		}
		$actual_calls = [];
		self::$mock_global_functions->expects( $this->exactly( count( $expected_calls ) ) )
			->method( 'mock_current_user_can' )
			->willReturnCallback( function ( string $capability, array $args ) use ( $decisions, &$actual_calls ) {
				$this->assertArrayHasKey( $capability, $decisions );
				$this->assertSame( [], $args );
				$actual_calls[] = $capability;
				return $decisions[ $capability ];
			} );

		$condition = new Capability_Condition( ...array_keys( $decisions ) );
		$this->assertSame( $expected_result, $condition->evaluate() );
		$this->assertSame( $expected_calls, $actual_calls );
	}
}

/**
 * Use the scoped capability spy when present, otherwise call WordPress.
 */
function current_user_can( string $capability, ...$args ) {
	return is_null( Capability_Condition_Test::$mock_global_functions ) ? \current_user_can( $capability, ...$args ) : Capability_Condition_Test::$mock_global_functions->mock_current_user_can( $capability, $args );
}
