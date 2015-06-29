jQuery( function($) {
	var t = AJAXCommentPreview;
	$.extend( t, {
		/**
		 * Returns DOM Nodes built from string input interpreted as HTML.
		 * May return element(s) or a text node.
		 */
		stringToDOM: function( string ) {
		 	// This function just uses jQuery( string ).  Will not work on some sites served as XML in some browsers.
			return $( '<div>' + string + '</div>' ).get(0).childNodes;
		},
		data: {}, // Holds about-to-be-POSTed data
		oldData: {}, // Holds serialized string of last POSTed data
		button: $('#acp-preview'),
		// The output div
		preview: $('#ajax-comment-preview').ajaxError( function(event, request, settings) {
			$(this).html( t.error ); // If error, display message
		} ),
		send: function() { // sends the AJAX request
			if ( !t.button.size() || !t.preview.size() ) {
				return false; // Guess we didn't load properly
			}
			t.oldData = $.param( t.data ); // Store old data to compare against during the next AJAX request
			t.data = {};
			$.each( t.button.parents('form:first').serializeArray(), function(i, input) { // Build POST data object
				t.data[input.name] = input.value;
			} );
			if ( !t.data.comment || t.oldData == $.param( t.data ) ) {
				return false; // Blank || Last AJAX request was the same, so bail on this one.
			}
			jQuery.post( t.url, t.data, function( response ) { // POST the request
				try {
					try {
						var content = response.firstChild.childNodes[0].nodeValue;
					} catch(e) {
						var content = $( response ).text();
					}
					t.preview.show().html( t.stringToDOM( content ) || t.error ); // display the response
				} catch(e) {
					t.preview.show().html( t.error ); // display an error message
				}
			}, 'xml' );
		}
	} );
	if ( !t.preview.size() ) { return; } // Don't do anything else if the output div is missing
	try { // Test to see if we can easily add content to the web page (HTML and XML most of the time) or not (some XML)
		// some browsers/content-type combinations can't use jQuer( string ) or don't process the NCRs correctly
		if ( !t.stringToDOM( '<a href="?foo&#38;bob">test&#160;</a>' )[0].href.match( '&b' ) ) { // Con we do it the easy way?
			throw 'broken';
		}
	} catch(e) { // No.
		// Overwrite with XML DOM based function.  Only works if string is valid XML.
		t.stringToDOM = function( string ) {
			// We're on an XML site, and the easy way won't work.  Parse the string as XML and return the nodes.
			// Assume XHTML since we're almost assuredly serving as XML
			string = '<acp xmlns="http://www.w3.org/1999/xhtml">' + string + '</acp>'; // not an XHTML tag, but it doesn't seem to matter
			try { // Catch all errors and return false
				if ( 'undefined' == typeof DOMParser ) {
					xmlDoc = new ActiveXObject( 'Microsoft.XMLDOM' );
					xmlDoc.async = false;
					xmlDoc.loadXML( string );
				} else { // Firefox, Mozilla, Opera, etc.
					xmlDoc = new DOMParser().parseFromString( string, "application/xhtml+xml" );
				}
				var outerXML = xmlDoc.getElementsByTagName( 'acp' )[0];
				if ( 'undefined' != typeof document.importNode ) {
					outerXML = document.importNode( outerXML, true );
				}
				return $( outerXML.childNodes );
			} catch(e) {
				return false;
			}
		};
	}
	t.loading = t.stringToDOM( t.loading ) || '';
	t.error = t.stringToDOM( t.error ) || '';
	if ( '' == t.emptyString ) {
		t.preview.hide();
	} else {
		t.preview.html( t.stringToDOM( t.emptyString ) || '' );
	}
	t.button.click( t.send ); // Click me!
} );
