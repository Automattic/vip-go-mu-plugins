/**
 * External dependencies
 */
import { expect, test } from '@playwright/test';

/**
 * Internal dependencies
 */
import { EditorPage } from '../lib/pages/wp-editor-page';
import { PublishedPostPage } from '../lib/pages/published-post-page';
import { PostListPage } from '../lib/pages/post-list-page';
import * as DataHelper from '../lib/data-helper';
import * as WPAPIHelper from '../lib/wp-api-helper';

let titleText = DataHelper.getRandomPhrase();
let bodyText =
    '<!-- wp:paragraph --><p>"Sometimes you will never know the value of a moment, until it becomes a memory."</p><!-- /wp:paragraph -->' +
    '<!-- wp:paragraph --><p>– Dr. Seuss</p><!-- /wp:paragraph -->';
let postID: string;
let postURL: string;

test.beforeAll( async ( { request } ) => {
    // Create new post to edit
    const response = await WPAPIHelper.createPost( request, {
        title: titleText,
        body: bodyText,
        postType: 'post',
    } );
    const responseJSON = await response.json();
    postID = responseJSON.id;
    postURL = responseJSON.link;
    expect( response.ok() ).toBeTruthy();
} );

test.afterAll( async ( { request } ) => {
    // Delete created page
    const response = await WPAPIHelper.deletePost( request, postID, 'post' );
    expect( response.ok() ).toBeTruthy();
} );

test( 'edit a Post', async ( { page } ) => {
    let editorPage: EditorPage;
    let postListPage: PostListPage;

    await test.step( 'Go to Post List page', () => {
        postListPage = new PostListPage( page );
        return postListPage.visit();
    } );

    await test.step( 'Select post to edit', () => {
        return postListPage.editPostByID( postID );
    } );

    await test.step( 'Edit Post', async () => {
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

    await test.step( 'Publish changes and visit post', async () => {
        await editorPage.update();
        return page.goto( postURL );
    } );

    await test.step( 'Validate published post', () => {
        const publishedPostPage = new PublishedPostPage( page );
        return publishedPostPage.validateTextInPost( titleText );
    } );
} );
