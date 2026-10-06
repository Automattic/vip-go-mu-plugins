import { expect, type Locator, type Page } from '@playwright/test';

const selectors = {
	devToolsMenu: () => '#wp-admin-bar-vip-search-dev-tools',
	devToolsTrigger: () => 'button.sdt-ab-btn',
	panel: () => '#search-dev-tools-portal > .sdt',
	closeButton: () => 'button[aria-label="Close VIP Search Dev Tools"]',
	infoStrip: () => '.sdt-info',
	queryListItem: () => '.sdt-list__item',
	editedDot: () => '.sdt-dot',
	detailTitle: () => '.sdt-detail__title',
	statusChip: () => '.sdt-detail__title-row .sdt-chip',
	detailStats: () => '.sdt-detail__stats',
	tabPanel: () => '.sdt-pane:not(.sdt-pane--response) [role="tabpanel"]',
	requestEditor: () => 'textarea.sdt-code__textarea',
	inlineRun: () => '.sdt-pane:not(.sdt-pane--response) .sdt-pane__head button.sdt-btn--primary',
	requestError: () => '.sdt-pane:not(.sdt-pane--response) [role="alert"]',
	responseTree: () => '.sdt-pane--response .sdt-json',
	responseLine: () => '.sdt-pane--response .sdt-json__line',
	listCallerFile: () => '.sdt-path__file',
	detailIndex: () => '.sdt-detail__index',
	highlightedKey: () => '.sdt-code__pre .token.property',
	requestNotice: () => '.sdt-code__notice',
	largeResponseNotice: () => '.sdt-pane--response .sdt-notice--inline',
	plainResponse: () => '.sdt-pane--response pre.sdt-plain',
};

const DEV_TOOLS_ENDPOINT = '/wp-json/vip/v1/search/dev-tools';

const TAB_IDS = {
	Request: 'request',
	WP_Query: 'wp_query',
	Trace: 'trace',
} as const;

export class SearchPage {
	private readonly page: Page;
	private readonly devToolsTriggerLocator: Locator;
	public readonly panel: Locator;

	/**
	 * Constructs an instance of the component.
	 *
	 * @param {Page} page The underlying page
	 */
	constructor( page: Page ) {
		this.page = page;
		this.devToolsTriggerLocator = page.locator( selectors.devToolsMenu() ).locator( selectors.devToolsTrigger() );
		this.panel = page.locator( selectors.panel() );
	}

	/**
	 * Perform a search.
	 *
	 * @param {string} searchTerm Search term
	 * @return {Promise<*>} Resolves after DOMContentLoaded event fires
	 */
	public visit( searchTerm: string ): Promise<unknown> {
		return this.page.goto( `/?s=${ encodeURIComponent( searchTerm ) }`, { waitUntil: 'domcontentloaded' } );
	}

	/**
	 * Opens the Search Dev Tools panel from the Admin Bar.
	 *
	 * @return {Promise<*>} Resolves when the panel is visible
	 */
	public async openSearchDevTools(): Promise<unknown> {
		await this.devToolsTriggerLocator.click();
		return expect( this.panel ).toBeVisible();
	}

	/**
	 * Closes the panel with its close button.
	 *
	 * @return {Promise<*>} Resolves when the panel is gone
	 */
	public async closeSearchDevTools(): Promise<unknown> {
		await this.panel.locator( selectors.closeButton() ).click();
		return expect( this.panel ).toBeHidden();
	}

	/**
	 * Text of the info strip under the header.
	 *
	 * @return {Promise<string>} Info strip text
	 */
	public getInfoStrip(): Promise<string> {
		return this.panel.locator( selectors.infoStrip() ).innerText();
	}

	/**
	 * Sidebar text (label, time, caller, hits) for a query.
	 *
	 * @param {number} index Zero-based query index
	 * @return {Promise<string>} Sidebar item text
	 */
	public getQueryListItemText( index: number ): Promise<string> {
		return this.queryListItems().nth( index ).innerText();
	}

	/**
	 * Locator for the sidebar "edited" marker of a query.
	 *
	 * @param {number} index Zero-based query index
	 * @return {Locator} Edited marker
	 */
	public editedMarker( index: number ): Locator {
		return this.queryListItems().nth( index ).locator( selectors.editedDot() );
	}

	/**
	 * Selected query title.
	 *
	 * @return {Locator} Title
	 */
	public detailTitle(): Locator {
		return this.panel.locator( selectors.detailTitle() );
	}

	/**
	 * Status chip in the detail header ("200 OK", "Edited", "Re-run", …).
	 *
	 * @return {Locator} Status chip
	 */
	public statusChip(): Locator {
		return this.panel.locator( selectors.statusChip() );
	}

