/**
 * External dependencies
 */
import { expect, test, type APIRequestContext } from '@playwright/test';
import { createHash } from 'node:crypto';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';

/**
 * Internal dependencies
 */
import { LargeMediaWarningModal } from '../lib/pages/large-media-warning-modal';
import { MediaUploadPage } from '../lib/pages/media-upload-page';
import { ClassicEditorPage } from '../lib/pages/wp-classic-editor-page';

const LARGE = 'test_media/image_01.jpg'; // 1.9 MB — above 512 KB test threshold
const SMALL = 'test_media/image_small.jpg'; // ~22 KB — below threshold

/**
 * Fetches an uploaded fixture and verifies the server returned the original JPEG bytes.
 *
 * @param {APIRequestContext} request The authenticated Playwright API request context
 * @param {string}            url     Public URL returned by the media library
 * @param {string}            file    Fixture path relative to the e2e package
 */
async function expectUploadedBytes( request: APIRequestContext, url: string, file: string ): Promise<void> {
	const expectedBytes = await readFile( resolve( __dirname, '..', file ) );
	const response = await request.get( url );
	expect( response.status() ).toBe( 200 );
	expect( response.headers()[ 'content-type' ] ).toMatch( /^image\/jpeg(?:;|$)/ );

	const expectedHash = createHash( 'sha256' ).update( expectedBytes ).digest( 'hex' );
	const actualHash = createHash( 'sha256' ).update( await response.body() ).digest( 'hex' );
	expect( actualHash ).toBe( expectedHash );
}

/**
 * Verifies a rendered attachment URL returns image bytes with the expected MIME type.
 *
 * @param {APIRequestContext} request The authenticated Playwright API request context
 * @param {string}            url     The rendered image URL
 */
async function expectRenderedImage( request: APIRequestContext, url: string ): Promise<void> {
	const response = await request.get( url );
	expect( response.status() ).toBe( 200 );
	expect( response.headers()[ 'content-type' ] ).toMatch( /^image\/(?:jpeg|webp|png)(?:;|$)/ );
	expect( ( await response.body() ).byteLength ).toBeGreaterThan( 0 );
}

test.describe( 'Large media upload warning', () => {
	test( 'Media Library: cancel aborts upload', async ( { page } ) => {
		const upload = new MediaUploadPage( page );
		const modal = new LargeMediaWarningModal( page );
		const uploadRequests: string[] = [];
		page.on( 'request', ( request ) => {
			const pathname = new URL( request.url() ).pathname;
			if ( /(?:async-upload\.php|\/wp-json\/wp\/v2\/media(?:\/|$))/.test( pathname ) ) {
				uploadRequests.push( request.url() );
			}
		} );
		await upload.visit();

		await Promise.all( [
			modal.waitForVisible(),
			upload.uploadFile( LARGE ),
		] );

		const uploadRequestAfterCancel = page
			.waitForRequest(
				( request ) => /(?:async-upload\.php|\/wp-json\/wp\/v2\/media(?:\/|$))/.test( new URL( request.url() ).pathname ),
				{ timeout: 1000 },
			)
			.catch( () => null );
		await modal.cancel();

		await expect( modal.dialog ).toBeHidden();
		// Observe a bounded quiet period after cancel; networkidle may already have happened on navigation.
		expect( await uploadRequestAfterCancel ).toBeNull();
		// Cancellation must prevent the server upload request, not just hide the attachment details.
		expect( uploadRequests ).toHaveLength( 0 );
		// No attachment should appear. data-clipboard-text on copy button is the post-upload marker.
		await expect( page.locator( '.copy-attachment-url' ) ).toHaveCount( 0 );
	} );

	test( 'Media Library: confirm completes upload', async ( { page, request } ) => {
		const upload = new MediaUploadPage( page );
		const modal = new LargeMediaWarningModal( page );
		await upload.visit();

		await Promise.all( [
			modal.waitForVisible(),
			upload.uploadFile( LARGE ),
		] );

		await modal.confirm();

		const url = await upload.getMediaUrl();
		expect( url ).toContain( 'image_01' );
		await expectUploadedBytes( request, url!, LARGE );
	} );

	test( 'Media Library: below threshold shows no warning', async ( { page, request } ) => {
		const upload = new MediaUploadPage( page );
		const modal = new LargeMediaWarningModal( page );
		await upload.visit();

		await upload.uploadFile( SMALL );
		const url = await upload.getMediaUrl();
		expect( url ).toContain( 'image_small' );
		await expectUploadedBytes( request, url!, SMALL );
		await expect( modal.dialog ).toBeHidden();
	} );

	test( 'Classic Editor: confirm inserts image', async ( { page, request } ) => {
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

		// addImage resolves after insertion; assert the image actually landed in the editor.
		const image = page.frameLocator( '#content_ifr' ).locator( '#tinymce img' );
		await expect( image ).toBeVisible();
		const renderedUrl = ( await image.getAttribute( 'src' ) )!;
		await expectRenderedImage( request, renderedUrl );

		// The editor may insert a resized rendition; verify original bytes through the attachment REST record.
		const imageClass = await image.getAttribute( 'class' );
		const attachmentId = imageClass?.match( /(?:^|\s)wp-image-(\d+)(?:\s|$)/ )?.[ 1 ];
		expect( attachmentId ).toBeDefined();
		const attachmentResponse = await request.get( `./wp-json/wp/v2/media/${ attachmentId }`, {
			headers: {
			'X-WP-Nonce': `${ process.env.WP_E2E_NONCE! }`,
		},
	} );
		expect( attachmentResponse.status() ).toBe( 200 );
		const attachment = await attachmentResponse.json() as { source_url: string };
		await expectUploadedBytes( request, attachment.source_url, LARGE );
	} );

	// Supported upload coverage is Media Library and Classic Editor. Gutenberg upload coverage is
	// explicitly out of scope until the block inserter can be opened reliably in Playwright.
} );
