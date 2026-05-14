( function () {
	'use strict';

	function wrap( bag ) {
		if ( ! bag || typeof bag.uploadMedia !== 'function' ) {
			return false;
		}
		if ( bag.__vipLargeMediaWrapped ) {
			return true;
		}

		var config = window.vipLargeMediaWarningConfig || {};
		var threshold = parseInt( config.thresholdBytes, 10 ) || ( 8 * 1024 * 1024 );
		var mimes = Array.isArray( config.mimeTypes ) ? config.mimeTypes : [];
		var original = bag.uploadMedia;

		bag.uploadMedia = async function ( settings ) {
			try {
				if ( ! settings || ! settings.filesList ) {
					return original.call( this, settings );
				}

				var incoming = Array.from( settings.filesList );
				var kept = [];
				var cancelled = [];

				for ( var i = 0; i < incoming.length; i++ ) {
					var file = incoming[ i ];
					var size = file && typeof file.size === 'number' ? file.size : 0;
					var type = file && typeof file.type === 'string' ? file.type : '';

					var needsConfirm =
						size > threshold &&
						mimes.indexOf( type ) !== -1;

					if ( ! needsConfirm ) {
						kept.push( file );
						continue;
					}

					// eslint-disable-next-line no-await-in-loop
					var ok = await window.vipLargeMediaWarning.confirmLargeUpload( file, threshold );
					if ( ok ) {
						kept.push( file );
					} else {
						cancelled.push( file );
					}
				}

				if ( cancelled.length && typeof settings.onError === 'function' ) {
					cancelled.forEach( function ( f ) {
						try {
							settings.onError( {
								code: 'large_media_cancelled',
								message: 'Upload cancelled by user (file too large).',
								file: f,
							} );
						} catch ( _ ) { /* ignore */ }
					} );
				}

				if ( ! kept.length ) {
					return;
				}

				var nextSettings = Object.assign( {}, settings, { filesList: kept } );
				return original.call( this, nextSettings );
			} catch ( e ) {
				return original.call( this, settings );
			}
		};

		bag.__vipLargeMediaWrapped = true;
		return true;
	}

	function tryWrap() {
		if ( ! window.wp || ! window.vipLargeMediaWarning ) {
			return false;
		}
		var done = false;
		if ( window.wp.mediaUtils ) {
			done = wrap( window.wp.mediaUtils ) || done;
		}
		if ( window.wp.mediaUtilsExperimental ) {
			done = wrap( window.wp.mediaUtilsExperimental ) || done;
		}
		return done;
	}

	if ( window.wp && typeof window.wp.domReady === 'function' ) {
		window.wp.domReady( tryWrap );
	} else if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', tryWrap );
	} else {
		tryWrap();
	}
}() );
