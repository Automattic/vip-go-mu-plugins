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
 *      drag-drop onto the editor canvas, plupload drag-drop on media-new.php,
 *      and anything else that bypasses file inputs. Catches the upload at the
 *      network boundary, before bytes leave the browser.
 *
 * The XHR/fetch wrap is a safety net — it sees uploads our DOM intercept
 * already approved. To avoid double-prompting, the DOM intercept registers
 * approved files in a transient "pending" map; the network wrap consumes from
 * that map and skips the dialog when a match is found.
 *
 * No persistent fingerprint cache: the previous version cached confirmations
 * for the lifetime of the page, which broke re-picking the same file.
 *
 * Failure mode: every handler is wrapped in try/catch and falls open. The
 * original upload path runs unchanged if anything in this file throws.
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
			if ( e[ SKIP_MARKER ] ) {
				return; // our own re-dispatched event; let it through
			}
			const input = e.target;
			if ( ! ( input instanceof HTMLInputElement ) || input.type !== 'file' ) {
				return;
			}
			const files = Array.from( input.files || [] );
			if ( files.length === 0 ) {
				return;
			}
			const cfg = getConfig();
			const needsReview = files.some( ( f ) => needsConfirmation( f, cfg ) );
			if ( ! needsReview ) {
				return;
			}

			e.stopImmediatePropagation();

			reviewFiles( files, { registerApprovals: true } ).then( ( allOk ) => {
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
					} catch ( _ ) { /* ignore */ }
					// Drain anything we registered as pending for these files —
					// we never let them reach the network layer.
					for ( const f of files ) {
						consumePending( f );
					}
				}
			} );
		} catch ( _ ) { /* fail open */ }
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
							reviewFiles( remaining ).then( ( allOk ) => {
								if ( allOk ) {
									originalSend.apply( xhr, args );
								} else {
									try { xhr.abort(); } catch ( _ ) { /* ignore */ }
								}
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
								// 499 is nginx's "client closed request" — a reasonable
								// non-success status for "user cancelled".
								return new Response( null, { status: 499, statusText: 'Upload cancelled by user' } );
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
