import { visitAdminPage } from '@wordpress/e2e-test-utils';

export const PLUGIN_VERSION = '3.1.3';

export const waitForWpAdmin = () => page.waitForSelector( 'body.wp-admin' );

export const changeKeysState = async ( activateApiKey, activateApiSecret ) => {
	await visitAdminPage( '/options-general.php', '?page=parsely' );

	await page.evaluate( () => document.getElementById( 'apikey' ).value = '' );
	if ( activateApiKey ) {
		await page.focus( '#apikey' );
		await page.keyboard.type( 'e2etest.example.com' );
	}

	await page.evaluate( () => document.getElementById( 'api_secret' ).value = '' );
	if ( activateApiSecret ) {
		await page.focus( '#api_secret' );
		await page.keyboard.type( 'somesecret' );
	}

	const [ input ] = await page.$x( '//p[contains(@class, \'submit\')]//input' );
	await input.click();
	await waitForWpAdmin();
};

export const deactivatePluginApiKey = async () => {
	await visitAdminPage( '/options-general.php', '?page=parsely' );
	await page.evaluate( () => document.getElementById( 'apikey' ).value = '' );
	const [ input ] = await page.$x( '//p[contains(@class, \'submit\')]//input' );
	await input.click();
	await waitForWpAdmin();
};

export const activatePluginApiKey = async () => {
	await visitAdminPage( '/options-general.php', '?page=parsely' );
	await page.focus( '#apikey' );
	await page.evaluate( () => document.getElementById( 'apikey' ).value = '' );
	await page.keyboard.type( 'e2etest.example.com' );
	await page.keyboard.press( 'Enter' );
	await waitForWpAdmin();
};
