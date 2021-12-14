import { expect, test, request, chromium } from '@playwright/test';
import { EditorPage } from '../lib/pages/wp-editor-page';
import { PublishedPagePage } from '../lib/pages/published-page-page';
import { PageListPage } from '../lib/pages/page-list-page';
import * as DataHelper from '../lib/data-helper';
import * as HookHelper from '../lib/hook-helper'

let titleText = DataHelper.getRandomPhrase();
let bodyText = '<!-- wp:paragraph --><p>"Sometimes you will never know the value of a moment, until it becomes a memory."</p><!-- /wp:paragraph --> \
<!-- wp:paragraph --><p>– Dr. Seuss</p><!-- /wp:paragraph -->'
let postID: string;
let postURL: string;

test.beforeAll( async ( { request } ) => {
    // Create new page to edit
    const response = await HookHelper.createPost( request, { 
        title: titleText,
        body: bodyText,
        postType: 'page'
        }
    );
    const responseJSON = await response.json();
    postID = responseJSON.id;
    postURL =responseJSON.link;
    expect( response.ok() ).toBeTruthy();
} );

test.afterAll( async ( { request } ) => {
    // Delete created page
    const response = await HookHelper.deletePost( request, postID, 'page' );
    expect( response.ok() ).toBeTruthy();
} );

test( 'Edit a Page', async ( {page} ) => {
    let editorPage: EditorPage;
    let pageListPage: PageListPage;

    await test.step( 'Go to Page List page', async () => {
        pageListPage = new PageListPage( page );
        await pageListPage.visit();
    } );

    await test.step( 'Select post to edit', async () => {
        pageListPage.editPageByID( postID );
    } );

    await test.step( 'Edit Page', async () => {
        titleText = DataHelper.getRandomPhrase();
        bodyText = '"Many of life’s failures are people who did not realize how close they were to success when they gave up. \n \
        – Thomas A. Edison';
        editorPage = new EditorPage( page );
        await editorPage.clearText();
        await editorPage.clearTitle();
        await editorPage.enterTitle( titleText );
        await editorPage.enterText( bodyText );
    } );

    await test.step( 'Publish changes and visit page', async () => {
        await editorPage.update();
        await page.goto( postURL );
    } );

    await test.step( 'Validate published page', async () => {
        const publishedPagePage = new PublishedPagePage( page );
        await publishedPagePage.validateTextInPost( titleText );
    } );
} )