	/**
	 * Hits/failure reason and timing line in the detail header.
	 *
	 * @return {Locator} Stats line
	 */
	public detailStats(): Locator {
		return this.panel.locator( selectors.detailStats() );
	}

	/**
	 * Get the WP_Query arguments tab contents.
	 *
	 * @return {Promise<string>} Arguments as text
	 */
	public getWPQuery(): Promise<string> {
		return this.getTabContents( 'WP_Query' );
	}

	/**
	 * Get the stack trace tab contents.
	 *
	 * @return {Promise<string>} Backtrace as text
	 */
	public getTrace(): Promise<string> {
		return this.getTabContents( 'Trace' );
	}

	/**
	 * Returns the request body in the editor.
	 *
	 * @return {Promise<string>} Query
	 */
	public async getQuery(): Promise<string> {
		await this.openTab( 'Request' );
		return this.panel.locator( selectors.requestEditor() ).inputValue();
	}

	/**
	 * Replaces the request body in the editor.
	 *
	 * @param {string} newQuery New Query
	 * @return {Promise<*>} Resolves on success
	 */
	public async editQuery( newQuery: string ): Promise<unknown> {
		await this.openTab( 'Request' );
		return this.panel.locator( selectors.requestEditor() ).fill( newQuery );
	}

	/**
	 * Sets a very large request body in one input event. Playwright's `fill()` inserts text slowly enough
	 * to time out on hundreds of KB, while the editor itself renders it in well under a second.
	 *
	 * @param {string} newQuery New Query
	 * @return {Promise<*>} Resolves on success
	 */
	public async setLargeQuery( newQuery: string ): Promise<unknown> {
		await this.openTab( 'Request' );
		return this.panel.locator( selectors.requestEditor() ).evaluate( ( el: HTMLTextAreaElement, value: string ) => {
			Object.getOwnPropertyDescriptor( HTMLTextAreaElement.prototype, 'value' )!.set!.call( el, value );
			el.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		}, newQuery );
	}

	/**
	 * Inline Run button in the Request tab bar; only present while there are unrun edits.
	 *
	 * @return {Locator} Inline Run button
	 */
	public inlineRunButton(): Locator {
		return this.panel.locator( selectors.inlineRun() );
	}

	/**
	 * Error shown above the request editor (e.g. invalid JSON).
	 *
	 * @return {Locator} Request error
	 */
	public requestError(): Locator {
		return this.panel.locator( selectors.requestError() );
	}

	/**
	 * Resets the query to the original value.
	 *
	 * @return {Promise<string>} Original query
	 */
	public async resetQuery(): Promise<string> {
		await this.panel.getByRole( 'button', { name: 'Reset', exact: true } ).click();
		return this.getQuery();
	}

	/**
	 * Runs the query from the header button and returns the REST response JSON.
	 *
	 * @return {Promise<*>} Result of the query
	 */
	public runQuery(): Promise<unknown> {
		return this.runWith( () => this.panel.getByRole( 'button', { name: 'Run query' } ).click() );
	}

	/**
	 * Runs the query with the Cmd/Ctrl+Enter shortcut from inside the editor.
	 *
	 * @return {Promise<*>} Result of the query
	 */
	public runQueryWithShortcut(): Promise<unknown> {
		return this.runWith( () => this.panel.locator( selectors.requestEditor() ).press( 'ControlOrMeta+Enter' ) );
	}

	/**
	 * Waits until the response tree contains the specific substring.
	 *
	 * @param {string} substring Substring to check
	 * @return {Promise<*>} Resolves on success
	 */
	public ensureQueryResponse( substring: string ): Promise<unknown> {
		return expect( this.panel.locator( selectors.responseTree() ) ).toContainText( substring );
	}

	/**
	 * Number of rendered lines in the response tree.
	 *
	 * @return {Promise<number>} Line count
	 */
	public getResponseLineCount(): Promise<number> {
		return this.panel.locator( selectors.responseLine() ).count();
	}

	/**
	 * Click a response toolbar button ("Expand all", "Collapse all", "Copy").
	 *
	 * @param {string} name Button label
	 * @return {Promise<*>} Resolves on click
	 */
	public clickResponseAction( name: 'Expand all' | 'Collapse all' | 'Copy' ): Promise<unknown> {
		return this.panel.locator( '.sdt-pane--response' ).getByRole( 'button', { name, exact: true } ).click();
	}

