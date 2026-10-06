/**
 * External dependencies
 */
import { expect, test } from '@playwright/test';

/**
 * Internal dependencies
 */
import { SearchPage } from '../lib/pages/search-page';

test.describe( 'Search Dev Tools', () => {
	let searchPage: SearchPage;

	test.beforeEach( async ( { page } ) => {
		searchPage = new SearchPage( page );
		await searchPage.visit( 'Hello' );
		await searchPage.openSearchDevTools();
	} );

	test( 'inspects the queries run on the page', async () => {
		// Formatting rules (labels, timing, index names, cross-site flags) are unit tested in
		// search/search-dev-tools/tests/; these steps check the real WordPress + Elasticsearch data.
		await test.step( 'Info strip lists global settings, then this site\'s settings', async () => {
			expect( await searchPage.getInfoStrip() ).toMatch( /Elasticsearch[\s\S]*Rate limited[\s\S]*Concurrent[\s\S]*This site:[\s\S]*Post types[\s\S]*Statuses[\s\S]*Meta/ );
		} );

		await test.step( 'Sidebar lists the main search with its caller', async () => {
			const first = await searchPage.getQueryListItemText( 0 );
			expect( first ).toContain( 'Main search “Hello”' );
			expect( first ).toMatch( /\b1 hit\b/ );
			// Caller comes from ElasticPress's real backtrace format.
			await expect( searchPage.queryListCallerFile( 0 ) ).toHaveText( /^class-wp\.php:\d+$/ );
		} );

		await test.step( 'Detail header shows status, matched hits and index', async () => {
			await expect( searchPage.detailTitle() ).toHaveText( 'Main search “Hello”' );
			await expect( searchPage.statusChip() ).toHaveText( '200 OK' );
			await expect( searchPage.detailStats() ).toContainText( '1 hit' );
			// Index name with the real site prefix stripped.
			// Visible label first; the full index name follows as screen-reader text.
			await expect( searchPage.detailIndex() ).toHaveText( /^post-\d+\./ );
		} );

		// Browser-only bug: Preact + the editor's `:empty` rule hid the highlighted layer.
		await test.step( 'Request is syntax highlighted like the response', () => expect( searchPage.isRequestHighlighted() ).resolves.toBe( true ) );

		await test.step( 'WP_Query tab lists the query arguments', () => expect( searchPage.getWPQuery() ).resolves.toMatch( /search_terms\s+\["Hello"\]/ ) );

		await test.step( 'Trace tab lists the backtrace', () => expect( searchPage.getTrace() ).resolves.toContain( 'ElasticPress\\Elasticsearch->remote_request' ) );

		await test.step( 'Expand all and Collapse all fold the response', async () => {
			const autoLines = await searchPage.getResponseLineCount();
			await searchPage.clickResponseAction( 'Expand all' );
			const expandedLines = await searchPage.getResponseLineCount();
			await searchPage.clickResponseAction( 'Collapse all' );
			const collapsedLines = await searchPage.getResponseLineCount();

			expect( expandedLines ).toBeGreaterThan( autoLines );
			expect( collapsedLines ).toBeLessThan( autoLines );
		} );

		await test.step( 'Copy confirms only copies the browser accepts', async () => {
			await searchPage.useCopyFallback( false );
			await searchPage.clickResponseAction( 'Copy' );
			await expect.poll( () => searchPage.copyAttempts() ).toEqual( { copies: [ expect.any( String ) ], copiedShown: false, leftovers: 0 } );

			await searchPage.useCopyFallback( true );
			await searchPage.clickResponseAction( 'Copy' );
			await expect( searchPage.copyButton() ).toHaveText( 'Copied' );
			// The whole response was selected when copied, and focus stays on the button.
			const { copies, leftovers } = await searchPage.copyAttempts();
			expect( JSON.parse( copies[ 0 ] ) ).toHaveProperty( 'hits' );
			expect( leftovers ).toBe( 0 );
			await expect( searchPage.copyButton() ).toBeFocused();
		} );

		await test.step( 'Close Dev Tools', () => searchPage.closeSearchDevTools() );
	} );

	test( 'edits and re-runs a query', async () => {
		let query = '';

		await test.step( 'Get the original query', async () => {
			query = await searchPage.getQuery();
			expect( query ).toContain( '"query": "Hello",' );
			await expect( searchPage.inlineRunButton() ).toBeHidden();
		} );

		await test.step( 'Editing shows the inline Run button and the edited marker', async () => {
			await searchPage.editQuery( query.replace( /"Hello"/g, '"world"' ) );
			await expect( searchPage.inlineRunButton() ).toBeVisible();
			await expect( searchPage.editedMarker( 0 ) ).toBeVisible();
		} );

		await test.step( 'Typing back to the original is not an edit', async () => {
			await searchPage.editQuery( query );
			await expect( searchPage.inlineRunButton() ).toBeHidden();
			await expect( searchPage.editedMarker( 0 ) ).toBeHidden();
		} );

		await test.step( 'Reset restores the original query', async () => {
			await searchPage.editQuery( query.replace( /"Hello"/g, '"world"' ) );
			await expect( searchPage.resetQuery() ).resolves.toBe( query );
		} );

		await test.step( 'Re-running the unchanged query is labelled Re-run', async () => {
			await searchPage.runQuery();
			await expect( searchPage.statusChip() ).toHaveText( 'Re-run' );
			await expect( searchPage.editedMarker( 0 ) ).toBeHidden();
			await searchPage.resetQuery();
		} );

		await test.step( 'Running an edited query with the shortcut shows its response', async () => {
			await searchPage.editQuery( query.replace( /"Hello"/g, '"world"' ) );
			const json = await searchPage.runQueryWithShortcut();
			expect( json ).toMatchObject( {
				result: expect.objectContaining( {
					body: expect.any( Object ),
				} ),
			} );

			await searchPage.ensureQueryResponse( 'world' );
			await expect( searchPage.statusChip() ).toHaveText( 'Edited' );
			await expect( searchPage.editedMarker( 0 ) ).toBeVisible();
			await expect( searchPage.inlineRunButton() ).toBeHidden();
		} );

		await test.step( 'Repeated shortcuts start only one request at a time', async () => {
			await searchPage.resetQuery();
			await searchPage.delayRuns( 800 );
			const requests = await searchPage.countRunRequests( async () => {
				const editor = searchPage.panel.locator( 'textarea.sdt-code__textarea' );
				await editor.press( 'ControlOrMeta+Enter' );
				await editor.press( 'ControlOrMeta+Enter' );
				await editor.press( 'ControlOrMeta+Enter' );
				await expect( searchPage.statusChip() ).toHaveText( 'Re-run' );
			} );
			expect( requests ).toBe( 1 );
		} );

		await test.step( 'A run in flight survives closing and reopening the panel and blocks a second run', async () => {
			await searchPage.resetQuery();
			const requests = await searchPage.countRunRequests( async () => {
				await searchPage.headerRunButton().click();
				await searchPage.closeSearchDevTools();
				await searchPage.openSearchDevTools();
				await expect( searchPage.headerRunButton() ).toHaveText( 'Running…' );
				await expect( searchPage.headerRunButton() ).toBeDisabled();
				await searchPage.panel.locator( 'textarea.sdt-code__textarea' ).press( 'ControlOrMeta+Enter' );
				await expect( searchPage.statusChip() ).toHaveText( 'Re-run' );
			} );
			expect( requests ).toBe( 1 );
		} );

		await test.step( 'Typing back to the original during a run keeps the latest text', async () => {
			// Runs are still delayed by the route above.
			await searchPage.editQuery( query.replace( /"Hello"/g, '"world"' ) );
			await searchPage.panel.getByRole( 'button', { name: 'Run query' } ).click();
			await expect( searchPage.panel.getByRole( 'button', { name: 'Running…' } ).first() ).toBeVisible();
			await searchPage.editQuery( query );
			await expect( searchPage.statusChip() ).toHaveText( 'Edited' );
			await expect( searchPage.getQuery() ).resolves.toBe( query );
			await searchPage.stopDelayingRuns();
		} );

		await test.step( 'A failing edited query shows the status in the header and the reason in the response', async () => {
			const failing = JSON.parse( query ) as Record<string, unknown>;
			failing.sort = [ { 'meta.e2e_missing_field.long': { order: 'asc' } } ];
			await searchPage.editQuery( JSON.stringify( failing, null, 2 ) );
			await searchPage.runQuery();

			await expect( searchPage.statusChip() ).toHaveText( 'Edited' );
			await expect( searchPage.statusChip() ).toHaveClass( /sdt-chip--bad/ );
			await searchPage.ensureQueryResponse( 'No mapping found' );
		} );

		await test.step( 'Invalid JSON is rejected without a request', async () => {
			await searchPage.editQuery( '{ "query": ' );
			await searchPage.panel.getByRole( 'button', { name: 'Run query' } ).click();
			await expect( searchPage.requestError() ).toContainText( 'Invalid JSON' );
		} );
	} );

	test( 'keeps large requests and responses responsive', async () => {
		await test.step( 'Large requests are edited without syntax highlighting', async () => {
			const terms = Array.from( { length: 12000 }, ( _unused, idx ) => `term-value-${ idx }` );
			await searchPage.setLargeQuery( JSON.stringify( { query: { terms: { 'post_tag.slug': terms } } }, null, 2 ) );
			await expect( searchPage.requestNotice() ).toContainText( 'syntax highlighting is off' );
			expect( await searchPage.highlightedRequestKeys() ).toBe( 0 );
			await searchPage.resetQuery();
			await expect( searchPage.requestNotice() ).toBeHidden();
		} );

		await test.step( 'Expand all shows a large response as plain text', async () => {
			const hits = Array.from( { length: 3000 }, ( _unused, idx ) => ( {
				_index: 'vip-1-post-1',
				_id: String( idx ),
				_source: { post_title: `Post ${ idx }`, post_excerpt: 'x'.repeat( 300 ) },
			} ) );
			await searchPage.stubRunResponse( { took: 5, hits: { total: { value: 3000, relation: 'eq' }, hits } } );
			await searchPage.runQuery();

			await searchPage.clickResponseAction( 'Expand all' );
			await expect( searchPage.largeResponseNotice() ).toContainText( 'shown as plain text' );
			await expect( searchPage.plainResponse() ).toContainText( '"post_title": "Post 2999"' );

			await searchPage.panel.getByRole( 'button', { name: 'Show tree anyway' } ).click();
			await expect( searchPage.plainResponse() ).toBeHidden();
			expect( await searchPage.getResponseLineCount() ).toBeGreaterThan( 1000 );

			await searchPage.clickResponseAction( 'Collapse all' );
			await expect( searchPage.largeResponseNotice() ).toBeHidden();
		} );

		await test.step( 'A response that is huge even when folded starts as plain text', async () => {
			const buckets = Array.from( { length: 15000 }, ( _unused, idx ) => ( { key: `tag-${ idx }`, doc_count: idx } ) );
			await searchPage.stubRunResponse( { took: 9, hits: { total: { value: 0, relation: 'eq' }, hits: [] }, aggregations: { tags: { buckets } } } );
			await searchPage.runQuery();

			await expect( searchPage.largeResponseNotice() ).toContainText( 'shown as plain text' );
			await expect( searchPage.plainResponse() ).toContainText( '"key": "tag-14999"' );

			await searchPage.clickResponseAction( 'Collapse all' );
			await expect( searchPage.plainResponse() ).toBeHidden();
		} );
	} );

	test( 'keeps edits across closing and handles Escape', async ( { page } ) => {
		const edited = ( await searchPage.getQuery() ).replace( /"Hello"/g, '"world"' );
		await searchPage.editQuery( edited );

		await test.step( 'Escape inside the editor does not close the panel', async () => {
			await searchPage.pressEscapeInEditor();
			await expect( searchPage.panel ).toBeVisible();
		} );

		await test.step( 'Escape elsewhere closes the panel', async () => {
			await searchPage.pressEscapeOnPanel();
			await expect( searchPage.panel ).toBeHidden();
		} );

		await test.step( 'Reopening keeps the edited query', async () => {
			await searchPage.openSearchDevTools();
			await expect( searchPage.getQuery() ).resolves.toBe( edited );
			await expect( searchPage.editedMarker( 0 ) ).toBeVisible();
		} );

		await test.step( 'Focus that leaves for the Admin Bar comes back to the panel', async () => {
			await searchPage.focusAdminBarLink();
			await expect.poll( () => searchPage.isFocusInsidePanel() ).toBe( true );
		} );

		await test.step( 'Theme choice persists across page loads', async () => {
			await searchPage.setTheme( 'Dark' );
			await page.reload( { waitUntil: 'domcontentloaded' } );
			await searchPage.openSearchDevTools();
			await expect( searchPage.panel ).toHaveAttribute( 'data-theme', 'dark' );
			await searchPage.setTheme( 'Light' );
		} );
	} );
} );
