// enhance company list with JavaScript
// requires jQuery
var angellist = {
	// Plugin / JS version
	version: '1.2',

	// track if page is visible. ignores prefetch or preview
	page_visible: false,

	// track if container is visible in the viewport
	container_visible: false,

	// store the offset of the container
	container_offset_top: 0,

	// test if current browsing context supports Web Visibility API
	// tap into visibility events if supported, else assume visible
	visibility_init: function() {
		// check for standard and vendor prefix Visibility APIs
		var hidden = null;
		var event = null;
		if ( document.hidden !== undefined ) { // W3C draft standard
			hidden = "hidden";
			event = "visibilitychange";
		} else if ( document.webkitHidden !== undefined ) {
			hidden = "webkitHidden";
			event = "webkitvisibilitychange";
		} else if ( document.msHidden !== undefined ) {
			hidden = "msHidden";
			event = "msvisibilitychange";
		}

		// store the position of the container on the page
		var container_offset = angellist.container.offset();
		if ( container_offset.top > 0 ) {
			angellist.container_offset_top = container_offset.top;
		}
		container_offset = null;

		if ( hidden === null || document[hidden] === false ) {
			angellist.page_visible = true;
			if ( angellist.viewport_test() === false ) {
				// track if we enter viewport with each scroll
				jQuery( window ).scroll( angellist.viewport_test );
			} else {
				angellist.on_visible();
			}
		} else {
			jQuery( document ).bind( event, {hidden:hidden}, angellist.visiblity_change );
		}
	},

	// event handler checks for visibility change from hidden to non-hidden
	visibility_change: function( event ) {
		if ( angellist.page_visible === true ) {
			return;
		}
		if ( document[event.data.hidden] === false ) {
			angellist.page_visible = true;
			jQuery( document ).unbind( event );
			if ( angellist.viewport_test() === false ) {
				jQuery( window ).scroll( angellist.viewport_test );
			} else {
				angellist.on_visible();
			}
		}
	},

	// test if top of the container is at or above the last vertical pixel in the current window
	viewport_test: function() {
		if ( angellist.container_visible === true ) {
			return true;
		}
		var jwindow = jQuery(window);
		if ( ( jwindow.height() + jwindow.scrollTop() ) >= angellist.container_offset_top ) {
			jQuery( window ).unbind( "scroll", angellist.viewport_test );
			angellist.container_visible = true;
			angellist.on_visible();
			return true;
		}
		return false;
	},

	// actions once the container appears in viewport
	on_visible: function() {
		angellist.lazy_load_images();
	},

	// delay loading remote image assets to speed up the rest of the page
	lazy_load_images: function() {
		if ( angellist.container === undefined || angellist.container.length === 0 ) {
			return;
		}

		angellist.container.find( "noscript.img" ).each( function() {
			var noscript = jQuery(this);
			var html = jQuery( noscript.data( "html" ) );
			if ( html.length > 0 ) {
				noscript.replaceWith( html );
			}
			noscript=html=null;
		} );
	},

	// turn it on
	enable: function() {
		angellist.container = jQuery( "#angellist-companies" );
		if ( angellist.container.length === 0 ) {
			delete angellist.container;
			return;
		}
		angellist.visibility_init();
	}
};
jQuery(function() {
	angellist.enable();
} );