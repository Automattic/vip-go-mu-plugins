/**
 * Minimal plupload state tracker.
 *
 * Inlined immediately after `wp-plupload` so we wrap `plupload.Uploader`
 * before WP's inline media JS constructs an instance.
 *
 * Sole purpose: expose a live `{ up, file }` reference for the file plupload
 * is currently processing, so the XHR cancel path in upload-interceptor.js
 * can call `up.removeFile(file)` to flush plupload's internal queue.
 *
 * Without this, `xhr.abort()` alone leaves plupload's queue holding a FAILED
 * file, which prevents the Media Library modal's Select Files button and
 * drop zone from accepting any subsequent uploads — the entire modal upload
 * pipeline wedges until the page is reloaded.
 */
( function () {
	'use strict';

	const plupload = globalThis.plupload;
	if ( ! plupload || ! plupload.Uploader || plupload.Uploader.__vipTracked ) {
		return;
	}

	const Original = plupload.Uploader;

	function Tracked( settings ) {
		Original.call( this, settings );
		const self = this;
		try {
			if ( typeof self.bind !== 'function' ) {
				return;
			}
			self.bind( 'BeforeUpload', function ( up, file ) {
				globalThis.__vipPluploadCurrent = { up, file };
			} );
			const clear = function () {
				globalThis.__vipPluploadCurrent = null;
			};
			self.bind( 'FileUploaded', clear );
			self.bind( 'UploadComplete', clear );
			self.bind( 'Error', clear );
		} catch ( _ ) { /* swallow; do not disrupt plupload */ }
	}
	Tracked.prototype = Original.prototype;
	Tracked.__vipTracked = true;
	plupload.Uploader = Tracked;
}() );
