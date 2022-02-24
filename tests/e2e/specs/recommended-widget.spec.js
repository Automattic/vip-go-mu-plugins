/**
 * External dependencies
 */
import { activateTheme, visitAdminPage } from '@wordpress/e2e-test-utils';

/**
 * Internal dependencies
 */
import {
	changeKeysState,
	startUpTest,
	waitForWpAdmin,
} from '../utils';

const deactivatedPluginWidgetText = 'The Parse.ly Site ID and Parse.ly API Secret fields need to be populated on the Parse.ly settings page for this widget to work.';

const closeWidgetScreenModal = () => page.keyboard.press( 'Escape' );

const searchForParselyWidget = async () => {
	await page.waitForSelector( '.block-list-appender', {
		visible: true,
	} );
	await page.click( '.block-list-appender' );
	await page.focus( '#components-search-control-0' );
	await page.keyboard.type( 'parse.ly recommended widget' );
};

const selectParselyWidgetFromWidgetSearch = async () => {
	const [ button ] = await page.$x( "//button[contains(., 'Parse.ly Recommended Widget')]" );
	await button.click();
};

const getNonActiveWidgetText = async () => {
	// Checking if Parse.ly widget is present in the widgets list
	await page.waitForSelector( '.wp-block-legacy-widget__edit-form', {
		visible: true,
	} );
	const [ h3 ] = await page.$x( "//h3[contains(., 'Parse.ly Recommended Widget')]" );
	expect( h3 ).toBeTruthy();

	await h3.click();

	const widgetContent = await page.evaluateHandle( ( el ) => el.nextElementSibling, h3 );
	return page.evaluate( ( el ) => el.textContent, widgetContent );
};

describe( 'Recommended widget', () => {
	beforeAll( async () => {
		page.on( 'dialog', async function( dialog ) {
			await dialog.accept();
		} );

		await startUpTest();
		await activateTheme( 'twentytwentyone' );
	} );

	afterAll( async () => {
		await activateTheme( 'twentytwentytwo' );
	} );

	it( 'Widget should be available but inactive without api key and secret', async () => {
		await changeKeysState( false, false );

		await visitAdminPage( '/widgets.php', '' );
		await waitForWpAdmin();

		await closeWidgetScreenModal();
		await searchForParselyWidget();
		await selectParselyWidgetFromWidgetSearch();

		expect( await getNonActiveWidgetText() ).toContain( deactivatedPluginWidgetText );
	} );

	it( 'Widget should be available but inactive without api secret', async () => {
		await changeKeysState( true, false );

		await visitAdminPage( '/widgets.php', '' );
		await waitForWpAdmin();

		await closeWidgetScreenModal();
		await searchForParselyWidget();
		await selectParselyWidgetFromWidgetSearch();

		expect( await getNonActiveWidgetText() ).toContain( deactivatedPluginWidgetText );
	} );
} );
