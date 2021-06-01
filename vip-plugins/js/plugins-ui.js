( function( $ ) {
	function init() {
		protectCodeActivatedPlugins();
	}

	function protectCodeActivatedPlugins() {
		var $row = $( '.vip-code-activated-plugin' ).closest( 'tr' );
		$row.addClass( 'active' ).removeClass( 'inactive' );
		$row.find( 'input[type="checkbox"]' ).hide();
	}



	$( function() {
		init();
	} );
}( jQuery ) );
