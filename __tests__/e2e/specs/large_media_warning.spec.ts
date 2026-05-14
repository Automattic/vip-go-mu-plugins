/**
 * External dependencies
 */
import { expect, test } from '@playwright/test';

/**
 * Internal dependencies
 */
import { WPAdminSidebarComponent } from '../lib/components/wp-admin-sidebar-component';
import { LargeMediaWarningModal } from '../lib/pages/large-media-warning-modal';
import { MediaUploadPage } from '../lib/pages/media-upload-page';
import { WPAdminPage } from '../lib/pages/wp-admin-page';
import { ClassicEditorPage } from '../lib/pages/wp-classic-editor-page';
import { EditorPage } from '../lib/pages/wp-editor-page';

const LARGE = 'test_media/image_01.jpg'; // 1.9 MB — above 512 KB test threshold
const SMALL = 'test_media/image_small.jpg'; // ~22 KB — below threshold

test.describe( 'Large media upload warning', () => {
	test( 'DIAGNOSTIC: globals are present on upload.php', async ( { page } ) => {
		await new WPAdminPage( page ).visit();
		await page.goto( '/wp-admin/upload.php' );
		await page.waitForLoadState( 'domcontentloaded' );

		const state = await page.evaluate( () => {
			const win = globalThis as unknown as Record<string, unknown>;
			const wp = win.wp as { Uploader?: unknown; mediaUtils?: unknown } | undefined;
			return {
				hasWp: typeof wp !== 'undefined',
				hasUploader: typeof wp?.Uploader !== 'undefined',
				hasMediaUtils: typeof wp?.mediaUtils !== 'undefined',
				hasWarning: typeof win.vipLargeMediaWarning !== 'undefined',
				hasConfig: typeof win.vipLargeMediaWarningConfig !== 'undefined',
				config: win.vipLargeMediaWarningConfig ?? null,
				scriptCount: document.querySelectorAll(
					'script[src*="large-media-upload-warning"]',
				).length,
			};
		} );

		// eslint-disable-next-line no-console
		console.log( 'DIAGNOSTIC state:', JSON.stringify( state, null, 2 ) );
		expect( state.scriptCount ).toBeGreaterThan( 0 );
		expect( state.hasWarning ).toBe( true );
		expect( state.hasConfig ).toBe( true );
	} );

	test( 'Media Library: cancel aborts upload', async ( { page } ) => {
		await new WPAdminPage( page ).visit();
		const sidebar = new WPAdminSidebarComponent( page );
		await sidebar.clickMenuItem( 'Media' );
		await sidebar.clickSubMenuItem( 'Add Media File' );

		const upload = new MediaUploadPage( page );
		const modal = new LargeMediaWarningModal( page );

		await Promise.all( [
			modal.waitForVisible(),
			upload.uploadFile( LARGE ),
		] );

		await modal.cancel();

		// No attachment should appear. data-clipboard-text on copy button is the post-upload marker.
		await expect( page.locator( '.copy-attachment-url' ) ).toHaveCount( 0 );
	} );

	test( 'Media Library: confirm completes upload', async ( { page } ) => {
		await new WPAdminPage( page ).visit();
		const sidebar = new WPAdminSidebarComponent( page );
		await sidebar.clickMenuItem( 'Media' );
		await sidebar.clickSubMenuItem( 'Add Media File' );

		const upload = new MediaUploadPage( page );
		const modal = new LargeMediaWarningModal( page );

		await Promise.all( [
			modal.waitForVisible(),
			upload.uploadFile( LARGE ),
		] );

		await modal.confirm();

		await expect( upload.getMediaUrl() ).resolves.toContain( 'image_01' );
	} );

	test( 'Media Library: below threshold shows no warning', async ( { page } ) => {
		await new WPAdminPage( page ).visit();
		const sidebar = new WPAdminSidebarComponent( page );
		await sidebar.clickMenuItem( 'Media' );
		await sidebar.clickSubMenuItem( 'Add Media File' );

		const upload = new MediaUploadPage( page );
		const modal = new LargeMediaWarningModal( page );

		await upload.uploadFile( SMALL );
		await expect( upload.getMediaUrl() ).resolves.toContain( 'image_small' );
		await expect( modal.dialog ).toBeHidden();
	} );

	test( 'Classic Editor: confirm inserts image', async ( { page } ) => {
		// eslint-disable-next-line playwright/no-skipped-test
		test.skip( process.env.E2E_CLASSIC_TESTS === 'false', 'Classic Tests skipped' );

		await new WPAdminPage( page ).visit();
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

	test( 'Gutenberg: cancel leaves block empty', async ( { page } ) => {
		await new WPAdminPage( page ).visit();
		await EditorPage.automaticallyDismissAnnoyingNuisances( page );
		await page.goto( '/wp-admin/post-new.php' );

		const editor = new EditorPage( page );
		const modal = new LargeMediaWarningModal( page );

		await editor.enterTitle( 'Gutenberg cancel test' );

		const addImagePromise = editor.addImage( LARGE ).catch( () => undefined );
		await modal.waitForVisible();
		await modal.cancel();
		await addImagePromise;

		// Upload button should still be visible (placeholder unchanged); no <img> in editor.
		await expect( page.locator( '.block-editor-media-placeholder__upload-button' ) ).toBeVisible();
	} );

	test( 'Gutenberg: confirm populates image block', async ( { page } ) => {
		await new WPAdminPage( page ).visit();
		await EditorPage.automaticallyDismissAnnoyingNuisances( page );
		await page.goto( '/wp-admin/post-new.php' );

		const editor = new EditorPage( page );
		const modal = new LargeMediaWarningModal( page );

		await editor.enterTitle( 'Gutenberg confirm test' );

		const addImagePromise = editor.addImage( LARGE );
		await modal.waitForVisible();
		await modal.confirm();
		await addImagePromise;

		await expect( page.locator( 'figure.wp-block-image img' ) ).toBeVisible();
	} );
} );
