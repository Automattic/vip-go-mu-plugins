/**
 * External dependencies
 */
import { expect, test } from '@playwright/test';

/**
 * Internal dependencies
 */
import { WPAdminPage } from '../lib/pages/wp-admin-page';
import { WPAdminSidebarComponent } from '../lib/components/wp-admin-sidebar-component';
import { EditorPage } from '../lib/pages/wp-editor-page';
import { PublishedPagePage } from '../lib/pages/published-page-page';
import * as DataHelper from '../lib/data-helper';

test( 'publish a Page', async ( { page } ) => {
    let editorPage: EditorPage;
    const titleText = DataHelper.getRandomPhrase();
    const bodyText =
        '"Be who you are and say what you feel, because \n' +
        'those who mind don’t matter and those who matter don’t mind." \n' +
        '– Bernard M. Baruch';

    await test.step( 'Go to WP-admin', async () => {
        const wpAdminPage = new WPAdminPage( page );
        await wpAdminPage.visit();
        await expect( wpAdminPage.adminBar ).toBeVisible();
    } );

    await test.step( 'Select add new Page', async () => {
        const wpAdminSidebarComponent = new WPAdminSidebarComponent( page );
        await wpAdminSidebarComponent.clickMenuItem( 'Pages' );
        await wpAdminSidebarComponent.clickSubMenuItem( 'Add New' );
    } );

    await test.step( 'Write Page', async () => {
        editorPage = new EditorPage( page );
        await editorPage.enterTitle( titleText );
        await editorPage.enterText( bodyText );
        await editorPage.addImage( 'test_media/image_01.jpg' );
    } );

    await test.step( 'Publish and visit page', async () => {
        const publishedURL = await editorPage.publish( { visit: true } );
        return expect( publishedURL ).toBe( page.url() );
    } );

    await test.step( 'Validate published page', async () => {
        const publishedPagePage = new PublishedPagePage( page );
        await publishedPagePage.validateTextInPost( titleText );
        return expect( publishedPagePage.isImageDisplayed() ).resolves.toBeTruthy();
    } );
} );
