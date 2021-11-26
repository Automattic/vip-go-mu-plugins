import domReady from '@wordpress/dom-ready';
import { __, sprintf } from '@wordpress/i18n';

domReady( () => {
	const keyEl = document.querySelector( '#apikey' );
	const requiresRecrawlNotice = document.querySelectorAll(
		'.parsely-form-controls[data-requires-recrawl="true"] .help-text'
	);
	if ( ! ( keyEl && requiresRecrawlNotice.length ) ) {
		return;
	}

	const notice = sprintf(
		/* translators: %s: The API Key that will be used to request a recrawl */
		__(
			'<strong style="color:#d63638">Important:</strong> changing this value on a site currently tracked with Parse.ly will require reprocessing of your Parse.ly data. Once you have changed this value, please contact <a href="mailto:support@parsely.com?subject=Please reprocess %s">support@parsely.com</a>',
			'wp-parsely'
		),
		keyEl.value
	);

	[].forEach.call( requiresRecrawlNotice, function( node ) {
		const descWrapper = document.createElement( 'p' );
		descWrapper.className = 'description';
		descWrapper.innerHTML = notice;
		node.appendChild( descWrapper );
	} );
} );