	/**
	 * The response toolbar's Copy button; it reads "Copied" briefly after a successful copy.
	 *
	 * @return {Locator} Copy button
	 */
	public copyButton(): Locator {
		return this.panel.locator( '.sdt-pane--response' ).getByRole( 'button', { name: /^Cop(y|ied)$/ } );
	}

	/**
	 * Force the execCommand copy fallback used on http sites. Records what each copy would take, and whether the
	 * Copy button ever reads "Copied" (it reverts after a moment, so a later check could miss it).
	 *
	 * @param {boolean} accept Whether the browser accepts the copy (execCommand's return value)
	 * @return {Promise<void>} Resolves once installed
	 */
	public useCopyFallback( accept: boolean ): Promise<void> {
		return this.page.evaluate( ( acceptCopy ) => {
			Object.defineProperty( navigator, 'clipboard', { value: undefined, configurable: true } );
			const copies: string[] = [];
			const state = { copies, copiedShown: false };
			( window as unknown as { sdtCopy: typeof state } ).sdtCopy = state;
			const toolbar = document.querySelector( '.sdt-pane--response' );
			if ( toolbar ) {
				new MutationObserver( () => {
					state.copiedShown ||= toolbar.querySelector( 'button.sdt-btn' )?.textContent === 'Copied';
				} ).observe( toolbar, { subtree: true, childList: true, characterData: true } );
			}
			Object.defineProperty( document, 'execCommand', {
				configurable: true,
				value: () => {
					// The copy takes the selection in the focused element.
					const el = document.activeElement;
					copies.push( el instanceof HTMLTextAreaElement ? el.value.slice( el.selectionStart, el.selectionEnd ) : '' );
					return acceptCopy;
				},
			} );
		}, accept );
	}

	/**
	 * Since useCopyFallback(): the text each copy attempt took, whether "Copied" was shown, and how many temporary
	 * textareas remain.
	 *
	 * @return {Promise<{copies: string[], copiedShown: boolean, leftovers: number}>} Copy state
	 */
	public copyAttempts(): Promise<{ copies: string[], copiedShown: boolean, leftovers: number }> {
		return this.page.evaluate( () => {
			const { copies, copiedShown } = ( window as unknown as { sdtCopy: { copies: string[], copiedShown: boolean } } ).sdtCopy;
			return { copies, copiedShown, leftovers: document.querySelectorAll( 'textarea[readonly]' ).length };
		} );
	}

	/**
	 * Switch the color theme.
	 *
	 * @param {string} theme Light or Dark
	 * @return {Promise<*>} Resolves when the panel reflects the theme
	 */
	public async setTheme( theme: 'Light' | 'Dark' ): Promise<unknown> {
		await this.panel.getByRole( 'group', { name: 'Color theme' } ).getByRole( 'button', { name: theme } ).click();
		return expect( this.panel ).toHaveAttribute( 'data-theme', theme.toLowerCase() );
	}

	/**
	 * Press Escape with focus inside the request editor.
	 *
	 * @return {Promise<*>} Resolves after the key press
	 */
	public pressEscapeInEditor(): Promise<unknown> {
		return this.panel.locator( selectors.requestEditor() ).press( 'Escape' );
	}

	/**
	 * Press Escape with focus on the panel itself (outside the editor).
	 *
	 * @return {Promise<*>} Resolves after the key press
	 */
	public async pressEscapeOnPanel(): Promise<unknown> {
		await this.panel.focus();
		return this.page.keyboard.press( 'Escape' );
	}

	/**
	 * File name (with line) of the caller shown for a query in the sidebar; never truncated.
	 *
	 * @param {number} index Zero-based query index
	 * @return {Locator} Caller file name
	 */
	public queryListCallerFile( index: number ): Locator {
		return this.queryListItems().nth( index ).locator( selectors.listCallerFile() );
	}

	/**
	 * Short index name shown in the detail header stats.
	 *
	 * @return {Locator} Index label
	 */
	public detailIndex(): Locator {
		return this.panel.locator( selectors.detailIndex() );
	}

	/**
	 * Whether the request editor shows syntax highlighting: highlighted tokens exist and the
	 * textarea text on top of them is transparent.
	 *
	 * @return {Promise<boolean>} Highlighting visible
	 */
	public async isRequestHighlighted(): Promise<boolean> {
		await this.openTab( 'Request' );
		const tokens = await this.panel.locator( selectors.highlightedKey() ).count();
		const fill = await this.panel.locator( selectors.requestEditor() ).evaluate( ( el ) => getComputedStyle( el ).webkitTextFillColor );
		return tokens > 0 && fill === 'rgba(0, 0, 0, 0)';
	}

