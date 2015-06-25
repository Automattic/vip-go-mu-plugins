( function( $ ) {

	tinyMCE.PluginManager.add( 'findthebest', function( editor, url ) {
		var getAttribute = function( s, n ) {
			n = new RegExp( n + '=\"([^\"]+)\"', 'g' ).exec( s );
			return n ? tinyMCE.DOM.decode( n[ 1 ] ) : '';
		};

		var replaceShortcode = function( co ) {
			var replaceCallback = function( match, options ) {
				var title = getAttribute( options, 'title' );
				var id = getAttribute( options, 'id' );
				var width = getAttribute( options, 'width' );
				var height = getAttribute( options, 'height' );
				var style = {};

				if ( width.length > 0 && height.length > 0 ) {
					style = {width: width, height: height};
				}

				var img = $( '<img/>' )
					.addClass( 'ftb-tiny-mce-widget mceItem' )
					.attr( {
						'data-code': 'findthebest' + options,
						'data-id': id ,
						'data-title': title ,
						'data-mce-resize': 'false' ,
						'data-mce-placeholder': '1'
					} )
					.css( style );

				return img[0].outerHTML;
			};

			return co.replace( /\[findthebest([^\]]*)\]/g, replaceCallback );
		};

		var emplaceShortcode = function( co ) {
			var replaceCallback = function( match, options ) {
				var cls = getAttribute( options, 'class' );

				if ( -1 !== cls.indexOf( 'ftb-tiny-mce-widget' ) ) {
					return '[' + getAttribute( options, 'data-code' ) + ']<br /><br />';
				}

				return match;
			};

			return co.replace( /(<img[^>]+>)/g, replaceCallback );
		};

		editor.onBeforeSetContent.add( function( editor, o ) {
			o.content = replaceShortcode( o.content );
		} );

		editor.onExecCommand.add( function( editor, command ) {
			if ( 'mceInsertContent' === command ) {
				var content = tinyMCE.activeEditor.getContent();
				tinyMCE.activeEditor.setContent( replaceShortcode( content ) );
			}
		} );

		editor.onPostProcess.add( function( editor, o ) {
			if ( o.get ) {
				o.content = emplaceShortcode( o.content );
			}
		} );

	} );

} )( jQuery );