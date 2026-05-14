( function () {
	'use strict';

	globalThis.__vipPluploadInlineRan = ( globalThis.__vipPluploadInlineRan || 0 ) + 1;

	// Wrap plupload.Uploader.prototype.init rather than wp.Uploader.prototype.init.
	// wp.Uploader is one consumer of plupload (used inside the post-edit Media modal),
	// but the standalone Media Library upload page (media-new.php) constructs a
	// plupload.Uploader directly without going through wp.Uploader. Wrapping at the
	// plupload layer catches both cases.
	const plupload = globalThis.plupload;

	if ( ! plupload?.Uploader || ! globalThis.vipLargeMediaWarning ) {
		return;
	}
	if ( plupload.Uploader.prototype.__vipLargeMediaWrapped ) {
		return;
	}

	const config = globalThis.vipLargeMediaWarningConfig || {};
	const threshold = Number.parseInt( config.thresholdBytes, 10 ) || ( 8 * 1024 * 1024 );
	const mimes = Array.isArray( config.mimeTypes ) ? config.mimeTypes : [];

	const originalInit = plupload.Uploader.prototype.init;

	plupload.Uploader.prototype.init = function () {
		originalInit.apply( this, arguments );

		const self = this;

		try {
			if ( typeof self.bind !== 'function' ) {
				return;
			}

			const confirmedIds = new Set();

			self.bind( 'BeforeUpload', function ( up, file ) {
				globalThis.__vipPluploadBeforeUploadFired = ( globalThis.__vipPluploadBeforeUploadFired || 0 ) + 1;
				globalThis.__vipPluploadLastBeforeUpload = {
					hasFile: !! file,
					size: file?.size ?? null,
					type: file?.type ?? null,
					confirmedAlready: file?.id ? confirmedIds.has( file.id ) : false,
				};
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

					globalThis.__vipPluploadDialogTriggered = ( globalThis.__vipPluploadDialogTriggered || 0 ) + 1;
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

	plupload.Uploader.prototype.__vipLargeMediaWrapped = true;
}() );
