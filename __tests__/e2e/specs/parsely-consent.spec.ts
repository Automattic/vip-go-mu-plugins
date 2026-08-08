/**
 * External dependencies
 */
import { expect, test } from '@playwright/test';

declare global {
	interface Window {
		PARSELY?: {
			enable_consent_tracking?: boolean;
			initialConsent?: boolean;
			setConsent?: ( granted: boolean ) => boolean;
		};
		__testCmpState?: string;
		__setConsentCalls?: boolean[];
	}
}

/**
 * Stands in for the real p.js. It only installs a setConsent() spy, so the bridge emitted by
 * wp-parsely-consent.php can be asserted without shipping a real tracker bundle to CI. It must
 * PRESERVE window.PARSELY, because the module's `before` inline script has already put
 * enable_consent_tracking / initialConsent on it.
 */
const TRACKER_STUB = `
window.PARSELY = window.PARSELY || {};
window.__setConsentCalls = [];
window.PARSELY.setConsent = function ( granted ) {
	window.__setConsentCalls.push( granted );
	return true;
};
`;

// The e2e fixture (fixtures/e2e-parsely-consent.php) registers a minimal test CMP adapter via the
// vip_parsely_consent_cmp_bridge filter: `before` seeds initialConsent from window.__testCmpState
// ('granted' | 'refused'; unset = undecided), and `after` listens for 'test-cmp-change' events.
// The spec asserts the module's bridge CONTRACT through that adapter — in particular the
// tri-state rule: an undecided visitor must produce NO setConsent call, because setConsent(false)
// records an explicit denial (zero beacons by default), not "hasn't answered yet".

// The tracker is skipped for logged-in users (is_blog_member_logged_in), so drop the admin session
// that global-setup stores. Without this the tracker never enqueues and every assertion is vacuous.
test.use( { storageState: { cookies: [], origins: [] } } );

test.describe( 'Parse.ly consent module', () => {
	let forbiddenRequests: string[] = [];

	test.beforeEach( async ( { page } ) => {
		forbiddenRequests = [];

		// Nothing may ever reach the real Parse.ly CDN or pixel host: that would mean CI is firing
		// live pageview beacons at production analytics.
		await page.route( /(cdn|p1)\.parsely\.com/, async ( route ) => {
			forbiddenRequests.push( route.request().url() );
			await route.abort();
		} );

		await page.route( '**/parsely-e2e-stub.js*', ( route ) => route.fulfill( {
			status: 200,
			contentType: 'application/javascript',
			body: TRACKER_STUB,
		} ) );
	} );

	test( 'an undecided visitor stays undecided, even when the CMP fires an on-load callback', async ( { page } ) => {
		await page.goto( '/' );

		// Proves the module's `before` inline ran on a real front-end render.
		expect( await page.evaluate( () => window.PARSELY?.enable_consent_tracking ) ).toBe( true );
		// No recorded prior choice: initialConsent must be absent entirely, because false would
		// restore an explicit refusal.
		expect( await page.evaluate( () => window.PARSELY?.initialConsent ) ).toBeUndefined();

		// Many CMPs fire their change callback on plain page load, before any user choice. The
		// bridge contract requires that to produce NO setConsent call.
		await page.evaluate( () => {
			window.dispatchEvent( new CustomEvent( 'test-cmp-change', { detail: 'undecided' } ) );
		} );
		expect( await page.evaluate( () => window.__setConsentCalls ) ).toEqual( [] );

		expect( forbiddenRequests ).toEqual( [] );
	} );

	test( 'a returning consenter is restored via initialConsent = true', async ( { page } ) => {
		await page.addInitScript( () => {
			window.__testCmpState = 'granted';
		} );

		await page.goto( '/' );

		expect( await page.evaluate( () => window.PARSELY?.initialConsent ) ).toBe( true );

		expect( forbiddenRequests ).toEqual( [] );
	} );

	test( 'a returning refuser is restored via initialConsent = false', async ( { page } ) => {
		await page.addInitScript( () => {
			window.__testCmpState = 'refused';
		} );

		await page.goto( '/' );

		expect( await page.evaluate( () => window.PARSELY?.initialConsent ) ).toBe( false );

		expect( forbiddenRequests ).toEqual( [] );
	} );

	test( 'granting consent calls setConsent(true)', async ( { page } ) => {
		await page.goto( '/' );

		await page.evaluate( () => {
			window.dispatchEvent( new CustomEvent( 'test-cmp-change', { detail: 'granted' } ) );
		} );

		expect( await page.evaluate( () => window.__setConsentCalls ) ).toEqual( [ true ] );

		expect( forbiddenRequests ).toEqual( [] );
	} );

	test( 'revoking after a grant calls setConsent(false)', async ( { page } ) => {
		await page.goto( '/' );

		await page.evaluate( () => {
			window.dispatchEvent( new CustomEvent( 'test-cmp-change', { detail: 'granted' } ) );
			window.dispatchEvent( new CustomEvent( 'test-cmp-change', { detail: 'refused' } ) );
		} );

		expect( await page.evaluate( () => window.__setConsentCalls ) ).toEqual( [ true, false ] );

		expect( forbiddenRequests ).toEqual( [] );
	} );
} );
