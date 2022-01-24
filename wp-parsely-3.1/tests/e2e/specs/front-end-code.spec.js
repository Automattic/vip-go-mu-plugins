/**
 * External dependencies
 */
import {
	activatePlugin,
	createURL,
	loginUser,
} from '@wordpress/e2e-test-utils';

/**
 * Internal dependencies
 */
import { changeKeysState, PLUGIN_VERSION } from '../utils';

describe( 'Front end code insertion', () => {
	it( 'Should inject loading script homepage', async () => {
		await loginUser();
		await activatePlugin( 'wp-parsely' );
		await changeKeysState( true, false );

		await page.goto( createURL( '/' ) );

		const content = await page.content();

		expect( content ).toContain( '<link rel="dns-prefetch" href="//cdn.parsely.com">' );
		expect( content ).toContain( `<script data-parsely-site="e2etest.example.com" src="https://cdn.parsely.com/keys/e2etest.example.com/p.js?ver=${ PLUGIN_VERSION }" id="parsely-cfg"></script>` );
		expect( content ).not.toContain( '<script id="wp-parsely-api-js-extra">' );
		expect( content ).not.toContain( 'var wpParsely' );
	} );

	it( 'Should inject loading script homepage and extra variable', async () => {
		await loginUser();
		await activatePlugin( 'wp-parsely' );
		await changeKeysState( true, true );

		await page.goto( createURL( '/' ) );

		const content = await page.content();

		expect( content ).toContain( '<link rel="dns-prefetch" href="//cdn.parsely.com">' );
		expect( content ).toContain( `<script data-parsely-site="e2etest.example.com" src="https://cdn.parsely.com/keys/e2etest.example.com/p.js?ver=${ PLUGIN_VERSION }" id="parsely-cfg"></script>` );
		expect( content ).toContain( '<script id="wp-parsely-api-js-extra">' );
		expect( content ).toContain( 'var wpParsely = {"apikey":"e2etest.example.com"};' );
	} );
} );
