/**
 * External dependencies
 */
import { Locator } from 'playwright';

export async function getClassList( locator: Locator ): Promise<string[]> {
    const classList = await locator.evaluate( node => node.classList );
    return Array.from( Object.values( classList ) );
}
