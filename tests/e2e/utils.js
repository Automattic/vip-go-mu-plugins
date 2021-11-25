import { visitAdminPage } from '@wordpress/e2e-test-utils';

export const waitForWpAdmin = () => page.waitForSelector( 'body.wp-admin' );

export const deactivatePluginApiKey = async () => {
	await waitForWpAdmin();
	await visitAdminPage( '/options-general.php', '?page=parsely' );
	await page.evaluate( () => document.getElementById( 'apikey' ).value = '' );
	const [ input ] = await page.$x( '//p[contains(@class, \'submit\')]//input' );
	await input.click();
	await waitForWpAdmin();
};

export const deactivatePluginApiSecret = async () => {
	await waitForWpAdmin();
	await visitAdminPage( '/options-general.php', '?page=parsely' );
	await page.evaluate( () => document.getElementById( 'api_secret' ).value = '' );
	await page.keyboard.press( 'Enter' );
	await waitForWpAdmin();
};

export const activatePluginApiKey = async () => {
	await waitForWpAdmin();
	await visitAdminPage( '/options-general.php', '?page=parsely' );
	await page.focus( '#apikey' );
	await page.evaluate( () => document.getElementById( 'apikey' ).value = '' );
	await page.keyboard.type( 'e2etest.example.com' );
	await page.keyboard.press( 'Enter' );
	await waitForWpAdmin();
};
