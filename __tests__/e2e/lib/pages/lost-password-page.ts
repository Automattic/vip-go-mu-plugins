import type { Locator, Page, Response } from '@playwright/test';

export class LostPasswordPage {
	private readonly loginField: Locator;
	private readonly getPasswordButton: Locator;

	public constructor( private readonly page: Page ) {
		this.loginField = page.locator( 'input#user_login' );
		this.getPasswordButton = page.locator( 'input#wp-submit' );
	}

	public async resetPassword( login: string ): Promise<Response> {
		await this.loginField.fill( login );
		const responsePromise = this.page.waitForResponse( ( resp ) => resp.url().includes( '/wp-login.php' ) && resp.request().method() === 'GET' );
		await this.getPasswordButton.click();
		return responsePromise;
	}
}
