import {
	activatePlugin,
	loginUser,
	visitAdminPage,
} from '@wordpress/e2e-test-utils';

const waitForWpAdmin = () => page.waitForSelector( 'body.wp-admin' );

describe( 'Activation flow', () => {
	jest.setTimeout( 30000 );
	it( 'Should progress as intended', async () => {
		await loginUser();
		await activatePlugin( 'wp-parsely' );
		await visitAdminPage( '/options-general.php', '?page=parsely' );

		await waitForWpAdmin();

		const versionText = await page.$eval( '#wp-parsely_version', ( el ) => el.innerText );
		expect( versionText ).toMatch( /^Version \d+.\d+/ );

		const errorMessage = await page.$eval( '#message.error', ( el ) => el.innerText );
		expect( errorMessage ).toBe(
			'The Parse.ly plugin is not active. You need to provide your Parse.ly Dash Site ID before things get cooking.'
		);

		await page.focus( '#apikey' );
		await page.keyboard.type( 'wp-parsely.plugin.e2etest.example.com' );
		await page.keyboard.press( 'Enter' );

		await waitForWpAdmin();
		expect( await page.$( '#message.error' ) ).toBe( null );
	} );
} );
