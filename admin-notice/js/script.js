function persistDismissedNotice(containerElement) {
	const dismissIdentifier = containerElement && containerElement.getAttribute(dismissal_data.data_attribute);
	if (dismissIdentifier) {
		const formData = new FormData();

		formData.append('_ajax_nonce', dismissal_data.nonce);
		formData.append('action', 'dismiss_vip_notice');
		formData.append(dismissal_data.identifier_key, dismissIdentifier);

		fetch(ajaxurl, {
			method: "POST",
			body: formData,
		});
	}
}

function vipNoticeClickHandler( ev ) {
	const button = ev.target.closest( '.notice-dismiss' );
	if ( button ) {
		const noticeContainer = button.closest( `[${dismissal_data.data_attribute}]` );
		if (noticeContainer) {
			persistDismissedNotice(noticeContainer);
		}
	}
}

function registerDismissHooks() {
	document.querySelectorAll( '.vip-notice' ).forEach( notice => notice.addEventListener( 'click', vipNoticeClickHandler ) );
}

window.onload = (function (oldLoad) {
	return function () {
		oldLoad && oldLoad();
		registerDismissHooks();
	}
})(window.onload)
