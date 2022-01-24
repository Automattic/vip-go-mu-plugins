/**
 * External dependencies
 */
import Cookies from 'js-cookie';

const VISITOR_COOKIE_KEY_NAME = '_parsely_visitor';

export function getVisitorCookieRaw() {
	return Cookies.get( VISITOR_COOKIE_KEY_NAME );
}

export function getVisitorCookie() {
	const cookieVal = getVisitorCookieRaw();

	if ( ! cookieVal ) {
		return undefined;
	}

	const unescapedCookieVal = unescape( cookieVal );

	try {
		return JSON.parse( unescapedCookieVal );
	} catch ( e ) {
		return undefined;
	}
}

export function getUuidFromVisitorCookie() {
	return getVisitorCookie()?.id;
}
