/**
 * External dependencies
 */
import { Page } from 'playwright';

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
            await this.page.waitForSelector( '.entry-title' );
            if ( await this.page.locator( `text=${ text }` ) ) {
                break;
            } else {
                await this.page.reload();
                pageTry++;
            }
        }
    }
}
