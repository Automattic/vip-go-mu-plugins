( async () => {
	async function postData( url = '', data = {} ) {
		// Default options are marked with *
		const response = await fetch( url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json'
			},
			body: JSON.stringify( { ...data } )
		} );
		return response.json();
	}

	// Indicates whether there's a purge request happening
	let purgeInProgress = false;
	// Stores the ref to the DOM node
	let btn;

	/**
	 * Grab all the necesary URLs (incl. scripts and CSS) for the purge.
	 */
	const getURLsToPurge = () => {
		return [document.location.toString()].concat(
			Array.from( document.querySelectorAll( 'script[src]' ) ).map( ( { src } ) => src ),
			Array.from( document.querySelectorAll( 'link[rel=stylesheet]' ) ).map( ( { href } ) => href )
		)
			.filter( url => url.includes( document.location.hostname ) );
	}

	/**
	 * Cache purge click handler.
	 * 
	 * @param {Event} e
	 */
	async function onClickHandler( e ) {
		e.preventDefault();

		if ( purgeInProgress ) {
			return;
		}

		let { nonce = '', ajaxurl = '' } = window.VIPPageFlush || {};

		if ( !( nonce && ajaxurl ) ) {
			alert( 'VIP Cache Manager: page cache purging disabled' );
		}

		purgeInProgress = true;

		const urls = getURLsToPurge();

		try {
			const res = await postData( ajaxurl, { nonce, urls } );
			const { success, data } = res;

			btn.textContent = data.result || 'Success';
			btn.disabled = true;
			btn.removeEventListener( 'click', onClickHandler );
		} catch ( err ) {
			purgeInProgress = false;
			btn.textContent = '❌ Cache Purge Failed';
		}
	}

	document.addEventListener( 'DOMContentLoaded', () => {
		btn = document.querySelector( '#wp-admin-bar-vip-purge-page > .ab-item' )
		if ( btn ) {
			btn.addEventListener( 'click', onClickHandler );
		}
	} );

} )();
