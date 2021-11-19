import { Page } from "@playwright/test";

export class LoginPage {
    private page: Page; 

    /**
     * @param { import( 'playwright' ).Page } page 
     */
    constructor( page: Page ) {
      this.page = page;
    }

    /**
     * Navigate to login page
     * @returns {Promise<void>} No return value.
     */
    async visit(): Promise< void > {
      await this.page.goto( '/wp-login.php' );
    }

    /**
     * 
     * @param {string} username Username to login as
     * @param {string} password Password for account
     * @returns {Promise<void>} No return value.
     */
    async login( username: string, password: string ): Promise< void > {
      await this.page.fill( '#user_login', username );
      await this.page.fill( '#user_pass', password );
      await Promise.all( [
        this.page.waitForNavigation(),
        this.page.click( '#wp-submit' )
      ] );
    }
}
