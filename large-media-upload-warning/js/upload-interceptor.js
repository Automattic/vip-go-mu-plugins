/**
 * Large media upload warning — DOM-level interceptor.
 *
 * We intercept `change` events on file inputs and `drop` events for drag-drop, both
 * in the capture phase on the document. This is the single chokepoint that catches
 * every upload UI we care about — Media Library, Classic Editor's Add Media,
 * Gutenberg's image-block placeholder, and Gutenberg drag-drop onto the editor —
 * BEFORE plupload, the block editor, or `wp.mediaUtils.uploadMedia` ever sees the
 * file. This is the only approach that reliably stops the upload from starting
 * while we ask the user; trying to pause plupload (`up.stop()`) from inside
 * `FilesAdded` or `BeforeUpload` races with plupload's auto-start, and Gutenberg's
 * `MediaPlaceholder` inlines `@wordpress/media-utils` so neither
 * `wp.mediaUtils.uploadMedia` nor `dispatch('core/block-editor').updateSettings`
 * reaches its bundled `uploadMedia`.
 *
 * Flow:
 *   1. Capture-phase listener fires before plupload / block editor handlers.
 *   2. If any oversized image is in the file list, stopImmediatePropagation.
 *   3. Show the shared confirm dialog for each oversized file.
 *   4. On confirm: re-attach the files to the input (via DataTransfer) and dispatch
 *      a fresh `change` (or `drop`) event so plupload / Gutenberg pick up the
 *      already-confirmed files. The confirmed-file fingerprint cache prevents a
 *      re-prompt on the re-dispatch.
 *   5. On cancel: clear the input / discard the drop.
 *
 * Failure mode: every handler is wrapped in try/catch and falls open. The original
 * upload path runs unchanged if anything in this file throws.
 */
( function () {
	'use strict';

	if ( globalThis.__vipLargeMediaInterceptorInstalled ) {
		return;
	}
	globalThis.__vipLargeMediaInterceptorInstalled = true;

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

	// Cache confirmed files by fingerprint. We can't rely on object identity once
	// we re-attach files via DataTransfer (some browsers re-wrap them).
	const confirmedFingerprints = new Set();

	function fingerprint( file ) {
		return `${ file.name }|${ file.size }|${ file.lastModified || 0 }|${ file.type }`;
	}

	function isConfirmed( file ) {
		return confirmedFingerprints.has( fingerprint( file ) );
	}

	function markConfirmed( file ) {
		confirmedFingerprints.add( fingerprint( file ) );
	}

	function reviewFiles( files ) {
		const cfg = getConfig();
		const helper = globalThis.vipLargeMediaWarning;
		if ( ! helper || typeof helper.confirmLargeUpload !== 'function' ) {
			return Promise.resolve( true );
		}
		const oversized = files.filter( ( f ) => needsConfirmation( f, cfg ) && ! isConfirmed( f ) );
		if ( oversized.length === 0 ) {
			return Promise.resolve( true );
		}

		// Review sequentially — multiple dialogs at once would be confusing UX.
		return oversized.reduce( ( chain, file ) => chain.then( ( aborted ) => {
			if ( aborted ) {
				return true;
			}
			return helper.confirmLargeUpload( file, cfg.threshold )
				.then( ( ok ) => {
					if ( ok ) {
						markConfirmed( file );
						return false;
					}
					return true; // user cancelled — abort the whole upload
				} )
				.catch( () => {
					markConfirmed( file ); // fail open on dialog error
					return false;
				} );
		} ), Promise.resolve( false ) ).then( ( aborted ) => ! aborted );
	}

	function onChangeCapture( e ) {
		try {
			const input = e.target;
			if ( ! ( input instanceof HTMLInputElement ) || input.type !== 'file' ) {
				return;
			}
			const files = Array.from( input.files || [] );
			if ( files.length === 0 ) {
				return;
			}
			const cfg = getConfig();
			const needsReview = files.some( ( f ) => needsConfirmation( f, cfg ) && ! isConfirmed( f ) );
			if ( ! needsReview ) {
				return;
			}

			e.stopImmediatePropagation();

			reviewFiles( files ).then( ( allOk ) => {
				if ( allOk ) {
					try {
						const dt = new DataTransfer();
						files.forEach( ( f ) => dt.items.add( f ) );
						input.files = dt.files;
					} catch ( _ ) { /* Safari occasionally refuses; the original input.files survives */ }
					input.dispatchEvent( new Event( 'change', { bubbles: true, cancelable: true } ) );
				} else {
					try {
						input.value = '';
					} catch ( _ ) { /* ignore */ }
				}
			} );
		} catch ( _ ) { /* fail open */ }
	}

	function onDropCapture( e ) {
		try {
			const dt = e.dataTransfer;
			if ( ! dt ) {
				return;
			}
			const files = Array.from( dt.files || [] );
			if ( files.length === 0 ) {
				return;
			}
			const cfg = getConfig();
			const needsReview = files.some( ( f ) => needsConfirmation( f, cfg ) && ! isConfirmed( f ) );
			if ( ! needsReview ) {
				return;
			}

			e.stopImmediatePropagation();
			e.preventDefault();

			const target = e.target;
			const clientX = e.clientX;
			const clientY = e.clientY;

			reviewFiles( files ).then( ( allOk ) => {
				if ( ! allOk ) {
					return;
				}
				try {
					const newDt = new DataTransfer();
					files.forEach( ( f ) => newDt.items.add( f ) );
					const newEvent = new DragEvent( 'drop', {
						bubbles: true,
						cancelable: true,
						dataTransfer: newDt,
						clientX,
						clientY,
					} );
					target.dispatchEvent( newEvent );
				} catch ( _ ) { /* ignore — some browsers restrict synthetic DragEvent.dataTransfer */ }
			} );
		} catch ( _ ) { /* fail open */ }
	}

	globalThis.document.addEventListener( 'change', onChangeCapture, true );
	globalThis.document.addEventListener( 'drop', onDropCapture, true );
}() );
