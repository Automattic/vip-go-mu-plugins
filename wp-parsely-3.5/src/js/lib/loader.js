import { createHooks } from '@wordpress/hooks';

window.wpParselyHooks = createHooks();

export function wpParselyInitCustom() {
	/**
	 * The `wpParselyOnLoad` hook gets called with the `onload` event of the `window.PARSELY` object.
	 * All functions enqueued on that hook will be executed on that event according to their priorities. Those
	 * functions should not expect any parameters and shouldn't return any.
	 */
	const customOnLoad = () => window.wpParselyHooks.doAction( 'wpParselyOnLoad' );

	/**
	 * The `wpParselyOnReady` hook gets called with the `onReady` event of the `window.PARSELY` object.
	 * All functions enqueued on that hook will be executed on that event according to their priorities. Those
	 * functions should not expect any parameters and shouldn't return any.
	 */
	const customOnReady = () => window.wpParselyHooks.doAction( 'wpParselyOnReady' );

	// Construct window.PARSELY object.
	if ( typeof window.PARSELY === 'object' ) {
		if ( typeof window.PARSELY.onload !== 'function' ) {
			window.PARSELY.onload = customOnLoad;
		} else {
			const oldOnLoad = window.PARSELY.onload;
			window.PARSELY.onload = function() {
				if ( oldOnLoad ) {
					oldOnLoad();
				}
				customOnLoad();
			};
		}

		if ( typeof window.PARSELY.onReady !== 'function' ) {
			window.PARSELY.onReady = customOnReady;
		} else {
			const oldOnReady = window.PARSELY.onReady;
			window.PARSELY.onReady = function() {
				if ( oldOnReady ) {
					oldOnReady();
				}
				customOnReady();
			};
		}
	} else {
		window.PARSELY = {
			onload: customOnLoad,
			onReady: customOnReady,
		};
	}

	// Disable autotrack if it was set as such in settings.
	if ( window.wpParselyDisableAutotrack === true ) {
		window.PARSELY.autotrack = false;
	}
}

wpParselyInitCustom();
