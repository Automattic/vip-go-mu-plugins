import { Page } from "@playwright/test";

export class PostListPage {
    readonly page: Page;

    /**
     * @param {import('playwright').Page} page 
     */
    constructor(page: Page) {
      this.page = page;
    }

    /**
     * Navigate to Post List page
     * @returns {Promise<void>} No return value.
     */
    async visit(): Promise< void > {
      await this.page.goto( '/wp-admin/edit.php' );
    }

    /**
     * Edit Post by ID
     * @returns {Promise<void>} No return value.
     */
    async editPostByID( postID: string): Promise< void > {
      await this.page.click( `#post-${postID} a.row-title` );
    }
}
