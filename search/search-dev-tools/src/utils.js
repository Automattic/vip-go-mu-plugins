export const callOnEscapeKey = callback => event => event.key === 'Escape' && callback();

/**
 *  Async Helper to post data to a REST Endpoint
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
