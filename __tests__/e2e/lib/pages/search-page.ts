/**
 * External dependencies
 */
import { expect, Locator, Page, Request } from '@playwright/test';

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
};

export class SearchPage {
    private readonly page: Page;
    private readonly devToolsMenuLocator: Locator;
    private readonly devToolsPortalLocator: Locator;
    private readonly devToolsContainerLocator: Locator;
    private readonly devToolsCloseButtonLocator: Locator;
    private readonly queryWrapLocator: Locator;
    private readonly queryExtrasLocator: Locator;
    private readonly queryExtrasExpandLocator: Locator;
    private readonly queryExtrasListLocator: Locator;
    private readonly queryColumnLocator: Locator;
    private readonly sourceQueryLocator: Locator;
    private readonly queryActionsLocator: Locator;

    constructor( page: Page ) {
        this.page = page;
        this.devToolsMenuLocator = page.locator( selectors.devToolsMenu() );
        this.devToolsPortalLocator = page.locator( selectors.devToolsPortal() );
        this.devToolsContainerLocator = page.locator( selectors.devToolsContainer() );
        this.devToolsCloseButtonLocator = this.devToolsContainerLocator.locator( selectors.devToolsClose() );
        this.queryWrapLocator = this.devToolsContainerLocator.locator( selectors.queryWrap() );
        this.queryExtrasLocator = this.queryWrapLocator.locator( selectors.queryExtras() );
        this.queryExtrasExpandLocator = this.queryExtrasLocator.locator( selectors.queryExtrasExpand() );
        this.queryExtrasListLocator = this.queryExtrasLocator.locator( selectors.queryExtrasList() );
        this.queryColumnLocator = this.queryWrapLocator.locator( selectors.queryColumn() );
        this.sourceQueryLocator = this.queryColumnLocator.locator( selectors.sourceQuery() );
        this.queryActionsLocator = this.queryColumnLocator.locator( selectors.queryActionsButton() );
    }

    async visit( searchTerm: string ): Promise<unknown> {
        await this.page.goto( `/?s=${ encodeURIComponent( searchTerm ) }` );
        return expect( this.devToolsMenuLocator ).toBeVisible();
    }

    async openSearchDevTools(): Promise<unknown> {
        await expect( this.devToolsMenuLocator ).toBeVisible();
        await this.devToolsMenuLocator.click();
        return this.ensureDevToolsOpen();
    }

    async getNumberOfQueries(): Promise<number> {
        await this.ensureDevToolsOpen();

        const text = ( await this.devToolsContainerLocator.locator( selectors.queryCount() ).innerText() ).trim();
        const matches = /^(\d+)/.exec( text );
        expect( matches ).not.toBeNull();
        return +matches[ 1 ];
    }

    async getNumberOfResults(): Promise<number> {
        await this.ensureDevToolsOpen();

        const text = ( await this.devToolsContainerLocator.locator( selectors.resultCount() ).innerText() ).trim();
        const matches = /^(\d+)/.exec( text );
        expect( matches ).not.toBeNull();
        return +matches[ 1 ];
    }

    async expandResults(): Promise<unknown> {
        await this.ensureDevToolsOpen();

        const queryHandleLocator = this.queryWrapLocator.locator( selectors.queryHandle() );

        let classes = await getClassList( this.queryWrapLocator );
        const isCollapsed = classes.some( className => className.startsWith( 'query_collapsed' ) );
        expect( isCollapsed ).toBe( true );

        await queryHandleLocator.click();

        classes = classes = await getClassList( this.queryWrapLocator );
        const isExpanded = ! classes.every( className => className.startsWith( 'query_collapsed' ) );
        return expect( isExpanded ).toBe( true );
    }

    async getWPQuery(): Promise<string> {
        await this.ensureDevToolsOpen();
        return this.getQueryExtrasHelper( 0 );
    }

    async getTrace(): Promise<string> {
        await this.ensureDevToolsOpen();
        return this.getQueryExtrasHelper( 1 );
    }

    async getQuery(): Promise<string> {
        await this.ensureDevToolsOpen();
        return this.sourceQueryLocator.inputValue();
    }

    async editQuery( newQuery: string ): Promise<unknown> {
        await this.ensureDevToolsOpen();
        return this.sourceQueryLocator.fill( newQuery );
    }

    async resetQuery(): Promise<string> {
        await this.ensureDevToolsOpen();
        await expect( this.queryActionsLocator.count() ).resolves.toBe( 2 );
        await this.queryActionsLocator.nth( 1 ).click();
        return this.getQuery();
    }

    async runQuery(): Promise<unknown> {
        await this.ensureDevToolsOpen();
        await expect( this.queryActionsLocator.count() ).resolves.toBe( 2 );

        const [ response ] = await Promise.all( [
            this.page.waitForResponse( resp => /\/wp-json\/vip\/v1\/search\/dev-tools$/.test( resp.url() ) && resp.request().method() === 'POST' && resp.status() === 200, {
                timeout: 1000,
            } ),
            this.queryActionsLocator.nth( 0 ).click(),
        ] );

        const text = await response.text();
        const json = JSON.parse( text );
        return expect( json ).toMatchObject( {
            result: expect.objectContaining( {
                body: expect.any( Object ),
            } ),
        } );
    }

    async closeSearchDevTools(): Promise<unknown> {
        await this.ensureDevToolsOpen();
        await expect( this.devToolsCloseButtonLocator ).toBeVisible();
        await this.devToolsCloseButtonLocator.click();
        return this.ensureDevToolsClosed();
    }

    private async ensureDevToolsOpen(): Promise<void> {
        await expect( this.devToolsPortalLocator ).not.toBeEmpty();
        await expect( this.devToolsContainerLocator ).toBeVisible();
    }

    private ensureDevToolsClosed(): Promise<Locator> {
        return expect( this.devToolsPortalLocator ).toBeEmpty();
    }

    private async getQueryExtrasHelper( index: number ): Promise<string> {
        await expect( this.queryExtrasListLocator.nth( index ) ).not.toBeVisible();
        await this.queryExtrasExpandLocator.nth( index ).click();
        await expect( this.queryExtrasListLocator.nth( index ) ).toBeVisible();
        const result = await this.queryExtrasListLocator.nth( index ).innerText();
        await this.queryExtrasExpandLocator.nth( index ).click();
        await expect( this.queryExtrasListLocator.nth( index ) ).not.toBeVisible();
        return result;
    }
}
