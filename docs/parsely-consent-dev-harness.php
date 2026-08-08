<?php
/**
 * Plugin Name: Parse.ly consent — local dev harness (TEMPLATE)
 * Description: Copy to the mu-plugins root to browser-test wp-parsely-consent.php against a fake, vendor-neutral consent management platform.
 *
 * TEMPLATE — this file lives in docs/ so it is NOT auto-loaded. To use it:
 *
 *     cp docs/parsely-consent-dev-harness.php ./parsely-consent-dev.php
 *
 * The root copy is gitignored (/parsely-consent-dev.php), so local edits -- your site
 * id, your bundle URL -- stay out of version control.
 *
 * WHY A FAKE CMP: wp-parsely-consent.php is deliberately CMP-agnostic. It switches the
 * tracker into consent mode and attaches whatever bridge JS a site supplies via the
 * `vip_parsely_consent_cmp_bridge` filter. To exercise that end to end you need *some*
 * CMP, so this harness ships a minimal stand-in plus a matching bridge.
 *
 * The stand-in is intentionally VENDOR-NEUTRAL. It is not a model of any particular
 * product; it exposes the three things any real CMP adapter needs, which makes it a
 * usable reference for writing one:
 *
 *   1. A readable set of granted categories        (window.devCmpCategories)
 *   2. A callback fired on load and on change      (window.devCmpOnConsentChange)
 *   3. Evidence the visitor actually ANSWERED      (the dev_cmp_answered cookie)
 *
 * Item 3 is the subtle one. See the tri-state note below.
 *
 * TRI-STATE CONTRACT: the tracker distinguishes 'denied' (an explicit refusal -- zero
 * beacons) from 'undecided' (hasn't answered -- anonymous ping, ephemeral id). Most
 * CMPs fire their callbacks on plain page load, before any choice has been made, so a
 * bridge that calls setConsent(false) merely because consent "is not granted yet" will
 * silently misclassify every undecided visitor as a refuser. This harness only records
 * a denial once the answered-evidence cookie proves a real choice -- mirror that logic
 * in any real adapter.
 *
 * NOTE: do NOT paste this into `dev-env-plugin.php` -- that filename is generated and
 * owned by `vip dev-env create` (HTTPS handling, 2FA, the autologin key). Overwriting it
 * breaks the environment.
 *
 * @package Automattic\VIP\WP_Parsely_Integration
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Stand down under PHPUnit: bin/test.sh mounts the repo root as WPMU_PLUGIN_DIR, so
 * WordPress auto-loads every top-level *.php -- including this file once copied there.
 * Enabling consent here would break the "not opted in" test.
 */
if ( defined( 'VIP_GO_MUPLUGINS_TESTS__DIR__' )
	|| ( defined( 'WP_RUN_CORE_TESTS' ) && WP_RUN_CORE_TESTS ) ) {
	return;
}

// Stand down on the e2e site: its fixture supplies its own adapter, and two bridges on
// the same filter would clobber each other.
if ( defined( 'VIP_PARSELY_CONSENT_E2E' ) ) {
	return;
}

/**
 * EDIT ME: the Parse.ly site id (apikey) to report as.
 *
 * Use a site you own. Beacons from this harness reach PRODUCTION Parse.ly, so never
 * point it at a customer's site id -- local development traffic must not land in
 * someone else's dashboard.
 */
const PARSELY_DEV_SITE_ID = 'example.com';

/**
 * EDIT ME (optional): a consent-capable tracker bundle to load instead of the CDN default.
 *
 * Leave as '' to use whatever wp-parsely would normally load. Set it when you need a
 * specific build -- consent support must be present in the bundle, or PARSELY.setConsent
 * will not exist and the bridge below will no-op.
 */
const PARSELY_DEV_TRACKER_URL = '';

/** The category this harness treats as "analytics", mirroring a real CMP's category ids. */
const PARSELY_DEV_CMP_CATEGORY = 'analytics';

/*
 * 1. Make VIP mu-load wp-parsely, and switch consent tracking on.
 *
 * VIP_PARSELY_ENABLED must be defined before wp-parsely's loader runs (plugins_loaded:1),
 * so it happens at file scope. Consent enablement normally comes from the platform
 * environment variable `VIP_PARSELY_CONSENT_TRACKING`, which VIP surfaces as the
 * constant below; defining it here is the local equivalent. To test the DISABLED path, comment
 * this out (or set it to '0') and confirm no consent scripts are emitted at all.
 */
