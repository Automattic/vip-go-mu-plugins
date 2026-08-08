<?php
/**
 * Plugin Name: Parse.ly consent — e2e enabling fixture
 * Description: Copied into the mu-plugins root by __tests__/e2e/bin/setup-env.sh so the e2e site loads wp-parsely with the consent module switched on.
 *
 * Not loaded from this path. `setup-env.sh` copies it to the repo root as
 * `e2e-parsely-consent.php` (gitignored), because the repo root is bind-mounted as
 * WPMU_PLUGIN_DIR by `vip dev-env --mu-plugins=<path>`. A dedicated filename is used so it can
 * never clobber `dev-env-plugin.php`, which `vip dev-env create` generates and owns.
 *
 * Timing: all mu-plugins load before `plugins_loaded`, so the constant is defined before
 * wp-parsely.php's loader (plugins_loaded:1) and the credentials filter is registered before
 * `new Parsely()` (plugins_loaded:10), which caches `are_credentials_managed` in its constructor.
 *
 * @package Automattic\VIP\WP_Parsely_Integration
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Stand down under PHPUnit.
 *
 * setup-env.sh copies this file to the mu-plugins root, where it survives until `destroy-env`.
 * bin/test.sh mounts that same root as WPMU_PLUGIN_DIR, so WordPress would auto-load it and switch
 * the module on, breaking test-consent-bridge.php::test_no_output_when_not_opted_in.
 * VIP_GO_MUPLUGINS_TESTS__DIR__ is defined by tests/bootstrap.php:16 before WordPress loads,
 * so it is visible to auto-loaded mu-plugins. WP_RUN_CORE_TESTS covers the WP core suite.
 */
if ( defined( 'VIP_GO_MUPLUGINS_TESTS__DIR__' )
	|| ( defined( 'WP_RUN_CORE_TESTS' ) && WP_RUN_CORE_TESTS ) ) {
	return;
}

/*
 * Marks this request as the e2e site. A developer's local test harness may share the same
 * mu-plugins root and would otherwise also load here, overriding tracker filters and breaking
 * parsely-consent.spec.ts. Harnesses must bail when they see this constant.
 */
define( 'VIP_PARSELY_CONSENT_E2E', true );

// Make VIP mu-load wp-parsely, and switch on the consent module under test.
if ( ! defined( 'VIP_PARSELY_ENABLED' ) ) {
	define( 'VIP_PARSELY_ENABLED', true );
}
if ( ! defined( 'VIP_PARSELY_CONSENT_TRACKING_ENABLED' ) ) {
	define( 'VIP_PARSELY_CONSENT_TRACKING_ENABLED', true );
}

/*
 * Supply the Site ID WITHOUT writing the `parsely` option.
 *
 * Writing the option row makes get_options() skip set_default_track_as_values(), leaving
 * track_post_types/track_page_types EMPTY — after which the tracker silently never enqueues and
 * the consent spec would pass vacuously. The credentials filter leaves the option unset.
 */
add_filter(
	'wp_parsely_credentials',
	static function (): array {
		return array(
			'is_managed' => true,
			'site_id'    => 'e2e.example.com',
		);
	}
);

/*
 * Point the tracker at a SAME-ORIGIN stub URL rather than cdn.parsely.com.
 *
 * This is load-bearing for CI safety. Enabling Parse.ly site-wide means any spec that loads a
 * front-end page (generic.spec.ts, the search page) would otherwise fetch the real tracker from
 * the CDN and fire a live pageview beacon at production p1.parsely.com. With a same-origin stub
 * URL, those specs just get a harmless same-origin 404 and no tracker JS ever executes.
 *
 * parsely-consent.spec.ts intercepts this URL with page.route() and fulfills a fake p.js that
 * installs a setConsent() spy, so it can assert the bridge without the real tracker.
 */
add_filter(
	'wp_parsely_tracker_url',
	static function (): string {
		return home_url( '/parsely-e2e-stub.js' );
	}
);

/*
 * A minimal test CMP adapter, so the e2e spec can exercise the bridge contract without any real
 * CMP. It honors the tracker's tri-state rule: `before` seeds initialConsent only from a recorded
 * prior choice (window.__testCmpState), and `after` maps CMP events to setConsent — with an
 * undecided signal deliberately producing NO call, because setConsent(false) records an explicit
 * denial, not "hasn't answered yet".
 */
add_filter(
	'vip_parsely_consent_cmp_bridge',
	static function ( array $bridge ): array {
		$bridge['before'] = <<<'JS'
(function () {
	var s = window.__testCmpState;
	if (s === 'granted') {
		window.PARSELY.initialConsent = true;
	} else if (s === 'refused') {
		window.PARSELY.initialConsent = false;
	}
}());
JS;
		$bridge['after'] = <<<'JS'
(function () {
	window.addEventListener('test-cmp-change', function (ev) {
		if (!window.PARSELY || typeof window.PARSELY.setConsent !== 'function') {
			return;
		}
		if (ev.detail === 'granted') {
			window.PARSELY.setConsent(true);
		} else if (ev.detail === 'refused') {
			window.PARSELY.setConsent(false);
		}
		// Any other signal (e.g. an undecided on-load callback): do nothing.
	});
}());
JS;
		return $bridge;
	}
);
