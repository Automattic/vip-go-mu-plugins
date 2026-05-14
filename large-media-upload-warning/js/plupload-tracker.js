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
 *      collections on cancel and clean up the orphan `wp.media.model
 *      .Attachment`. That's the model behind the visible `.attachment
 *      .uploading` tile; removing it triggers the Backbone view to unmount,
 *      which neither `xhr.abort()` nor `plupload.removeFile()` does on its
 *      own.
 *
 * Diagnostic logging is gated on `globalThis.__vipDebug` (set to truthy in
 * the browser console to enable). Off by default.
 */
( function () {
	'use strict';

	function debug() {
		if ( globalThis.__vipDebug ) {
			// eslint-disable-next-line no-console
			console.log.apply( console, [ '[VIP-LMW tracker]' ].concat( Array.from( arguments ) ) );
		}
	}

	debug( 'installing; plupload:', typeof globalThis.plupload, 'wp.Uploader:', typeof globalThis.wp?.Uploader );

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
					debug( 'plupload BeforeUpload', file?.name, 'size:', file?.size );
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
		debug( 'wrapped plupload.Uploader' );
	} else {
		debug( 'plupload.Uploader unavailable or already wrapped' );
	}

	// Helper used by upload-interceptor.js on cancel — calls plupload's
	// removeFile while the tracker refs are still live.
	globalThis.__vipRemoveCurrentPluploadFile = function () {
		const file = globalThis.__vipCurrentPluploadFile;
		const up = globalThis.__vipCurrentPluploadUp;
		debug( 'removeCurrentPluploadFile', { hasFile: !!file, hasUp: !!up, fileName: file?.name } );
		try {
			if ( file && up && typeof up.removeFile === 'function' ) {
				up.removeFile( file );
				debug( 'plupload.removeFile called' );
			}
		} catch ( e ) { debug( 'removeFile threw', e ); }
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
				debug( 'wp.Uploader instance registered; total:', globalThis.__vipWpUploaderInstances.size, 'queue:', !!this.queue );
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
		debug( 'wrapped wp.Uploader' );
	} else {
		debug( 'wp.Uploader unavailable or already wrapped' );
	}

	/**
	 * Walk every tracked wp.Uploader's queue and clean up the orphan
	 * attachment that backs the visible "uploading…" tile.
	 *
	 * Tries both Backbone strategies in order:
	 *   1. `attachment.destroy({ wait: false })` — fires `destroy` event,
	 *      collection removes the model, view unmounts.
	 *   2. `queue.remove(attachment)` — collection-level remove as a fallback
	 *      in case destroy() didn't propagate (e.g. attachment had an ID and
	 *      Backbone wanted to wait for a DELETE response).
	 *
	 * Returns the count of attachments touched.
	 */
	globalThis.__vipDestroyUploadingAttachment = function ( fileName ) {
		let touched = 0;
		try {
			const instances = globalThis.__vipWpUploaderInstances;
			debug( 'destroyUploadingAttachment called; fileName:', fileName, 'instances:', instances?.size );
			if ( ! instances || instances.size === 0 ) {
				return 0;
			}
			for ( const wpUploader of instances ) {
				const queue = wpUploader && wpUploader.queue;
				if ( ! queue || typeof queue.filter !== 'function' ) {
					debug( 'uploader has no usable queue', wpUploader );
					continue;
				}
				debug( 'queue length:', queue.length, 'models:', queue.length > 0 ? queue.map( ( m ) => ( {
					filename: m.get && m.get( 'filename' ),
					uploading: m.get && m.get( 'uploading' ),
					id: m.id,
					cid: m.cid,
				} ) ) : [] );
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
				debug( 'matched', matches.length, 'attachments' );
				for ( const attachment of matches ) {
					debug( 'cleaning attachment', { cid: attachment.cid, filename: attachment.get( 'filename' ) } );
					try {
						attachment.destroy( { wait: false } );
						debug( 'destroy() called' );
					} catch ( e ) { debug( 'destroy threw', e ); }
					try {
						queue.remove( attachment );
						debug( 'queue.remove() called; new queue length:', queue.length );
					} catch ( e ) { debug( 'queue.remove threw', e ); }
					touched += 1;
				}
			}
		} catch ( e ) { debug( 'destroyUploadingAttachment outer error', e ); }
		debug( 'destroyUploadingAttachment returning', touched );
		return touched;
	};
}() );
