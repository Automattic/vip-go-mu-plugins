/**
 * External dependencies
 */
import { Page } from '@playwright/test';

export class PageListPage {
    readonly page: Page;

    /**
     *  Constructs an instance of the component.
     *
     * @param { Page } page The underlying page
     */
    constructor( page: Page ) {
        this.page = page;
    }

    /**
     * Navigate to Page List page
     */
    async visit(): Promise<void> {
        await this.page.goto( '/wp-admin/edit.php?post_type=page' );
    }

    /**
     * Edit Page by ID
     *
     * @param { string } pageID ID of the page to be edited
     */
    async editPageByID( pageID: string ): Promise<void> {
        await this.page.click( `#post-${ pageID } a.row-title` );
    }
}
