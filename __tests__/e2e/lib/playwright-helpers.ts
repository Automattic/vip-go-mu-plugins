/**
 * External dependencies
 */
import { Locator } from 'playwright';

/**
 * Returns the list of classes for the given element as a string list.
 *
 * @param {Locator} locator Element locator
 * @returns {string[]} Class list
 */
export async function getClassList( locator: Locator ): Promise<string[]> {
    const classList = await locator.evaluate( node => node.classList );
    return Array.from( Object.values( classList ) );
}
