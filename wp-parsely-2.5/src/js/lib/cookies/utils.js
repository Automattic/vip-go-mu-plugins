/**
 * Wrapper to allow us to mock document.cookie in automated testing
 *
 * @return {string} document.cookie
 */
export function documentCookieWrapper() {
	return document?.cookie ?? '';
}
