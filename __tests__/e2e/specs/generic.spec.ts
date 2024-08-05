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

	test( 'REST API smoke test', async ( { request } ) => {
		const response = await request.get( './wp-json/' );
		expect( response.status() ).toBe( 200 );
		const data: unknown = await response.json();
		expect( typeof data ).toBe( 'object' );
		expect.soft( data ).toHaveProperty( 'name' );
		expect.soft( data ).toHaveProperty( 'description' );
		expect.soft( data ).toHaveProperty( 'url' );
		expect.soft( data ).toHaveProperty( 'routes' );
	} );

	test( 'XML RPC smoke test', async ( { request } ) => {
		const xmlPayload = '<?xml version="1.0"?><methodCall><methodName>demo.sayHello</methodName><params/></methodCall>';

		const response = await request.post( './xmlrpc.php', {
			headers: {
				'Content-Type': 'text/xml',
			},
			data: xmlPayload,
		} );

		expect( response.status() ).toBe( 200 );
		const responseText = await response.text();
		expect( responseText ).toContain( '<methodResponse>' );
		expect( responseText ).not.toContain( '<fault>' );
		expect( responseText ).toContain( '<string>Hello!</string>' );
	} );
} );
