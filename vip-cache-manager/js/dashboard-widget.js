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

	function setResultNotice( message, isSuccess ) {
		result.classList.remove( 'notice-success', 'notice-error' );
		result.textContent = message;
		result.classList.add( isSuccess ? 'notice-success' : 'notice-error' );
		result.classList.add( 'notice' );
	}

	function normalizeUrlForComparison( value ) {
		if ( 'string' !== typeof value || ! value.trim() ) {
			return '';
		}

		try {
			const parsed = new URL( value.trim() );
			let port = parsed.port;
			if ( ( 'https:' === parsed.protocol && '443' === parsed.port ) || ( 'http:' === parsed.protocol && '80' === parsed.port ) ) {
				port = '';
			}

			const host = parsed.hostname.toLowerCase() + ( port ? ':' + port : '' );
			const pathname = parsed.pathname.replace( /\/+$/, '' ) || '/';

			return `${ parsed.protocol.toLowerCase() }//${ host }${ pathname }`;
		} catch ( error ) {
			return '';
		}
	}

	function urlsMatchCurrentSite( candidateUrl ) {
		return normalizeUrlForComparison( candidateUrl ) === normalizeUrlForComparison( cfg.siteUrl );
	}

	function openNonUrlConfirmationModal( selectedOption ) {
		const optionTitle = selectedOption ? ( selectedOption.text || select.value ) : select.value;
		const optionDescription = selectedOption && selectedOption.dataset ? ( selectedOption.dataset.description || '' ) : '';
		const actionPrompt = optionDescription || optionTitle;
		const expectedUrl = cfg.siteUrl || '';
		const canUseWPModal = window.wp && window.wp.element && window.wp.components && typeof window.wp.element.render === 'function';

		if ( ! canUseWPModal ) {
			const enteredUrl = window.prompt(
				`${ cfg.confirmationPrompt }\n\n${ expectedUrl }\n\n${ actionPrompt }`
			);

			if ( null === enteredUrl ) {
				return Promise.resolve( false );
			}

			if ( urlsMatchCurrentSite( enteredUrl ) ) {
				return Promise.resolve( true );
			}

			setResultNotice( cfg.confirmationMismatchMessage || 'The entered URL does not match this site URL.', false );
			return Promise.resolve( false );
		}

		return new Promise( ( resolve ) => {
			const modalRoot = document.createElement( 'div' );
			document.body.appendChild( modalRoot );

			const { createElement, useState } = window.wp.element;
			const { Modal, TextControl, Button, Notice } = window.wp.components;

			function closeModal( isConfirmed ) {
				window.wp.element.render( null, modalRoot );
				modalRoot.remove();
				resolve( isConfirmed );
			}

			function ConfirmationModal() {
				const [ enteredUrl, setEnteredUrl ] = useState( '' );
				const [ hasMismatch, setHasMismatch ] = useState( false );

				function onConfirm() {
					if ( urlsMatchCurrentSite( enteredUrl ) ) {
						closeModal( true );
						return;
					}

					setHasMismatch( true );
				}

				function onUrlChange( value ) {
					setEnteredUrl( value );
					if ( hasMismatch ) {
						setHasMismatch( false );
					}
				}

				return createElement(
					Modal,
					{
						title: cfg.confirmationTitle || 'Confirm cache purge',
						className: 'vip-cache-manager-confirmation-modal',
						onRequestClose: () => closeModal( false ),
						shouldCloseOnClickOutside: false,
					},
					createElement( 'p', null, actionPrompt ),
					createElement( 'p', null, '⚠️ This operation may result in temporary performance degradation.' ),
					createElement( 'p', null, cfg.confirmationPrompt || 'Type the current site URL to confirm this cache purge action.' ),
					createElement( 'p', null, createElement( 'strong', null, expectedUrl ) ),
					createElement( TextControl, {
						label: cfg.confirmationInputLabel || 'Site URL',
						value: enteredUrl,
						onChange: onUrlChange,
						placeholder: expectedUrl,
					} ),
					hasMismatch && createElement( Notice, { status: 'error', isDismissible: false }, cfg.confirmationMismatchMessage || 'The entered URL does not match this site URL.' ),
					createElement(
						'div',
						{ className: 'vip-cache-manager-confirmation-modal__actions' },
						createElement(
							Button,
							{
								variant: 'secondary',
								onClick: () => closeModal( false ),
							},
							cfg.confirmationCancelLabel || 'Cancel'
						),
						createElement(
							Button,
							{
								variant: 'primary',
								onClick: onConfirm,
							},
							cfg.confirmationSubmitLabel || 'Confirm purge'
						)
					)
				);
			}

			window.wp.element.render( createElement( ConfirmationModal ), modalRoot );
		} );
	}

	function updateUrlVisibility() {
		const isUrl = select.value === cfg.urlKey;
		urlWrap.style.display = isUrl ? 'block' : 'none';
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
			const confirmed = await openNonUrlConfirmationModal( selectedOption );
			if ( ! confirmed ) {
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

			setResultNotice( message, isSuccess );
		} catch ( e ) {
			// Network or unexpected error.
			setResultNotice( 'Request failed.', false );
		} finally {
			submit.disabled = false;
		}
	} );
}() );
