/**
 * Large media upload warning — DOM + network interceptor.
 *
 * Two chokepoints, both registered as early as possible:
 *
 *   1. Capture-phase `change` listener on `document` for file inputs. Covers
 *      Media Library "Select Files", Classic Editor "Add Media", Gutenberg
 *      image-block placeholder "Upload" button. Blocks BEFORE upload starts.
 *
 *   2. `XMLHttpRequest.prototype.send` + `globalThis.fetch` wraps. Covers
 *      drag-drop onto the editor canvas, plupload drag-drop on the Media
 *      Library modal, and anything else that bypasses file inputs. Catches
 *      the upload at the network boundary, before bytes leave the browser.
 *
 * The XHR/fetch wrap is a safety net — it sees uploads our DOM intercept
 * already approved. To avoid double-prompting, the DOM intercept registers
 * approved files in a transient "pending" map; the network wrap consumes from
 * that map and skips the dialog when a match is found.
 *
 * On cancel from the XHR path, two cleanup branches run side by side:
 *
 *   - Backbone collection cleanup for wp.media-based uploaders (Media
 *     Library modal, Classic Editor "Add Media", Gutenberg). Removes
 *     the orphan `wp.media.model.Attachment` from `wp.Uploader.queue`,
 *     `state.get('library')`, `state.get('selection')`, and
 *     `wp.media.model.Attachments.all`. Both the queue (status bar
 *     above the grid) and library (grid tile) have to be cleaned —
 *     library `.observe()`s the queue, so leaving the queue dirty
 *     re-adds the attachment to library on the next `validateAll()`.
 *
 *   - DOM cleanup for raw-plupload + handlers.js uploaders
 *     (media-new.php's multi-file uploader). Removes the
 *     `#media-item-<plupload-id>` element appended by
 *     `wp-includes/js/plupload/handlers.js` — WP intentionally leaves
 *     it behind on cancel.
 *
 * No persistent fingerprint cache: the previous version cached confirmations
 * for the lifetime of the page, which broke re-picking the same file.
 *
 * Failure mode: every handler is wrapped in try/catch and falls open. The
 * original upload path runs unchanged if anything in this file throws.
 *
 * Diagnostic logging: set `globalThis.__vipDebug = true` in the console to
 * see `[VIP-LMW]`-prefixed traces of each step.
 */
