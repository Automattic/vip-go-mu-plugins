import { activatePlugin, loginUser, visitAdminPage } from '@wordpress/e2e-test-utils';

export const PLUGIN_VERSION = '3.1.1';

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

	const [ input ] = await page.$x( '//p[contains(@class, \'submit\')]//input[contains(@name, \'submit\')]' );
	await input.click();
	await waitForWpAdmin();
};

export const deactivatePluginApiKey = async () => {
	await visitAdminPage( '/options-general.php', '?page=parsely' );
	await page.evaluate( () => document.getElementById( 'apikey' ).value = '' );
	const [ input ] = await page.$x( '//p[contains(@class, \'submit\')]//input[contains(@name, \'submit\')]' );
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

export const checkH2DoesNotExist = async ( text ) => {
	const [ h2 ] = await page.$x( `//h2[contains(text(), "${ text }")]` );
	return h2 === undefined;
};

/**
 * Set the visible sections in the array to their values `true` for visible and `false` for not visible.
 *
 * @param {Object} sections Dictionary containing the desired sections to change. Currently, `recrawl` and `advanced`.
 * @return {Promise<void>}
 */
export const selectScreenOptions = async ( sections ) => {
	const [ button ] = await page.$x( '//button[@id="show-settings-link"]' );
	await button.click();

	await page.waitForSelector( '#requires-recrawl' );

	const recrawlInput = await page.$( '#requires-recrawl' );
	const isRecrawlChecked = await ( await recrawlInput.getProperty( 'checked' ) ).jsonValue();
	if ( ( sections.recrawl && ! isRecrawlChecked ) || ( ! sections.recrawl && isRecrawlChecked ) ) {
		await recrawlInput.click();
	}

	const advancedInput = await page.$( '#advanced' );
	const isAdvancedChecked = await ( await advancedInput.getProperty( 'checked' ) ).jsonValue();
	if ( ( sections.advanced && ! isAdvancedChecked ) || ( ! sections.advanced && isAdvancedChecked ) ) {
		await advancedInput.click();
	}

	const [ input ] = await page.$x( '//p[contains(@class, \'submit\')]//input[contains(@name, \'screen-options-apply\')]' );
	await input.click();
};

/**
 * Save settings in the settings page and force a hard refresh.
 *
 * @return {Promise<void>}
 */
export const saveSettingsAndHardRefresh = async () => {
	await page.click( '#submit' );
	await page.waitForSelector( '#submit' );
	await page.evaluate( () => {
		location.reload( true );
	} );
	await page.waitForSelector( '#submit' );
};

/**
 * Some common actions to do before starting tests.
 *
 * @return {Promise<void>}
 */
export const startUpTest = async () => {
	await loginUser();
	await activatePlugin( 'wp-parsely' );
	await waitForWpAdmin();
};
