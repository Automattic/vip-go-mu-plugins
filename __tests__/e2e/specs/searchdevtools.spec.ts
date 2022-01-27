/**
 * External dependencies
 */
import { expect, test } from '@playwright/test';
import { promisify } from 'util';

/**
 * Internal dependencies
 */
import { SearchPage } from '../lib/pages/search-page';

test( 'Search Dev Tools', async ( { page } ) => {
    const searchPage = new SearchPage( page );

    let query: string;

    await test.step( 'Do a search', () => searchPage.visit( 'Hello' ) );
    await test.step( 'Open DevTools', () => searchPage.openSearchDevTools() );
    await test.step( 'Check number of queries', () => expect( searchPage.getNumberOfQueries() ).resolves.toBe( 1 ) );
    await test.step( 'Check number of results', () => expect( searchPage.getNumberOfResults() ).resolves.toBe( 1 ) );
    await test.step( 'Expand search results', () => searchPage.expandResults() );
    await test.step( 'Ensure WP_Query is functional', () => expect( searchPage.getWPQuery() ).resolves.toMatch( 'search_terms: ["Hello"]' ) );
    await test.step( 'Ensure Trace is functional', () => expect( searchPage.getTrace() ).resolves.toMatch( 'ElasticPress\\Elasticsearch->remote_request' ) );
    await test.step( 'Get the query', async () => {
        query = await searchPage.getQuery();
        return expect( query ).toMatch( '"query": "Hello",' );
    } );
    await test.step( 'Modify the query', async () => {
        const newQuery = query.replace( /"Hello"/g, '"world"' );
        await searchPage.editQuery( newQuery );
    } );
    await test.step( 'Ensure Reset works', () => expect( searchPage.resetQuery() ).resolves.toBe( query ) );
    await test.step( 'Run a new query', async () => {
        const newQuery = query.replace( /"Hello"/g, '"world"' );
        await searchPage.editQuery( newQuery );
        await searchPage.runQuery();
    } );
    await test.step( 'Close DevTools', () => searchPage.closeSearchDevTools() );
} );
