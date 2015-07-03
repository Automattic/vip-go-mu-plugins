jQuery(function($){

	/**
	 * Socialflow compose form initializer
	 */
	$.init_compose_form = function( is_ajax ) {

		// This is from ajax call or not ?
		is_ajax = is_ajax || false

		// Collect all necessary objects

		/* =Statistics table
		----------------------------------------------- */

		var stat_toggler = $( '#js-sf-toggle-statistics' ),
			extanded_stat = $( '#sf-statistics' ),
			update_button = $( '.sf-js-update-multiple-messages' );

		stat_toggler.on( 'click', function() {
			extanded_stat.toggle()
		})

		update_button.on( 'click', function( e ) {
			e.preventDefault();
			// update message 
			update_all_messages( $(this) );
		})

		/* =Compose form
		----------------------------------------------- */

		// Autofill button click
		$('#sf_autofill').on( 'click', function(e){
			e.preventDefault()

			// find all autofill fields and fill them automatically
			$( '#socialflow-compose .autofill' ).each( function(){
				var input = $(this),
					text = '';

				// Retrieve content either from tinyMCE or from passed selector
				if ( 'editor' == input.attr( 'data-content-selector' ) ) {
					text = (is_ajax) ? sf_post[ input.attr( 'data-content-selector' ) ] : tinyMCE.activeEditor.getContent()
				} else {
					text = (is_ajax) ? sf_post[ input.attr( 'data-content-selector' ) ] : $( input.attr( 'data-content-selector' ) ).val()
				}

				// Remove html tags, shortcodes, html special chars and whitespaces from the beginning and end of text
				text = text.replace(/<(?:.|\n)*?>/gm, '').replace( /\[(?:.|\n)*?\]/gm, '' )
				text = text.replace( '&nbsp;', '' )
				text = $.trim( text )

				input.val( text ).trigger( 'change' );
			});

			// update attachements
			$('#sf-update-attachments').trigger( 'click' )
		})

		// Compose Tabs
		$('#sf-compose-tabs').delegate('a', 'click', function(e) {
			var link = $(this)
				//panel

			e.preventDefault()

			// Don't do anything if the click is for the tab already showing.
			if ( link.is('.active a') )
				return false

			// Links
			$('#sf-compose-tabs .active').removeClass('active')
			link.parent('li').addClass('active')

			panel = $( link.attr('href') )

			// Panels
			$('.sf-tabs-panel').not( panel ).removeClass('active').hide()
			panel.addClass('active').show()
		})
		$('#sf-compose-tabs li:first-child a').click()


		$('textarea.socialflow-message-twitter').maxlength({
			'maxCharacters' : 117,
			'events' : [ 'change' ]
		})

		/* =Advanced block
		----------------------------------------------- */

		// Enable advanced tab toggler
		var advanced = $('#sf-advanced-content');

		// Hide advanced block on load
		advanced.hide();
		$('#sf-advanced-toggler').click(function(e){
			e.preventDefault();
			advanced.toggle();
		});

		// Show/hide range picker
		var optimizePeriodSelect = $('.optimize-period');

		optimizePeriodSelect.on('change', function(){
			var self = $(this);

			if ( self.val() == 'range' ) {
				self.parent().find('.optimize-range').show();
			} else {
				self.parent().find('.optimize-range').hide();
			}
		});


		// Activate timepicker
		$('.datetimepicker').each(function(){
			var field = $(this);

			var now = new Date(),
				user_time = new Date(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate(),  now.getUTCHours(), now.getUTCMinutes()+1, now.getUTCSeconds(), now.getUTCMilliseconds()+( field.attr( 'data-tz-offset' ) * 1000 ) );

			field.datetimepicker({
				dateFormat: 'dd-mm-yy',
				ampm: true,
				minDate: user_time
			});
		});

		// enable toggle buttons
		// To-Do toggle hidden input variable
		$('.must_send').click(function(){
			var attr = $(this).attr('data-toggle_html'),
				html = $(this).html()

			// toggle text
			$(this).html(attr).attr('data-toggle_html', html)
			// toggle input value
			$(this).parent().find('input.must_send').val( 1 )
		})

		// Toggle options for 
		$('.socialflow-user-advanced').each(function(){
			var field = $(this),
				select = field.find('select'),
				optimize = field.find('.optimize'),
				schedule = field.find('.schedule')

			// toggle additional options
			toggle_pub_type(select, optimize, schedule)
			select.change(function(){
				toggle_pub_type(select, optimize, schedule)
			})
		})

		function toggle_pub_type(select, optimize, schedule) {
			optimize.hide()
			schedule.hide()
			if ( 'schedule' == select.val() )
				schedule.show()
			if ( 'optimize' == select.val() )
				optimize.show()
		}

		// Thumbnail slider
		function init_slides() {
			var slides = $('#sf-attachment-slider .slide'),
				slide_input = $('#sf-current-attachment'),
				start  = slides.filter(':has( img[src="'+ slide_input.val() +'"] )').index() || 0

			if ( slides.length > 0 ) {
				slides.bind('slide', function(){
					slide_input.val( $(this).find('img').attr('src') )
				})

				$.featureList( 
					slides,
					{ start_item : start, nav_next : '#sf-attachment-slider-next', nav_prev : '#sf-attachment-slider-prev' } 
				)
			}
		}
		init_slides()

		// update image attachments list via ajax
		$('#sf-update-attachments').click(function(e){
			e.preventDefault()

			var id = $('#sf-post-id').val(),
				content = is_ajax ? sf_post['editor'] : tinyMCE.activeEditor.getContent()

			// Send ajax request for attachments
			$.ajax({
				url: ajaxurl,
				type: 'post',
				dataType: 'html',
				data: { action: 'sf_attachments', ID: id, content:content },
				success: function(slides){
					$('#sf-attachment-slider').html(slides)
					init_slides()
				}
			})


		})
		
		// bind form submit whe ajax call
		if ( is_ajax ) {
			
			$( '#sf-compose-form' ).on( 'submit', function(e){
				e.preventDefault()
				var form = $(this),
					loader = form.find( 'img.sf-loader' )

				// collect data from form and send as ajax message

				// Send ajax request for attachments
				$.ajax({
					url: ajaxurl,
					type: 'post',
					dataType: 'json',
					data: form.serialize(),
					beforeSend: function(slides){
						loader.show()
					}
				}).success( function( data ) {
					// update messges block
					form.find( '.socialflow-messages' ).remove();

					// Update statistics
					$('#socialflow-compose').find('.full-stats-container').html( data.messages );

					// Update ajax messages block
					$( '#ajax-messages' ).html( data.ajax_messages );

					stat_toggler = $( '#js-sf-toggle-statistics' );
					extanded_stat = $( '#sf-statistics' );
					update_button = $( '.sf-js-update-multiple-messages' );

					stat_toggler.on( 'click', function(){
						extanded_stat.toggle()
					})

					update_button.on( 'click', function( e ) {
						e.preventDefault()
						// update message 
						update_all_messages( $(this) )
					})

				}).done( function( data ){
					loader.hide()
				})
				
			})
			
		}
		
	}

	if ( typeof optionsSF !== 'undefined' ) {


		// Initialize compose form for single post edit page
		if ( optionsSF.hasOwnProperty('initForm') && optionsSF.initForm ) {
			$.init_compose_form( false )
		}

		// Activate stat toggler for post list pages
		if ( typeof pagenow != 'undefined' && pagenow.indexOf('edit') === 0 && typeof typenow != 'undefined' && optionsSF.postType.indexOf( typenow ) > -1 ) {
			$( '.js-sf-extended-stat-toggler' ).on( 'click', function(e){
				$(this).parent().toggleClass( 'show-stat' );
			})
		}
	}

	


	
	// Multiple update for the list of pages
	$('table.wp-list-table .sf-js-update-multiple-messages').on( 'click', function( e ){
		e.preventDefault();
		update_all_messages( $(this) );
	});


	// toggle disconnect link
	$('#toggle-disconnect').click(function(e){
		e.preventDefault()
		$('#disconnect-link').toggle()
	})

	/**
	 * Update all messages
	 * Update button must be inside .sf-statistics container
	 *
	 * @param button jQuery DOM object
	 */
	function update_all_messages( button ) {
		var container = button.parents( '.sf-statistics' );

		// Do Nothing if we are not inside container
		if ( !container.get(0) ) {
			return;
		}

		// Get all messages inside container and fire update function
		container.find( '.message' ).each(function(){
			update_message_status( $(this) );
		})
	}

	/**
	 * Update single message status
	 * Dom Message container is passed
	 * This message container has all necessary message attributes
	 */
	function update_message_status( message_cont ) {
		var message_id = message_cont.attr( 'data-id' ),
			account_id = message_cont.attr( 'data-account-id' ),
			date = message_cont.attr( 'data-date' ),
			post_id = message_cont.attr('data-post_id'),

			status_cont = message_cont.find( '.status' ),
			msg_loader = message_cont.find( '.sf-message-loader' );

		// perform ajax call 
		$.ajax({
			url: ajaxurl,
			type: 'get',
			dataType: 'html',
			data: { action: 'sf-get-message', id: message_id, post_id:post_id, date:date, account_id:account_id },
			beforeSend: function(){
				msg_loader.show()
			}
		}).success(function( data ){
			// update status container html
			status_cont.html( data )

		}).done(function( data ){
			msg_loader.hide()
		})
	}

	if ( pagenow.indexOf('socialflow') > -1 ) {

		// Activate timepicker
		$('.datetimepicker').each(function(){
			var field = $(this);

			var now = new Date(),
				user_time = new Date(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate(),  now.getUTCHours(), now.getUTCMinutes()+1, now.getUTCSeconds(), now.getUTCMilliseconds()+( field.attr( 'data-tz-offset' ) * 1000 ) );

			field.datetimepicker({
				dateFormat: 'dd-mm-yy',
				ampm: true,
				minDate: user_time
			});
		});

		// publish options toggler
		// Enable togglers
		$('#js-publish-options').map(function(){
			var publishOptionsSelect = $(this),
				optimizeOptions = $('#js-optimize-options'),
				optimizeSelect = $('#js-optimize-period'),
				optimizeRange = $('#js-optimize-range');

			publishOptionsSelect.on('change', function(){
				if ('optimize' == publishOptionsSelect.val()) {
					optimizeOptions.show();
				} else {
					optimizeOptions.hide();
				}
			});

			optimizeSelect.on('change', function(){
				if ('range' == optimizeSelect.val()) {
					optimizeRange.show();
				} else {
					optimizeRange.hide();
				}
			});
		});
	}

});// jQuery shell function
