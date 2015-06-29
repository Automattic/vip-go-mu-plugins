jQuery(function($) {
	jQuery.fn.reset = function () {
	  $(this).each (function() { this.reset(); });
	}

	$('input[type="radio"]').click( function() {
	$('#blimply_push, #blimply_push_send').removeAttr( 'disabled' );
	});

	$('#blimply-dashboard-widget').submit( function(e) {
		e.preventDefault();
		var $this = $(this);
		var val = $this.find( 'textarea' ).val();
		$.post( $this.attr('action'), $this.serialize(), function (data) {
			if ( 'ok' == data ) {
				$this.reset();
				var updated = $this.children( '.updated' );
				if ( updated.length == 0 )
					$this.prepend( '<div class="updated"><p>' + Blimply.push_sent + ': ' + val + '</p></div>' )
				else
					updated.hide().replaceWith( '<div class="updated"><p>' + Blimply.push_sent + ': ' + val + '</p></div>' ).show();

			} else {
				$this.prepend( '<div class="error"><p>' + Blimply.push_error + '</p></div>' );
			}
		}

			);
	});
	$('#blimply-dashboard-widget textarea').keyup( function( e ) {
		var $this = $(this);
		if ( Blimply.character_limit > 0 ) {
			var $limit = $('#blimply-dashboard-widget .limit');
			$limit.text( Blimply.character_limit - $this.val().length);
		}
	} );

	$('#urban_airship\\[blimply_quiet_time_from\\], #urban_airship\\[blimply_quiet_time_to\\]').timePicker();
});