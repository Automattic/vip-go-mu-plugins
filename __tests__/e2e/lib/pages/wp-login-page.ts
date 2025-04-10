import type { Page } from '@playwright/test';

const selectors = {
	userField: '#user_login',
	passwordField: '#user_pass',
	rememberMeField: 'input#rememberme',
	submitButton: '#wp-submit',
	lostPasswordLink: '#nav a[href*="wp-login.php?action=lostpassword"]',
	loginErrorBlock: 'div#login_error',
};

export class LoginPage {
	private page: Page;

	/**
	 * Constructs an instance of the component.
	 *
	 * @param { Page } page The underlying page
	 */
	constructor( page: Page ) {
		this.page = page;
	}

	/**
	 * Navigate to login page
	 */
	public visit(): Promise<unknown> {
		return this.page.goto( '/wp-login.php' );
	}

	/**
	 * Logs in to account with specified username and password
	 *
	 * @param {string} username Username to login as
	 * @param {string} password Password for account
	 */
	public async login( username: string, password: string ): Promise<unknown> {
		await this.page.fill( selectors.userField, username );
		await this.page.fill( selectors.passwordField, password );
		return Promise.all( [ this.page.waitForURL( '**/wp-admin/**' ), this.page.click( selectors.submitButton ) ] );
	}

	public async loginEx( login: string, password: string, rememberMe: boolean ): Promise<unknown> {
		await this.page.locator( selectors.userField ).fill( login );
		await this.page.locator( selectors.passwordField ).fill( password );
		if ( rememberMe ) {
			await this.page.locator( selectors.rememberMeField ).check();
		} else {
			await this.page.locator( selectors.rememberMeField ).uncheck();
		}

		await this.page.click( selectors.submitButton );
		return this.page.waitForLoadState( 'load' );
	}

	public lostPassword(): Promise<unknown> {
		return Promise.all( [
			this.page.waitForURL( /\/wp-login\.php\?action=lostpassword/ ),
			this.page.locator( selectors.lostPasswordLink ).click(),
		] );
	}

	public async getLoginError(): Promise<string | null> {
		const locator = this.page.locator( selectors.loginErrorBlock );
		if ( await locator.count() > 0 ) {
			return locator.textContent();
		}

		return null;
	}
}
