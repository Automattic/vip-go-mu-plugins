/**
 * DOM helpers for a panel that renders inside a shadow root, where `document.activeElement` and event targets
 * seen from the document stop at the shadow host.
 */

/**
 * The focused element, looking inside open shadow roots.
 *
 * @return {Element|null} Focused element.
 */
export function deepActiveElement() {
	let element = document.activeElement;
	while ( element?.shadowRoot?.activeElement ) {
		element = element.shadowRoot.activeElement;
	}
	return element;
}

/**
 * The element an event started on, even when it came from inside a shadow root.
 *
 * @param {Event} evt Event.
 * @return {EventTarget|null} Original target.
 */
export const eventOrigin = evt => evt.composedPath?.()[ 0 ] ?? evt.target;

/**
 * Apply CSS to a shadow root. A constructed style sheet isn't an inline `<style>` element, so a strict
 * Content-Security-Policy `style-src` (no 'unsafe-inline') doesn't block it; `<style>` is the fallback for
 * browsers without constructable style sheets.
 *
 * @param {ShadowRoot} shadow Shadow root.
 * @param {string}     styles CSS.
 */
function applyStyles( shadow, styles ) {
	if ( 'adoptedStyleSheets' in shadow && 'replaceSync' in CSSStyleSheet.prototype ) {
		const sheet = new CSSStyleSheet();
		sheet.replaceSync( styles );
		shadow.adoptedStyleSheets = [ sheet ];
		return;
	}
	const style = document.createElement( 'style' );
	style.textContent = styles;
	shadow.append( style );
}

/**
 * A container in a shadow root on `host`, with `styles` applied, created once and reused.
 * Theme and plugin CSS can't reach inside, and the styles can't leak out.
 *
 * @param {Element|null} host   Shadow host.
 * @param {string}       styles CSS for the shadow root.
 * @return {Element|null} Container to render into, or null without a host.
 */
export function shadowContainer( host, styles ) {
	if ( ! host ) {
		return null;
	}
	if ( ! host.shadowRoot ) {
		const shadow = host.attachShadow( { mode: 'open' } );
		applyStyles( shadow, styles );
		shadow.append( document.createElement( 'div' ) );
	}
	return host.shadowRoot.lastElementChild;
}