if ( ! defined( 'VIP_PARSELY_ENABLED' ) ) {
	define( 'VIP_PARSELY_ENABLED', true );
}
if ( ! defined( 'VIP_ENV_VAR_VIP_PARSELY_CONSENT_TRACKING' ) ) {
	define( 'VIP_ENV_VAR_VIP_PARSELY_CONSENT_TRACKING', '1' );
}

/*
 * 2. Supply the Site ID WITHOUT writing the `parsely` option.
 *
 * Writing that option row makes get_options() skip set_default_track_as_values(), leaving
 * track_post_types/track_page_types EMPTY -- after which the tracker silently never
 * enqueues anywhere, which is a confusing failure to debug. The credentials filter leaves
 * the option unset so the defaults populate.
 */
add_filter(
	'wp_parsely_credentials',
	static function (): array {
		return array(
			'is_managed' => true,
			'site_id'    => PARSELY_DEV_SITE_ID,
		);
	}
);

// 3. Optionally point the tracker at a specific bundle.
if ( '' !== PARSELY_DEV_TRACKER_URL ) {
	add_filter(
		'wp_parsely_tracker_url',
		static function (): string {
			return PARSELY_DEV_TRACKER_URL;
		}
	);
}

// 4. Track logged-in admins too, so you can browse without a private window.
add_filter(
	'wp_parsely_managed_options',
	static function ( $options ) {
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		$options['track_authenticated_users'] = true;
		return $options;
	}
);

/*
 * 5. The CMP bridge -- a worked example of what a real adapter supplies.
 *
 * 'before' runs ahead of the tracker and seeds a prior choice; 'after' registers the
 * listeners that push later changes into the tracker.
 *
 * HAZARD: never let the literal string " src=" appear in this JS. wp-parsely's
 * script_loader_tag() runs an unbounded str_replace( ' src=', ' data-parsely-site="..."
 * src=', $tag ) over the WHOLE concatenated block -- before-inline, the script tag, and
 * after-inline -- so any occurrence inside the JS would be silently rewritten.
 * (Relatedly: do not enable the wp_parsely_enable_cfasync_attribute filter, whose
 * /^<script / regex targets the first tag in that block, which is our before-inline.)
 */
add_filter(
	'vip_parsely_consent_cmp_bridge',
	static function ( array $bridge ): array {
		$category = wp_json_encode( PARSELY_DEV_CMP_CATEGORY );

		// Seed initialConsent ONLY from a real recorded prior choice. Absent the
		// answered-evidence cookie we seed nothing, leaving the visitor 'undecided'.
		$bridge['before'] = <<<JS
(function () {
	var granted = (window.devCmpCategories || []).indexOf({$category}) !== -1;
	if (granted) {
		window.PARSELY.initialConsent = true;
	} else if (document.cookie.indexOf('dev_cmp_answered=') !== -1) {
		window.PARSELY.initialConsent = false;
	}
}());
JS;

		// Push the current state in on load and on every subsequent change.
		$bridge['after'] = <<<JS
(function () {
	var CATEGORY = {$category};
	function granted() {
		return (window.devCmpCategories || []).indexOf(CATEGORY) !== -1;
	}
	function answered() {
		return document.cookie.indexOf('dev_cmp_answered=') !== -1;
	}
	function sync() {
		if (!window.PARSELY || typeof window.PARSELY.setConsent !== 'function') {
			return;
		}
		if (granted()) {
			window.PARSELY.setConsent(true);
		} else if (answered()) {
			// Only a proven choice becomes a denial -- never "not granted yet".
			window.PARSELY.setConsent(false);
		}
	}
	// Chain rather than replace, so we do not clobber another integration.
	var previous = window.devCmpOnConsentChange;
	window.devCmpOnConsentChange = function () {
		if (typeof previous === 'function') {
			try { previous(); } catch (e) {}
		}
		sync();
	};
	if (typeof window.addEventListener === 'function') {
		window.addEventListener('devcmp:change', sync);
	}
}());
JS;

		return $bridge;
	}
);

/*
 * 6. Seed the fake CMP in <head>, before the tracker cluster (registered $in_footer).
 * Start with no categories granted and no answered-cookie, i.e. genuinely UNDECIDED.
 */
add_action(
	'wp_head',
	static function (): void {
		?>
<script>
window.PARSELY = window.PARSELY || {};
(function () {
	// A real CMP persists the visitor's category choices and RESTORES them on every page
	// load; this stand-in must too. Otherwise, after granting consent, a reload would
	// report no categories while the answered-evidence cookie still says the visitor
	// answered -- a combination any correct adapter must treat as an explicit denial.
	var match = document.cookie.match(/(?:^|;)\s*dev_cmp_categories=([^;]*)/);
	var stored = match ? decodeURIComponent(match[1]) : '';
	window.devCmpCategories = stored ? stored.split(',') : [];
}());
</script>
		<?php
	},
	1
);

