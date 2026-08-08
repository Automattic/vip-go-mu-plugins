<?php
/**
 * Plugin Name: VIP Parse.ly Consent Integration
 * Description: Platform-managed, consent-aware Parse.ly tracking for VIP sites. Turns on the tracker's consent mode — visitors are tracked with ephemeral IDs until they grant analytics consent — and attaches a per-site CMP bridge (via the vip_parsely_consent_cmp_bridge filter) that maps the site's CMP to PARSELY.setConsent().
 * Author: Automattic
 * Author URI: https://wpvip.com/
 * License: GPL2+
 * Text Domain: wp-parsely
 *
 * This module is intentionally STANDALONE (a sibling to wp-parsely.php) rather
 * than part of the mu-load path in vip-parsely/vip-parsely.php: it keys off the
 * rendered `wp-parsely-tracker` script handle, so it works whether VIP mu-loads
 * wp-parsely OR the site self-manages its own copy (SELF_MANAGED). It requires
 * NO change to the wp-parsely plugin.
 *
 * Consent tracking is part of the Parse.ly tracker core, so every current p.js
 * bundle understands enable_consent_tracking / PARSELY.setConsent -- this module
 * just activates it.
 *
 * @package Automattic\VIP\WP_Parsely_Integration
 */

declare(strict_types=1);

namespace Automattic\VIP\WP_Parsely_Integration;

/**
 * Script handle the wp-parsely plugin registers for the p.js tracker bundle.
 * This is the script that reads its config off `window.PARSELY`, so consent
 * config must be present before it executes.
 */
const CONSENT_TRACKER_HANDLE = 'wp-parsely-tracker';

/**
 * Platform environment-variable key that enables consent tracking for a site.
 *
 * The `vip_` prefix is deliberate. VIP reserves the `vip_`, `wpcom_` and `is_vip_`
 * prefixes for platform-managed values, so this setting is administered by VIP rather
 * than through the usual per-site environment-variable tooling -- appropriate for a value
 * that governs whether and how visitors are measured.
 *
 * There is intentionally no fallback to an unprefixed key, so enablement has a single
 * unambiguous source.
 *
 * Environment variables reach PHP as constants named VIP_ENV_VAR_<KEY>, so this resolves
 * to VIP_ENV_VAR_VIP_PARSELY_CONSENT_TRACKING; vip_get_env_var() applies the prefix.
 *
 * For local development:
 *     vip dev-env envvar set VIP_PARSELY_CONSENT_TRACKING 1 --slug <slug>
 */
const CONSENT_ENV_VAR = 'vip_parsely_consent_tracking';

/**
 * Whether a platform-supplied enablement value should be read as "on".
 *
 * Environment variable values arrive as STRINGS, so a plain boolean cast is unsafe:
 * (bool) 'false' and (bool) '0.0' are both true, which would silently enable a site
 * that was explicitly switched off. FILTER_VALIDATE_BOOLEAN maps the conventional
 * spellings (1/true/yes/on) and -- the reason it is used here -- fails closed for
 * anything it does not recognise.
 *
 * @param mixed $value Raw value as supplied by the platform.
 * @return bool
 */
function consent_value_is_enabled( $value ): bool {
	return true === filter_var( $value, FILTER_VALIDATE_BOOLEAN );
}

/**
 * The platform-supplied enablement value, or null when the site has not set one.
 *
 * VIP environment variables reach PHP by two different transports, and a site may see
 * either one:
 *
 *  1. As a constant named VIP_ENV_VAR_<KEY>, which is what Automattic\VIP\Environment
 *     (and therefore vip_get_env_var()) reads. This is the platform transport.
 *  2. As an ordinary OS environment variable. `vip dev-env envvar set` writes one of
 *     these -- it does NOT create the constant above -- so consulting only the constant
 *     would leave local environments unable to exercise the very switch production uses.
 *     Reading both is what makes a local run behave like the real thing.
 *
 * Null is returned rather than false so that "not configured" stays distinguishable from
 * "configured as off": an explicit off must beat the fallback constant, an absent value
 * must not.
 *
 * @return string|null
 */
function consent_env_var_value() {
	if ( function_exists( 'vip_has_env_var' ) && vip_has_env_var( CONSENT_ENV_VAR ) ) {
		return (string) vip_get_env_var( CONSENT_ENV_VAR );
	}

	$raw = getenv( strtoupper( CONSENT_ENV_VAR ) );

	return false === $raw ? null : (string) $raw;
}

/**
 * Whether consent-based tracking is enabled for this site.
 *
 * Opt-in, default off. Resolved in order:
 *
 *  1. The platform environment variable (preferred). It is scoped to a single site and
 *     environment, so enabling one environment without affecting another is a
 *     configuration change rather than a code change. When set it is authoritative: an
 *     explicit "off" is honoured even if the constant below says otherwise.
 *  2. The VIP_PARSELY_CONSENT_TRACKING_ENABLED constant, for site code or a
 *     platform-injected feature flag.
 *  3. The filter below, for request-aware overrides and tests.
 *
 * @return bool True if the site opted into consent-based tracking.
 */
function is_consent_tracking_enabled(): bool {
	$configured = consent_env_var_value();

	if ( null !== $configured ) {
		$enabled = consent_value_is_enabled( $configured );
	} else {
		$enabled = defined( 'VIP_PARSELY_CONSENT_TRACKING_ENABLED' ) && true === constant( 'VIP_PARSELY_CONSENT_TRACKING_ENABLED' );
	}

	/**
	 * Filters whether platform-managed consent-based Parse.ly tracking is
	 * enabled for this site.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $enabled True to enable consent mode. Default false.
	 */
	return (bool) apply_filters( 'vip_parsely_consent_tracking_enabled', $enabled );
}

