import { type Response, expect, test } from '@playwright/test';

test.describe( 'Generic Checks', () => {
	test( 'Page contains closing html tag and no wp_die() message', async ( { page, baseURL } ) => {
		expect( baseURL ).toBeDefined();
		const response = await page.goto( baseURL! ) as Response;
		expect.soft( response.status() ).toBeLessThan( 500 );
		await expect( page.locator( '.wp-die-message' ) ).toHaveCount( 0 );
		const html = await page.content();
		expect( html ).toContain( '</html>' );
	} );
} );
