/**
 * External dependencies
 */
import { Page } from '@playwright/test';

const selectors = {
    selectFilesButton: '#plupload-browse-button',
    attachedMediaDetails: '.attachment-details',
    copyURLButton: '.copy-attachment-url',
};

export class MediaUploadPage {
    readonly page: Page;

    /**
     * Constructs an instance of the component.
     *
     * @param { Page } page The underlying page
     */
    constructor( page: Page ) {
        this.page = page;
    }

    /**
     * Upload File
     *
     * @param { string } mediaFile Media file name
     */
    async uploadFile( mediaFile: string ): Promise<void> {
        const [ fileChooser ] = await Promise.all( [
            // It is important to call waitForEvent before click to set up waiting.
            this.page.waitForEvent( 'filechooser' ),
            this.page.click( selectors.selectFilesButton ),
        ] );

        return fileChooser.setFiles( mediaFile );
    }

    /**
     * Get Meduia URL
     *
     * @return { Promise<string | null> } Url of uploaded media
     */
    async getMediaUrl(): Promise<string | null> {
        await this.page.waitForSelector( selectors.attachedMediaDetails );
        return this.page.locator( selectors.copyURLButton ).getAttribute( 'data-clipboard-text' );
    }
}
