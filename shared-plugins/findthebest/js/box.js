( function( $, window, document ) {
	var latestBox;

	Box = function( options ) {
		var self = this,
		fixed = options.fixed,
		width = options.w || 'auto',
		height = options.h || 'auto',
		pad = options.pad || 0,
		top = options.top || 0,
		opacity = options.opacity || 60,
		bottom = ( undefined === options.bottom ) ? 30 : options.bottom,
		iframe = options.iframe,
		full = ! ! options.full,
		img = options.img,
		overflow = options.overflow || 'auto',
		noescape = options.noescape, // Disable escape key from closing popup
		animate = options.animate,
		/**
		 * With cacheKey set the pop is hidden instead of destroyed when closed.
		 * When the pop is again called with the same cacheKey it is restored
		 * to destroy the pop up either manualy remove #{cacheKey} from the DOM
		 * or remove the id attribute from the popup.
		 */
		cacheKey = options.cacheKey,
		mask = $( '<div />', {
			'class': 'mask-back',
			css: {
				opacity: opacity / 100,
				'z-index': 199999 + $( '.BOX' ).length
			}
		} ),
		popup;

		if ( full ) {
			fixed = true;
			width = height = '100%';
		}

		if ( cacheKey && $( '#' + cacheKey ).length ) {
			popup = $( '#' + cacheKey ).show();
			popup.FromCache = true;
		} else {
			var css = {
				width: 'auto' === width ? 'auto' : width + ( 2 * pad ),
				height: iframe ? height + bottom : height,
				background: options.bg || '#fff',
				position: fixed ? 'fixed' : 'absolute',
				top: top,
				padding: pad,
				overflow: overflow,
				'z-index': 200000 + $( '.BOX' ).length
			};

			popup = $( '<div />', {
				'class': 'BOX ',
				css: css
			} );

			if ( cacheKey ) {
				popup.attr( 'id', cacheKey );
			}

			if ( animate ){
				popup.css( {
					transform: 'scale3d(.9, .9, 1)',
					opacity: 0
				} );
			}
		}

		if ( full ) {
			popup.css( 'left', 0 );
		}

		self.recenter = function () {
			if ( full ) {
				return;
			}

			popup.css( 'margin-left', -( popup.width() ) / 2 - pad );

			if ( fixed ) {
				popup.css( {
					top: '50%',
					'margin-top': -popup.height() / 2
				} );
			} else if ( ! top ) {
				var windowHeight = $( window ).height(),
				offset = Math.max( windowHeight / 2 - popup.height() / 2, 10 );

				popup.css( {
					top: $( window ).scrollTop() + offset
				} );
			}
		};

		var esc = function ( e ) {
			if ( 27 === e.keyCode && ! noescape ) {
				close();
			}
		};

		var close = self.hide = function () {
			if ( options.onclose ) {
				options.onclose();
			}

			mask.remove();

			if ( cacheKey && popup.attr( 'id' ) ) {
				popup.hide();
			} else {
				popup.remove();
			}
			$( document ).off( 'keyup', esc );
		};

		self.size = function ( w, h, doRecenter ) {
			popup.width( width = w ).height( height = h );
			if ( doRecenter ) {
				self.recenter();
			}
		};

		iframe = iframe ? '<iframe width="' + width + '" height="' + height +
			'" frameborder="0" src="' + iframe + '"></iframe>' : '';
		img = img ? $( '<img style="vertical-align: top;" src="' + img + '"/>' ).
			load( function () {
			self.recenter();
			popup.show();
			width = ( 'auto' === width ) ? this.width : width;
			height = ( 'auto' === height ) ? this.height : height;
			$( this ).css( {
				width: width,
				height: height
			} );
		} ) : '';

		if ( popup.FromCache ) {
			popup.show();
			mask.click( close );
			popup.before( mask ).find( '.x' ).click( close );
		} else {
			popup.html( img || iframe || options.html );

			if ( ! options.hideX ) {
				popup.append( $( '<div class="x">' ).click( close ) );
			}

			if ( ! options.disableClose ) {
				mask.click( close );
			}

			$( 'body' ).append( mask ).append( popup );
		}

		if ( ! img || img[ 0 ].complete ) {
			self.recenter();
			popup.show();
		}

		if ( animate ){
			setTimeout( function() {
				popup.css( {
					transition: '.5s ease-out',
					transform: 'scale3d(1, 1, 1)',
					opacity: 1
				} );
			} );
		}

		if ( options.onopen ) {
			options.onopen();
		}

		$( document ).on( 'keyup', esc );
	};

	BOX = {
		show: function ( options ) {
			latestBox = new Box( options );
		},
		hide: function () {
			if (latestBox) {
				latestBox.hide();
			}
		},
		size: function ( w, h, doRecenter ) {
			if (latestBox) {
				latestBox.size( w, h, doRecenter );
			}
		}
	};
} )( jQuery, window, document );