/**
 * Attach consent config + the CMP bridge to the Parse.ly tracker, if enabled.
 *
 * Runs on wp_enqueue_scripts at a late priority (after wp-parsely's own enqueue
 * at the default priority 10). No-ops unless the site opted in AND the tracker
 * handle is actually enqueued on this request -- the latter check is what makes
 * this robust to both the mu-loaded and SELF_MANAGED integration types.
 */
function enqueue_consent_bridge(): void {
	if ( ! is_consent_tracking_enabled() ) {
		return;
	}

	// Only act when the Parse.ly tracker is actually on this request. Works
	// regardless of how wp-parsely was loaded.
	if ( ! wp_script_is( CONSENT_TRACKER_HANDLE, 'enqueued' ) ) {
		return;
	}

	// HAZARD: never let the literal string " src=" appear in the inline JS below
	// (including filter-supplied bridge JS). wp-parsely's script_loader_tag() runs
	// an unbounded str_replace( ' src=', ' data-parsely-site="..." src=', $tag )
	// over the WHOLE concatenated block -- before-inline + <script src> +
	// after-inline -- so any occurrence inside the JS would be silently rewritten.
	// (Relatedly, do not enable the wp_parsely_enable_cfasync_attribute filter:
	// its /^<script / regex targets the first tag in that block, which is our
	// before-inline once this module is active.)

	// (1) Enable consent mode BEFORE p.js runs. Ephemeral mode: the tracker
	// loads now and uses throwaway IDs until consent is granted. This is the
	// fail-closed default and is CMP-agnostic.
	$before = <<<JS
window.PARSELY = window.PARSELY || {};
window.PARSELY.enable_consent_tracking = true;
JS;

	/**
	 * Filters the CMP bridge scripts attached alongside the Parse.ly tracker.
	 *
	 * The module itself is CMP-agnostic: by default it only switches the tracker
	 * into consent mode (fail-closed — ephemeral IDs, no persistent identifiers,
	 * until consent is granted). Platform per-site config or site code supplies
	 * the JS that maps the site's CMP to the tracker:
	 *
	 *  - 'before' runs before p.js evaluates. Seed `window.PARSELY.initialConsent`
	 *    here, and ONLY from a real recorded prior choice: `true` for a prior
	 *    grant, `false` for a prior explicit refusal, and leave it UNSET when the
	 *    visitor has not answered.
	 *  - 'after' is emitted after the tracker tag. Register CMP listeners here and
	 *    call `PARSELY.setConsent(true)` on grant, `PARSELY.setConsent(false)`
	 *    ONLY on an explicit refusal or revocation.
	 *
	 * TRI-STATE HAZARD: the tracker distinguishes 'denied' (explicit refusal —
	 * zero beacons by default) from 'undecided' (hasn't answered — anonymous
	 * ping, ephemeral ID). Never call `setConsent(false)` merely because the CMP
	 * has not granted consent YET: many CMPs fire their callbacks on plain page
	 * load, before any user choice, and an unguarded `false` there silently
	 * misclassifies every undecided visitor as a refuser.
	 *
	 * EXACTLY ONE ADAPTER PER SITE: adapters ASSIGN to 'before'/'after' rather
	 * than appending, so when two register, the later one silently discards the
	 * earlier one's JS. There is no error and no warning — the losing CMP simply
	 * never reaches setConsent(), and the site looks like it is ignoring consent
	 * for no visible reason. This is deliberate: concatenating two adapters would
	 * be worse, since both would push their own (possibly contradictory) state
	 * into the tracker and the last call would win a race nobody declared. If a
	 * site genuinely needs to combine sources, do it INSIDE a single adapter,
	 * where the precedence is explicit and reviewable.
	 *
	 * @since 1.0.0
	 *
	 * @param array $bridge { @type string $before JS, @type string $after JS }
	 */
	$bridge = apply_filters( 'vip_parsely_consent_cmp_bridge', array(
		'before' => '',
		'after'  => '',
	) );

	$bridge_before = is_array( $bridge ) && isset( $bridge['before'] ) ? (string) $bridge['before'] : '';
	$bridge_after  = is_array( $bridge ) && isset( $bridge['after'] ) ? (string) $bridge['after'] : '';

	if ( '' !== $bridge_before ) {
		$before .= "\n" . $bridge_before;
	}

	wp_add_inline_script( CONSENT_TRACKER_HANDLE, $before, 'before' );
	if ( '' !== $bridge_after ) {
		wp_add_inline_script( CONSENT_TRACKER_HANDLE, $bridge_after, 'after' );
	}

	/**
	 * Fires after consent config has been attached to the Parse.ly tracker.
	 *
	 * Hook point for platform telemetry (e.g. VIP Tracks) to record that
	 * consent-based tracking was applied on this request.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $has_bridge Whether a CMP bridge was supplied for this site
	 *                         (false = consent mode only; the site must wire its
	 *                         CMP to PARSELY.setConsent itself).
	 */
	do_action( 'vip_parsely_consent_config_applied', '' !== $bridge_before || '' !== $bridge_after );
}

add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueue_consent_bridge', 20 );
