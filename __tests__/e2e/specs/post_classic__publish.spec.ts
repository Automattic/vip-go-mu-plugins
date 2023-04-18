/**
 * External dependencies
 */
import { expect, test } from '@playwright/test';

/**
 * Internal dependencies
 */
import { WPAdminPage } from '../lib/pages/wp-admin-page';
import { ClassicEditorPage } from '../lib/pages/wp-classic-editor-page';
import { PublishedPostPage } from '../lib/pages/published-post-page';
import * as DataHelper from '../lib/data-helper';

test( 'publish a Post', async ( { page } ) => {
    let classicEditorPage: ClassicEditorPage;
    const titleText = DataHelper.getRandomPhrase();
    const bodyText =
        '"Be who you are and say what you feel, because \n' +
        'those who mind don’t matter and those who matter don’t mind." \n' +
        '– Bernard M. Baruch';

    // eslint-disable-next-line playwright/no-skipped-test
    test.skip( process.env.E2E_CLASSIC_TESTS === 'false', 'Classic Tests skipped, plugin not installed' );

    await test.step( 'Go to WP-admin', async () => {
        const wpAdminPage = new WPAdminPage( page );
        await wpAdminPage.visit();
        return expect( wpAdminPage.adminBar ).toBeVisible();
    } );

    await test.step( 'Add new post in classic editor', () => {
        return page.goto( '/wp-admin/post-new.php?classic-editor&classic-editor__forget' );
    } );

    await test.step( 'Write Post', async () => {
        classicEditorPage = new ClassicEditorPage( page );
        await classicEditorPage.enterTitle( titleText );
        await classicEditorPage.enterText( bodyText );
        await classicEditorPage.addImage( 'test_media/image_01.jpg' );
    } );

    await test.step( 'Publish and visit post', async () => {
        const publishedURL = await classicEditorPage.publish( { visit: true } );
        return expect( publishedURL ).toBe( page.url() );
    } );

    await test.step( 'Validate published post', () => {
        const publishedPostPage = new PublishedPostPage( page );
        return publishedPostPage.validateTextInPost( titleText );
    } );
} );
