/**
 * External dependencies
 */
import { test } from '@playwright/test';

/**
 * Internal dependencies
 */
import * as DataHelper from '../lib/data-helper';
import { PageListPage } from '../lib/pages/page-list-page';
import { PublishedPagePage } from '../lib/pages/published-page-page';
import { EditorPage } from '../lib/pages/wp-editor-page';
import * as WPAPIHelper from '../lib/wp-api-helper';

let titleText = DataHelper.getRandomPhrase();
let bodyText =
	'<!-- wp:paragraph --><p>"Sometimes you will never know the value of a moment, until it becomes a memory."</p><!-- /wp:paragraph -->' +
	'<!-- wp:paragraph --><p>– Dr. Seuss</p><!-- /wp:paragraph -->';
let postID: string;
let postURL: string;

test.beforeAll( async ( { request } ) => {
	interface JSONResponse {
		id: string;
		link: string;
	}

	// Create new page to edit
	const response = await WPAPIHelper.createPost( request, {
		title: titleText,
		body: bodyText,
		postType: 'page',
	} );

	if ( ! response.ok() ) {
		throw new Error( `Failed to create a new page. HTTP error: ${ response.status() }` );
	}

	const responseJSON = await response.json() as JSONResponse;
	postID = responseJSON.id;
	postURL = responseJSON.link;
} );

test.afterAll( async ( { request } ) => {
	// Delete created page
	const response = await WPAPIHelper.deletePost( request, postID, 'page' );
	if ( ! response.ok() ) {
		throw new Error( `Failed to delete the page. HTTP error: ${ response.status() }` );
	}
} );

// eslint-disable-next-line playwright/expect-expect
test( 'edit a Page', async ( { page } ) => {
	let editorPage: EditorPage;
	let pageListPage: PageListPage;

	await test.step( 'Go to Page List page', () => {
		pageListPage = new PageListPage( page );
		return pageListPage.visit();
	} );

	await test.step( 'Select post to edit', () => {
		return pageListPage.editPageByID( postID );
	} );

	await test.step( 'Edit Page', async () => {
		titleText = DataHelper.getRandomPhrase();
		bodyText =
			'"Many of life’s failures are people who did not realize how close they were to success when they gave up. \n' +
			'– Thomas A. Edison';
		editorPage = new EditorPage( page );
		await editorPage.clearText();
		await editorPage.clearTitle();
		await editorPage.enterTitle( titleText );
		await editorPage.enterText( bodyText );
	} );

	await test.step( 'Publish changes and visit page', async () => {
		await editorPage.update();
		return page.goto( postURL );
	} );

	await test.step( 'Validate published page', async () => {
		const publishedPagePage = new PublishedPagePage( page );
		return publishedPagePage.validateTextInPost( titleText );
	} );
} );
