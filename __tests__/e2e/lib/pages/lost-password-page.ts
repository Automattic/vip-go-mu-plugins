import type { Locator, Page, Response } from '@playwright/test';

export class LostPasswordPage {
	public readonly loginField: Locator;
	public readonly getPasswordButton: Locator;
	public readonly loginErrorBlock: Locator;
	public readonly loginLink: Locator;
	public readonly registerLink: Locator;
	public readonly backToBlogLink: Locator;

	public constructor( private readonly page: Page ) {
		this.loginField = page.locator( 'input#user_login' );
		this.getPasswordButton = page.locator( 'input#wp-submit' );
		this.loginErrorBlock = page.locator( 'div#login_error' );
		this.loginLink = page.locator( '#nav a[href$="wp-login.php"]' );
		this.registerLink = page.locator( '#nav a[href*="wp-login.php?action=register"]' );
		this.backToBlogLink = page.locator( '#backtoblog a' );
	}

	public visit(): Promise<Response> {
		return this.page.goto( './wp-login.php?action=lostpassword' ) as Promise<Response>;
	}

	public async resetPassword( login: string ): Promise<Response> {
		await this.loginField.fill( login );
		const responsePromise = this.page.waitForResponse( ( resp ) => resp.url().includes( '/wp-login.php' ) && resp.request().method() === 'GET' );
		await this.getPasswordButton.click();
		return responsePromise;
	}
}
