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
 * On cancel from the XHR path, we also walk WP's media-frame collections
 * (`wp.media.frame.state().get('library')`, `wp.media.model.Attachments.all`)
 * and destroy/remove the orphan `wp.media.model.Attachment` that backs the
 * visible "uploading…" tile in the Media Library / Classic Editor modal —
 * `xhr.abort()` alone leaves that tile behind because the tile is rendered
 * by a Backbone view bound to the model, not by plupload directly.
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
	 * Ask the user about each oversized file in order. Resolves to true if all
	 * files are approved (none cancelled), false otherwise. Fail-open on any
	 * dialog error.
	 */
	function reviewFiles( files, options ) {
		const cfg = getConfig();
		const helper = globalThis.vipLargeMediaWarning;
		if ( ! helper || typeof helper.confirmLargeUpload !== 'function' ) {
			return Promise.resolve( true );
		}
		const oversized = files.filter( ( f ) => needsConfirmation( f, cfg ) );
		if ( oversized.length === 0 ) {
			return Promise.resolve( true );
		}

		return oversized.reduce( ( chain, file ) => chain.then( ( aborted ) => {
			if ( aborted ) {
				return true;
			}
			return helper.confirmLargeUpload( file, cfg.threshold )
				.then( ( ok ) => {
					if ( ok ) {
						if ( options && options.registerApprovals ) {
							addPending( file );
						}
						return false;
					}
					return true; // user cancelled — abort the whole upload
				} )
				.catch( () => {
					if ( options && options.registerApprovals ) {
						addPending( file ); // fail open
					}
					return false;
				} );
		} ), Promise.resolve( false ) ).then( ( aborted ) => ! aborted );
	}

	function filesFromFormData( body ) {
		const files = [];
		try {
			body.forEach( ( value ) => {
				if ( value instanceof File ) {
					files.push( value );
				}
			} );
		} catch ( _ ) { /* ignore */ }
		return files;
	}

	function debug() {
		if ( globalThis.__vipDebug ) {
			// eslint-disable-next-line no-console
			console.log.apply( console, [ '[VIP-LMW]' ].concat( Array.from( arguments ) ) );
		}
	}

	/**
	 * Clean up the orphan "uploading…" tile that WP's media modal leaves
	 * behind when we abort an in-flight upload.
	 *
	 * The tile is rendered by a Backbone view bound to a
	 * `wp.media.model.Attachment` that lives in the media frame's library
	 * state — and is mirrored in `wp.media.model.Attachments.all`. Removing
	 * the model from those collections is what makes Backbone unmount the
	 * view. We call both `attachment.destroy({wait:false})` (model-level)
	 * and `collection.remove(attachment)` (collection-level) because either
	 * alone has been observed to no-op depending on the WP build.
	 *
	 * Returns the count of attachments cleaned. Returns 0 (no-op) cleanly
	 * when `wp.media` isn't loaded — e.g. the standalone media-new.php page,
	 * which has its own UI and isn't affected by this orphan.
	 */
	function destroyUploadingAttachment( fileName ) {
		let touched = 0;
		debug( 'destroyUploadingAttachment; fileName:', fileName );

		const candidates = [];
		try {
			const frame = globalThis.wp?.media?.frame;
			const state = frame && typeof frame.state === 'function' ? frame.state() : null;
			if ( state && typeof state.get === 'function' ) {
				const library = state.get( 'library' );
				if ( library ) {
					candidates.push( [ 'frame.state.library', library ] );
				}
				const selection = state.get( 'selection' );
				if ( selection ) {
					candidates.push( [ 'frame.state.selection', selection ] );
				}
			}
		} catch ( e ) { debug( 'frame probe threw', e ); }

		try {
			const all = globalThis.wp?.media?.model?.Attachments?.all;
			if ( all ) {
				candidates.push( [ 'Attachments.all', all ] );
			}
		} catch ( e ) { debug( 'Attachments.all probe threw', e ); }

		debug( 'candidates:', candidates.map( ( [ n ] ) => n ) );

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
					debug( name, 'cleaning', { cid: attachment.cid, filename: attachment.get( 'filename' ) } );
					try { attachment.destroy( { wait: false } ); } catch ( _ ) { /* ignore */ }
					try { collection.remove( attachment ); } catch ( _ ) { /* ignore */ }
					touched += 1;
				}
			} catch ( e ) { debug( name, 'error', e ); }
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
					} catch ( _ ) { /* Safari may refuse — fall back to the original input.files */ }
					const newEvent = new Event( 'change', { bubbles: true, cancelable: true } );
					newEvent[ SKIP_MARKER ] = true;
					input.dispatchEvent( newEvent );
				} else {
					try {
						input.value = '';
						debug( 'cleared input.value; new value:', input.value, 'new files length:', input.files?.length );
					} catch ( err ) { debug( 'clearing input.value threw', err ); }
					// Drain anything we registered as pending for these files —
					// we never let them reach the network layer.
					for ( const f of files ) {
						consumePending( f );
					}
				}
			} );
		} catch ( e2 ) { debug( 'onChangeCapture threw', e2 ); }
	}

	// ---- XHR.send interception (network boundary, catches drag-drop) ----

	const NativeXHR = globalThis.XMLHttpRequest;
	if ( NativeXHR && NativeXHR.prototype && typeof NativeXHR.prototype.send === 'function' ) {
		const originalSend = NativeXHR.prototype.send;
		NativeXHR.prototype.send = function ( body ) {
			try {
				if ( body instanceof FormData ) {
					const files = filesFromFormData( body );
					if ( files.length > 0 ) {
						const remaining = filterAgainstPending( files );
						const cfg = getConfig();
						const needsReview = remaining.some( ( f ) => needsConfirmation( f, cfg ) );
						if ( needsReview ) {
							const xhr = this;
							const args = arguments;
							const cancelledName = files[ 0 ] && files[ 0 ].name;
							debug( 'xhr intercepted; cancelledName:', cancelledName );
							reviewFiles( remaining ).then( ( allOk ) => {
								if ( allOk ) {
									originalSend.apply( xhr, args );
									return;
								}
								// Cancel: stop the network request, then clean up the
								// orphan wp.media attachment that backs the visible
								// "uploading…" tile.
								try { xhr.abort(); } catch ( _ ) { /* ignore */ }
								destroyUploadingAttachment( cancelledName );
							} );
							return;
						}
					}
				}
			} catch ( _ ) { /* fail open */ }
			return originalSend.apply( this, arguments );
		};
	}

	// ---- fetch interception (modern uploads, e.g. Gutenberg via wp/v2/media) ----

	const nativeFetch = globalThis.fetch;
	if ( typeof nativeFetch === 'function' ) {
		globalThis.fetch = async function ( input, init ) {
			try {
				const body = init && init.body;
				if ( body instanceof FormData ) {
					const files = filesFromFormData( body );
					if ( files.length > 0 ) {
						const remaining = filterAgainstPending( files );
						const cfg = getConfig();
						const needsReview = remaining.some( ( f ) => needsConfirmation( f, cfg ) );
						if ( needsReview ) {
							const allOk = await reviewFiles( remaining );
							if ( ! allOk ) {
								// Return a WP-error-shaped JSON body so the calling
								// uploader (Gutenberg's mediaUpload, apiFetch, etc.)
								// can parse it and surface a sensible message rather
								// than the generic "The response is not a valid JSON
								// response" banner. Shape is a hybrid of REST
								// (top-level `code`/`message`) and async-upload.php
								// (`success`/`data.message`) so both endpoints behave.
								const message = ( globalThis.wp && globalThis.wp.i18n && typeof globalThis.wp.i18n.__ === 'function' )
									? globalThis.wp.i18n.__( 'Upload cancelled.', 'vip' )
									: 'Upload cancelled.';
								return new Response(
									JSON.stringify( {
										success: false,
										code: 'large_media_upload_cancelled',
										message,
										data: {
											status: 400,
											message,
										},
									} ),
									{
										status: 400,
										statusText: 'Upload cancelled',
										headers: { 'Content-Type': 'application/json' },
									}
								);
							}
						}
					}
				}
			} catch ( _ ) { /* fail open */ }
			return nativeFetch.call( this, input, init );
		};
	}

	globalThis.document.addEventListener( 'change', onChangeCapture, true );
}() );
