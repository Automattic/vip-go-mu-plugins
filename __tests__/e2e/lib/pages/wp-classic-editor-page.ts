/**
 * External dependencies
 */
import { Page } from '@playwright/test';

const selectors = {
    editorTitle: '#title',
    editorFrame: '#content_ifr',
    editorBody: '#tinymce p',
    saveDraftButton: '#save-post',
    previewButton: '#post-preview',
    publishButton: '#publish',
    viewButton: '#message p a',
    permalink: '#sample-permalink a',
    insertMediaButton: '#insert-media-button',
    mediaUploadButton: '#__wp-uploader-id-1',
    addMediaButton: '.media-button-insert',
    uploadTab: '#menu-item-upload',
    postImage: '#tinymce img',
};

export class ClassicEditorPage {
    private page: Page;

    /**
     * Constructs an instance of the component.
     *
     * @param { Page } page The underlying page
     */
    constructor( page: Page ) {
        this.page = page;
    }

    /**
     * Enter Title of page or post
     *
     * @param {string} title Page/Post Title
     */
    async enterTitle( title: string ): Promise<void> {
        await this.page.click( selectors.editorTitle );
        await this.page.fill( selectors.editorTitle, title );
    }

    /**
     * Enter text in to page or post
     *
     * @param {string} text Text to enter
     */
    async enterText( text: string ): Promise<void> {
        await this.page.click( selectors.editorFrame );
        await this.page.keyboard.type( text );
    }

    /**
     * Add Image to post or page
     *
     * @param {string} fileName Name of image file
     */
    async addImage( fileName: string ): Promise<void> {
        await this.page.click( selectors.insertMediaButton );
        await this.page.click( selectors.uploadTab );

        const [ fileChooser ] = await Promise.all( [
            // It is important to call waitForEvent before click to set up waiting.
            this.page.waitForEvent( 'filechooser' ),
            this.page.click( selectors.mediaUploadButton ),
        ] );
        await fileChooser.setFiles( fileName );
        await this.page.click( selectors.addMediaButton );
        await this.page.waitForLoadState( 'networkidle' );
        await this.page.frameLocator( selectors.editorFrame ).locator( selectors.postImage );
    }

    /**
     * Publishes the post or page.
     *
     * @param {boolean} visit Whether to then visit the page.
     * @return {string} Url of the published post or page
     */
    async publish( { visit = false }: { visit?: boolean } = {} ): Promise<string> {
        const publishedURL = ( await this.page.locator( selectors.permalink ).textContent() ) as string;
        await this.page.click( selectors.publishButton );

        if ( visit ) {
            await this.visitPublishedPost( publishedURL );
        }
        return publishedURL;
    }

    /**
     * Visits the published entry from the post-publish sidebar.
     *
     * @param { string } url URL of post to visit
     */
    private visitPublishedPost( url: string ): Promise<unknown> {
        return Promise.all( [
            this.page.waitForNavigation( { waitUntil: 'networkidle', url } ),
            this.page.click( selectors.viewButton ),
        ] );
    }
}
