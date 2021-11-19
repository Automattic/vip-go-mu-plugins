import { Locator, Page } from "@playwright/test";

export class WPAdminPage {
    readonly page: Page;
    readonly adminBar: Locator;

    /**
     * @param {import('playwright').Page} page 
     */
    constructor(page: Page) {
      this.page = page;
      this.adminBar = page.locator( '#wpadminbar' );
    }

    /**
     * Navigate to WP Admin
     * @returns {Promise<void>} No return value.
     */
    async visit(): Promise< void > {
      await this.page.goto( '/wp-admin' );
    }
}
