/**
 * External dependencies
 */
import { randomUUID } from 'node:crypto';

/**
 * Generates a unique post title (E2E Post 1a2b3c4d).
 *
 * @return {string} Generated title.
 */
export function getRandomTitle(): string {
	return `E2E Post ${ randomUUID().slice( 0, 8 ) }`;
}
