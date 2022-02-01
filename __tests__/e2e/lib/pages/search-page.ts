/**
 * External dependencies
 */
import { Locator, Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import { getClassList } from '../playwright-helpers';

const selectors = {
    devToolsMenu: () => '#wp-admin-bar-vip-search-dev-tools',
    devToolsPortal: () => '#search-dev-tools-portal',
    devToolsContainer: () => '#search-dev-tools-portal > .search-dev-tools__overlay',
    devToolsClose: () => '.search-dev-tools__overlay__close',
    queryCount: () => 'h2[class^="vip-h2 query_count"]',
    resultCount: () => 'div[class^="query_handle"] > h3.vip-h3',
    queryWrap: () => 'div[class^="query_wrap"]',
    queryHandle: () => 'div[class^="query_handle"]',
    queryExtras: () => 'div[class^="query_src_extra"]',
    queryExtrasExpand: () => 'strong.vip-h4',
    queryExtrasList: () => 'ol[class^="collapsible_list_details"]',
    queryColumn: () => 'div[class^="query_src__"]',
    sourceQuery: () => 'textarea',
    queryActionsButton: () => 'div[class^="query_actions"] > button',
    queryResult: () => 'div[class^="query_result__"] > pre > code',
    queryResultText: ( text: string ) => `#search-dev-tools-portal div[class^="query_result__"] > pre:has-text(${ text })`,
};

export class SearchPage {
    private readonly page: Page;
    private readonly devToolsMenuLocator: Locator;
    private readonly devToolsContainerLocator: Locator;
    private readonly devToolsCloseButtonLocator: Locator;
    private readonly queryWrapLocator: Locator;
    private readonly queryExtrasLocator: Locator;
    private readonly queryExtrasExpandLocator: Locator;
    private readonly queryExtrasListLocator: Locator;
    private readonly queryColumnLocator: Locator;
    private readonly sourceQueryLocator: Locator;
    private readonly queryActionsLocator: Locator;
    private readonly queryResultLocator: Locator;

    constructor( page: Page ) {
        this.page = page;
        this.devToolsMenuLocator = page.locator( selectors.devToolsMenu() );
        this.devToolsContainerLocator = page.locator( selectors.devToolsContainer() );
        this.devToolsCloseButtonLocator = this.devToolsContainerLocator.locator( selectors.devToolsClose() );
        this.queryWrapLocator = this.devToolsContainerLocator.locator( selectors.queryWrap() );
        this.queryExtrasLocator = this.queryWrapLocator.locator( selectors.queryExtras() );
        this.queryExtrasExpandLocator = this.queryExtrasLocator.locator( selectors.queryExtrasExpand() );
        this.queryExtrasListLocator = this.queryExtrasLocator.locator( selectors.queryExtrasList() );
        this.queryColumnLocator = this.queryWrapLocator.locator( selectors.queryColumn() );
        this.sourceQueryLocator = this.queryColumnLocator.locator( selectors.sourceQuery() );
        this.queryActionsLocator = this.queryColumnLocator.locator( selectors.queryActionsButton() );
        this.queryResultLocator = this.queryWrapLocator.locator( selectors.queryResult() );
    }

    visit( searchTerm: string ): Promise<unknown> {
        return this.page.goto( `/?s=${ encodeURIComponent( searchTerm ) }` );
    }

    async openSearchDevTools(): Promise<unknown> {
        await this.devToolsMenuLocator.waitFor( { state: 'visible' } );
        await this.devToolsMenuLocator.click();
        return this.devToolsContainerLocator.waitFor( { state: 'visible' } );
    }

    async getNumberOfQueries(): Promise<number> {
        const text = ( await this.devToolsContainerLocator.locator( selectors.queryCount() ).innerText() ).trim();
        const matches = /^(\d+)/.exec( text );
        return matches ? +matches[ 1 ] : -1;
    }

    async getNumberOfResults(): Promise<number> {
        const text = ( await this.devToolsContainerLocator.locator( selectors.resultCount() ).innerText() ).trim();
        const matches = /^(\d+)/.exec( text );
        return matches ? +matches[ 1 ] : -1;
    }

    async expandResults(): Promise<boolean> {
        const queryHandleLocator = this.queryWrapLocator.locator( selectors.queryHandle() );

        let classes = await getClassList( this.queryWrapLocator );
        const isCollapsed = classes.some( className => className.startsWith( 'query_collapsed' ) );
        if ( isCollapsed ) {
            await queryHandleLocator.click();

            classes = await getClassList( this.queryWrapLocator );
            return ! classes.every( className => className.startsWith( 'query_collapsed' ) );
        }

        return false;
    }

    async getWPQuery(): Promise<string> {
        return this.getQueryExtrasHelper( 0 );
    }

    async getTrace(): Promise<string> {
        return this.getQueryExtrasHelper( 1 );
    }

    async getQuery(): Promise<string> {
        return this.sourceQueryLocator.inputValue();
    }

    async editQuery( newQuery: string ): Promise<unknown> {
        return this.sourceQueryLocator.fill( newQuery );
    }

    async resetQuery(): Promise<string> {
        await this.queryActionsLocator.nth( 1 ).click();
        return this.getQuery();
    }

    async runQuery(): Promise<string> {
        const [ response ] = await Promise.all( [
            this.page.waitForResponse( resp => /\/wp-json\/vip\/v1\/search\/dev-tools$/.test( resp.url() ) && resp.request().method() === 'POST' && resp.status() === 200 ),
            this.queryActionsLocator.first().click(),
        ] );

        return response.json();
    }

    ensureQueryResponse( substring: string ): Promise<unknown> {
        return this.page.waitForSelector( selectors.queryResultText( substring ) );
    }

    async closeSearchDevTools(): Promise<unknown> {
        await this.devToolsCloseButtonLocator.waitFor( { state: 'visible' } );
        return this.devToolsCloseButtonLocator.click();
    }

    private async getQueryExtrasHelper( index: number ): Promise<string> {
        await this.queryExtrasExpandLocator.nth( index ).click();
        await this.queryExtrasListLocator.nth( index ).waitFor( { state: 'visible' } );
        const result = await this.queryExtrasListLocator.nth( index ).innerText();
        await this.queryExtrasExpandLocator.nth( index ).click();
        await this.queryExtrasListLocator.nth( index ).waitFor( { state: 'hidden' } );
        return result;
    }
}
