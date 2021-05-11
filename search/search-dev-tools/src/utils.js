/* global fetch */
export const callOnEscapeKey = callback => event => event.key === 'Escape' && callback();

/**
 * Async helper to post data to a REST andpoint.
 *
 * This function doesn't handle the Errors that maybe thrown after
 * an unsuccessful request. Please handle these in the caller function/method.
 *
 * @param {String} url Request URL
 * @param {Object} data Any data to post.
 * @param {String} nonce The nonce verify the permissions
 */
export async function postData( url = '', data = {}, nonce = '' ) {
	const response = await fetch( url, {
		method: 'POST',
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': nonce,
		},
		body: JSON.stringify( data ),
	} );
	return response.json();
}
