( function( $ ) {
	function init() {
		bindActions();
		protectCodeActivatedPlugins();
	}

	function protectCodeActivatedPlugins() {
		var $row = $( '.vip-code-activated-plugin' ).closest( 'tr' );
		$row.addClass( 'active' ).removeClass( 'inactive' );
		$row.find( 'input[type="checkbox"]' ).hide();
	}

	function bindActions() {
		$( '.plugin' ).hover(
			function() {
				var plugin = $( this );
				pluginHover( plugin );
			},
			function() {
				var plugin = $( this );
				pluginReset( plugin );
			}
		);
	}

	function pluginHover( plugin ) {
		plugin.addClass( 'hovered' ),
		plugin.find( '.fp-overlay' ).addClass( 'visible' );
	}

	function pluginReset( plugin ) {
		plugin.removeClass( 'hovered' ),
		plugin.find( '.fp-overlay' ).removeClass( 'visible' );
	}

	$( function() {
		init();
	} );
}( jQuery ) );
