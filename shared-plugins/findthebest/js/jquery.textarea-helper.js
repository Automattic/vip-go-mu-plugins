( function( $ ) {
	$.fn.caretPosition = function( selectionStart ) {
		if ( 'number' === typeof selectionStart ) {
			var input = this[ 0 ];

			if ( input.setSelectionRange ) {
				input.focus();
				input.setSelectionRange( selectionStart, selectionStart );
			} else if ( input.createTextRange ) {
				var textRange = input.createTextRange();
				textRange.collapse( true );
				textRange.moveEnd( 'character', selectionStart );
				textRange.moveStart( 'character', selectionStart );
				textRange.select();
			}

			return;
		}

		var el = this[ 0 ],
		start = 0,
		end = 0,
		normalizedValue,
		range,
		textInputRange,
		len,
		endRange;

		if ( 'number' === typeof el.selectionStart &&
					'number' === typeof el.selectionEnd ) {
			start = el.selectionStart;
			end = el.selectionEnd;
		} else {
			range = document.selection.createRange();

			if ( range && el === range.parentElement() ) {
				len = el.value.length;
				normalizedValue = el.value.replace( /\r\n/g, '\n' );

				// Create a working TextRange that lives only in the input
				textInputRange = el.createTextRange();
				textInputRange.moveToBookmark( range.getBookmark() );

				// Check if the start and end of the selection are at the very end
				// of the input, since moveStart/moveEnd doesn't return what we want
				// in those cases
				endRange = el.createTextRange();
				endRange.collapse( false );

				if ( textInputRange.compareEndPoints( 'StartToEnd', endRange ) > -1 ) {
					start = end = len;
				} else {
					start = -textInputRange.moveStart( 'character', -len );
					start += normalizedValue.slice( 0, start ).split( '\n' ).length - 1;

					if ( textInputRange.compareEndPoints( 'EndToEnd', endRange ) > -1 ) {
						end = len;
					} else {
						end = -textInputRange.moveEnd( 'character', -len );
						end += normalizedValue.slice( 0, end ).split( '\n' ).length - 1;
					}
				}
			}
		}

		return {
			start: start,
			end: end
		};
	};
} )( jQuery );