( function () {
	'use strict';

	if ( globalThis.__vipLargeMediaInterceptorInstalled ) {
		return;
	}
	globalThis.__vipLargeMediaInterceptorInstalled = true;

	// Symbol marker on re-dispatched events. Lets our `change` listener
	// recognise events it itself synthesised and pass them through.
	const SKIP_MARKER = Symbol( 'vipLargeMediaApproved' );

	function getConfig() {
		const c = globalThis.vipLargeMediaWarningConfig || {};
		return {
			threshold: Number.parseInt( c.thresholdBytes, 10 ) || ( 8 * 1024 * 1024 ),
			mimes: Array.isArray( c.mimeTypes ) ? c.mimeTypes : [],
		};
	}

	function needsConfirmation( file, cfg ) {
		return file
			&& typeof file.size === 'number'
			&& file.size > cfg.threshold
			&& cfg.mimes.includes( file.type );
	}

	function fingerprint( file ) {
		return `${ file.name }|${ file.size }|${ file.lastModified || 0 }|${ file.type }`;
	}

	// Transient registry of files the user has approved that haven't been
	// network-uploaded yet. The XHR/fetch wrap consumes from this on each
	// `send`/`fetch` to avoid double-prompting after the DOM intercept.
	const pendingApprovals = new Map(); // fingerprint -> count

	function addPending( file ) {
		const key = fingerprint( file );
		pendingApprovals.set( key, ( pendingApprovals.get( key ) || 0 ) + 1 );
	}

	function consumePending( file ) {
		const key = fingerprint( file );
		const count = pendingApprovals.get( key ) || 0;
		if ( count <= 0 ) {
			return false;
		}
		if ( count === 1 ) {
			pendingApprovals.delete( key );
		} else {
			pendingApprovals.set( key, count - 1 );
		}
		return true;
	}

	/**
	 * Prompt the user about a single oversized file. Resolves to `true` if
	 * the user cancelled (caller should abort the whole upload), `false`
	 * otherwise. Fail-open: a dialog error counts as a confirm.
	 *
	 * Extracted from `reviewFiles` to keep nesting under Sonar's S2004
	 * threshold and to make the per-file flow easier to read in isolation.
	 */
	function confirmOne( file, threshold, registerApprovals ) {
		const helper = globalThis.vipLargeMediaWarning;
		return helper.confirmLargeUpload( file, threshold )
			.then( ( ok ) => {
				if ( ! ok ) {
					return true; // user cancelled
				}
				if ( registerApprovals ) {
					addPending( file );
				}
				return false;
			} )
			.catch( () => {
				if ( registerApprovals ) {
					addPending( file ); // fail open
				}
				return false;
			} );
	}

	/**
	 * Ask the user about each oversized file in order. Resolves to true if all
	 * files are approved (none cancelled), false otherwise. Fail-open on any
	 * dialog error.
	 */
	function reviewFiles( files, options ) {
		const cfg = getConfig();
		const helper = globalThis.vipLargeMediaWarning;
		if ( typeof helper?.confirmLargeUpload !== 'function' ) {
			return Promise.resolve( true );
		}
		const oversized = files.filter( ( f ) => needsConfirmation( f, cfg ) );
		if ( oversized.length === 0 ) {
			return Promise.resolve( true );
		}

		const registerApprovals = !! options?.registerApprovals;
		return oversized.reduce(
			( chain, file ) => chain.then( ( aborted ) => {
				if ( aborted ) {
					return true;
				}
				return confirmOne( file, cfg.threshold, registerApprovals );
			} ),
			Promise.resolve( false )
		).then( ( aborted ) => ! aborted );
	}

	function filesFromFormData( body ) {
		const files = [];
		try {
			body.forEach( ( value ) => {
				if ( value instanceof File ) {
					files.push( value );
				}
			} );
		} catch ( error ) { debug( 'filesFromFormData iterate threw', error ); }
		return files;
	}

	function debug( ...args ) {
		if ( globalThis.__vipDebug ) {
			// eslint-disable-next-line no-console
			console.log( '[VIP-LMW]', ...args );
		}
	}

	/**
	 * Remove the `#media-item-<plupload-file-id>` DOM node that
	 * `wp-includes/js/plupload/handlers.js` appends in `fileQueued()`.
	 *
	 * This is the "uploading block" on media-new.php's multi-file
	 * uploader (and any other surface that uses raw plupload + the
	 * handlers.js helpers — those don't go through wp.media/wp.Uploader
	 * at all, so `destroyUploadingAttachment` finds nothing to clean
	 * there). WP intentionally leaves the tile in place on cancel; see
	 * the commented-out `UPLOAD_STOPPED` / `FILE_CANCELLED` branch at
	 * handlers.js:332-335. We do the cleanup ourselves.
	 *
	 * No-op when the element doesn't exist, so safe to call on every
	 * cancel regardless of which uploader is in use.
	 */
	function removeHandlersMediaItem( pluploadFileId ) {
		if ( ! pluploadFileId ) {
			return;
		}
		try {
			const el = globalThis.document.getElementById( 'media-item-' + pluploadFileId );
			debug( 'handlers.js media-item', pluploadFileId, el ? 'found, removing' : 'not present' );
			el?.remove();
		} catch ( error ) { debug( 'removeHandlersMediaItem threw', error ); }
	}

	/**
	 * Clean up the orphan "uploading…" UI that WP's media modal leaves
	 * behind when we abort an in-flight upload.
	 *
	 * Two collections render UI for a file being uploaded:
	 *   - `wp.Uploader.queue` drives `wp.media.view.UploaderStatus` (the
	 *     status bar with progress + filename above the grid).
	 *   - `state.get('library')` drives the visible tile in the
	 *     Attachments grid. Library `.observe()`s the Uploader queue, so
	 *     anything still in the queue is auto-re-added to library on the
	 *     next `validateAll()` (which fires on state activate, modal
	 *     transitions, etc.). That's why removing from library alone is
	 *     not sticky.
	 *
	 * The fix is twofold:
	 *   1. Remove the attachment from `wp.Uploader.queue` so the status
	 *      bar's `visibility()` listener clears it.
	 *   2. Set `attachment.destroyed = true` so the library's `validator`
	 *      returns false and won't re-add the model on re-sync.
	 *
	 * We do not call `attachment.destroy()` because it has historically
	 * been observed to wedge wp.Uploader's state machine. Manual
	 * collection-level removal + the `destroyed` flag gives us the same
	 * end state without disturbing the upload pipeline.
	 *
	 * Returns the count of `(collection, attachment)` removals performed.
	 * Returns 0 (no-op) cleanly when `wp.media` isn't loaded — e.g. the
	 * standalone media-new.php page, which has its own UI.
	 */
	// Helpers for `destroyUploadingAttachment` — split out to keep its
	// cognitive complexity under Sonar's S3776 threshold.

	function pushCandidate( arr, name, getter ) {
		try {
			const collection = getter();
			if ( collection ) {
				arr.push( [ name, collection ] );
			}
		} catch ( error ) { debug( name, 'probe threw', error ); }
	}

	function getMediaFrameState() {
		try {
			const frame = globalThis.wp?.media?.frame;
			if ( typeof frame?.state === 'function' ) {
				const state = frame.state();
				return typeof state?.get === 'function' ? state : null;
			}
		} catch ( error ) { debug( 'frame probe threw', error ); }
		return null;
	}

	function collectAttachmentCandidates() {
		const candidates = [];
		pushCandidate( candidates, 'Uploader.queue', () => globalThis.wp?.Uploader?.queue );
		const state = getMediaFrameState();
		if ( state ) {
			pushCandidate( candidates, 'frame.state.library', () => state.get( 'library' ) );
			pushCandidate( candidates, 'frame.state.selection', () => state.get( 'selection' ) );
		}
		pushCandidate( candidates, 'Attachments.all', () => globalThis.wp?.media?.model?.Attachments?.all );
		return candidates;
	}

	function findUploadingTargets( candidates, fileName ) {
		const targets = new Map(); // cid -> attachment
		for ( const [ name, collection ] of candidates ) {
			try {
				if ( typeof collection.filter !== 'function' ) {
					continue;
				}
				const matches = collection.filter( ( a ) =>
					a && typeof a.get === 'function'
					&& a.get( 'uploading' )
					&& ( ! fileName || a.get( 'filename' ) === fileName )
				);
				debug( name, 'matched', matches.length );
				for ( const attachment of matches ) {
					if ( ! targets.has( attachment.cid ) ) {
						targets.set( attachment.cid, attachment );
					}
				}
			} catch ( error ) { debug( name, 'filter error', error ); }
		}
		return targets;
	}

	function markAttachmentDestroyed( attachment ) {
		debug( 'marking destroyed', { cid: attachment.cid, filename: attachment.get( 'filename' ) } );
		try {
			attachment.destroyed = true;
		} catch ( error ) { debug( 'attachment.destroyed set threw', error ); }
		try {
			attachment.set( 'uploading', false, { silent: true } );
		} catch ( error ) { debug( 'attachment.set(uploading,false) threw', error ); }
	}

	function destroyUploadingAttachment( fileName ) {
		debug( 'destroyUploadingAttachment; fileName:', fileName );

		const candidates = collectAttachmentCandidates();
		debug( 'candidates:', candidates.map( ( [ n ] ) => n ) );

		// First pass: identify unique attachments across all candidates.
		// Must run before we set `uploading: false`, otherwise the filter
		// (which keys on `uploading`) would miss them on subsequent passes.
		const targets = findUploadingTargets( candidates, fileName );

		// Mark each unique attachment as destroyed + not-uploading. The
		// `destroyed` flag is what the library's `validator` checks to
		// decide whether to re-add this model on re-sync. `uploading:
		// false` is set silently so we don't trigger an extra round of
		// `change:uploading` listeners in the UploaderStatus view.
		for ( const attachment of targets.values() ) {
			markAttachmentDestroyed( attachment );
		}

		// Second pass: remove from every candidate collection. Removing
		// from `Uploader.queue` is what unmounts the status-bar UI;
		// removing from `library`/`selection`/`Attachments.all` covers
		// the grid tile and any cached references.
		let touched = 0;
		for ( const [ name, collection ] of candidates ) {
			for ( const attachment of targets.values() ) {
				try {
					collection.remove( attachment );
					touched += 1;
				} catch ( error ) { debug( name, 'remove threw', error ); }
			}
		}

		debug( 'destroyUploadingAttachment returning', touched );
		return touched;
	}

	/**
	 * Filter the input files through the pending approvals — any matches are
	 * treated as already-confirmed and need no dialog. Returns the files that
	 * still require review.
	 */
	function filterAgainstPending( files ) {
		const stillNeed = [];
		for ( const file of files ) {
			if ( ! consumePending( file ) ) {
				stillNeed.push( file );
			}
		}
		return stillNeed;
	}

	// ---- File input change interception (pre-upload) ----

	function onChangeCapture( e ) {
		try {
			const target = e.target;
			const isFileInput = target instanceof HTMLInputElement && target.type === 'file';
			debug( 'change captured; target:', target?.tagName, 'isFileInput:', isFileInput, 'skipMarker:', !! e[ SKIP_MARKER ], 'pendingApprovalsSize:', pendingApprovals.size );

			if ( e[ SKIP_MARKER ] ) {
				return; // our own re-dispatched event; let it through
			}
			if ( ! isFileInput ) {
				return;
			}
			const input = target;
			const files = Array.from( input.files || [] );
			debug( 'file input change; files:', files.map( ( f ) => ( { name: f.name, size: f.size, type: f.type } ) ) );
			if ( files.length === 0 ) {
				return;
			}
			const cfg = getConfig();
			const needsReview = files.some( ( f ) => needsConfirmation( f, cfg ) );
			debug( 'needsReview:', needsReview, 'threshold:', cfg.threshold );
			if ( ! needsReview ) {
				return;
			}

			e.stopImmediatePropagation();
			debug( 'showing dialog' );

			reviewFiles( files, { registerApprovals: true } ).then( ( allOk ) => {
				debug( 'dialog result; allOk:', allOk );
				if ( allOk ) {
					try {
						const dt = new DataTransfer();
						files.forEach( ( f ) => dt.items.add( f ) );
						input.files = dt.files;
					} catch ( error ) {
						// Safari may refuse — fall back to the original input.files.
						debug( 'DataTransfer rebuild threw (Safari?)', error );
					}
					const newEvent = new Event( 'change', { bubbles: true, cancelable: true } );
					newEvent[ SKIP_MARKER ] = true;
					input.dispatchEvent( newEvent );
				} else {
					try {
						input.value = '';
						debug( 'cleared input.value; new value:', input.value, 'new files length:', input.files?.length );
					} catch ( error ) { debug( 'clearing input.value threw', error ); }
					// Drain anything we registered as pending for these files —
					// we never let them reach the network layer.
					for ( const f of files ) {
						consumePending( f );
					}
				}
			} );
		} catch ( error ) { debug( 'onChangeCapture threw', error ); }
	}

	// ---- XHR.send interception (network boundary, catches drag-drop) ----

	const NativeXHR = globalThis.XMLHttpRequest;
	if ( typeof NativeXHR?.prototype?.send === 'function' ) {
		const originalSend = NativeXHR.prototype.send;
		// Regular `function` (not arrow) so `this` binds to the XHR
		// instance when called as `xhr.send(body)`. The inner `.then`
		// callback is an arrow so it inherits the same `this`.
		NativeXHR.prototype.send = function ( ...args ) {
			try {
				const body = args[ 0 ];
				if ( body instanceof FormData ) {
					const files = filesFromFormData( body );
					if ( files.length > 0 ) {
						const remaining = filterAgainstPending( files );
						const cfg = getConfig();
						const needsReview = remaining.some( ( f ) => needsConfirmation( f, cfg ) );
						if ( needsReview ) {
							const cancelledName = files[ 0 ]?.name;
							debug( 'xhr intercepted; cancelledName:', cancelledName );
							reviewFiles( remaining ).then( ( allOk ) => {
								if ( allOk ) {
									originalSend.apply( this, args );
									return;
								}
								handleXhrCancel( this, cancelledName );
							} );
							return;
						}
					}
				}
			} catch ( error ) { debug( 'xhr wrap threw', error ); }
			return originalSend.apply( this, args );
		};
	}

	/**
	 * Cancel cleanup for an XHR-driven upload. Order matters:
	 *
	 *   1. `up.removeFile(file)` on plupload's tracked uploader — without
	 *      this, plupload's queue is stuck with a FAILED entry that wedges
	 *      the modal's Select Files button and drop zone for any
	 *      subsequent uploads.
	 *   2. Remove the `#media-item-<plupload-id>` DOM node used by
	 *      `wp-includes/js/plupload/handlers.js` (the multi-file uploader
	 *      on media-new.php). WP intentionally leaves this tile behind on
	 *      cancel — see the commented-out FILE_CANCELLED case in
	 *      handlers.js:332-335. Selector matches nothing on modal-based
	 *      pages, so this is a safe no-op there.
	 *   3. Abort the XHR (network).
	 *   4. Remove the orphan `wp.media.model.Attachment` from
	 *      `wp.Uploader.queue` / `state.library` / etc. (modal pages).
	 */
	function handleXhrCancel( xhr, cancelledName ) {
		let pluploadFileId = null;
		try {
			const current = globalThis.__vipPluploadCurrent;
			if ( typeof current?.up?.removeFile === 'function' && current.file ) {
				pluploadFileId = current.file.id;
				debug( 'plupload.removeFile for', current.file.name, 'id:', pluploadFileId );
				current.up.removeFile( current.file );
			}
		} catch ( error ) { debug( 'plupload.removeFile threw', error ); }
		removeHandlersMediaItem( pluploadFileId );
		try {
			xhr.abort();
		} catch ( error ) { debug( 'xhr.abort threw', error ); }
		destroyUploadingAttachment( cancelledName );
	}

	// ---- fetch interception (modern uploads, e.g. Gutenberg via wp/v2/media) ----

	/**
	 * If the fetch body is a FormData with one or more oversized Files,
	 * await the dialog and return `false` when the user cancelled. Returns
	 * `true` for "nothing to review / approved" — caller should pass
	 * through to the native fetch.
	 */
	async function maybeReviewFetch( init ) {
		const body = init?.body;
		if ( ! ( body instanceof FormData ) ) {
			return true;
		}
		const files = filesFromFormData( body );
		if ( files.length === 0 ) {
			return true;
		}
		const remaining = filterAgainstPending( files );
		const cfg = getConfig();
		if ( ! remaining.some( ( f ) => needsConfirmation( f, cfg ) ) ) {
			return true;
		}
		return reviewFiles( remaining );
	}

	/**
	 * WP-error-shaped JSON 400 response. Returned from the fetch cancel
	 * path so the calling uploader (Gutenberg's `mediaUpload`, `apiFetch`,
	 * etc.) can parse it and surface "Upload cancelled." rather than the
	 * generic "The response is not a valid JSON response" banner. Shape
	 * is a hybrid of REST (top-level `code`/`message`) and
	 * `async-upload.php` (`success`/`data.message`) so both endpoints
	 * behave.
	 */
	function cancelledResponse() {
		const message = ( typeof globalThis.wp?.i18n?.__ === 'function' )
			? globalThis.wp.i18n.__( 'Upload cancelled.', 'vip' )
			: 'Upload cancelled.';
		return new Response(
			JSON.stringify( {
				success: false,
				code: 'large_media_upload_cancelled',
				message,
				data: { status: 400, message },
			} ),
			{
				status: 400,
				statusText: 'Upload cancelled',
				headers: { 'Content-Type': 'application/json' },
			}
		);
	}

	const nativeFetch = globalThis.fetch;
	if ( typeof nativeFetch === 'function' ) {
		globalThis.fetch = async function ( input, init ) {
			try {
				const allOk = await maybeReviewFetch( init );
				if ( ! allOk ) {
					return cancelledResponse();
				}
			} catch ( error ) { debug( 'fetch wrap threw', error ); }
			return nativeFetch.call( this, input, init );
		};
	}

	globalThis.document.addEventListener( 'change', onChangeCapture, true );
}() );
