/**
 * External dependencies
 */
import { expect, type Page, test } from '@playwright/test';

/**
 * Internal dependencies
 */
import * as DataHelper from '../lib/data-helper';
import { PublishedPostPage } from '../lib/pages/published-post-page';
import { ClassicEditorPage } from '../lib/pages/wp-classic-editor-page';
import { EditorPage } from '../lib/pages/wp-editor-page';
import { type PostType } from '../lib/wp-api-helper';

const postTypes: PostType[] = [ 'post', 'page' ];
const bodyText =
	'"Be who you are and say what you feel, because \n' +
	'those who mind don’t matter and those who matter don’t mind." \n' +
	'– Bernard M. Baruch';

const editors = [
	{
		name: 'block editor',
		newPostURL: ( postType: PostType ) => `/wp-admin/post-new.php?post_type=${ postType }`,
		open: ( page: Page ) => new EditorPage( page ),
		isUnavailable: () => false,
	},
	{
		name: 'classic editor',
		newPostURL: ( postType: PostType ) => `/wp-admin/post-new.php?post_type=${ postType }&classic-editor&classic-editor__forget`,
		open: ( page: Page ) => new ClassicEditorPage( page ),
		isUnavailable: () => process.env.E2E_CLASSIC_TESTS === 'false',
	},
];

for ( const editor of editors ) {
	test.describe( `Publish with the ${ editor.name }`, () => {
		// eslint-disable-next-line playwright/no-skipped-test
		test.skip( editor.isUnavailable, 'Classic Tests skipped, plugin not installed' );

		for ( const postType of postTypes ) {
			test( `publish a ${ postType }`, async ( { page } ) => {
				const editorPage = editor.open( page );
				const titleText = DataHelper.getRandomPhrase();

				await test.step( `Add new ${ postType }`, () => page.goto( editor.newPostURL( postType ) ) );

				await test.step( `Write ${ postType }`, async () => {
					await editorPage.enterTitle( titleText );
					await editorPage.enterText( bodyText );
					await editorPage.addImage( 'test_media/image_small.jpg' );
				} );

				await test.step( `Publish and visit ${ postType }`, async () => {
					const publishedURL = await editorPage.publish( { visit: true } );
					expect( publishedURL ).toBe( page.url() );
				} );

				await test.step( `Validate published ${ postType }`, async () => {
					const publishedPostPage = new PublishedPostPage( page );
					await expect( publishedPostPage.heading( titleText ) ).toBeVisible();
					await expect( publishedPostPage.content ).toContainText( 'Be who you are and say what you feel' );
					await expect( publishedPostPage.content ).toContainText( 'those who mind don’t matter and those who matter don’t mind.' );
					await expect( publishedPostPage.image ).toBeVisible();
				} );
			} );
		}
	} );
}
