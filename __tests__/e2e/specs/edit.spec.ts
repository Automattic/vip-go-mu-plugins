/**
 * External dependencies
 */
import { expect, test } from '@playwright/test';

/**
 * Internal dependencies
 */
import * as DataHelper from '../lib/data-helper';
import { PostListPage } from '../lib/pages/post-list-page';
import { PublishedPostPage } from '../lib/pages/published-post-page';
import { EditorPage } from '../lib/pages/wp-editor-page';
import * as WPAPIHelper from '../lib/wp-api-helper';

interface CreatedPost {
	id: string;
	link: string;
}

const postTypes: WPAPIHelper.PostType[] = [ 'post', 'page' ];
const initialBodyText =
	'<!-- wp:paragraph --><p>"Sometimes you will never know the value of a moment, until it becomes a memory."</p><!-- /wp:paragraph -->' +
	'<!-- wp:paragraph --><p>– Dr. Seuss</p><!-- /wp:paragraph -->';
const updatedBodyText =
	'"Many of life’s failures are people who did not realize how close they were to success when they gave up. \n' +
	'– Thomas A. Edison';

for ( const postType of postTypes ) {
	test.describe( `Edit a ${ postType }`, () => {
		let createdPost: CreatedPost | undefined;

		test.beforeEach( async ( { request } ) => {
			const response = await WPAPIHelper.createPost( request, {
				title: DataHelper.getRandomPhrase(),
				body: initialBodyText,
				postType,
			} );

			if ( ! response.ok() ) {
				throw new Error( `Failed to create a new ${ postType }. HTTP error: ${ response.status() }` );
			}

			createdPost = await response.json() as CreatedPost;
		} );

		test.afterEach( async ( { request } ) => {
			if ( ! createdPost ) {
				return;
			}

			const response = await WPAPIHelper.deletePost( request, createdPost.id, postType );
			createdPost = undefined;
			if ( ! response.ok() ) {
				throw new Error( `Failed to delete the ${ postType }. HTTP error: ${ response.status() }` );
			}
		} );

		test( `edit a ${ postType } in the block editor`, async ( { page } ) => {
			const { id, link } = createdPost!;
			const editorPage = new EditorPage( page );
			const titleText = DataHelper.getRandomPhrase();

			await test.step( `Select ${ postType } to edit from the list`, async () => {
				const postListPage = new PostListPage( page, postType );
				await postListPage.visit();
				await postListPage.editPostByID( id );
			} );

			await test.step( `Edit ${ postType }`, async () => {
				await editorPage.clearText();
				await editorPage.clearTitle();
				await editorPage.enterTitle( titleText );
				await editorPage.enterText( updatedBodyText );
			} );

			await test.step( `Save changes and visit ${ postType }`, async () => {
				await editorPage.update();
				await page.goto( link );
			} );

			await test.step( `Validate updated ${ postType }`, () => expect( new PublishedPostPage( page ).heading( titleText ) ).toBeVisible() );
		} );
	} );
}
