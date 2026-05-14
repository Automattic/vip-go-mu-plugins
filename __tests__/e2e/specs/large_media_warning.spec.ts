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
	async function probe( page: import( '@playwright/test' ).Page, label: string ): Promise<void> {
		const state = await page.evaluate( () => {
			const win = globalThis as unknown as Record<string, unknown>;
			const wp = win.wp as {
				Uploader?: unknown;
				mediaUtils?: { uploadMedia?: unknown; __vipLargeMediaWrapped?: unknown };
			} | undefined;
			const plupload = win.plupload as {
				Uploader?: { __vipLargeMediaWrapped?: unknown };
			} | undefined;
			return {
				hasWp: typeof wp !== 'undefined',
				hasWpUploader: typeof wp?.Uploader !== 'undefined',
				hasPlupload: typeof plupload?.Uploader !== 'undefined',
				pluploadWrapped: plupload?.Uploader?.__vipLargeMediaWrapped === true,
				hasMediaUtils: typeof wp?.mediaUtils !== 'undefined',
				hasMediaUtilsUpload: typeof wp?.mediaUtils?.uploadMedia === 'function',
				mediaUtilsWrapped: wp?.mediaUtils?.__vipLargeMediaWrapped === true,
				gutenbergInlineRan: win.__vipGutenbergInlineRan ?? 0,
				gutenbergInlineSawMediaUtils: win.__vipGutenbergMediaUtilsAtInlineTime ?? 'never-ran',
				gutenbergSettingsPatched: win.__vipGutenbergSettingsPatched === true,
				gutenbergPatchAttempts: win.__vipGutenbergPatchAttempts ?? 0,
				pluploadInlineRan: win.__vipPluploadInlineRan ?? 0,
				pluploadBeforeUploadFired: win.__vipPluploadBeforeUploadFired ?? 0,
				pluploadDialogTriggered: win.__vipPluploadDialogTriggered ?? 0,
				pluploadLastBeforeUpload: win.__vipPluploadLastBeforeUpload ?? null,
				hasWarning: typeof win.vipLargeMediaWarning !== 'undefined',
				hasConfig: typeof win.vipLargeMediaWarningConfig !== 'undefined',
				config: win.vipLargeMediaWarningConfig ?? null,
				scriptCount: document.querySelectorAll(
					'script[src*="large-media-upload-warning"]',
				).length,
			};
		} );
		// eslint-disable-next-line no-console
		console.log( `DIAGNOSTIC[${ label }]:`, JSON.stringify( state, null, 2 ) );
	}

	test( 'DIAGNOSTIC: globals on Media Library + post edit', async ( { page } ) => {
		await new WPAdminPage( page ).visit();

		await page.goto( '/wp-admin/upload.php' );
		await page.waitForLoadState( 'domcontentloaded' );
		await probe( page, 'upload.php' );

		await page.goto( '/wp-admin/media-new.php' );
		await page.waitForLoadState( 'domcontentloaded' );
		await probe( page, 'media-new.php' );

		await page.goto( '/wp-admin/post-new.php' );
		await page.waitForLoadState( 'domcontentloaded' );
		await probe( page, 'post-new.php (Gutenberg)' );
	} );

	test( 'DIAGNOSTIC: upload flow probe (no dialog assertion)', async ( { page } ) => {
		await new WPAdminPage( page ).visit();
		await page.goto( '/wp-admin/media-new.php' );
		await page.waitForLoadState( 'domcontentloaded' );

		// Try to upload large file. We don't await modal — just look at sentinels
		// after the attempt so we can see whether BeforeUpload fired.
		const upload = new MediaUploadPage( page );
		await upload.uploadFile( LARGE ).catch( () => undefined );
		// Give plupload a moment to process.
		await page.waitForTimeout( 2000 );

		await probe( page, 'after-upload-attempt' );
	} );

	test( 'DIAGNOSTIC: Gutenberg editor mount + settings patch', async ( { page } ) => {
		await new WPAdminPage( page ).visit();
		await EditorPage.automaticallyDismissAnnoyingNuisances( page );
		await page.goto( '/wp-admin/post-new.php' );
		// Wait for the block editor to actually mount.
		await page.locator( '.block-editor-default-block-appender, .wp-block' ).first().waitFor( { state: 'visible', timeout: 30000 } );
		// Give the settings patch poll a moment after mount.
		await page.waitForTimeout( 1000 );

		await probe( page, 'post-new.php after-editor-mount' );
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
