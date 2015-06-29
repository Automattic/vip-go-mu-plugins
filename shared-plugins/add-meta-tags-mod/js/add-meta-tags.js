(function($) {

counter = {
	init : function() {
		var t = this,
		    $title = $('#mt_seo_title'),
		    $desc = $('#mt_seo_description');

		if ( $title.length ) {
			t.buildCounter( $title, 70, 'title' );

			$title.keyup( function() {
				t.updateTitle();
			});

			$title.on('change', function() {
				t.updateTitle();
			});
		}

		if ( $desc.length ) {
			t.buildCounter( $desc, 140, '<code>meta</code> description' );

			$desc.keyup( function() {
				t.updateDesc();
			});

			$desc.on('change', function() {
				t.updateDesc();
			});
		}
	},

	buildCounter : function( el, count, desc ) {
		var t = this,
		    $counter = $( "<div class='mt_counter' data-limit="+count+" />" );

		el.after( $counter );
		$counter.html( "The " + desc + " is limited to " + count + " characters in search engines. <span class='count'>"+count+"</span> characters remaining." );
		t.updateTitle();
		t.updateDesc();
	},

	updateTitle : function() {
		var t = this,
		    $title = $('#mt_seo_title');

		if ( ! $title.length )
			return;

		var count = $title.val().replace('%title%', originalTitle).length,
		    limit = $title.attr('data-limit') || 70,
		    originalTitle = $('#title').val();

		$title.siblings( '.mt_counter' ).find( '.count' ).replaceWith( t.updateCounter( count, limit ) );
		$("#mt_snippet .title").html( jQuery( '<p>' + $title.val().replace('%title%', originalTitle).substring(0, limit) + '</p>' ).text() );
	},

	updateDesc : function() {
		var t = this,
		    $desc = $('#mt_seo_description');

		if ( ! $desc.length )
			return;

		var count = $desc.val().length,
		    limit = $desc.attr('data-limit') || 140;

		$desc.siblings( '.mt_counter' ).find( '.count' ).replaceWith( t.updateCounter( count, limit ) );
		$('#mt_snippet .content').html( jQuery( '<p>' + $desc.val().substring(0, limit) + '</p>' ).text() );
	},

	updateCounter : function( count, limit ) {
		var $counter = $( '<span class="count" />' ),
		    left = limit - count;

		$counter.text( left );

		if( left > 0 )
			$counter.removeClass( 'negative' ).addClass( 'positive' );
		else
			$counter.removeClass( 'postive' ).addClass( 'negative' );

		return $('<b>').append( $counter ).html();
	}
};

$(document).ready(function(){counter.init();});
})(jQuery);