	/**
	 * Replace the Dev Tools REST response (re-runs) with a fixed body.
	 *
	 * @param {Object} body Elasticsearch response body to return
	 * @return {Promise<void>} Resolves once the route is registered
	 */
	public async stubRunResponse( body: unknown ): Promise<void> {
		await this.page.route( `**${ DEV_TOOLS_ENDPOINT }`, ( route ) => route.fulfill( { json: { result: { body } } } ) );
	}

	/**
	 * Hold Dev Tools re-run requests for `ms` before letting them through, so a run stays in flight.
	 *
	 * @param {number} ms Delay in milliseconds
	 * @return {Promise<void>} Resolves once the route is registered
	 */
	public async delayRuns( ms: number ): Promise<void> {
		await this.page.route( `**${ DEV_TOOLS_ENDPOINT }`, async ( route ) => {
			await new Promise( ( resolve ) => setTimeout( resolve, ms ) );
			await route.continue();
		} );
	}

	/**
	 * Header Run button (reads "Running…" while a run is in flight).
	 *
	 * @return {Locator} Run button
	 */
	public headerRunButton(): Locator {
		return this.panel.locator( '.sdt-detail__actions .sdt-btn--primary' );
	}

	/**
	 * Move focus to an Admin Bar link outside the panel.
	 *
	 * @return {Promise<void>} Resolves after focusing
	 */
	public async focusAdminBarLink(): Promise<void> {
		await this.page.locator( '#wp-admin-bar-site-name > a' ).focus();
	}

	/**
	 * Whether keyboard focus is inside the panel.
	 *
	 * @return {Promise<boolean>} Focus inside
	 */
	public isFocusInsidePanel(): Promise<boolean> {
		return this.panel.evaluate( ( panel ) => panel.contains( document.activeElement ) );
	}

	/**
	 * Stop delaying Dev Tools re-run requests.
	 *
	 * @return {Promise<void>} Resolves once the route is removed
	 */
	public stopDelayingRuns(): Promise<void> {
		return this.page.unroute( `**${ DEV_TOOLS_ENDPOINT }` );
	}

	/**
	 * Count Dev Tools re-run requests started while `action` runs (and until its responses settle).
	 *
	 * @param {Function} action Action that may start runs
	 * @return {Promise<number>} Number of requests started
	 */
	public async countRunRequests( action: () => Promise<unknown> ): Promise<number> {
		let count = 0;
		const onRequest = ( request: { url: () => string; method: () => string } ): void => {
			if ( request.url().endsWith( DEV_TOOLS_ENDPOINT ) && request.method() === 'POST' ) {
				count++;
			}
		};
		this.page.on( 'request', onRequest );
		try {
			await action();
		} finally {
			this.page.off( 'request', onRequest );
		}
		return count;
	}

	/**
	 * Notice shown when a large request is edited without syntax highlighting.
	 *
	 * @return {Locator} Notice
	 */
	public requestNotice(): Locator {
		return this.panel.locator( selectors.requestNotice() );
	}

	/**
	 * Count of syntax-highlighted keys in the request editor.
	 *
	 * @return {Promise<number>} Highlighted key tokens
	 */
	public highlightedRequestKeys(): Promise<number> {
		return this.panel.locator( selectors.highlightedKey() ).count();
	}

	/**
	 * Notice shown when an expanded response is too large for the tree.
	 *
	 * @return {Locator} Notice
	 */
	public largeResponseNotice(): Locator {
		return this.panel.locator( selectors.largeResponseNotice() );
	}

	/**
	 * Plain-text rendering of a large expanded response.
	 *
	 * @return {Locator} Plain text block
	 */
	public plainResponse(): Locator {
		return this.panel.locator( selectors.plainResponse() );
	}

	private queryListItems(): Locator {
		return this.panel.locator( selectors.queryListItem() );
	}

	private async openTab( name: 'Request' | 'WP_Query' | 'Trace' ): Promise<void> {
		const tab = this.panel.locator( `#sdt-tab-${ TAB_IDS[ name ] }` );
		await tab.click();
		await expect( tab ).toHaveAttribute( 'aria-selected', 'true' );
	}

	private async getTabContents( name: 'WP_Query' | 'Trace' ): Promise<string> {
		await this.openTab( name );
		return this.panel.locator( selectors.tabPanel() ).innerText();
	}

	private async runWith( trigger: () => Promise<unknown> ): Promise<unknown> {
		const [ response ] = await Promise.all( [
			this.page.waitForResponse( ( resp ) => resp.url().endsWith( DEV_TOOLS_ENDPOINT ) && resp.request().method() === 'POST' && resp.status() === 200 ),
			trigger(),
		] );

		return response.json();
	}
}
