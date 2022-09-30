/**
 * External dependencies
 */
import { expect, test } from '@playwright/test';

/**
 * Internal dependencies
 */
import { WPAdminSidebarComponent } from '../lib/components/wp-admin-sidebar-component';
import { WPAdminPage } from '../lib/pages/wp-admin-page';
import { MediaUploadPage } from '../lib/pages/media-upload-page';

test( 'Add Media', async ( { page } ) => {
    let mediaUploadPage: MediaUploadPage;

    await test.step( 'Go to WP-admin', async () => {
        const wpAdminPage = new WPAdminPage( page );
        await wpAdminPage.visit();
        return expect( wpAdminPage.adminBar ).toBeVisible();
    } );

    await test.step( 'Select add new media', async () => {
        const wpAdminSidebarComponent = new WPAdminSidebarComponent( page );
        await wpAdminSidebarComponent.clickMenuItem( 'Media' );
        await wpAdminSidebarComponent.clickSubMenuItem( 'Add New' );
    } );

    await test.step( 'Upload Image', () => {
        mediaUploadPage = new MediaUploadPage( page );
        return mediaUploadPage.uploadFile( 'test_media/image_02.jpg' );
    } );

    await test.step( 'Verify image url', () => {
        const imageURL = mediaUploadPage.getMediaUrl();
        return expect( imageURL ).resolves.toContain( 'image_02' );
    } );
} );
