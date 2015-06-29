(function($){
	$(document).ready(function(){
		var $win = window;
		var $history= $win.history;
		var gcl = {
			lock: false,
			load: function( page, newUrl ) {
				if ( gcl.lock )
					return false; // Prevent double-fires
				gcl.lock = true;
				page = typeof(page) != 'undefined' ? page : cpage;
				$.ajax({
					url: ajaxurl,
					data: {
						action: 'google-get-comments',
						postid: gpid,
						cpage: page
					},
					complete: function() {
						gcl.lock = false; // Regardess of success, release the lock
					},
					success: function( data ){
						$('#comments-loading').hide();
						$('#comments-loaded').html(data).slideDown();
						if ( typeof(newUrl) != 'undefined' ) {
							var state = { 'cpage' : page };
							if ( '' == newUrl )
								history.replaceState( state, $win.title, newUrl );
							else
								history.pushState( state, $win.title, newUrl );
						}
						var hrefParts = $win.location.toString().split("#");
						if ( typeof(hrefParts[1]) != 'undefined' ) {
							$('html, body').animate( { scrollTop: $('#' + hrefParts[1]).offset().top }, 200 );
						}
					}
				});
			},
			cpage: function( url ) {
				match = /([&?]|\/comment-page-)([0-9]+)/.exec( url );
				if ( match )
					return match[2];
				return false;
			},
			loading: function() {
				$('#comments-loaded').hide();
				$('#comments-loading').show().css('height', '500px');
				$('html, body').animate( { scrollTop: $('#comments-loading').offset().top }, 200 );
			}
		};
		gcl.load(cpage, '', true);
		if ( !!($history && $history.pushState) ) {
			$($win).bind('popstate', function(event) {
				var state = event.originalEvent.state;
				if ( state && typeof( state['cpage'] ) != 'undefined' ) {
					gcl.loading();
					gcl.load( state['cpage'] );
				}
			});
			$('body').on( 'click', 'a[href*=/comment-page-], a[href*=cpage], #comments .navigation a', function(event){
				var href = $(this).attr('href');
				if ( href.indexOf( $('link[rel=canonical]').attr('href') ) == 0 && href.indexOf('#respond') == -1 ) {
					var newPage = gcl.cpage( href );
					if ( newPage != cpage ) {
						event.preventDefault();
						gcl.loading();
						gcl.load( newPage, href );
						cpage = newPage;
					}
				}
			});
		}
	});
})(jQuery);