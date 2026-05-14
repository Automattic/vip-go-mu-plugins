( function () {
	'use strict';

	// Diagnostic sentinel — survives wp.mediaUtils object replacement, lets us tell
	// "inline never ran" from "inline ran but wp.mediaUtils was replaced afterward".
	globalThis.__vipGutenbergInlineRan = ( globalThis.__vipGutenbergInlineRan || 0 ) + 1;
	globalThis.__vipGutenbergMediaUtilsAtInlineTime = typeof globalThis.wp?.mediaUtils?.uploadMedia;

	// Helper — decide whether a file needs the warning.
	const needsConfirmation = ( file, threshold, mimes ) => {
		const size = typeof file?.size === 'number' ? file.size : 0;
		const type = typeof file?.type === 'string' ? file.type : '';
		return size > threshold && mimes.includes( type );
	};

	// Helper — notify cancellation via onError.
	const notifyCancelled = ( cancelled, onError ) => {
		if ( ! cancelled.length || typeof onError !== 'function' ) {
			return;
		}
		for ( const f of cancelled ) {
			try {
				const msg = typeof globalThis.wp?.i18n?.__ === 'function'
					? globalThis.wp.i18n.__( 'Upload cancelled by user (file too large).', 'vip' )
					: 'Upload cancelled by user (file too large).';
				const err = new Error( msg );
				err.code = 'large_media_cancelled';
				err.file = f;
				onError( err );
			} catch ( _ ) {
				// swallow consumer errors
			}
		}
	};

	function wrap( bag ) {
		if ( ! bag || typeof bag.uploadMedia !== 'function' ) {
			return false;
		}
		if ( bag.__vipLargeMediaWrapped ) {
			return true;
		}

		const config = globalThis.vipLargeMediaWarningConfig || {};
		const threshold = Number.parseInt( config.thresholdBytes, 10 ) || ( 8 * 1024 * 1024 );
		const mimes = Array.isArray( config.mimeTypes ) ? config.mimeTypes : [];
		const original = bag.uploadMedia;

		bag.uploadMedia = async function ( settings ) {
			try {
				if ( ! settings || ! settings.filesList ) {
					return original.call( this, settings );
				}

				const incoming = Array.from( settings.filesList );
				const kept = [];
				const cancelled = [];

				for ( const file of incoming ) {
					if ( ! needsConfirmation( file, threshold, mimes ) ) {
						kept.push( file );
						continue;
					}

					// eslint-disable-next-line no-await-in-loop
					const ok = await globalThis.vipLargeMediaWarning.confirmLargeUpload( file, threshold );
					if ( ok ) {
						kept.push( file );
					} else {
						cancelled.push( file );
					}
				}

				notifyCancelled( cancelled, settings.onError );

				if ( ! kept.length ) {
					return;
				}

				const nextSettings = Object.assign( {}, settings, { filesList: kept } );
				return original.call( this, nextSettings );
			} catch ( e ) {
				return original.call( this, settings );
			}
		};

		bag.__vipLargeMediaWrapped = true;
		return true;
	}

	function tryWrap() {
		if ( ! globalThis.wp || ! globalThis.vipLargeMediaWarning ) {
			return false;
		}
		let done = false;
		if ( globalThis.wp.mediaUtils ) {
			done = wrap( globalThis.wp.mediaUtils ) || done;
		}
		if ( globalThis.wp.mediaUtilsExperimental ) {
			done = wrap( globalThis.wp.mediaUtilsExperimental ) || done;
		}
		return done;
	}

	// Wrap eagerly at script load. We declare `wp-media-utils` as a dependency on the
	// PHP side, so by the time this IIFE runs `wp.mediaUtils.uploadMedia` is already
	// defined. This catches consumers that read `wp.mediaUtils.uploadMedia` live.
	// Wrap in try/catch — if wp.mediaUtils.uploadMedia is defined as a read-only
	// property, our assignment will throw in strict mode, and we don't want that
	// to prevent the polling-based block-editor-settings patch below from running.
	try {
		tryWrap();
	} catch ( _ ) { /* swallow; polling below is the real fallback */ }

	// Belt-and-suspenders: the modern block editor reads `mediaUpload` from its own
	// data store, not from `wp.mediaUtils` directly. (Some Gutenberg bundles inline
	// `@wordpress/media-utils` rather than resolving it to the global, so wrapping
	// the global is insufficient.) On `wp.domReady`, replace the editor's
	// `mediaUpload` setting with a wrapper that calls back through whatever the
	// editor was going to use.
	function patchBlockEditorSettings() {
		const data = globalThis.wp?.data;
		if ( ! data || typeof data.select !== 'function' || typeof data.dispatch !== 'function' ) {
			return false;
		}
		const select = data.select( 'core/block-editor' );
		const dispatch = data.dispatch( 'core/block-editor' );
		if ( ! select || ! dispatch || typeof dispatch.updateSettings !== 'function' ) {
			return false;
		}

		const settings = select.getSettings?.();
		const originalMediaUpload = settings?.mediaUpload;
		if ( typeof originalMediaUpload !== 'function' ) {
			return false;
		}
		if ( originalMediaUpload.__vipLargeMediaWrapped ) {
			return true;
		}

		const config = globalThis.vipLargeMediaWarningConfig || {};
		const threshold = Number.parseInt( config.thresholdBytes, 10 ) || ( 8 * 1024 * 1024 );
		const mimes = Array.isArray( config.mimeTypes ) ? config.mimeTypes : [];

		const wrapped = async function ( opts ) {
			try {
				if ( ! opts || ! opts.filesList ) {
					return originalMediaUpload.call( this, opts );
				}
				const incoming = Array.from( opts.filesList );
				const kept = [];
				const cancelled = [];
				for ( const file of incoming ) {
					if ( ! needsConfirmation( file, threshold, mimes ) ) {
						kept.push( file );
						continue;
					}
					// eslint-disable-next-line no-await-in-loop
					const ok = await globalThis.vipLargeMediaWarning.confirmLargeUpload( file, threshold );
					if ( ok ) {
						kept.push( file );
					} else {
						cancelled.push( file );
					}
				}
				notifyCancelled( cancelled, opts.onError );
				if ( ! kept.length ) {
					return;
				}
				const nextOpts = Object.assign( {}, opts, { filesList: kept } );
				return originalMediaUpload.call( this, nextOpts );
			} catch ( e ) {
				return originalMediaUpload.call( this, opts );
			}
		};
		wrapped.__vipLargeMediaWrapped = true;

		dispatch.updateSettings( { mediaUpload: wrapped } );
		globalThis.__vipGutenbergSettingsPatched = true;
		return true;
	}

	function retryPatch() {
		globalThis.__vipGutenbergPatchAttempts = ( globalThis.__vipGutenbergPatchAttempts || 0 ) + 1;
		if ( patchBlockEditorSettings() ) {
			return;
		}
		// Editor data store not ready yet; poll. Some Gutenberg builds take >10s on
		// CI to mount the block-editor data store with a populated `mediaUpload`
		// setting, so we poll for ~30 seconds total before giving up.
		let attempts = 0;
		const interval = globalThis.setInterval( () => {
			attempts += 1;
			globalThis.__vipGutenbergPatchAttempts = ( globalThis.__vipGutenbergPatchAttempts || 0 ) + 1;
			if ( patchBlockEditorSettings() || attempts >= 150 ) {
				globalThis.clearInterval( interval );
			}
		}, 200 );
	}

	if ( typeof globalThis.wp?.domReady === 'function' ) {
		globalThis.wp.domReady( retryPatch );
	} else if ( globalThis.document.readyState === 'loading' ) {
		globalThis.document.addEventListener( 'DOMContentLoaded', retryPatch );
	} else {
		retryPatch();
	}
}() );
