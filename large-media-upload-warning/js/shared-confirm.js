( function () {
	'use strict';

	if ( globalThis.vipLargeMediaWarning ) {
		return;
	}

	const SESSION_KEY = 'vip_large_media_warning_dismissed';

	// i18n is wired through wp.i18n.__ so strings are translation-ready, but the
	// 'vip' text-domain is not yet loaded via wp_set_script_translations() — that's
	// a follow-up once the broader translation strategy for this module is decided.
	// Until then, __() falls through to English literals.
	function translate( text ) {
		if ( typeof globalThis.wp?.i18n?.__ === 'function' ) {
			return globalThis.wp.i18n.__( text, 'vip' );
		}
		return text;
	}

	function formatMb( bytes ) {
		return ( bytes / ( 1024 * 1024 ) ).toFixed( 1 );
	}

	function sprintfTwo( fmt, a, b ) {
		if ( typeof globalThis.wp?.i18n?.sprintf === 'function' ) {
			return globalThis.wp.i18n.sprintf( fmt, a, b );
		}
		return fmt.replace( /%1\$s/g, String( a ) ).replace( /%2\$s/g, String( b ) );
	}

	function el( tag, attrs, text ) {
		const node = globalThis.document.createElement( tag );
		if ( attrs ) {
			for ( const key in attrs ) {
				if ( Object.prototype.hasOwnProperty.call( attrs, key ) ) {
					if ( key === 'style' ) {
						node.style.cssText = attrs[ key ];
					} else if ( key === 'className' ) {
						node.className = attrs[ key ];
					} else {
						node.setAttribute( key, attrs[ key ] );
					}
				}
			}
		}
		if ( typeof text === 'string' ) {
			node.textContent = text;
		}
		return node;
	}

	function buildDialog( file, threshold ) {
		const sizeMb = formatMb( file.size );
		const thresholdMb = formatMb( threshold );

		const dialog = el( 'dialog', {
			className: 'vip-large-media-warning-dialog',
			role: 'alertdialog',
			'aria-labelledby': 'vip-lmw-title',
			style: 'max-width:480px;padding:1.5em;border:1px solid #ccd0d4;border-radius:4px;',
		} );

		const title = el( 'h2', { id: 'vip-lmw-title', style: 'margin-top:0' }, translate( 'Large image upload' ) );
		dialog.appendChild( title );

		const body = el( 'p', null, sprintfTwo(
			translate( 'This image is large (%1$s MB). Using large images on the site may negatively affect visitor experience and, in certain cases, cause your application to become slow. We recommend resizing the image to under %2$s MB before uploading. Do you want to continue anyway?' ),
			sizeMb,
			thresholdMb
		) );
		dialog.appendChild( body );

		const dismissWrapper = el( 'p' );
		const dismissLabel = el( 'label' );
		const dismissInput = el( 'input', { type: 'checkbox', id: 'vip-lmw-dismiss' } );
		dismissLabel.appendChild( dismissInput );
		dismissLabel.appendChild( globalThis.document.createTextNode( ' ' + translate( "Don't ask again this session" ) ) );
		dismissWrapper.appendChild( dismissLabel );
		dialog.appendChild( dismissWrapper );

		const buttonRow = el( 'div', { style: 'display:flex;justify-content:flex-end;gap:0.5em' } );
		const cancelBtn = el( 'button', { type: 'button', class: 'button', 'data-action': 'cancel', autofocus: 'autofocus' }, translate( 'Cancel upload' ) );
		const confirmBtn = el( 'button', { type: 'button', class: 'button button-primary', 'data-action': 'confirm' }, translate( 'Upload anyway' ) );
		buttonRow.appendChild( cancelBtn );
		buttonRow.appendChild( confirmBtn );
		dialog.appendChild( buttonRow );

		return dialog;
	}

	function confirmLargeUpload( file, threshold ) {
		return new Promise( function ( resolve ) {
			try {
				if ( globalThis.sessionStorage?.getItem( SESSION_KEY ) === '1' ) {
					return resolve( true );
				}
			} catch ( e ) { /* sessionStorage unavailable; fall through */ }

			const dialog = buildDialog( file, threshold );
			globalThis.document.body.appendChild( dialog );

			function cleanup( result ) {
				try {
					const dismiss = dialog.querySelector( '#vip-lmw-dismiss' );
					if ( result && dismiss?.checked && globalThis.sessionStorage ) {
						globalThis.sessionStorage.setItem( SESSION_KEY, '1' );
					}
				} catch ( e ) { /* ignore */ }

				if ( dialog.open ) {
					dialog.close();
				}
				dialog.remove();
				resolve( result );
			}

			dialog.querySelector( '[data-action="cancel"]' ).addEventListener( 'click', function () {
				cleanup( false );
			} );
			dialog.querySelector( '[data-action="confirm"]' ).addEventListener( 'click', function () {
				cleanup( true );
			} );
			dialog.addEventListener( 'cancel', function ( e ) {
				e.preventDefault();
				cleanup( false );
			} );

			if ( typeof dialog.showModal === 'function' ) {
				dialog.showModal();
			} else {
				dialog.setAttribute( 'open', 'open' );
			}
		} );
	}

	globalThis.vipLargeMediaWarning = {
		confirmLargeUpload: confirmLargeUpload,
		SESSION_KEY: SESSION_KEY,
	};
}() );
