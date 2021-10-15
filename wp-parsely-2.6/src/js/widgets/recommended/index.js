/**
 * External dependencies
 */
import domReady from '@wordpress/dom-ready';

/**
 * Internal dependencies
 */
import { getUuidFromVisitorCookie } from '../../lib/personalization';

function widgetLoad( outerDiv, {
	displayAuthor,
	displayDirection,
	apiUrl,
	imgDisplay,
	permalink,
	personalized,
	widgetId,
} ) {
	let fullUrl = apiUrl;
	const uuid = personalized ? getUuidFromVisitorCookie() : undefined;

	if ( uuid ) {
		fullUrl += `&uuid=${ encodeURIComponent( uuid ) }`;
	} else {
		fullUrl += `&url=${ encodeURIComponent( permalink ) }`;
	}

	if ( imgDisplay !== 'none' ) {
		outerDiv.classList.add( 'display-thumbnail' );
	}

	if ( displayDirection ) {
		outerDiv.classList.add( 'list-' + displayDirection );
	}

	const outerList = document.createElement( 'ul' );
	outerList.className = 'parsely-recommended-widget';
	outerDiv.appendChild( outerList );

	fetch( fullUrl )
		.then( ( response ) => response.json() )
		.then( ( data ) => {
			for ( const [ key, value ] of Object.entries( data.data ) ) {
				const widgetEntry = document.createElement( 'li' );
				widgetEntry.className = 'parsely-recommended-widget-entry';
				widgetEntry.setAttribute( 'id', 'parsely-recommended-widget-item' + key );

				const textDiv = document.createElement( 'div' );
				textDiv.className = 'parsely-text-wrapper';

				const thumbnailImg = document.createElement( 'img' );
				if ( imgDisplay === 'parsely_thumb' ) {
					thumbnailImg.setAttribute( 'src', value.thumb_url_medium );
				} else if ( imgDisplay === 'original' ) {
					thumbnailImg.setAttribute( 'src', value.image_url );
				}
				widgetEntry.appendChild( thumbnailImg );

				const itmId = `?itm_campaign=${ widgetId }`;
				const itmMedium = '&itmMedium=site_widget';
				const itmSource = '&itmSource=parsely_recommended_widget';
				const itmContent = '&itm_content=widget_item-' + key;
				const itmLink = value.url + itmId + itmMedium + itmSource + itmContent;

				const postTitle = document.createElement( 'div' );
				postTitle.className = 'parsely-recommended-widget-title';

				const postLink = document.createElement( 'a' );
				postLink.setAttribute( 'href', itmLink );
				postLink.textContent = value.title;

				postTitle.appendChild( postLink );
				textDiv.appendChild( postTitle );

				if ( displayAuthor ) {
					const authorLink = document.createElement( 'div' );
					authorLink.className = 'parsely-recommended-widget-author';
					authorLink.textContent = value.author;

					textDiv.appendChild( authorLink );
				}

				widgetEntry.appendChild( textDiv );
				outerList.appendChild( widgetEntry );
			}

			outerDiv.appendChild( outerList );
			outerDiv.parentElement.classList.remove( 'parsely-recommended-widget-hidden' );
		} );
}

domReady( () => {
	const widgets = document.querySelectorAll( '.parsely-recommended-widget' );

	widgets.forEach( ( widget ) => {
		widgetLoad( widget, {
			displayAuthor: widget.getAttribute( 'data-parsely-widget-display-author' ) === 'true',
			displayDirection: widget.getAttribute( 'data-parsely-widget-display-direction' ),
			apiUrl: widget.getAttribute( 'data-parsely-widget-api-url' ),
			imgDisplay: widget.getAttribute( 'data-parsely-widget-img-display' ),
			permalink: widget.getAttribute( 'data-parsely-widget-permalink' ),
			personalized: widget.getAttribute( 'data-parsely-widget-personalized' ) === 'true',
			widgetId: widget.getAttribute( 'data-parsely-widget-id' ),
		} );
	} );
} );
