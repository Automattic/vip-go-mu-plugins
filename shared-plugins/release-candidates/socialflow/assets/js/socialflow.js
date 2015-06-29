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
					text = '',
					contentWrap = document.getElementById('wp-content-wrap');

				// Retrieve content either from tinyMCE or from passed selector
				if (is_ajax) {
					text = sf_post[ input.attr( 'data-content-selector' ) ];
					// console.log(sf_post);
				} else {
					if ( '#content' == input.attr( 'data-content-selector' ) && tinyMCE.activeEditor && contentWrap.className.indexOf('tmce-active') > -1 ) {
						text = tinyMCE.activeEditor.getContent();
					} else {
						text = $( input.attr( 'data-content-selector' ) ).val();
					}
				}

				text = cleanText(text);

				if ( typeof input.attr('value') == 'undefined' )
					input.html( text ).trigger( 'change' );
				else
					input.val( text ).trigger( 'change' );
			});

			// update attachements
			$('.sf-update-attachments').trigger( 'click' );
		});

		/**
		 * Remove html tags, shortcodes, html special chars and whitespaces from the beginning and end of text
		 * @param  {string} text Text to be cleand
		 * @return {string}      CLean text
		 */
		function cleanText(text) {
			text = text.replace(/<(?:.|\n)*?>/gm, '').replace( /\[(?:.|\n)*?\]/gm, '' );
			text = text.replace( '&nbsp;', '' );
			return $.trim( text );
		}

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
		$('#sf-compose-tabs li:first-child a').trigger('click');

		$('#sf_message_twitter').maxlength({
			'maxCharacters' : 117,
			'events' : [ 'change' ],
			'statusText' : 'characters left',
			'twitterText' : true
		});

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
			$('.sf-attachments').each(function() {
				var slider = $(this),
					slides = slider.find('.slide'),
					curSlideInput = slider.find('.sf-current-attachment'),
					startItem = slides.filter(':has( img[src="'+ curSlideInput.val() +'"] )').index();
				
				if ( slides.length < 1 )
					return;

				// Check if startItem exists
				startItem = (startItem < 0) ? 0 : startItem;

				$.featureList( 
					slides,
					{ 
						start_item : startItem, 
						nav_next : slider.find('.sf-attachment-slider-next'), 
						nav_prev : slider.find('.sf-attachment-slider-prev')
					} 
				);

				// Set initial slide as curSlideInput
				curSlideInput.val( slides.eq(startItem).find('img').attr('src') );
				

				slides.on('slide', function() {
					curSlideInput.val( $(this).find('img').attr('src') );
				});
			});
		}
		init_slides();

		$('body').on('click', '.sf-update-attachments', function (e) {
			e.preventDefault();
			var updater = $(this);

			var id = $('#sf-post-id').val(),
				content;

			// Get content either from tinyMce or textarea
			if ( is_ajax )
				content = sf_post['editor'];
			else
				content = ( document.getElementById('wp-content-wrap').className.indexOf('tmce-active') > -1 ) ? tinyMCE.activeEditor.getContent() : document.getElementById('content').value;

			$.ajax({
				url: ajaxurl,
				type: 'post',
				dataType: 'html',
				data: { action: 'sf_attachments', ID: id, content:content },
				success: function(slides){
					var container = updater.parents('.sf-attachments');
					container.find('.sf-attachment-slider').html(slides);
					container.find('.sf-current-attachment').val('');
					init_slides()
				}
			})
		});


		// Update images after featured image was set
		$(document).bind("ajaxComplete", function(event, xhr, settings){
			if ( !settings.hasOwnProperty('data') )
				return;

			// New thumbnail added 
			if ( settings.data.indexOf('action=set-post-thumbnail') > -1 && settings.data.indexOf('thumbnail_id=-1') === -1 ) {
				maybeUpdateCustomMedias(getParameterByName('thumbnail_id', settings.data));
			}

			// Posts Image updated
			if ( settings.data.indexOf('action=set-post-thumbnail') > -1 || settings.data.indexOf('action=send-attachment-to-editor') > -1 ) {
				$('.sf-update-attachments').trigger('click');
			}
		});


		function maybeUpdateCustomMedias(thumbnailId) {
			if ('' === thumbnailId)
				return;

			$('.sf-custom-image[value=""]').each(function (i, field) {

				// We are attached to the button if want to update custom image
				sf_attach_custom_image( $(field).parents('.sf-attachments').find('.js-attachments-set-custom-image'), thumbnailId );
			});

			// Attach media If not set
			if ( $('.sf-media-attachment .sf-image-container').has('img').length == 0 ) {
				sf_attach_media( $('.js-attachments-set-media'), thumbnailId );
			}
		}

		/**
		 * Get attribute name from serialized string
		 * @param  {string} name   Attribute name
		 * @param  {string} string String to parse
		 * @return {string}        Attribute value
		 */
		function getParameterByName(name, string) {
			var regex = new RegExp("[\\?&]" + name + "=([^&#]*)"),

			name = name.replace(/[\[]/, "\\[").replace(/[\]]/, "\\]");

			results = regex.exec(string);
			return results == null ? "" : decodeURIComponent(results[1].replace(/\+/g, " "));
		}

		// Media mode
		$('body').on('change', '.sf_media_compose', function(event) {
			event.preventDefault();

			if ( $(this).is(':checked') )
				$('#socialflow-compose').addClass('sf-compose-attachment');
			else
				$('#socialflow-compose').removeClass('sf-compose-attachment');

			// Trigger change to update chars left counter
			$('#sf_message_twitter').trigger('change');
		});


		// Update custom facebook text block
		$('body').on('blur', '#content', updateFbMediaDescription);
		$('body').on('blur', '#title', updateFbMediaTitle);


		$('body').on('wp-tinymce-loaded', initMCEFbMediaDescription);

		/**
		 * Fill in facebook title and description (uneditable) for media composition
		 * @todo cache dom elements to speed up
		 * @return {void}
		 */
		function updateFbMediaDescription (event) {
			var description;

			if ( event.hasOwnProperty('target') && 'TEXTAREA' == event.target.nodeName ) {
				description = $(this).val();
			} else {
				description = tinyMCE.activeEditor.getContent();
			}

			description = cleanText(description);

			$('.sf-media-facebook-description').html(description);
		}
		function updateFbMediaTitle () {
			$('.sf-media-facebook-title').html( $(this).val() );
		}

		function initMCEFbMediaDescription() {
			tinyMCE.activeEditor.on('blur', updateFbMediaDescription);
		}

		/**
		 * Toggle custom image and one of the attachments select
		 */
		$('body').on('click', '.js-toggle-custom-image', function(event) {
			var container = $(this).parents('.sf-attachments'),
				statusInput = container.find('.sf-is-custom-image'),
				className = 'sf-is-custom-attachment';

			event.preventDefault();

			if ( '1' === statusInput.val() ) {
				container.removeClass(className);
				statusInput.val('0');
			} else {
				container.addClass(className);
				statusInput.val('1');
			}
		});

		/**
		 * Set Custom image
		 * @return {void}
		 */
		$('body').on('click', '.js-attachments-set-custom-image', function(event) {
			var button = $(this);

			event.preventDefault();

			if ( button.data('frame') ) {
				button.data('frame').open();
				return;
			}

			// Create the media frame.
			button.data('frame', wp.media.frames.file_frame = wp.media({
				title: jQuery( this ).data( 'uploader_title' ),
				button: {
					text: jQuery( this ).data( 'uploader_button_text' ),
				},
				multiple: false  // Set to true to allow multiple files to be selected
			}));

			// When an image is selected, run a callback.
			button.data('frame').on( 'select', $.proxy(sf_attach_custom_image, button, button));

			// Finally, open the modal
			button.data('frame').open();
		});

		/**
		 * Set Media image
		 * @return {void}
		 */
		$('body').on('click', '.js-attachments-set-media', function(event) {
			var button = $(this);

			event.preventDefault();

			if ( button.data('frame') ) {
				button.data('frame').open();
				return;
			}

			// Create the media frame.
			button.data('frame', wp.media.frames.file_frame = wp.media({
				title: jQuery( this ).data( 'uploader_title' ),
				button: {
					text: jQuery( this ).data( 'uploader_button_text' ),
				},
				multiple: false  // Set to true to allow multiple files to be selected
			}));

			// When an image is selected, run a callback.
			button.data('frame').on( 'select', $.proxy(sf_attach_media, null, button));

			// Finally, open the modal
			button.data('frame').open();
		});

		function sf_attach_media(button, id) {
			var id = id || button.data('frame').state().get('selection').first().toJSON().id;

			$.ajax({
				url: ajaxurl,
				type: 'post',
				dataType: 'json',
				data: {
					action: 'sf_get_custom_message_image',
					attachment_id: id,
					attach_to_post: $('#sf_current_post_id').val()
				}
			}).success(function(data) {
				var container = button.parents('.sf-media-attachment');

				// Show attached image
				container.find('.sf-image-container').html('<img src="'+data.medium_thumbnail_url+'" alt="" />');
			});
		}

		/**
		 * Attach custom attachment to the group message
		 * @param  {Object} button     DOM button that called media library
		 * @param  {object} attachment Media Library attachment object
		 * @return {void}
		 */
		function sf_attach_custom_image(button, id) {
			id = id || button.data('frame').state().get('selection').first().toJSON().id;

			$.ajax({
				url: ajaxurl,
				type: 'post',
				dataType: 'json',
				data: {
					action: 'sf_get_custom_message_image',
					attachment_id: id
				}
			}).success(function(data) {
				var container = button.parents('.sf-attachments');

				// Update hidden value with src and filename, both necessary for future api call
				container.find('.sf-custom-image').val(data.medium_thumbnail_url);
				container.find('.sf-custom-image-filename').val(data.filename);

				// Show attached image
				container.find('.sf-attachments-custom .image-container').html('<img src="'+data.medium_thumbnail_url+'" alt="" />');
			});
		}

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

});