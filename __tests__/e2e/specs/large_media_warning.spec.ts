/**
 * External dependencies
 */
import { expect, test } from '@playwright/test';

/**
 * Internal dependencies
 */
import { LargeMediaWarningModal } from '../lib/pages/large-media-warning-modal';
import { MediaUploadPage } from '../lib/pages/media-upload-page';
import { ClassicEditorPage } from '../lib/pages/wp-classic-editor-page';

const LARGE = 'test_media/image_01.jpg'; // 1.9 MB — above 512 KB test threshold
const SMALL = 'test_media/image_small.jpg'; // ~22 KB — below threshold

test.describe( 'Large media upload warning', () => {
	test( 'Media Library: cancel aborts upload', async ( { page } ) => {
		const upload = new MediaUploadPage( page );
		const modal = new LargeMediaWarningModal( page );
		await upload.visit();

		await Promise.all( [
			modal.waitForVisible(),
			upload.uploadFile( LARGE ),
		] );

		await modal.cancel();

		// No attachment should appear. data-clipboard-text on copy button is the post-upload marker.
		await expect( page.locator( '.copy-attachment-url' ) ).toHaveCount( 0 );
	} );

	test( 'Media Library: confirm completes upload', async ( { page } ) => {
		const upload = new MediaUploadPage( page );
		const modal = new LargeMediaWarningModal( page );
		await upload.visit();

		await Promise.all( [
			modal.waitForVisible(),
			upload.uploadFile( LARGE ),
		] );

		await modal.confirm();

		await expect( upload.getMediaUrl() ).resolves.toContain( 'image_01' );
	} );

	test( 'Media Library: below threshold shows no warning', async ( { page } ) => {
		const upload = new MediaUploadPage( page );
		const modal = new LargeMediaWarningModal( page );
		await upload.visit();

		await upload.uploadFile( SMALL );
		await expect( upload.getMediaUrl() ).resolves.toContain( 'image_small' );
		await expect( modal.dialog ).toBeHidden();
	} );

	test( 'Classic Editor: confirm inserts image', async ( { page } ) => {
		// eslint-disable-next-line playwright/no-skipped-test
		test.skip( process.env.E2E_CLASSIC_TESTS === 'false', 'Classic Tests skipped' );

		await page.goto( '/wp-admin/post-new.php?classic-editor&classic-editor__forget' );

		const classic = new ClassicEditorPage( page );
		const modal = new LargeMediaWarningModal( page );

		await classic.enterTitle( 'Classic large image test' );

		const addImagePromise = classic.addImage( LARGE );
		await modal.waitForVisible();
		await modal.confirm();
		await addImagePromise;

		// addImage waits for the insert button; we then assert the image actually landed in the editor.
		await expect( page.frameLocator( '#content_ifr' ).locator( '#tinymce img' ) ).toBeVisible();
	} );

	// The block editor image upload (DOM `change` capture plus the `fetch` wrap) has no e2e coverage yet:
	// `EditorPage.addImage` cannot open the image block from an empty post, so those cases never ran.
} );