/*
 * 7. The fake CMP banner: Accept / Reject / Reset, plus a live readout of the resulting
 * tracker state. The readout is the point -- it makes an otherwise invisible state
 * machine legible while you click through it.
 */
add_action(
	'wp_footer',
	static function (): void {
		$category = wp_json_encode( PARSELY_DEV_CMP_CATEGORY );
		?>
<style>
#dev-cmp{position:fixed;left:16px;bottom:16px;z-index:99999;max-width:430px;background:#111;
	color:#eee;font:13px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;padding:14px 16px;
	border-radius:8px;box-shadow:0 6px 24px rgba(0,0,0,.45)}
#dev-cmp h4{margin:0 0 8px;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#8ad}
#dev-cmp-status{white-space:pre-line;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px}
#dev-cmp button{margin:10px 8px 0 0;padding:7px 12px;border:0;border-radius:5px;font-weight:600;
	cursor:pointer;font-size:12px}
#dev-cmp-accept{background:#2ea44f;color:#fff}
#dev-cmp-reject{background:#444;color:#eee}
#dev-cmp-reset{background:#7a5d2b;color:#fff}
#dev-cmp-clear{background:#6b3030;color:#fff}
#dev-cmp-pageview{background:#2b5d76;color:#fff}
#dev-cmp-custom{background:#3d5a80;color:#fff}
#dev-cmp button:disabled{opacity:.35;cursor:not-allowed}
</style>
<div id="dev-cmp">
	<h4>Fake CMP — dev harness</h4>
	<div id="dev-cmp-status"></div>
	<button type="button" id="dev-cmp-accept">Accept analytics</button>
	<button type="button" id="dev-cmp-reject">Reject</button>
	<br />
	<button type="button" id="dev-cmp-pageview">Fire pageview</button>
	<button type="button" id="dev-cmp-custom">Fire custom event</button>
	<br />
	<button type="button" id="dev-cmp-reset">Reset (undecided)</button>
	<button type="button" id="dev-cmp-clear">Clear Parse.ly cookies</button>
