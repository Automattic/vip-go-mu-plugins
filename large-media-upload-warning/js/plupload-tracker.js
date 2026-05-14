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
	 * Match predicate for the orphan "uploading…" attachment.
	 */
	function matchesOrphan( attachment, fileName ) {
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
	}

	/**
	 * Try to remove an attachment from a Backbone Collection. Tries both
	 * `attachment.destroy({wait:false})` (model-level) and
	 * `collection.remove(attachment)` (collection-level) — either alone has
	 * been observed to no-op in different WP setups.
	 */
	function cleanAttachmentFromCollection( collection, attachment ) {
		let ok = false;
		try {
			attachment.destroy( { wait: false } );
			ok = true;
			debug( 'attachment.destroy() called' );
		} catch ( e ) { debug( 'destroy() threw', e ); }
		try {
			collection.remove( attachment );
			ok = true;
			debug( 'collection.remove() called; new length:', collection.length );
		} catch ( e ) { debug( 'collection.remove() threw', e ); }
		return ok;
	}

	/**
	 * Walk every plausible Backbone Attachments collection in WP's media stack
	 * and clean up the orphan attachment that backs the visible
	 * "uploading…" tile.
	 *
	 * In modern WP, the tile is rendered from `wp.media.frame.state().get('library')`
	 * (the modal's library) and/or `wp.media.model.Attachments.all` (the global
	 * cache). The wp.Uploader instance does NOT carry a queue collection of its
	 * own in current builds, so the earlier approach missed.
	 *
	 * Returns the count of attachments touched.
	 */
	globalThis.__vipDestroyUploadingAttachment = function ( fileName ) {
		let touched = 0;
		debug( 'destroyUploadingAttachment called; fileName:', fileName );

		// Dump the keys of every tracked wp.Uploader so we know exactly what
		// the instance carries on this WP build. Useful for debugging future
		// regressions if the property layout changes again.
		const instances = globalThis.__vipWpUploaderInstances;
		if ( instances ) {
			for ( const u of instances ) {
				debug( 'wp.Uploader instance keys:', Object.keys( u || {} ) );
			}
		}

		// Candidate collections to scan. Order matters: most-specific first.
		const candidates = [];

		try {
			const frame = globalThis.wp?.media?.frame;
			if ( frame && typeof frame.state === 'function' ) {
				const state = frame.state();
				const library = state && typeof state.get === 'function' && state.get( 'library' );
				if ( library ) {
					candidates.push( [ 'frame.state().library', library ] );
				}
				const selection = state && typeof state.get === 'function' && state.get( 'selection' );
				if ( selection ) {
					candidates.push( [ 'frame.state().selection', selection ] );
				}
			}
		} catch ( e ) { debug( 'frame.state() probe threw', e ); }

		try {
			const all = globalThis.wp?.media?.model?.Attachments?.all;
			if ( all ) {
				candidates.push( [ 'Attachments.all', all ] );
			}
		} catch ( e ) { debug( 'Attachments.all probe threw', e ); }

		// Also include any `queue` that DOES happen to live on a wp.Uploader
		// instance (older WP builds did this).
		if ( instances ) {
			for ( const u of instances ) {
				if ( u && u.queue && typeof u.queue.filter === 'function' ) {
					candidates.push( [ 'wpUploader.queue', u.queue ] );
				}
			}
		}

		debug( 'collection candidates:', candidates.map( ( [ n ] ) => n ) );

		for ( const [ name, collection ] of candidates ) {
			try {
				if ( typeof collection.filter !== 'function' ) {
					debug( name, 'is not a Backbone Collection, skipping' );
					continue;
				}
				const length = typeof collection.length === 'number' ? collection.length : '?';
				debug( name, 'length:', length );
				const matches = collection.filter( ( a ) => matchesOrphan( a, fileName ) );
				debug( name, 'matched', matches.length, 'attachments' );
				for ( const attachment of matches ) {
					debug( name, 'cleaning', { cid: attachment.cid, filename: attachment.get( 'filename' ), uploading: attachment.get( 'uploading' ) } );
					if ( cleanAttachmentFromCollection( collection, attachment ) ) {
						touched += 1;
					}
				}
			} catch ( e ) { debug( name, 'outer error', e ); }
		}

		debug( 'destroyUploadingAttachment returning', touched );
		return touched;
	};
}() );
