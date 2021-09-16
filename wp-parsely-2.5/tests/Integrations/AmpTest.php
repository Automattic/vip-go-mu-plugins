<?php
/**
 * AMP integration tests.
 *
 * @package Parsely\Tests
 */

namespace Parsely\Tests\Integrations;

use Parsely;
use Parsely\Tests\TestCase;

/**
 * Test AMP integration.
 */
final class AmpTest extends TestCase {
	/**
	 * Check the AMP integration when plugin is not active or request is not an AMP request.
	 *
	 * @covers \Parsely::parsely_add_amp_actions
	 * @uses \Parsely::get_options()
	 * @uses \Parsely::is_amp_request()
	 * @group amp
	 */
	public function test_amp_integration_with_amp_plugin_not_active_or_not_an_AMP_request() {
		// Mock the Parsely class, but only the is_amp_request() method. This leaves
		// the AMP-related methods unmocked, and therefore testable.

		$parsely_mock = $this->getMockBuilder( 'Parsely' )->setMethods( array( 'is_amp_request' ) )->getMock();

		// On the first run, let is_amp_request() return false.
		$parsely_mock->method( 'is_amp_request' )->willReturn( false );

		self::assertSame( '', $parsely_mock->parsely_add_amp_actions() );
	}

	/**
	 * Check the AMP integration when plugin is active and a request is an AMP request.
	 *
	 * @covers \Parsely::parsely_add_amp_actions
	 * @uses \Parsely::get_options()
	 * @uses \Parsely::is_amp_request()
	 * @group amp
	 * @group settings
	 */
	public function test_amp_integration_with_amp_plugin_active_and_a_request_is_an_AMP_request() {
		// Mock the Parsely class, but only the is_amp_request() method. This leaves
		// the AMP-related methods unmocked, and therefore testable.

		$parsely_mock = $this->getMockBuilder( 'Parsely' )->setMethods( array( 'is_amp_request' ) )->getMock();

		$parsely_mock->method( 'is_amp_request' )->willReturn( true );

		// Check with AMP marked as disabled.
		$options                = get_option( $parsely_mock::OPTIONS_KEY );
		$options['disable_amp'] = true;
		update_option( $parsely_mock::OPTIONS_KEY, $options );

		// Check the early return because AMP is marked as disabled.
		self::assertSame( '', $parsely_mock->parsely_add_amp_actions() );

		// Now check with AMP not marked as disabled.
		$options['disable_amp'] = false;
		update_option( $parsely_mock::OPTIONS_KEY, $options );

		// Null return, so check filters have been added.
		self::assertNull( $parsely_mock->parsely_add_amp_actions() );
		self::assertNotFalse( has_filter( 'amp_post_template_analytics', array( $parsely_mock, 'parsely_add_amp_analytics' ) ) );
		self::assertNotFalse( has_filter( 'amp_analytics_entries', array( $parsely_mock, 'parsely_add_amp_native_analytics' ) ) );
	}

	/**
	 * Check the registration of Parse.ly with AMP.
	 *
	 * @covers \Parsely::parsely_add_amp_actions
	 * @covers \Parsely::parsely_add_amp_analytics
	 * @uses \Parsely::get_options()
	 * @uses \Parsely::is_amp_request()
	 * @group amp
	 * @group settings
	 */
	public function test_amp_integration_registration() {
		$parsely   = new Parsely();
		$options   = get_option( 'parsely' );
		$analytics = array();

		// If apikey is empty, $analytics are returned.
		self::assertSame( $analytics, $parsely->parsely_add_amp_analytics( $analytics ) );

		// Now set the key and test for changes.
		$options['apikey'] = 'my-api-key.com';
		update_option( 'parsely', $options );

		$output = $parsely->parsely_add_amp_analytics( $analytics );

		self::assertSame( 'parsely', $output['parsely']['type'] );
		self::assertSame( 'my-api-key.com', $output['parsely']['config_data']['vars']['apikey'] );
	}
}
