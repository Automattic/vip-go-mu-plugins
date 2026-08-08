<?php
/**
 * Tests for the standalone VIP Parse.ly consent bridge (wp-parsely-consent.php).
 *
 * @package Automattic\VIP\WP_Parsely_Integration
 */

namespace Automattic\VIP\WP_Parsely_Integration;

use WP_Scripts;
use WP_UnitTestCase;

// Self-load the module under test so the suite does not depend on mu-plugin
// autoloading being active in the test bootstrap.
if ( ! function_exists( __NAMESPACE__ . '\enqueue_consent_bridge' ) ) {
	require_once dirname( __DIR__, 2 ) . '/wp-parsely-consent.php';
}

/**
 * @covers \Automattic\VIP\WP_Parsely_Integration\enqueue_consent_bridge
 * @covers \Automattic\VIP\WP_Parsely_Integration\is_consent_tracking_enabled
 * @covers \Automattic\VIP\WP_Parsely_Integration\consent_value_is_enabled
 */
class Consent_Bridge_Test extends WP_UnitTestCase {
	/**
	 * Reset the scripts registry before each test so inline-script assertions
	 * are isolated.
	 */
	public function set_up(): void {
		parent::set_up();
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test isolation: reset the scripts registry between tests.
		$GLOBALS['wp_scripts'] = new WP_Scripts();
	}

	/**
	 * Register + enqueue a stand-in for the wp-parsely tracker handle, matching
	 * how the plugin registers p.js (no deps, in the footer). We do not need the
	 * real plugin: the module keys off the handle being enqueued.
	 */
	private function enqueue_fake_tracker(): void {
		wp_register_script( CONSENT_TRACKER_HANDLE, 'https://cdn.parsely.com/keys/example.com/p.js', array(), '1.0.0', true );
		wp_enqueue_script( CONSENT_TRACKER_HANDLE );
	}

	/**
	 * Concatenated inline data attached to the tracker handle at a position.
	 *
	 * @param string $position 'before' or 'after'.
	 * @return string
	 */
	private function inline_for( string $position ): string {
		global $wp_scripts;
		$registered = $wp_scripts->registered[ CONSENT_TRACKER_HANDLE ] ?? null;
		if ( null === $registered || ! isset( $registered->extra[ $position ] ) ) {
			return '';
		}
		return implode( "\n", (array) $registered->extra[ $position ] );
	}

	public function test_no_output_when_not_opted_in(): void {
		$this->enqueue_fake_tracker();

		enqueue_consent_bridge();

		$this->assertSame( '', $this->inline_for( 'before' ), 'No before-script when consent is not enabled.' );
		$this->assertSame( '', $this->inline_for( 'after' ), 'No after-script when consent is not enabled.' );
	}

	public function test_no_output_and_no_error_when_tracker_absent(): void {
		add_filter( 'vip_parsely_consent_tracking_enabled', '__return_true' );

		// Tracker handle intentionally NOT enqueued (e.g. a page where wp-parsely
		// chose not to load, under either integration type).
		enqueue_consent_bridge();

		$this->assertSame( '', $this->inline_for( 'before' ) );
		$this->assertSame( '', $this->inline_for( 'after' ) );
	}

	public function test_emits_consent_config_when_opted_in(): void {
		add_filter( 'vip_parsely_consent_tracking_enabled', '__return_true' );
		$this->enqueue_fake_tracker();

		enqueue_consent_bridge();

		$before = $this->inline_for( 'before' );

		$this->assertStringContainsString( 'window.PARSELY.enable_consent_tracking = true', $before );
		// The module is CMP-agnostic: with no bridge supplied, consent mode is on
		// (fail-closed) and nothing CMP-specific is emitted.
		$this->assertSame( '', $this->inline_for( 'after' ), 'No after-script unless a CMP bridge is supplied.' );
	}

