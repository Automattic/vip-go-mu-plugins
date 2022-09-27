/**
 * External dependencies
 */
import { Locator, Page } from 'playwright';

/**
 * Returns the list of classes for the given element as a string list.
 *
 * @param {Locator} locator Element locator
 * @return {string[]} Class list
 */
export async function getClassList( locator: Locator ): Promise<string[]> {
    const classList = await locator.evaluate( node => node.classList );
    return Array.from( Object.values( classList ) );
}

/**
 * More robust version of `page.goto()`.
 *
 * @param {Page}   page Page object
 * @param {string} url  URL to navigate to
 */
export function goToPage( page: Page, url: string ): Promise<unknown> {
    return Promise.all( [
        page.waitForNavigation( { waitUntil: 'networkidle' } ),
        page.goto( url, { waitUntil: 'load' } ),
    ] );
}
