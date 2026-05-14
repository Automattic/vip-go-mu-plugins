( function () {
	'use strict';

	globalThis.__vipPluploadInlineRan = ( globalThis.__vipPluploadInlineRan || 0 ) + 1;

	// IMPORTANT: plupload.Uploader assigns `init`, `bind`, `start`, `stop`, etc. as
	// OWN methods on the instance inside its constructor — not on the prototype.
	// Wrapping `plupload.Uploader.prototype.init` therefore does nothing useful:
	// `instance.init()` looks up `init` on the instance first and never reaches the
	// prototype. We instead wrap the constructor: after the original constructor
	// runs (which attaches the per-instance methods), we bind our event listener
	// directly on the new instance using `this.bind(...)`.
	//
	// We hook `FilesAdded` rather than `BeforeUpload`. Both fire before the actual
	// upload, but `stop()` inside `BeforeUpload` followed by `start()` after the
	// async dialog is unreliable — plupload doesn't always re-queue the "current
	// file" after a stop-during-BeforeUpload. `FilesAdded` fires before plupload
	// has begun processing the queue, so stop/start works cleanly there.
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

	function needsConfirmation( file ) {
		if ( ! file || typeof file.size !== 'number' ) {
			return false;
		}
		if ( file.size <= threshold ) {
			return false;
		}
		if ( ! mimes.length || ! mimes.includes( file.type ) ) {
			return false;
		}
		return true;
	}

	function reviewFile( file ) {
		return globalThis.vipLargeMediaWarning
			.confirmLargeUpload( file, threshold )
			.then( ( ok ) => ( { file, ok } ) )
			.catch( () => ( { file, ok: true } ) ); // fail open
	}

	function applyResults( up, results ) {
		try {
			results.forEach( ( r ) => {
				if ( ! r.ok ) {
					up.removeFile( r.file );
				}
			} );
		} catch ( _ ) { /* ignore */ }
		try { up.start(); } catch ( _ ) { /* ignore */ }
	}

	function handleFilesAdded( up, files ) {
		globalThis.__vipPluploadFilesAddedFired = ( globalThis.__vipPluploadFilesAddedFired || 0 ) + 1;
		try {
			const list = Array.isArray( files ) ? files : Array.from( files || [] );
			const oversized = list.filter( needsConfirmation );
			if ( oversized.length === 0 ) {
				return;
			}

			globalThis.__vipPluploadDialogTriggered = ( globalThis.__vipPluploadDialogTriggered || 0 ) + 1;

			// Pause the queue while we ask the user. Auto-start would otherwise
			// begin uploading before our async dialog resolves.
			up.stop();

			Promise.all( oversized.map( reviewFile ) ).then( ( results ) => applyResults( up, results ) );
		} catch ( _ ) {
			try { up.start(); } catch ( __ ) { /* ignore */ }
		}
	}

	function WrappedUploader( settings ) {
		OriginalUploader.call( this, settings );
		try {
			if ( typeof this.bind === 'function' ) {
				this.bind( 'FilesAdded', handleFilesAdded );
			}
		} catch ( _ ) { /* swallow; do not disrupt plupload */ }
	}

	// Preserve the prototype chain so any `instanceof plupload.Uploader` checks
	// elsewhere in plupload or in WordPress's wp.Uploader keep working.
	WrappedUploader.prototype = OriginalUploader.prototype;
	WrappedUploader.__vipLargeMediaWrapped = true;

	plupload.Uploader = WrappedUploader;
}() );
