jQuery( function ( $ ) {
	if ( 'formategory_template' == pagenow ) {
		$( '#visibility' ).hide();
		$( '#message.updated a' ).hide();
		
		$( '.formategory-placeholder' ).on( 'click', function ( e ) {
			e.preventDefault();
			
			var newContent = '{{ ' + $( this ).data( 'placeholder' ) + ' }}';
			
			var editor = tinyMCE.get( 'content' );
			
			// Insert the placeholder at the cursor.
			if ( ! editor || editor.isHidden() ) {
				var textarea = $( '#content' ).get(0);
				
				if ( document.selection ) {
					textarea.focus();
					sel = document.selection.createRange();
					sel.text = newContent;
					textarea.focus();
				}
				else if ( textarea.selectionStart || textarea.selectionStart == '0' ) {
					var startPos = textarea.selectionStart;
					var endPos = textarea.selectionEnd;
					var scrollTop = textarea.scrollTop;
					textarea.value = textarea.value.substring( 0, startPos ) + newContent + textarea.value.substring( endPos, textarea.value.length );
					textarea.focus();
					textarea.selectionStart = startPos + newContent.length;
					textarea.selectionEnd = startPos + newContent.length;
					textarea.scrollTop = scrollTop;
				}
				else {
					textarea.value += newContent;
					textarea.focus();
				}
			}
			else {
				var initialContent = editor.getContent();
				
				editor.selection.setContent( newContent );
				
				if ( editor.getContent() == initialContent ) {
					editor.setContent( editor.getContent() + newContent );
				}
			}
		} );
	}
} );