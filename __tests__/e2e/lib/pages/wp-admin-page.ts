/**
 * External dependencies
 */
import { Locator, Page } from '@playwright/test';

export class WPAdminPage {
    readonly page: Page;
    readonly adminBar: Locator;

    /**
     * Constructs an instance of the component.
     *
     * @param { Page } page The underlying page
     */
    constructor( page: Page ) {
        this.page = page;
        this.adminBar = page.locator( '#wpadminbar' );
    }

    /**
     * Navigate to WP Admin
     * @returns {Promise<void>} No return value.
     */
    async visit(): Promise<void> {
        await this.page.goto( '/wp-admin' );
    }
}
