/**
 * Plupload + wp.Uploader queue tracker.
 *
 * Loaded as an inline-after-`wp-plupload` script so we always run before
 * `new plupload.Uploader(...)` is called by WP's inline media JS, and before
 * `wp.media` constructs its `wp.Uploader` instances when the user opens the
 * media modal.
 *
 * Two wraps:
 *
 *   1. `plupload.Uploader` — bound to `BeforeUpload` so the XHR wrap in
 *      upload-interceptor.js knows which plupload file is about to leave the
 *      browser when it intercepts `send()`.
 *
 *   2. `wp.Uploader` — registers every instance in
 *      `globalThis.__vipWpUploaderInstances` so we can walk their `queue`
 *      collections on cancel and `.destroy()` the orphan `wp.media.model
 *      .Attachment`. That's the model behind the visible `.attachment
 *      .uploading` tile; destroying it triggers the Backbone view to unmount,
 *      which neither `xhr.abort()` nor `plupload.removeFile()` does on their
 *      own.
 *
 * Read-only: this file does not gate or modify uploads. The DOM and network
 * interceptors in upload-interceptor.js do the actual gating.
 */
( function () {
	'use strict';

	const plupload = globalThis.plupload;
	if ( plupload && plupload.Uploader && ! plupload.Uploader.__vipTracked ) {
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
		VipTrackedUploader.prototype = OriginalUploader.prototype;
		VipTrackedUploader.__vipTracked = true;
		plupload.Uploader = VipTrackedUploader;
	}

	// Helper used by upload-interceptor.js on cancel — calls plupload's
	// removeFile while the tracker refs are still live.
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

	// Maintain a Set of every wp.Uploader instance created on the page so we
	// can reach into its `queue` collection on cancel.
	if ( ! globalThis.__vipWpUploaderInstances ) {
		globalThis.__vipWpUploaderInstances = new Set();
	}

	if ( globalThis.wp && globalThis.wp.Uploader && ! globalThis.wp.Uploader.__vipTracked ) {
		const OriginalWpUploader = globalThis.wp.Uploader;
		function VipTrackedWpUploader( options ) {
			OriginalWpUploader.call( this, options );
			try {
				globalThis.__vipWpUploaderInstances.add( this );
			} catch ( _ ) { /* ignore */ }
		}
		VipTrackedWpUploader.prototype = OriginalWpUploader.prototype;
		// Preserve any class-level statics that callers might reach.
		for ( const k in OriginalWpUploader ) {
			if ( Object.prototype.hasOwnProperty.call( OriginalWpUploader, k ) ) {
				VipTrackedWpUploader[ k ] = OriginalWpUploader[ k ];
			}
		}
		VipTrackedWpUploader.__vipTracked = true;
		globalThis.wp.Uploader = VipTrackedWpUploader;
	}

	/**
	 * Walk every tracked wp.Uploader's queue and destroy the orphan attachment
	 * that backs the visible "uploading…" tile. Returns the count destroyed —
	 * the XHR cancel path uses 0 as the signal to fall back to a DOM strike.
	 *
	 * Matches by filename when supplied; falls back to "any uploading
	 * attachment" if no name is available (single-upload case).
	 */
	globalThis.__vipDestroyUploadingAttachment = function ( fileName ) {
		let destroyed = 0;
		try {
			const instances = globalThis.__vipWpUploaderInstances;
			if ( ! instances || instances.size === 0 ) {
				return 0;
			}
			for ( const wpUploader of instances ) {
				const queue = wpUploader && wpUploader.queue;
				if ( ! queue || typeof queue.filter !== 'function' ) {
					continue;
				}
				const matches = queue.filter( function ( attachment ) {
					if ( ! attachment || typeof attachment.get !== 'function' ) {
						return false;
					}
					if ( ! attachment.get( 'uploading' ) ) {
						return false;
					}
					if ( fileName && attachment.get( 'filename' ) !== fileName ) {
						return false;
					}
					return true;
				} );
				for ( const attachment of matches ) {
					try {
						attachment.destroy();
						destroyed += 1;
					} catch ( _ ) { /* ignore */ }
				}
			}
		} catch ( _ ) { /* ignore */ }
		return destroyed;
	};
}() );
