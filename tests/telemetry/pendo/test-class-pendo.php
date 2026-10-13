<?php

declare(strict_types=1);

namespace Automattic\VIP\Telemetry;

use Automattic\Test\Constant_Mocker;
use Automattic\VIP\Telemetry\Pendo\Pendo_Track_Client;
use PHPUnit\Framework\MockObject\MockObject;
use WP_UnitTestCase;

require_once __DIR__ . '/../trait-telemetry-test-helpers.php';

class Pendo_Test extends WP_UnitTestCase {
	use Telemetry_Test_Helpers;

	public function data_environments(): array {
		return [
			'not a VIP environment' => [ [], false ],
			'VIP production'        => [
				[
					'VIP_GO_APP_ENVIRONMENT' => 'production',
					'WPCOM_IS_VIP_ENV'       => true,
				],
				true,
			],
			'non-VIP production'    => [ [ 'VIP_GO_APP_ENVIRONMENT' => 'production' ], false ],
			'VIP non-production'    => [
				[
					'VIP_GO_APP_ENVIRONMENT' => 'preprod',
					'WPCOM_IS_VIP_ENV'       => true,
				],
				false,
			],
			'opted out by constant' => [
				[
					'VIP_DISABLE_PENDO_TELEMETRY' => true,
					'VIP_GO_APP_ENVIRONMENT'      => 'production',
					'WPCOM_IS_VIP_ENV'            => true,
				],
				false,
			],
			'FedRAMP'               => [
				[
					'VIP_GO_APP_ENVIRONMENT' => 'production',
					'VIP_IS_FEDRAMP'         => true,
					'WPCOM_IS_VIP_ENV'       => true,
				],
				false,
			],
			'sandbox'               => [
				[
					'VIP_GO_APP_ENVIRONMENT' => 'production',
					'WPCOM_IS_VIP_ENV'       => true,
					'WPCOM_SANDBOXED'        => true,
				],
				false,
			],
		];
	}

	/**
	 * @dataProvider data_environments
	 */
	public function test_is_pendo_enabled_for_environment( array $constants, bool $expected ) {
		foreach ( $constants as $name => $value ) {
			Constant_Mocker::define( $name, $value );
		}

		$this->assertSame( $expected, Pendo::is_pendo_enabled_for_environment() );
	}

	public function test_record_event_skips_queue_when_disabled() {
		$this->login_as();

		/** @var MockObject|Telemetry_Event_Queue */
		$queue = $this->getMockBuilder( Telemetry_Event_Queue::class )
			->disableOriginalConstructor()
			->getMock();

		$queue->expects( $this->never() )
			->method( 'record_event_asynchronously' );

		$pendo = new Pendo( 'test_', [], $queue );

		$this->assertFalse( $pendo->record_event( 'cool_event', [ 'foo' => 'bar' ] ) );
	}

	/**
	 * Provides request paths and their expected query-free event context.
	 *
	 * @return array
	 */
	public function request_context_data(): array {
		return array(
			'admin with query' => array( '/wp-admin/edit.php?token=private', '/wp-admin/edit.php' ),
			'root'             => array( '/', '/' ),
			'missing URI'      => array( null, '/' ),
		);
	}

	/**
	 * Verify production event context through the real event and queue.
	 *
	 * @dataProvider request_context_data
	 */
	// phpcs:disable WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__HTTP_USER_AGENT__, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Controlled request fixtures and restoration.
	public function test_event_context_contains_request_path( $uri, $expected_path ): void {
		$this->login_as();
		$this->enable_pendo_environment();
		$previous_uri   = $_SERVER['REQUEST_URI'] ?? null;
		$previous_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
		if ( null === $uri ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $uri;
		}
		$_SERVER['HTTP_USER_AGENT'] = 'Telemetry context test';

		$client = $this->createMock( Pendo_Track_Client::class );
		$client->expects( $this->once() )->method( 'batch_record_events' )
			->willReturnCallback( function ( $events ) use ( $expected_path ) {
				$this->assertCount( 1, $events );
				$data = $events[0]->get_data();
				$this->assertSame( array(
					'url'       => $expected_path,
					'userAgent' => 'Telemetry context test',
				), (array) $data->context );
				$this->assertIsString( $data->context->url );
				$this->assertStringNotContainsString( '?', $data->context->url );
				return true;
			} );
		$queue = new Telemetry_Event_Queue( $client );
		try {
			$this->assertTrue( ( new Pendo( 'test_', array(), $queue ) )->record_event( 'context_event', array() ) );
			$this->assertTrue( $queue->record_events() );
		} finally {
			remove_action( 'shutdown', array( $queue, 'record_events' ) );
			if ( null === $previous_uri ) {
				unset( $_SERVER['REQUEST_URI'] );
			} else {
				$_SERVER['REQUEST_URI'] = $previous_uri;
			}
			if ( null === $previous_agent ) {
				unset( $_SERVER['HTTP_USER_AGENT'] );
			} else {
				$_SERVER['HTTP_USER_AGENT'] = $previous_agent;
			}
		}
	}

	// phpcs:enable WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__HTTP_USER_AGENT__, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
}
