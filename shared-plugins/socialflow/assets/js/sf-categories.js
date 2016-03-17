jQuery(function($){

	// SocialFlow categories admin page
	$( '#socialflow-categories' ).map(function(){
		
		// Cache js objects
		var form = $( '#sf-account-category-form' ),
			categories_container = $( '#accounts-categories-list' ),
			remove_connection = $( '.js-remove-connection' ),
			loading = $( '.sf-loader' )

		// Global variables
		var action = 'sf_term_account'

		// Bind form submit and send 
		form.on( 'submit', function(e){
			e.preventDefault()
			connect_term_account( form )
		})

		// Bind account remove
		remove_connection.on( 'click', function( e ){
			e.preventDefault()
			disconnect_term_account( $(this) )
		})

		/**
		 * Connect term and accoun
		 *
		 * @param form object
		 */
		function connect_term_account( form ) {

			// Find if container for selected category exists
			var sendData = form.serialize(),
				term_id = $( '#sf-term-id' ).val(),
				accounts_container = $( '#js-term-accounts-' + term_id )

			// Success callback
			var success_callback = function( data ) {
				// Insert new connection to appropriate term block
				if ( 1 == data.status ) {

					data.html = $( data.html )

					// Add event listners
					data.html.find( '.js-remove-connection' ).on( 'click', function(e) {
						e.preventDefault()
						disconnect_term_account( $(this) )
					})

					if ( accounts_container.get(0) ) {
						accounts_container.append( data.html )
					} else {
						categories_container.append( data.html )
					}
				}
			}

			// Maybe show warning message and do nothing if no fields are selected

			// Decide wether we need html for the term or just for a single account
			if ( accounts_container.get( 0 ) ) {
				sendData += '&render=account'
			} else {
				sendData += '&render=term'
			}

			// Add wp action to data object
			sendData += '&action=' + action
			sendData += '&method=connect'
			
			sendData += '&security=' + sf_categories.security
			
			action_term_account( sendData, success_callback )
		}

		/**
		 * Disconnect term and accoun
		 *
		 * @param int term id
		 * @param int account id
		 * @param string term taxonomy
		 */
		function disconnect_term_account( closure, term_id, taxonomy ) {

			var account = closure.parent(),
				term = ( 'undefined' == typeof( term_id ) ) ? account.parents( '.catetory-accounts' ) : false

			// collect data object
			var data = {
				term_id:    term_id || term.attr( 'data-term_id' ),
				taxonomy:   taxonomy || term.attr( 'data-taxonomy' ),
				account_id: account.attr( 'data-account_id' ),
				action:     action,
				method:     'disconnect',
				security:   sf_categories.security
			}

			// Succefull ajax callback
			var success_callback = function( data ) {
				if ( 1 == data.status ) {
					account.remove()
				}
			}

			action_term_account( data, success_callback )
		}

		/**
		 * Ajax for term account action
		 *
		 * @param data to send with request
		 * @param function js callback
		 */
		function action_term_account( sendData, success_callback ) {
			$.ajax({
				url: ajaxurl,
				type: 'get',
				dataType: 'json',
				data: sendData,
				beforeSend: function () {
					loading.show()
				}
			}).success( function( data ){
				success_callback( data )
			}).done( function( data ){
				loading.hide()
			})
			
		}

	}) // SocialFlow categories admin page

	// Post edit page
	$( '#sf-advanced-content' ).map(function() {

		// Check if we have any terms functionality at all
		if ( 'undefined' == typeof sf_terms_accounts ) {
			return
		}

		// cache category checkboxes and all acctounts checkboxes
		var term_checkboxes = $( '#categorychecklist input' ),
			user_checkboxes = $( '.js-sf-account-checkbox' )

		// Bind category change
		term_checkboxes.on( 'change', function(){

			// Check if we have any connected accounts for current taxonomy
			if ( 'undefined' == typeof( sf_terms_accounts['category'] ) ) {
				return
			}

			var active_terms = new Array(),
				active_accounts = new Array()

			// Collect all seleced categories
			term_checkboxes.filter( ':checked' ).each(function(){
				active_terms.push( $(this).val() )
			})

			// Return if no categories are selected
			if ( active_terms.length == 0 ) {
                return;
			}

			// Collect all accounts that need to be enabled
			for ( var i=0; i < active_terms.length; i++ ) {
				if ( 'undefined' != typeof( sf_terms_accounts['category'][ active_terms[ i ] ] ) ) {
					active_accounts = active_accounts.concat( sf_terms_accounts['category'][ active_terms[ i ] ] )
				}
			}

			// If there are any accounts maybe remove duplicates
			if ( active_accounts.length == 0 ) {
				return;
			}
			
			// Remove duplicates from array
			active_accounts = $.grep( active_accounts, function( v, k ){
			    return $.inArray( v ,active_accounts ) === k
			})

			// Loop throuth accounts checkboxes and activate them
			/*
			for ( var i=0; i < active_accounts.length; i++ ) {
				$( '#sf_send_' + active_accounts[ i ] ).attr( 'checked', 'checked' )
			}
			*/

			user_checkboxes.each(function( i, e ){
				var checkbox = $(this);

				if ( -1 != $.inArray( parseInt(checkbox.val()) ,active_accounts ) ) {
					checkbox.attr( 'checked', 'checked' );
				} else {
					checkbox.removeAttr( 'checked' );
				}
			})

		})
		
		
	}) // Post edit page

});