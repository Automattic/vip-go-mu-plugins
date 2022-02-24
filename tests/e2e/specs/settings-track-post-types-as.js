/**
 * External dependencies
 */
import { visitAdminPage } from '@wordpress/e2e-test-utils';

/**
 * Internal dependencies
 */
import {
	saveSettingsAndHardRefresh,
	selectScreenOptions,
	startUpTest,
	waitForWpAdmin,
} from '../utils';

// Radio button selectors.
const radioPostAsPost = 'input#track_post_types_as_post_post';
const radioPostAsPage = '#track_post_types_as_post_page';
const radioPostAsNone = '#track_post_types_as_post_none';
const radioPageAsPost = '#track_post_types_as_page_post';
const radioPageAsPage = '#track_post_types_as_page_page';
const radioPageAsNone = '#track_post_types_as_page_none';
const radioAttachmentAsPost = '#track_post_types_as_attachment_post';
const radioAttachmentAsPage = '#track_post_types_as_attachment_page';
const radioAttachmentAsNone = '#track_post_types_as_attachment_none';

/**
 * Tests for "Track Post Types as" settings.
 */
describe( 'Track Post Types as', () => {
	/**
	 * Login, activate the Parse.ly plugin and show recrawl settings.
	 */
	beforeAll( async () => {
		await startUpTest();

		await visitAdminPage( '/options-general.php', '?page=parsely' );
		await waitForWpAdmin();
		await selectScreenOptions( { recrawl: true, advanced: false } );
	} );

	/**
	 * Set default values and save.
	 */
	afterAll( async () => {
		await selectScreenOptions( { recrawl: true, advanced: false } );
		await waitForWpAdmin();

		await page.click( radioPostAsPost );
		await page.click( radioPageAsPage );
		await page.click( radioAttachmentAsNone );

		await page.click( '#submit' );
		await waitForWpAdmin();
	} );

	/**
	 * Wait for last radio button to be ready.
	 */
	beforeEach( async () => {
		await page.waitForSelector( radioAttachmentAsNone );
	} );

	/**
	 * Test: Save selections in a non-default configuration.
	 */
	it( 'Should be able to save non-default selections', async () => {
		// Set new radio values
		await page.click( radioPostAsNone );
		await page.click( radioPageAsPost );
		await page.click( radioAttachmentAsPage );

		await saveSettingsAndHardRefresh();

		// Verify that post is tracked as none.
		expect( await page.$eval( radioPostAsPost, ( input ) => input.checked ) ).toBeFalsy();
		expect( await page.$eval( radioPostAsPage, ( input ) => input.checked ) ).toBeFalsy();
		expect( await page.$eval( radioPostAsNone, ( input ) => input.checked ) ).toBeTruthy();

		// Verify that page is tracked as post
		expect( await page.$eval( radioPageAsPost, ( input ) => input.checked ) ).toBeTruthy();
		expect( await page.$eval( radioPageAsPage, ( input ) => input.checked ) ).toBeFalsy();
		expect( await page.$eval( radioPageAsNone, ( input ) => input.checked ) ).toBeFalsy();

		// Verify that attachment is tracked as page
		expect( await page.$eval( radioAttachmentAsPost, ( input ) => input.checked ) ).toBeFalsy();
		expect( await page.$eval( radioAttachmentAsPage, ( input ) => input.checked ) ).toBeTruthy();
		expect( await page.$eval( radioAttachmentAsNone, ( input ) => input.checked ) ).toBeFalsy();
	} );

	/**
	 * Save all selections in a 'do not track' configuration.
	 */
	it( 'Should be able to save everything as none', async () => {
		// Set all radio values to none.
		await page.click( radioPostAsNone );
		await page.click( radioPageAsNone );
		await page.click( radioAttachmentAsNone );

		await saveSettingsAndHardRefresh();

		// Check that all selections are set to 'none'.
		expect( await page.$eval( radioPostAsPost, ( input ) => input.checked ) ).toBeFalsy();
		expect( await page.$eval( radioPostAsPage, ( input ) => input.checked ) ).toBeFalsy();
		expect( await page.$eval( radioPostAsNone, ( input ) => input.checked ) ).toBeTruthy();
		expect( await page.$eval( radioPageAsPost, ( input ) => input.checked ) ).toBeFalsy();
		expect( await page.$eval( radioPageAsPage, ( input ) => input.checked ) ).toBeFalsy();
		expect( await page.$eval( radioPageAsNone, ( input ) => input.checked ) ).toBeTruthy();
		expect( await page.$eval( radioAttachmentAsPost, ( input ) => input.checked ) ).toBeFalsy();
		expect( await page.$eval( radioAttachmentAsPage, ( input ) => input.checked ) ).toBeFalsy();
		expect( await page.$eval( radioAttachmentAsNone, ( input ) => input.checked ) ).toBeTruthy();
	} );

	/**
	 * Test that radio buttons can be browsed correctly using the keyboard.
	 */
	it( 'Should be browsable with arrow and tab keys', async () => {
		// Set initial values so we can start from a known position for each radio.
		await page.click( radioPostAsNone );
		await page.click( radioPageAsNone );
		await page.click( radioAttachmentAsNone );
		await saveSettingsAndHardRefresh();

		// Scroll to table to make it easier to view in interactive mode.
		await page.evaluate( () => {
			document.querySelector( '#track-post-types' ).scrollIntoView();
		} );

		// Make adjustments to values using keys and save.
		await page.focus( '#track-post-types' );
		await page.keyboard.press( 'Tab' );
		await page.keyboard.press( 'ArrowLeft' );
		await page.keyboard.press( 'ArrowLeft' );
		await page.keyboard.press( 'ArrowRight' );
		await page.keyboard.press( 'ArrowUp' );
		await page.keyboard.press( 'Tab' );
		await page.keyboard.press( 'ArrowDown' );
		await page.keyboard.press( 'ArrowDown' );
		await saveSettingsAndHardRefresh();

		// The above keys should set the default options. Verify that this is the case.
		expect( await page.$eval( radioPostAsPost, ( input ) => input.checked ) ).toBeTruthy();
		expect( await page.$eval( radioPostAsPage, ( input ) => input.checked ) ).toBeFalsy();
		expect( await page.$eval( radioPostAsNone, ( input ) => input.checked ) ).toBeFalsy();
		expect( await page.$eval( radioPageAsPost, ( input ) => input.checked ) ).toBeFalsy();
		expect( await page.$eval( radioPageAsPage, ( input ) => input.checked ) ).toBeTruthy();
		expect( await page.$eval( radioPageAsNone, ( input ) => input.checked ) ).toBeFalsy();
		expect( await page.$eval( radioAttachmentAsPost, ( input ) => input.checked ) ).toBeFalsy();
		expect( await page.$eval( radioAttachmentAsPage, ( input ) => input.checked ) ).toBeFalsy();
		expect( await page.$eval( radioAttachmentAsNone, ( input ) => input.checked ) ).toBeTruthy();
	} );
} );