</div>
<script>
(function () {
	var CATEGORY = <?php echo $category; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode output. ?>;
	var statusEl = document.getElementById('dev-cmp-status');

	function granted() {
		return (window.devCmpCategories || []).indexOf(CATEGORY) !== -1;
	}
	function answered() {
		return document.cookie.indexOf('dev_cmp_answered=') !== -1;
	}
	function hasVisitorCookie() {
		return /(^|;)\s*_parsely_visitor=/.test(document.cookie);
	}

	// Visitor ids, READ-ONLY.
	//
	// Deliberately NOT via PARSELY.visitorManager.getVisitorInfo(): that function has side
	// effects -- it mints an ephemeral id while consent is not granted, and creates+persists a visitor
	// when none exists. Merely rendering this panel would fabricate the identity it claims
	// to be observing. ParselyStorage.getJSON() is a pure read and resolves whichever
	// backend is live (cookie or localStorage), which hand-parsing document.cookie does not.
	function visitorIds() {
		var P = window.PARSELY || {};
		var out = { persistent: '(none)', ephemeral: '' };
		try {
			var vm = P.visitorManager;
			var key = (vm && vm.visitorCookieName) || '_parsely_visitor';
			if (P.ParselyStorage && typeof P.ParselyStorage.getJSON === 'function') {
				var info = P.ParselyStorage.getJSON(key);
				if (info && info.id) {
					out.persistent = info.id;
				}
			}
			// While consent is not granted the id lives in memory only and is never persisted -- that
			// is the point, so surface it separately rather than reporting "none".
			if (vm && vm._ephemeralVisitorInfo && vm._ephemeralVisitorInfo.id) {
				out.ephemeral = vm._ephemeralVisitorInfo.id;
			}
		} catch (e) {}
		return out;
	}

	/*
	 * Log every beacon that actually reaches the network, so the flow is readable without
	 * the Network panel.
	 *
	 * Deliberately observes real resource timings rather than wrapping
	 * PARSELY.beacon.pixel.beacon(). The consent gate lives INSIDE that function, so a
	 * wrapper would log calls the tracker then suppresses -- reporting "sent" for a denied
	 * visitor, which is exactly the behaviour this harness exists to verify. Watching the
	 * network means silence really is silence.
	 */
	(function () {
		if (!window.PerformanceObserver) {
			return;
		}
		var seen = {};
		function report(url) {
			if (seen[url]) {
				return;
			}
			seen[url] = true;
			var query = url.split('?')[1] || '';
			var params = {};
			query.split('&').forEach(function (pair) {
				var eq = pair.indexOf('=');
				if (eq > -1) {
					try {
						params[decodeURIComponent(pair.slice(0, eq))] =
							decodeURIComponent(pair.slice(eq + 1));
					} catch (e) {}
				}
			});
			var endpoint = url.indexOf('/px/') !== -1 ? '/px/ [anonymized]' :
				(url.indexOf('/plogger/') !== -1 ? '/plogger/ [tracked]' : '(other)');
			console.log(
				'%c[beacon]%c ' + (params.action || '?') +
				'\n    endpoint: ' + endpoint +
				'\n    cs:       ' + (params.cs || '(absent - consent mode off)') +
				'\n    id (u):   ' + (params.u || '(none)'),
				'color:#8ad;font-weight:bold', 'color:inherit'
			);
		}
		try {
			new PerformanceObserver(function (list) {
				list.getEntries().forEach(function (entry) {
					if (/\/px\/|\/plogger\//.test(entry.name)) {
						report(entry.name);
					}
				});
			}).observe({ entryTypes: [ 'resource' ] });
		} catch (e) {}
	}());

	function render() {
		var P = window.PARSELY || {};
		var ids = visitorIds();
		// With consent mode off the tracker is consent-unaware, so the CMP state does
		// not apply to it -- everyone is measured normally. Reporting an expected
		// anonymized ping in that case would be actively misleading.
		var consentOn = P.enable_consent_tracking === true;
		var state = !consentOn ? 'n/a — consent mode OFF' :
			(granted() ? 'GRANTED' : (answered() ? 'DENIED' : 'undecided'));
		var expected = !consentOn ? 'ALL traffic tracked (consent-unaware)' :
			(granted() ? 'tracked' : (answered() ? 'NONE (denied)' : 'anonymized ping'));
		statusEl.textContent =
			'categories:              ' + JSON.stringify(window.devCmpCategories || []) + '\n' +
			'answered:                ' + (answered() ? 'yes' : 'no (undecided)') + '\n' +
			'consent state:           ' + state + '\n' +
			'expected beacons:        ' + expected + '\n' +
			'enable_consent_tracking: ' + String(P.enable_consent_tracking) + '\n' +
			'initialConsent:          ' + String(P.initialConsent) + '\n' +
			'setConsent available:    ' + (P && typeof P.setConsent === 'function') + '\n' +
			'_parsely_visitor cookie: ' + (hasVisitorCookie() ? 'present' : 'absent') + '\n' +
			'visitor id (persistent): ' + ids.persistent +
			(ids.ephemeral ? '\nvisitor id (ephemeral):  ' + ids.ephemeral : '');

		// Grey out the choice already in effect, so current state is obvious from the
		// controls and not only the readout. Both stay live while undecided, which is the
		// one state where either is a real choice.
		document.getElementById('dev-cmp-accept').disabled = granted();
		document.getElementById('dev-cmp-reject').disabled = !granted() && answered();
	}

	// What a real CMP does on a banner interaction: persist that a choice was made
	// FIRST, then update categories and fire the callbacks. Without the answered
	// cookie the bridge deliberately ignores "not granted" (undecided is not a
	// denial), so Reject would appear to do nothing.
	function apply(categories) {
		document.cookie = 'dev_cmp_answered=' + new Date().toISOString() + '; path=/';
		// Persist the choice so it survives a reload, as a real CMP does.
		document.cookie = 'dev_cmp_categories=' + encodeURIComponent(categories.join(',')) +
			'; path=/; max-age=31536000';
		window.devCmpCategories = categories;
		if (typeof window.devCmpOnConsentChange === 'function') {
			window.devCmpOnConsentChange();
		}
		window.dispatchEvent(new Event('devcmp:change'));
		setTimeout(render, 50);
	}

	document.getElementById('dev-cmp-accept').addEventListener('click', function () {
		apply([CATEGORY]);
	});
	document.getElementById('dev-cmp-reject').addEventListener('click', function () {
		apply([]);
	});
	document.getElementById('dev-cmp-pageview').addEventListener('click', function () {
		try {
			window.PARSELY.beacon.trackPageView({ url: window.location.href });
		} catch (e) {
			if (window.console) {
				console.error('[dev-cmp] trackPageView failed', e);
			}
		}
		setTimeout(render, 50);
	});

	/*
	 * A custom event, via the generic pixel API.
	 *
	 * PARSELY.beacon exposes only trackPageView() and trackConversion(); anything else goes
	 * through pixel.beacon({ action: ... }) -- the same path engaged-time heartbeats use.
	 * Note this does NOT call initNewPage(), so unlike "Fire pageview" it reuses the current
	 * pageview's ephemeral id. Firing both while undecided shows the distinction: a new
	 * pageview gets a new throwaway id, an event within one does not.
	 */
	document.getElementById('dev-cmp-custom').addEventListener('click', function () {
		try {
			window.PARSELY.beacon.pixel.beacon({
				action: 'custom_event',
				url: window.location.href
			});
		} catch (e) {
			if (window.console) {
				console.error('[dev-cmp] custom event failed', e);
			}
		}
		setTimeout(render, 50);
	});

	// Drop every Parse.ly-owned cookie but LEAVE the consent choice intact, so you can
	// watch a fresh identity get minted without changing consent state -- useful for
	// confirming that revoking severs identity rather than resuming the old one.
	// Extracted so "Reset (undecided)" can reuse it: a visitor who has never answered
	// should not still be carrying an analytics identifier from a previous choice.
	function clearParselyState() {
		var names = document.cookie.split(';').map(function (c) {
			return c.split('=')[0].trim();
		}).filter(function (n) {
			return n.indexOf('_parsely') === 0;
		});
		for (var i = 0; i < names.length; i++) {
			// Expire on this path and on the bare domain; a cookie set at either scope
			// otherwise survives and the "fresh visitor" claim quietly fails.
			document.cookie = names[i] + '=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT';
			document.cookie = names[i] + '=; path=/; domain=' + window.location.hostname +
				'; expires=Thu, 01 Jan 1970 00:00:00 GMT';
		}

		// Drop the tracker's IN-MEMORY copy of the identity as well.
		//
		// Every beacon caches the current visitor id on PARSELY.config.uuid, and
		// when storage is empty getVisitorInfo() seeds a "new"
		// visitor FROM that cached value rather than minting a random one. Clearing
		// storage alone therefore does not guarantee a new identity: reloading races the
		// tracker's own unload beacon, which re-persists the old id on its way out.
		try {
			var P = window.PARSELY;
			if (P && P.config) {
				delete P.config.uuid;
				delete P.config.parsely_site_uuid;
			}
			// Ephemeral (consent-not-granted) identity lives here; drop it for the same reason.
			if (P && P.visitorManager) {
				P.visitorManager._ephemeralVisitorInfo = null;
			}
			if (P && P.sessionManager) {
				P.sessionManager._ephemeralSession = null;
			}
		} catch (e) {}

		// The tracker also MIRRORS identity into localStorage (prefix 'pStore-'), so
		// cookies alone are not enough -- on reload it restores the same visitor id from
		// storage and nothing appears to have happened.
		try {
			var stale = [];
			for (var k = 0; k < window.localStorage.length; k++) {
				var name = window.localStorage.key(k);
				if (name && (name.indexOf('pStore-') === 0 || name.indexOf('_parsely') !== -1)) {
					stale.push(name);
				}
			}
			// Collect first, delete after -- removing while indexing skips entries.
			for (var j = 0; j < stale.length; j++) {
				window.localStorage.removeItem(stale[j]);
			}
		} catch (e) { /* storage may be unavailable; nothing to do */ }
	}

	document.getElementById('dev-cmp-clear').addEventListener('click', function () {
		clearParselyState();
		window.location.reload();
	});

	// Back to never-answered: expire the evidence cookie and reload so the tracker
	// restarts as 'undecided'.
	document.getElementById('dev-cmp-reset').addEventListener('click', function () {
		document.cookie = 'dev_cmp_answered=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT';
		// Clear the persisted choice too, or "undecided" would restore the old categories
		// on the next load.
		document.cookie = 'dev_cmp_categories=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT';
		window.devCmpCategories = [];
		// Also drop the Parse.ly identity. "Undecided" means this visitor has never
		// answered, and such a visitor should not still hold a persistent analytics id
		// from an earlier grant -- the tracker ignores it until consent is granted, but leaving
		// it makes the readout contradict the state it claims.
		clearParselyState();
		window.location.reload();
	});

	render();
	// PARSELY config lands when the tracker evaluates; refresh the readout after.
	setTimeout(render, 600);
}());
</script>
		<?php
	},
	99
);
