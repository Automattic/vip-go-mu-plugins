/**
 * External dependencies
 */
import { visitAdminPage } from '@wordpress/e2e-test-utils';

/**
 * Internal dependencies
 */
import {
	activatePluginApiKey,
	checkH2DoesNotExist,
	deactivatePluginApiKey,
	waitForWpAdmin,
	selectScreenOptions,
	startUpTest,
} from '../utils';

describe( 'Activation flow', () => {
	beforeAll( startUpTest );

	it( 'Should progress as intended', async () => {
		await deactivatePluginApiKey();

		await visitAdminPage( '/options-general.php', '?page=parsely' );
		await waitForWpAdmin();

		const versionText = await page.$eval( '#wp-parsely_version', ( el ) => el.innerText );
		expect( versionText ).toMatch( /^Version \d+.\d+/ );

		const errorData = await page.$eval( '#wp-parsely-apikey-error-notice', ( el ) => ( {
			classes: el.classList.value,
			message: el.innerText,
		} ) );

		expect( errorData.classes ).toBe( 'notice notice-error' );
		expect( errorData.message ).toBe(
			'The Parse.ly plugin is not active. You need to provide your Parse.ly Dash Site ID before things get cooking.'
		);

		await activatePluginApiKey();

		await waitForWpAdmin();
		expect( await page.$( '#message.error' ) ).toBe( null );
	} );

	it( 'Should display all admin sections', async () => {
		await visitAdminPage( '/options-general.php', '?page=parsely' );
		await waitForWpAdmin();

		// Set initial state
		await selectScreenOptions( { recrawl: false, advanced: false } );

		await page.waitForXPath( '//h2[contains(text(), "Basic Settings")]' );
		expect( await checkH2DoesNotExist( 'Requires Recrawl Settings' ) ).toBe( true );
		expect( await checkH2DoesNotExist( 'Advanced Settings' ) ).toBe( true );

		await selectScreenOptions( { recrawl: true, advanced: true } );

		await page.waitForXPath( '//h2[contains(text(), "Basic Settings")]' );
		await page.waitForXPath( '//h2[contains(text(), "Requires Recrawl Settings")]' );
		await page.waitForXPath( '//h2[contains(text(), "Advanced Settings")]' );

		await selectScreenOptions( { recrawl: true, advanced: false } );

		await page.waitForXPath( '//h2[contains(text(), "Basic Settings")]' );
		await page.waitForXPath( '//h2[contains(text(), "Requires Recrawl Settings")]' );
		expect( await checkH2DoesNotExist( 'Advanced Settings' ) ).toBe( true );

		// Reverting to initial state
		await selectScreenOptions( { recrawl: false, advanced: false } );
	} );
} );
