jQuery( function($) {
	$('#daylife-search').keypress( function( event ) {
		if ( 13 == event.which ) {
			event.preventDefault();
			$('#daylife-search-button').click();
		}
	});

	$('#daylife-gallery-search').keypress( function( event ) {
		if ( 13 == event.which ) {
			event.preventDefault();
			$('#daylife-search-button').click();
		}
	});


	$('#daylife-search-button').click( function() {
		var data = {
			action: 'daylife-image-search',
			nonce: $('#daylife-search-nonce-field').val(),
			keyword: $('#daylife-search').val(),
			sort: $('#daylife-search-by').val(),
			start_date: $('#daylife-start-date').val(),
			end_date: $('#daylife-end-date').val()
		};
		$('.daylife-response').html('<img src="images/wpspin_light.gif" />Loading');
		$('.daylife-response').show();

		$.post(ajaxurl, data, function(response) {
			daylifeUpdateImages(response);
		});

		return false;
	});

	$('#daylife-gallery-search-button').click( function() {
		var data = {
			action: 'daylife-gallery-search',
			nonce: $('#daylife-gallery-search-nonce-field').val(),
			keyword: $('#daylife-gallery-search').val()
		};
		$.post(ajaxurl, data, function(response) {
			daylifeUpdateGalleries(response);
		});

		return false;
	});

	function daylifeGetImage(response) {
		var data = {
			action: 'daylife-get-images',
			nonce: $('#daylife-gallery-search-nonce-field').val(),
			daylife_gallery_page: $(this).attr('href').substring(1)
		};
		$.post(ajaxurl, data, function(response) {
			daylifeUpdateGalleries(response);
		});
	}

	function daylifeGalleryLoadProgress(response) {

		var countEl = '.daylife-gallery-loader .count';
		var totalEl = '.daylife-gallery-loader .total';

		var count = $(countEl).html();
		var total = $(totalEl).html();
		count++;
		$(countEl).html(count);
	
		if ( count >= total ) {
			$('.daylife-gallery-loader').hide();
			$('.daylife-gallery-response button').show();
			$(countEl).html('0');
			$(totalEl).html('0');
			autosave();
		}
	}

	function daylifeGalleryPull(response) {

		var images = JSON.parse(response);
		length = images.length;
	
		$('.daylife-gallery-loader .total').html(length);
		$('.daylife-gallery-loader').show();
		
		for( i=0; i < length; i++ ) {
			var url = images[i].split('|');

			var data = {
				action: 'daylife-gallery-load-image',
				nonce: $('#daylife-gallery-add-nonce-field').val(),
				image_url: url[0],
				caption: url[1],
				gallery_total: length,
				post_id: $('#post_ID').val()
			}
			$.post(ajaxurl, data, function(response) {
				daylifeGalleryLoadProgress(response);
			})
		}
		var data = {
			action: 'daylife-gallery-shortcode',
			nonce: $('#daylife-gallery-add-nonce-field').val(),
			post_id: $('#post_ID').val()
		}
		$.post(ajaxurl, data, function(response) {
			send_to_editor(response);
		})
	}

	function daylifeUpdateGalleries(response) {
		$('.daylife-gallery-response').show();
		$('.daylife-gallery-response').html( response );

		$('.daylife-gallery-response button').click( function() {
			var button = $(this);
			button.hide();
			var img = $( $(this).siblings( 'img' )[0] );
			var data = {
				action: 'daylife-gallery-get-images',
				nonce: $('#daylife-gallery-add-nonce-field').val(),
				daylife_url: img.data( 'daylife_url' ),
				gallery_id: img.data( 'gallery-id' ),
				post_id: $('#post_ID').val()
			}
			$.post(ajaxurl, data, function(response) {
				daylifeGalleryPull(response);
			})
			return false;
		});


		$( '.tablenav .gallery a' ).bind( 'click.daylife-gallery-tablenav', function() {
			var data = {
				action: 'daylife-gallery-search',
				nonce: $('#daylife-gallery-search-nonce-field').val(),
				daylife_gallery_page: $(this).attr('href').substring(1)
			};
			$.post(ajaxurl, data, function(response) {
				daylifeUpdateGalleries(response);
			});
		});

	}


	$('#daylife-suggest-button').click( function() {
		 $('#daylife-search').val('');
		var data = {
			action: 'daylife-image-search',
			nonce: $('#daylife-search-nonce-field').val(),
			content: $('#content').val()
		};
		$.post(ajaxurl, data, function(response) {
			daylifeUpdateImages(response);
		});

		return false;
	});
	function daylifeUpdateImages(response) {
		$('.daylife-response').show();
		$('.daylife-response').html( response );
		$('.daylife-response button').click( function() {
			var button = $(this);
			button.hide();
			var img = $( $(this).siblings( 'img' )[0] );
			var data = {
				action: 'daylife-image-load',
				nonce: $('#daylife-add-nonce-field').val(),
				daylife_url: img.data( 'daylife_url' ),
				caption: img.data( 'caption' ),
				credit: img.data( 'credit' ),
				image_title: img.data( 'image_title' ),
				thumb_url: img.data( 'thumb_url' ),
				url: img.data( 'url' ),
				width: img.data( 'width' ),
				height: img.data( 'height' ),
				post_id: $('#post_ID').val()
			}
			$.post(ajaxurl, data, function(response) {
				send_to_editor(response);
				button.show();
			})
			return false;
		});
		$( '.tablenav a' ).bind( 'click.daylife-tablenav', function() {
			var data = {
				action: 'daylife-image-search',
				nonce: $('#daylife-search-nonce-field').val(),
				daylife_page: $(this).attr('href').substring(1)
			};
			if ( 0 == $('#daylife-search').val().length )
				data.content = $('#content').val();
			else
				data.keyword = $('#daylife-search').val();

			$('.daylife-response').html('<img src="/wp-includes/images/wpspin.gif" />Loading');

			$.post(ajaxurl, data, function(response) {
				daylifeUpdateImages(response);
			});
		});
	}

});
