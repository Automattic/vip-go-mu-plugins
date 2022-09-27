/**
 * External dependencies
 */
import { Page } from 'playwright';

const selectors = {
    entryTitle: '.wp-block-post-title',
    pageImage: '.entry-content img',
    pageText: ( text: string ) => `text=${ text }`,
};

/**
 * Represents the site's published page.
 */
export class PublishedPagePage {
    private page: Page;

    /**
     * Constructs an instance of the component.
     *
     * @param {Page} page The underlying page.
     */
    constructor( page: Page ) {
        this.page = page;
    }

    /**
     * Validates that the provided text can be found in the page.
     *
     * @param {string} text Text to search for in the page
     */
    async validateTextInPost( text: string ): Promise<void> {
        // If text isn't found the first time, reload and check again up to 2 more times.
        let postTry = 0;
        /* eslint-disable no-await-in-loop */
        while ( postTry < 3 ) {
            await this.page.waitForSelector( selectors.entryTitle );
            if ( await this.page.locator( selectors.pageText( text ) ).first().isVisible() ) {
                break;
            } else {
                await this.page.reload();
                postTry++;
            }
        }
        /* eslint-enable no-await-in-loop */
    }

    /**
     * Returns boolean of whether or not image was found in page
     *
     * @return {Promise<boolean>} True if image is found, otherwise false
     */
    isImageDisplayed(): Promise<boolean> {
        return this.page.isVisible( selectors.pageImage );
    }
}
