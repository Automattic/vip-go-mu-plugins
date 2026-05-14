( function () {
	'use strict';

	if ( ! globalThis.wp?.Uploader || ! globalThis.vipLargeMediaWarning ) {
		return;
	}
	if ( globalThis.wp.Uploader.prototype.__vipLargeMediaWrapped ) {
		return;
	}

	const config = globalThis.vipLargeMediaWarningConfig || {};
	const threshold = Number.parseInt( config.thresholdBytes, 10 ) || ( 8 * 1024 * 1024 );
	const mimes = Array.isArray( config.mimeTypes ) ? config.mimeTypes : [];

	const originalInit = globalThis.wp.Uploader.prototype.init;

	globalThis.wp.Uploader.prototype.init = function () {
		originalInit.apply( this, arguments );

		const self = this;

		try {
			if ( ! self.uploader || typeof self.uploader.bind !== 'function' ) {
				return;
			}

			const confirmedIds = new Set();

			self.uploader.bind( 'BeforeUpload', function ( up, file ) {
				try {
					if ( ! file || typeof file.size !== 'number' ) {
						return;
					}
					if ( confirmedIds.has( file.id ) ) {
						return;
					}
					if ( file.size <= threshold ) {
						return;
					}
					if ( ! mimes.length || ! mimes.includes( file.type ) ) {
						return;
					}

					up.stop();

					globalThis.vipLargeMediaWarning
						.confirmLargeUpload( file, threshold )
						.then( function ( ok ) {
							if ( ok ) {
								confirmedIds.add( file.id );
								up.start();
							} else {
								up.removeFile( file );
								up.start();
							}
						} )
						.catch( function () {
							// Fail open: if our confirm dialog fails, never block the upload.
							confirmedIds.add( file.id );
							up.start();
						} );
				} catch ( e ) {
					try { up.start(); } catch ( _ ) { /* ignore */ }
				}
			} );
		} catch ( e ) { /* swallow; do not disrupt plupload */ }
	};

	globalThis.wp.Uploader.prototype.__vipLargeMediaWrapped = true;
}() );
