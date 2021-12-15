/**
 * External dependencies
 */
import { Page } from '@playwright/test';

export class LoginPage {
    private page: Page;

    /**
     *  Constructs an instance of the component.
     *
     * @param { Page } page The underlying page
     */
    constructor( page: Page ) {
        this.page = page;
    }

    /**
     * Navigate to login page
     *
     * @returns {Promise<void>} No return value.
     */
    async visit(): Promise<void> {
        await this.page.goto( '/wp-login.php' );
    }

    /**
     * Logs in to account with specified username and password
     *
     * @param {string} username Username to login as
     * @param {string} password Password for account
     * @returns {Promise<void>} No return value.
     */
    async login( username: string, password: string ): Promise<void> {
        await this.page.fill( '#user_login', username );
        await this.page.fill( '#user_pass', password );
        await Promise.all( [ this.page.waitForNavigation(), this.page.click( '#wp-submit' ) ] );
    }
}
