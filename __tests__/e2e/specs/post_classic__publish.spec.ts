import { expect, test } from '@playwright/test';
import { WPAdminPage } from '../lib/pages/wp-admin-page';
import { ClassicEditorPage } from '../lib/pages/wp-classic-editor-page';
import { PublishedPostPage } from '../lib/pages/published-post-page';
import * as DataHelper from '../lib/data-helper';

test( 'Publish a Post', async ( {page} ) => {
    let classicEditorPage: ClassicEditorPage;
    const titleText = DataHelper.getRandomPhrase();
    const bodyText = '"Be who you are and say what you feel, because \n \
    those who mind don’t matter and those who matter don’t mind." \n \
    – Bernard M. Baruch'

    test.skip( process.env.E2E_CLASSIC_TESTS === 'false', 'Classic Tests skipped, plugin not installed')

    await test.step( 'Go to WP-admin', async () => {
        const wpAdminPage = new WPAdminPage( page );
        await wpAdminPage.visit();
        await expect( wpAdminPage.adminBar ).toBeVisible();
    } );

    await test.step( 'Add new post in classic editor', async () => {
        await page.goto( '/wp-admin/post-new.php?classic-editor&classic-editor__forget' );
    } );

    await test.step( 'Write Post', async () => {
        classicEditorPage = new ClassicEditorPage( page );
        await classicEditorPage.enterTitle( titleText );
        await classicEditorPage.enterText( bodyText );
    } );

    await test.step( 'Publish and visit post', async () => {
        const publishedURL = await classicEditorPage.publish( { visit: true } );
        expect( publishedURL ).toBe( page.url() );
    } );

    await test.step( 'Validate published post', async () => {
        const publishedPostPage = new PublishedPostPage( page );
        await publishedPostPage.validateTextInPost( titleText );
    } );
} )