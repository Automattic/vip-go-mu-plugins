/**
 * External dependencies
 */
import { visitAdminPage } from '@wordpress/e2e-test-utils';

/**
 * Internal dependencies
 */
import { startUpTest, waitForWpAdmin } from '../utils';

describe( 'Plugin action link', () => {
	beforeAll( startUpTest );

	it( 'Should link to plugin settings page', async () => {
		await visitAdminPage( '/plugins.php', '' );
		await waitForWpAdmin();

		await expect( page ).toClick( '[data-slug=wp-parsely] .settings>a', { text: 'Settings' } );
		await waitForWpAdmin();

		const versionText = await page.$eval( '#wp-parsely_version', ( el ) => el.innerText );
		await expect( versionText ).toMatch( /^Version \d+.\d+/ );
	} );
} );
