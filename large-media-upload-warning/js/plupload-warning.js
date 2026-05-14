( function () {
	'use strict';

	globalThis.__vipPluploadInlineRan = ( globalThis.__vipPluploadInlineRan || 0 ) + 1;

	// IMPORTANT: plupload.Uploader assigns `init`, `bind`, `start`, `stop`, etc. as
	// OWN methods on the instance inside its constructor — not on the prototype.
	// Wrapping `plupload.Uploader.prototype.init` therefore does nothing useful:
	// `instance.init()` looks up `init` on the instance first and never reaches the
	// prototype. We instead wrap the constructor: after the original constructor
	// runs (which attaches the per-instance methods), we bind our `BeforeUpload`
	// listener directly on the new instance using `this.bind(...)`.
	const plupload = globalThis.plupload;

	if ( ! plupload?.Uploader || ! globalThis.vipLargeMediaWarning ) {
		return;
	}
	if ( plupload.Uploader.__vipLargeMediaWrapped ) {
		return;
	}

	const config = globalThis.vipLargeMediaWarningConfig || {};
	const threshold = Number.parseInt( config.thresholdBytes, 10 ) || ( 8 * 1024 * 1024 );
	const mimes = Array.isArray( config.mimeTypes ) ? config.mimeTypes : [];

	const OriginalUploader = plupload.Uploader;

	function WrappedUploader( settings ) {
		OriginalUploader.call( this, settings );

		const self = this;
		const confirmedIds = new Set();

		try {
			if ( typeof self.bind !== 'function' ) {
				return;
			}

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
	}

	// Preserve the prototype chain so any `instanceof plupload.Uploader` checks
	// elsewhere in plupload or in WordPress's wp.Uploader keep working.
	WrappedUploader.prototype = OriginalUploader.prototype;
	WrappedUploader.__vipLargeMediaWrapped = true;

	plupload.Uploader = WrappedUploader;
}() );
