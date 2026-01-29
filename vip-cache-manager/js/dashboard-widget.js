/* global VIPCacheManagerDashboard */
( function () {
	const cfg = window.VIPCacheManagerDashboard;
	if ( ! cfg ) {
		return;
	}

	const form = document.getElementById( 'vip-cache-manager-dashboard-form' );
	if ( ! form ) {
		return;
	}

	const select = form.querySelector( '#vip-cache-manager-dashboard-action' );
	const urlWrap = document.getElementById( 'vip-cache-manager-dashboard-url-wrap' );
	const urlInput = form.querySelector( '#vip-cache-manager-dashboard-url' );
	const nonceInput = form.querySelector( 'input[name="nonce"]' );
	const description = document.getElementById( 'vip-cache-manager-dashboard-description' );
	const result = form.querySelector( '.vip-cache-manager-dashboard-result' );
	const submit = form.querySelector( 'button[type="submit"]' );

	if ( ! select || ! urlWrap || ! urlInput || ! nonceInput || ! description || ! result || ! submit ) {
		return;
	}

	function updateUrlVisibility() {
		const isUrl = select.value === cfg.urlKey;
		urlWrap.style.display = isUrl ? '' : 'none';
		urlInput.required = isUrl;
		if ( ! isUrl ) {
			urlInput.value = '';
		}
	}

	function updateDescription() {
		const option = select.options[ select.selectedIndex ];
		if ( option && option.dataset ) {
			description.textContent = option.dataset.description || '';
		} else {
			description.textContent = '';
		}
	}

	updateUrlVisibility();
	updateDescription();
	select.addEventListener( 'change', updateUrlVisibility );
	select.addEventListener( 'change', updateDescription );

	form.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();

		if ( submit.disabled ) {
			return;
		}

		result.textContent = '';

		const selectedOption = select.options[ select.selectedIndex ];
		const isUrlAction = select.value === cfg.urlKey;
		if ( ! isUrlAction ) {
			const title = selectedOption ? ( selectedOption.text || select.value ) : select.value;
			const desc = selectedOption && selectedOption.dataset ? ( selectedOption.dataset.description || '' ) : '';
			const message = desc ? `Please confirm you want to ${ desc.toLowerCase() }\n\nProceed?` : `${ title }\n\nProceed?`;

			if ( ! window.confirm( message ) ) {
				return;
			}
		}

		submit.disabled = true;

		try {
			const payload = {
				nonce: nonceInput.value,
				purge_action: select.value,
				url: urlInput.value,
			};

			const response = await fetch( cfg.ajaxurl + '?action=' + encodeURIComponent( cfg.action ), {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( payload ),
			} );

			const json = await response.json();
			const hasMessage = json && json.data && json.data.message;
			const isSuccess = response.ok && ( ! json || json.success !== false );
			const message = hasMessage ? json.data.message : ( isSuccess ? 'Done.' : 'Request failed.' );

			// Clear previous notice state before setting new one.
			result.classList.remove( 'notice-success', 'notice-error' );

			result.textContent = message;
			if ( isSuccess ) {
				result.classList.add( 'notice-success' );
			} else {
				result.classList.add( 'notice-error' );
			}
			result.classList.add( 'notice' );
		} catch ( e ) {
			// Network or unexpected error.
			result.classList.remove( 'notice-success', 'notice-error' );
			result.textContent = 'Request failed.';
			result.classList.add( 'notice-error' );
			result.classList.add( 'notice' );
		} finally {
			submit.disabled = false;
		}
	} );
}() );
