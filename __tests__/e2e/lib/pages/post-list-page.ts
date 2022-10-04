/**
 * External dependencies
 */
import { Page } from '@playwright/test';

const selectors = {
    postLink: ( postID: string ) => `#post-${ postID } a.row-title`,
};

export class PostListPage {
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
     * Navigate to Post List page
     */
    visit(): Promise<unknown> {
        return this.page.goto( '/wp-admin/edit.php' );
    }

    /**
     * Edit Post by ID
     *
     * @param {string} postID ID of the post to be edited
     */
    editPostByID( postID: string ): Promise<void> {
        return this.page.click( selectors.postLink( postID ) );
    }
}
