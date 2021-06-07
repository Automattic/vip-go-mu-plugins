/**
 * Internal dependencies
 */
import { documentCookieWrapper } from './utils';

/**
 * Get the value of a particular cookie
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/Document/cookie
 * @param {string} key Which cookie value to get
 * @return {string | undefined} The value of the specified key, or `undefined` if it's not set.
 */
export const getCookieValue = ( key ) => {
	const row = documentCookieWrapper()
		.split( '; ' )
		.find( ( r ) => r.startsWith( `${ key }=` ) );
	return row?.split( '=' )[ 1 ];
};
