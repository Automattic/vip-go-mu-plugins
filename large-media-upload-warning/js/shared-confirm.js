( function () {
	'use strict';

	if ( window.vipLargeMediaWarning ) {
		return;
	}

	var SESSION_KEY = 'vip_large_media_warning_dismissed';

	function translate( text ) {
		if ( window.wp && window.wp.i18n && typeof window.wp.i18n.__ === 'function' ) {
			return window.wp.i18n.__( text, 'vip' );
		}
		return text;
	}

	function formatMb( bytes ) {
		return ( bytes / ( 1024 * 1024 ) ).toFixed( 1 );
	}

	function buildDialog( file, threshold ) {
		var dialog = document.createElement( 'dialog' );
		dialog.className = 'vip-large-media-warning-dialog';
		dialog.setAttribute( 'role', 'alertdialog' );
		dialog.setAttribute( 'aria-labelledby', 'vip-lmw-title' );
		dialog.style.cssText = 'max-width:480px;padding:1.5em;border:1px solid #ccd0d4;border-radius:4px;';

		dialog.innerHTML =
			'<h2 id="vip-lmw-title" style="margin-top:0">' +
				translate( 'Large image upload' ) +
			'</h2>' +
			'<p>' +
				translate( 'This image is large (' ) + formatMb( file.size ) + ' MB). ' +
				translate( 'Large images make uploads slow and can cause errors on your site. We recommend resizing the image to under ' ) +
				formatMb( threshold ) + ' MB ' +
				translate( 'before uploading.' ) +
			'</p>' +
			'<p><label><input type="checkbox" id="vip-lmw-dismiss"> ' +
				translate( "Don't ask again this session" ) +
			'</label></p>' +
			'<div style="display:flex;justify-content:flex-end;gap:0.5em">' +
				'<button type="button" class="button" data-action="cancel" autofocus>' +
					translate( 'Cancel upload' ) +
				'</button>' +
				'<button type="button" class="button button-primary" data-action="confirm">' +
					translate( 'Upload anyway' ) +
				'</button>' +
			'</div>';
		return dialog;
	}

	function confirmLargeUpload( file, threshold ) {
		return new Promise( function ( resolve ) {
			try {
				if ( window.sessionStorage && window.sessionStorage.getItem( SESSION_KEY ) === '1' ) {
					return resolve( true );
				}
			} catch ( e ) { /* sessionStorage unavailable; fall through */ }

			var dialog = buildDialog( file, threshold );
			document.body.appendChild( dialog );

			function cleanup( result ) {
				try {
					var dismiss = dialog.querySelector( '#vip-lmw-dismiss' );
					if ( result && dismiss && dismiss.checked && window.sessionStorage ) {
						window.sessionStorage.setItem( SESSION_KEY, '1' );
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

	window.vipLargeMediaWarning = {
		confirmLargeUpload: confirmLargeUpload,
		SESSION_KEY: SESSION_KEY,
	};
}() );
