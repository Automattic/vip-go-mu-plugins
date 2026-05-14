( function () {
	'use strict';

	if ( ! window.wp || ! window.wp.Uploader || ! window.vipLargeMediaWarning ) {
		return;
	}
	if ( window.wp.Uploader.prototype.__vipLargeMediaWrapped ) {
		return;
	}

	var config = window.vipLargeMediaWarningConfig || {};
	var threshold = parseInt( config.thresholdBytes, 10 ) || ( 8 * 1024 * 1024 );
	var mimes = Array.isArray( config.mimeTypes ) ? config.mimeTypes : [];

	var originalInit = window.wp.Uploader.prototype.init;

	window.wp.Uploader.prototype.init = function () {
		try {
			originalInit.apply( this, arguments );
		} catch ( e ) {
			throw e;
		}

		var self = this;

		try {
			if ( ! self.uploader || typeof self.uploader.bind !== 'function' ) {
				return;
			}

			var confirmedIds = new Set();

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
					if ( ! mimes.length || mimes.indexOf( file.type ) === -1 ) {
						return;
					}

					up.stop();

					window.vipLargeMediaWarning
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
							up.start();
						} );
				} catch ( e ) {
					try { up.start(); } catch ( _ ) { /* ignore */ }
				}
			} );
		} catch ( e ) { /* swallow; do not disrupt plupload */ }
	};

	window.wp.Uploader.prototype.__vipLargeMediaWrapped = true;
}() );
