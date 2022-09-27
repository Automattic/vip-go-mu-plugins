/**
 * External dependencies
 */
import { Page } from '@playwright/test';

const selectors = {
    pageLink: ( pageID: string ) => `#post-${ pageID } a.row-title`,
};

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
    visit(): Promise<unknown> {
        return this.page.goto( '/wp-admin/edit.php?post_type=page' );
    }

    /**
     * Edit Page by ID
     *
     * @param { string } pageID ID of the page to be edited
     */
    editPageByID( pageID: string ): Promise<void> {
        return this.page.click( selectors.pageLink( pageID ) );
    }
}
