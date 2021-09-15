async function uuidProfileCall() {
	const apikey = global.wpParsely?.apikey;
	const uuid = global.PARSELY?.config?.parsely_site_uuid;

	if ( ! ( apikey && uuid ) ) {
		return;
	}

	const url = `https://api.parsely.com/v2/profile?apikey=${ encodeURIComponent(
		apikey
	) }&uuid=${ encodeURIComponent( uuid ) }&url=${ encodeURIComponent( window.location.href ) }`;

	return fetch( url );
}

export function initApi() {
	if ( typeof global.PARSELY === 'object' ) {
		if ( typeof global.PARSELY.onload !== 'function' ) {
			global.PARSELY.onload = uuidProfileCall;
			return;
		}
		const oldonload = global.PARSELY.onload;
		global.PARSELY.onload = function() {
			if ( oldonload ) {
				oldonload();
			}
			uuidProfileCall();
		};
		return;
	}

	global.PARSELY = {
		onload: uuidProfileCall,
	};
}

initApi();
