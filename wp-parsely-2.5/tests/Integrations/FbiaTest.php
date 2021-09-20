<?php
/**
 * Facebook Instant Articles integration tests.
 *
 * @package Parsely\Tests
 */

namespace Parsely\Tests\Integrations;

use Parsely;
use Parsely\Tests\TestCase;

/**
 * Test Facebook Instant Articles integration.
 */
final class FbiaTest extends TestCase {
	/**
	 * Check the Facebook Instant Articles integration.
	 *
	 * @covers \Parsely::insert_parsely_tracking_fbia
	 * @uses \Parsely::get_options()
	 * @group fbia
	 */
	public function test_fbia_integration() {
		$parsely           = new Parsely();
		$options           = get_option( $parsely::OPTIONS_KEY );
		$options['apikey'] = 'my-api-key.com';
		update_option( 'parsely', $options );
		$registry = array();

		$parsely->insert_parsely_tracking_fbia( $registry );

		// Check Parse.ly got added to the registry.
		self::assertArrayHasKey( 'parsely-analytics-for-wordpress', $registry );

		// Check display name assigned to the integration.
		self::assertSame( 'Parsely Analytics', $registry['parsely-analytics-for-wordpress']['name'] );

		// Check embed code contains a script (don't test for specifics), and the API key.
		self::assertStringContainsString( '<script>', $registry['parsely-analytics-for-wordpress']['payload'] );
		self::assertStringContainsString( $options['apikey'], $registry['parsely-analytics-for-wordpress']['payload'] );
	}
}
