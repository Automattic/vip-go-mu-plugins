<?php
/**
 * Integrations collection tests.
 *
 * @package Parsely\Tests\Integrations
 */

declare(strict_types=1);

namespace Parsely\Tests\Integration\Integrations;

use Parsely\Tests\Integration\TestCase;
use ReflectionClass;

use function Parsely\parsely_integrations;

/**
 * Test plugin integrations collection class.
 *
 * @todo: Instantiate and then try to register something that doesn't implement the Integration interface.
 */
final class IntegrationsTest extends TestCase {
	/**
	 * Check an integration can be added via a filter.
	 *
	 * @covers \Parsely\parsely_integrations
	 * @uses \Parsely\Integrations\Amp::integrate
	 * @uses \Parsely\Integrations\Facebook_Instant_Articles::integrate
	 * @uses \Parsely\Integrations\Integrations::integrate
	 * @uses \Parsely\Integrations\Integrations::register
	 */
	public function test_an_integration_can_be_registered_via_the_filter(): void {
		add_action(
			'wp_parsely_add_integration',
			function( $integrations ) {
				$integrations->register( 'fake', new FakeIntegration2() );

				return $integrations;
			}
		);

		$integrations = parsely_integrations();

		// Use Reflection to look inside the collection.
		$reflector_property = ( new ReflectionClass( $integrations ) )->getProperty( 'integrations' );
		$reflector_property->setAccessible( true );
		$registered_integrations = $reflector_property->getValue( $integrations );

		self::assertCount( 3, $registered_integrations );
		self::assertSame( array( 'amp', 'fbia', 'fake' ), array_keys( $registered_integrations ) );

		// Use filter to override existing key.
		add_action(
			'wp_parsely_add_integration',
			function( $integrations ) {
				$integrations->register( 'amp', new FakeIntegration2() );

				return $integrations;
			}
		);

		self::assertCount( 3, $registered_integrations );
		self::assertSame( array( 'amp', 'fbia', 'fake' ), array_keys( $registered_integrations ) );
	}

}
// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
/**
 * Class FakeIntegration2
 *
 * @package Parsely\Tests\Integrations
 */
class FakeIntegration2 {
	/**
	 * Stub this method to avoid a fatal error.
	 */
	public function integrate(): void {
	}
}

