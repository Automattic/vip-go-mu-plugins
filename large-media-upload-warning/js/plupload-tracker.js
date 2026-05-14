/**
 * Plupload queue tracker.
 *
 * Loaded as an inline-after-`wp-plupload` script so we always run before
 * `new plupload.Uploader(...)` is called by WP's inline media JS. We wrap the
 * constructor to bind `BeforeUpload`, which gives us a live reference to the
 * file plupload is *about* to upload — exactly the file our XHR wrap is about
 * to intercept.
 *
 * When the user cancels via our dialog, `upload-interceptor.js` calls
 * `__vipRemoveCurrentPluploadFile()` which calls `uploader.removeFile(file)`
 * on the tracked context. That removes the file from plupload's queue (and
 * tears down its "uploading…" UI tile, which `xhr.abort()` alone does not).
 *
 * Read-only: this file does not gate or modify uploads. The DOM and network
 * interceptors in upload-interceptor.js do the actual gating.
 */
( function () {
	'use strict';

	const plupload = globalThis.plupload;
	if ( ! plupload || ! plupload.Uploader || plupload.Uploader.__vipTracked ) {
		return;
	}

	const OriginalUploader = plupload.Uploader;

	function VipTrackedUploader( settings ) {
		OriginalUploader.call( this, settings );
		const self = this;
		try {
			if ( typeof self.bind !== 'function' ) {
				return;
			}
			self.bind( 'BeforeUpload', function ( up, file ) {
				globalThis.__vipCurrentPluploadFile = file;
				globalThis.__vipCurrentPluploadUp = up;
			} );
			const clear = function () {
				globalThis.__vipCurrentPluploadFile = null;
				globalThis.__vipCurrentPluploadUp = null;
			};
			self.bind( 'FileUploaded', clear );
			self.bind( 'UploadComplete', clear );
			self.bind( 'Error', clear );
		} catch ( _ ) { /* swallow; do not disrupt plupload */ }
	}

	// Preserve the prototype chain so `instanceof plupload.Uploader` still works.
	VipTrackedUploader.prototype = OriginalUploader.prototype;
	VipTrackedUploader.__vipTracked = true;

	plupload.Uploader = VipTrackedUploader;

	// Called by upload-interceptor.js when the user cancels. Snapshots the
	// tracker globals into locals first so that `removeFile`'s internal event
	// dispatch (which clears the globals via the handlers above) can't race
	// with us.
	globalThis.__vipRemoveCurrentPluploadFile = function () {
		const file = globalThis.__vipCurrentPluploadFile;
		const up = globalThis.__vipCurrentPluploadUp;
		try {
			if ( file && up && typeof up.removeFile === 'function' ) {
				up.removeFile( file );
			}
		} catch ( _ ) { /* ignore */ }
		globalThis.__vipCurrentPluploadFile = null;
		globalThis.__vipCurrentPluploadUp = null;
	};
}() );
