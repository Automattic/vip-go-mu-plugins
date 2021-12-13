import { Locator, Page } from "@playwright/test";

export class PageListPage {
    readonly page: Page;

    /**
     * @param {import('playwright').Page} page 
     */
    constructor(page: Page) {
      this.page = page;
    }

    /**
     * Navigate to Page List page
     * @returns {Promise<void>} No return value.
     */
    async visit(): Promise< void > {
      await this.page.goto( '/wp-admin/edit.php?post_type=page' );
    }

    /**
     * Edit Page by ID
     * @returns {Promise<void>} No return value.
     */
    async editPageByID( pageID: string): Promise< void > {
      await this.page.click( `#post-${pageID} a.row-title` );
    }
}
