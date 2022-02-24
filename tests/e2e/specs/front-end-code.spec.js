/**
 * External dependencies
 */
import { createURL } from '@wordpress/e2e-test-utils';
import * as fs from 'fs';

/**
 * Internal dependencies
 */
import { changeKeysState, PLUGIN_VERSION, startUpTest } from '../utils';

const getAssetVersion = () => {
	const data = fs.readFileSync( 'build/loader.asset.php', { encoding: 'utf8', flag: 'r' } );
	const re = new RegExp( "\'version\' => \'(.*)\'" );
	const r = data.match( re );
	expect( r[ 1 ].length ).toBeGreaterThanOrEqual( 1 );
	return r[ 1 ];
};

describe( 'Front end code insertion', () => {
	beforeAll( startUpTest );

	it( 'Should inject loading script homepage', async () => {
		await changeKeysState( true, false );

		await page.goto( createURL( '/' ) );

		const content = await page.content();

		expect( content ).toContain( '<link rel="dns-prefetch" href="//cdn.parsely.com">' );
		expect( content ).toContain( `<script data-parsely-site="e2etest.example.com" src="https://cdn.parsely.com/keys/e2etest.example.com/p.js?ver=${ PLUGIN_VERSION }" id="parsely-cfg"></script>` );
		expect( content ).toContain( `<script src="http://localhost:8889/wp-content/plugins/wp-parsely/build/loader.js?ver=${ getAssetVersion() }" id="wp-parsely-loader-js"></script>` );
		expect( content ).not.toContain( "<script id='wp-parsely-loader-js-before'>" );
		expect( content ).not.toContain( 'window.wpParselyApiKey =' );
	} );

	it( 'Should inject loading script homepage and extra variable', async () => {
		await changeKeysState( true, true );

		await page.goto( createURL( '/' ) );

		const content = await page.content();

		expect( content ).toContain( '<link rel="dns-prefetch" href="//cdn.parsely.com">' );
		expect( content ).toContain( `<script data-parsely-site="e2etest.example.com" src="https://cdn.parsely.com/keys/e2etest.example.com/p.js?ver=${ PLUGIN_VERSION }" id="parsely-cfg"></script>` );
		expect( content ).toContain( `<script src="http://localhost:8889/wp-content/plugins/wp-parsely/build/loader.js?ver=${ getAssetVersion() }" id="wp-parsely-loader-js"></script>` );
		expect( content ).toContain( '<script id="wp-parsely-loader-js-before">' );
		expect( content ).toContain( "window.wpParselyApiKey = 'e2etest.example.com'" );
	} );
} );