	public function test_cmp_bridge_filter_supplies_adapter_js(): void {
		add_filter( 'vip_parsely_consent_tracking_enabled', '__return_true' );
		add_filter(
			'vip_parsely_consent_cmp_bridge',
			static function ( array $bridge ): array {
				$bridge['before'] = '/* seed-prior-choice */';
				$bridge['after']  = '/* cmp-listeners */';
				return $bridge;
			}
		);
		$this->enqueue_fake_tracker();

		enqueue_consent_bridge();

		$before = $this->inline_for( 'before' );

		// Bridge 'before' JS must come AFTER the enable flag, so an adapter can
		// rely on window.PARSELY existing and consent mode being on.
		$this->assertStringContainsString( '/* seed-prior-choice */', $before );
		$this->assertGreaterThan(
			strpos( $before, 'enable_consent_tracking' ),
			strpos( $before, '/* seed-prior-choice */' ),
			'Adapter before-JS must follow the consent-mode flag.'
		);
		$this->assertStringContainsString( '/* cmp-listeners */', $this->inline_for( 'after' ) );
	}

	public function test_malformed_bridge_filter_return_is_ignored(): void {
		add_filter( 'vip_parsely_consent_tracking_enabled', '__return_true' );
		// A filter that ignores the contract (returns a bare string) must not
		// fatal, and must be treated as "no bridge supplied".
		add_filter(
			'vip_parsely_consent_cmp_bridge',
			static function () {
				return 'not-an-array';
			}
		);
		$this->enqueue_fake_tracker();

		enqueue_consent_bridge();

		$this->assertStringContainsString( 'enable_consent_tracking', $this->inline_for( 'before' ) );
		$this->assertSame( '', $this->inline_for( 'after' ) );
	}

	/**
	 * Platform environment variables reach PHP as STRINGS, so enablement must not be
	 * a bare boolean cast, and anything unrecognised has to fail closed rather than
	 * silently switch tracking on.
	 *
	 * @dataProvider data_consent_env_values
	 *
	 * @param mixed  $raw      Value as the platform would supply it.
	 * @param bool   $expected Whether it should read as enabled.
	 * @param string $why      Assertion message.
	 */
	public function test_consent_value_is_enabled( $raw, bool $expected, string $why ): void {
		$this->assertSame( $expected, consent_value_is_enabled( $raw ), $why );
	}

	/**
	 * @return array<string, array{0: mixed, 1: bool, 2: string}>
	 */
	public function data_consent_env_values(): array {
		return array(
			'string "1"'     => array( '1', true, '"1" is what the CLI stores for an enabled flag.' ),
			'string "true"'  => array( 'true', true, 'Conventional spelling must enable.' ),
			'string "yes"'   => array( 'yes', true, 'Conventional spelling must enable.' ),
			'string "on"'    => array( 'on', true, 'Conventional spelling must enable.' ),
			'real bool true' => array( true, true, 'An actual boolean must still work.' ),

			'string "0"'     => array( '0', false, 'Explicit off must disable.' ),
			'string "false"' => array( 'false', false, 'A bare (bool) cast would wrongly read "false" as ENABLED.' ),
			'string "off"'   => array( 'off', false, 'Explicit off must disable.' ),
			'string "no"'    => array( 'no', false, 'Explicit off must disable.' ),
			'string "0.0"'   => array( '0.0', false, 'A bare (bool) cast would wrongly read "0.0" as ENABLED.' ),

			'empty string'   => array( '', false, 'Blank must fail closed.' ),
			'unrecognised'   => array( 'banana', false, 'Garbage must fail closed, never enable tracking.' ),
			'null'           => array( null, false, 'Absent value must fail closed.' ),
		);
	}

	public function test_consent_applied_action_reports_bridge_presence(): void {
		add_filter( 'vip_parsely_consent_tracking_enabled', '__return_true' );
		$this->enqueue_fake_tracker();

		$fired = array();
		add_action(
			'vip_parsely_consent_config_applied',
			static function ( $has_bridge ) use ( &$fired ): void {
				$fired[] = $has_bridge;
			}
		);

		enqueue_consent_bridge();

		$this->assertSame( array( false ), $fired, 'No bridge supplied: telemetry reports consent-mode-only.' );

		// Now with a bridge supplied.
		$GLOBALS['wp_scripts'] = new WP_Scripts(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test isolation.
		add_filter(
			'vip_parsely_consent_cmp_bridge',
			static function ( array $bridge ): array {
				$bridge['after'] = '/* cmp-listeners */';
				return $bridge;
			}
		);
		$this->enqueue_fake_tracker();

		enqueue_consent_bridge();

		$this->assertSame( array( false, true ), $fired );
	}
}
