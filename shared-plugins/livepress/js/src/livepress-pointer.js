/*global jQuery */
( function( window, $, undefined ) {
	var CORE = window.livepress_pointer;

	function Pointer( element ) {
		var SELF = this,
			$element = $( element );

		SELF.close = function () {
			$.post( CORE.ajaxurl, {
				pointer: 'livepress_pointer',
				action:  'dismiss-wp-pointer'
			} );
		};

		SELF.open = function() {
			$element.pointer( options ).pointer( 'open' );
		};

		var options = {
			'pointerClass': 'livepress_pointer',
			'content': CORE.content,
			'position': {
				'edge': 'top',
				'align': 'left'
			},
			'close': SELF.close
		};
	}

	$( window ).on( 'livepress.blogging-tools-loaded', function() {
		var pointer = CORE.pointer = new Pointer( '#blogging-tools-link-wrap' );
		pointer.open();
		// Close the pointer when live blogging tools opened (once)
		$( 'a#blogging-tools-link' ).one( 'click', function() {
			$( '.livepress_pointer a.close' ).trigger( 'click' );
		} );

	} );
}( this, jQuery ) );