import { expect, test } from '@playwright/test';

import { LostPasswordPage } from '../lib/pages/lost-password-page';
import { LoginPage } from '../lib/pages/wp-login-page';

const genericLoginError = 'Error: The username/email address or password is incorrect';
const resetConfirmation = 'If there is an account associated with the username/email address, you will receive an email with a link to reset your password.';

test.describe( 'Security', () => {
	test.beforeEach( async ( { page, context } ) => {
		await context.clearCookies();
		await page.goto( '/wp-login.php' );
	} );

	test( 'Reset password for existing user', async ( { page } ) => {
		const loginPage = new LoginPage( page );
		await loginPage.lostPassword();

		const lostPasswordPage = new LostPasswordPage( page );
		const response = await lostPasswordPage.resetPassword( process.env.E2E_USER! );

		expect( response.status() ).toBe( 200 );
		expect( response.url() ).toContain( '/wp-login.php?checkemail=confirm' );

		await expect( page.locator( '#login .message' ) ).toHaveText( resetConfirmation );
	} );

	test( 'Reset password for existing non-existing user', async ( { page } ) => {
		const loginPage = new LoginPage( page );
		await loginPage.lostPassword();

		const lostPasswordPage = new LostPasswordPage( page );
		const response = await lostPasswordPage.resetPassword( 'this-user-does-not-exist' );

		expect( response.status() ).toBe( 200 );
		expect( response.url() ).toContain( '/wp-login.php?checkemail=confirm' );

		await expect( page.locator( '#login .message' ) ).toHaveText( resetConfirmation );
	} );

	test( 'Login with incorrect password', async ( { page } ) => {
		const loginPage = new LoginPage( page );
		await loginPage.loginEx( process.env.E2E_USER!, 'bad-password', false );

		expect( page.url() ).toContain( '/wp-login.php' );

		const loginError = await loginPage.getLoginError();
		expect( loginError ).toContain( genericLoginError );
	} );

	test( 'Login with incorrect credentials', async ( { page } ) => {
		const loginPage = new LoginPage( page );
		await loginPage.loginEx( 'no-such-user', 'bad-password', false );

		expect( page.url() ).toContain( '/wp-login.php' );

		const loginError = await loginPage.getLoginError();
		expect( loginError ).toContain( genericLoginError );
	} );
} );
