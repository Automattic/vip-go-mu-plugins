import { expect, test } from '@playwright/test';

import { LostPasswordPage } from '../lib/pages/lost-password-page';
import { LoginPage } from '../lib/pages/wp-login-page';

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
	} );

	test( 'Reset password for existing non-existing user', async ( { page } ) => {
		const loginPage = new LoginPage( page );
		await loginPage.lostPassword();

		const lostPasswordPage = new LostPasswordPage( page );
		const response = await lostPasswordPage.resetPassword( 'this-user-does-not-exist' );

		expect( response.status() ).toBe( 200 );
		expect( response.url() ).toContain( '/wp-login.php?checkemail=confirm' );
	} );
} );
