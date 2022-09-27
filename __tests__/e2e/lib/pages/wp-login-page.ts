/**
 * External dependencies
 */
import { Page } from '@playwright/test';

const selectors = {
    userField: '#user_login',
    passwordField: '#user_pass',
    submitButton: '#wp-submit',
};

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
     */
    visit(): Promise<unknown> {
        return this.page.goto( '/wp-login.php' );
    }

    /**
     * Logs in to account with specified username and password
     *
     * @param {string} username Username to login as
     * @param {string} password Password for account
     */
    async login( username: string, password: string ): Promise<unknown> {
        await this.page.fill( selectors.userField, username );
        await this.page.fill( selectors.passwordField, password );
        return Promise.all( [ this.page.waitForNavigation(), this.page.click( selectors.submitButton ) ] );
    }
}
