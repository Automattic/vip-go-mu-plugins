/**
 * External dependencies
 */
import * as path from 'path';
import { visitAdminPage } from '@wordpress/e2e-test-utils';

/**
 * Internal dependencies
 */
import { startUpTest, waitForWpAdmin } from '../utils';

// General initializations.
const imageLocalPath = path.resolve( __dirname, '../../../.wordpress-org/icon-256x256.png' );
const uploadedImagePattern = /\/wp-content\/uploads\/\d{4}\/\d{2}\/icon-256x256-?\d*\.png$/;
const filePathInput = '#media-single-image-logo input.file-path';
const modalAttachment = 'li.attachment'; // Used in both modals.

// Media library modal selectors.
const modalMediaLibrary = 'div.media-modal-content';
const modalSelectFilesButton = '#media-single-image-logo button.browse';
const modalConfirmButton = `${ modalMediaLibrary } button.media-button-select`;
const modalFileUploadInput = `${ modalMediaLibrary } input[type=file]`;

// Edit attachment modal selectors.
const modalEditAttachment = 'div.media-modal div.edit-attachment-frame';
const modalDeleteAttachmentLink = `${ modalEditAttachment } button.delete-attachment`;

/**
 * Browse button tests
 */
describe( 'Browse for logo button', () => {
	beforeAll( startUpTest );

	/**
	 * Remove the uploaded image.
	 */
	afterAll( async () => {
		// Go to Media Library and select the image.
		await visitAdminPage( '/upload.php' );
		await page.waitForSelector( modalAttachment, { visible: true } );
		await page.click( modalAttachment );
		await page.waitForSelector( modalEditAttachment, { visible: true } );

		// Confirm image deletion.
		// Note: Dialog handling must be placed before the click event.
		await page.on( 'dialog', async ( dialog ) => {
			await dialog.accept();
		} );
		await page.click( modalDeleteAttachmentLink );
	} );

	/**
	 * Go to settings page and click the browse button.
	 */
	beforeEach( async () => {
		await visitAdminPage( '/options-general.php', '?page=parsely' );
		await waitForWpAdmin();

		// Click browse button and wait for Media Library to appear.
		await page.click( modalSelectFilesButton );
		await page.waitForSelector( modalMediaLibrary, { visible: true } );
	} );

	/**
	 * Test: Click the Browse button, upload new image, select it and confirm.
	 */
	it( 'Should set the file path when a new image is uploaded and confirmed', async () => {
		// Upload an image file and confirm the dialog.
		const fileInput = await page.$( modalFileUploadInput );
		await fileInput.uploadFile( imageLocalPath );
		await page.waitForTimeout( 500 );
		await page.click( modalConfirmButton );

		// Verify that the image path has been updated.
		await page.waitForTimeout( 500 );
		const filePath = await page.evaluate( ( element ) => element.value, await page.$( filePathInput ) );
		expect( filePath ).toMatch( uploadedImagePattern );
	} );

	/**
	 * Test: Click the Browse button, select an existing image and confirm.
	 */
	it( 'Should set the file path when an existing image is selected and confirmed', async () => {
		// Select the existing and confirm the dialog.
		await page.waitForSelector( modalAttachment, { visible: true } );
		await page.click( modalAttachment );
		await page.click( modalConfirmButton );

		// Verify that that the image path has been updated.
		const filePath = await page.$eval( filePathInput, ( input ) => input.value );
		expect( filePath ).toMatch( uploadedImagePattern );
	} );

	/**
	 * Test: Click the Brows button, select an existing image and dismiss the modal.
	 */
	it( 'Should not set the file path when dismissing the modal', async () => {
		// Select the existing image but cancel the dialog.
		await page.waitForSelector( modalAttachment, { visible: true } );
		await page.click( modalAttachment );
		await page.keyboard.press( 'Escape' );

		// Verify that the image path is empty.
		const filePath = await page.$eval( filePathInput, ( input ) => input.value );
		expect( filePath ).toMatch( '' );
	} );
} );
