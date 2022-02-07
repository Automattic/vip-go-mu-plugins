/**
 * External dependencies
 */
import { Page } from 'playwright';

const selectors = {
    entryTitle: '.entry-title',
    postImage: '.entry-content img',
    postText: ( text: string ) => `text=${ text }`,
};

/**
 * Represents the site's published post.
 */
export class PublishedPostPage {
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
     * Validates that the provided text can be found in the post page.
     *
     * @param {string} text Text to search for in post page
     */
    async validateTextInPost( text: string ): Promise<void> {
        // If text isn't found the first time, reload and check again up to 2 more times.
        let pageTry = 0;
        while ( pageTry < 3 ) {
            await this.page.waitForSelector( selectors.entryTitle );
            if ( await this.page.locator( selectors.postText( text ) ) ) {
                break;
            } else {
                await this.page.reload();
                pageTry++;
            }
        }
    }

    /**
     * Returns boolean of whether or not image was found in post
     *
     * @returns {boolean} True if image is found, otherwise false
     */
    async isImageDisplayed(): Promise<boolean> {
        return this.page.isVisible( selectors.postImage );
    }
}
