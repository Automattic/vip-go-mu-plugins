( function () {
	'use strict';

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

	if ( typeof globalThis.wp?.domReady === 'function' ) {
		globalThis.wp.domReady( tryWrap );
	} else if ( globalThis.document.readyState === 'loading' ) {
		globalThis.document.addEventListener( 'DOMContentLoaded', tryWrap );
	} else {
		tryWrap();
	}
}() );
