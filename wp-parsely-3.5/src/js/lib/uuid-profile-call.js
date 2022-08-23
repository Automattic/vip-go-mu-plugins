// Only enqueuing the action if the site has a defined API key.
if ( typeof window.wpParselyApiKey !== 'undefined' ) {
	window.wpParselyHooks.addAction( 'wpParselyOnLoad', 'wpParsely', uuidProfileCall );
}

async function uuidProfileCall() {
	const uuid = global.PARSELY?.config?.parsely_site_uuid;

	if ( ! ( window.wpParselyApiKey && uuid ) ) {
		return;
	}

	const url = `https://api.parsely.com/v2/profile?apikey=${ encodeURIComponent(
		window.wpParselyApiKey
	) }&uuid=${ encodeURIComponent( uuid ) }&url=${ encodeURIComponent( window.location.href ) }`;

	return fetch( url );
}
