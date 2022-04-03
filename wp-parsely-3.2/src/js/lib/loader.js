import { createHooks } from '@wordpress/hooks';

window.wpParselyHooks = createHooks();

export function wpParselyInitCustom() {
	/**
	 * The `wpParselyOnLoad` hook gets called with the `onLoad` event of the `window.PARSELY` object.
	 * All functions enqueued on that hook will be executed on that event according to their priorities. Those
	 * functions should not expect any parameters and shouldn't return any.
	 */
	const customOnLoad = () => window.wpParselyHooks.doAction( 'wpParselyOnLoad' );

	if ( typeof window.PARSELY === 'object' ) {
		if ( typeof window.PARSELY.onload !== 'function' ) {
			window.PARSELY.onload = customOnLoad;
			return;
		}

		const oldOnLoad = window.PARSELY.onload;
		window.PARSELY.onload = function() {
			if ( oldOnLoad ) {
				oldOnLoad();
			}
			customOnLoad();
		};
		return;
	}

	window.PARSELY = {
		onload: customOnLoad,
	};
}

wpParselyInitCustom();
