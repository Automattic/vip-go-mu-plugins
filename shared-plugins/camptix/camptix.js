/*
 * MDN Cookie Framework
 * https://developer.mozilla.org/en-US/docs/Web/API/document.cookie#A_little_framework.3A_a_complete_cookies_reader.2Fwriter_with_full_unicode_support
 * Updated: 2014-08-28
 */
var docCookies={getItem:function(e){return decodeURIComponent(document.cookie.replace(new RegExp("(?:(?:^|.*;)\\s*"+encodeURIComponent(e).replace(/[\-\.\+\*]/g,"\\$&")+"\\s*\\=\\s*([^;]*).*$)|^.*$"),"$1"))||null},setItem:function(e,o,n,t,c,r){if(!e||/^(?:expires|max\-age|path|domain|secure)$/i.test(e))return!1;var s="";if(n)switch(n.constructor){case Number:s=1/0===n?"; expires=Fri, 31 Dec 9999 23:59:59 GMT":"; max-age="+n;break;case String:s="; expires="+n;break;case Date:s="; expires="+n.toUTCString()}return document.cookie=encodeURIComponent(e)+"="+encodeURIComponent(o)+s+(c?"; domain="+c:"")+(t?"; path="+t:"")+(r?"; secure":""),!0},removeItem:function(e,o,n){return e&&this.hasItem(e)?(document.cookie=encodeURIComponent(e)+"=; expires=Thu, 01 Jan 1970 00:00:00 GMT"+(n?"; domain="+n:"")+(o?"; path="+o:""),!0):!1},hasItem:function(e){return new RegExp("(?:^|;\\s*)"+encodeURIComponent(e).replace(/[\-\.\+\*]/g,"\\$&")+"\\s*\\=").test(document.cookie)},keys:function(){for(var e=document.cookie.replace(/((?:^|\s*;)[^\=]+)(?=;|$)|^\s*|\s*(?:\=[^;]*)?(?:\1|$)/g,"").split(/\s*(?:\=[^;]*)?;\s*/),o=0;o<e.length;o++)e[o]=decodeURIComponent(e[o]);return e}};

/**
 * CampTix Javascript
 *
 * Hopefully runs during wp_footer.
 */
(function($){
	var tix = $( '#tix' );
	$( tix ).addClass( 'tix-js' );

	if ( $( tix ).hasClass( 'tix-has-dynamic-receipts' ) ) {
		refresh_receipt_emails = function() {
			var fields = $('.tix-field-email');
			var html = '';
			var previously_checked = $('[name="tix_receipt_email_js"]:checked').val();
			var checked = false;

			for ( var i = 0; i < fields.length; i++ ) {
				var value = fields[i].value;
				if ( value.length < 1 ) continue;

				var field = $('<div><label><input type="radio" name="tix_receipt_email_js" /> <span>container</span></label><br /></div>');
				$(field).find('span').text(value);
				$(field).find('input').attr('value', value);

				if ( previously_checked != undefined && previously_checked == value && ! checked )
					checked = $(field).find('input').attr('checked','checked');

				html += $(field).html();
			}

			if ( html.length < 1 )
				html = '<label>' + camptix_l10n.enterEmail + '</label>';

			if ( html == $('#tix-receipt-emails-list').html() )
				return;

			$('#tix-receipt-emails-list').html(html);

			previously_checked = $('[name="tix_receipt_email_js"]:checked').val();
			if ( previously_checked == undefined || previously_checked.length < 1 )
				$('#tix-receipt-emails-list input:first').attr('checked','checked');
		};

		$('.tix-field-email').change(refresh_receipt_emails);
		$('.tix-field-email').keyup(refresh_receipt_emails);
		$(document).ready(refresh_receipt_emails);
	}

	/**
	 * Automatically prepend http:// to URL fields if the user didn't.
	 *
	 * Some browsers will reject input like "example.org" as invalid because it's missing the protocol. This
	 * confuses users who don't realize that the protocol is required.
	 */
	tix.find( 'input[type=url]' ).on( 'blur', function( event ) {
		var url = $( this ).val();

		if ( '' == url ) {
			return;
		}

		if ( url.match( '^https?:\/\/.*' ) === null ) {
			$( this ).val( 'http://' + url );
		}
	} );

	// Get a cookie object
	function tixGetCookie( name ) {
		var cookie = docCookies.getItem( name );

		if ( null == cookie ) {
			cookie = {};
		} else {
			cookie = $.parseJSON( cookie );
		}

		return cookie;
	}

	// Count unique visitors to [tickets] page
	// TODO: Refactor to use wpCookies instead of MDN Cookie Framework
	$( document ).ready( function() {
		if ( ! tix.length ) {
			return;
		}

		var cookie  = tixGetCookie( 'camptix_client_stats' ),
			ajaxURL = camptix_l10n.ajaxURL;

		// Do nothing if we've already counted them
		if ( cookie.hasOwnProperty( 'visited_tickets_form' ) ) {
			return;
		}

		// If it's their first visit, bump the counter on the server and set the client cookie
		cookie.visited_tickets_form = true;

		if ( window.location.href.indexOf( 'tix_reservation_token' ) > -1 ) {
			ajaxURL += window.location.search;
		}

		$.post(
			ajaxURL,
			{
				action:  'camptix_client_stats',
				command: 'increment',
				stat:    'tickets_form_unique_visitors'
			},

			function( response ) {
				if ( true != response.success ) {
					return;
				}

				docCookies.setItem(
					'camptix_client_stats',
					JSON.stringify( cookie ),
					60 * 60 * 24 * 365
				);
			}
		);
	} );

	// Hide unknown attendee fields when reloading the page
	$( document ).ready( function() {
		tix.find( 'input.unknown-attendee' ).each( hide_input_rows_for_unknown_attendee );
	} );

	// Hide unknown attendee fields when checkbox is clicked
	tix.find( 'input.unknown-attendee' ).change( hide_input_rows_for_unknown_attendee );

	/**
	 * Hide the input fields for unknown attendees
	 */
	function hide_input_rows_for_unknown_attendee() {
		// Select core input rows. There aren't any question rows because those are removed by filter_unconfirmed_attendees_questions().
		var input_rows = $( this ).parents( 'table' ).find( 'tr.tix-row-first-name, tr.tix-row-last-name, tr.tix-row-email' );

		if ( this.checked ) {
			input_rows.each( function() {
				$( this ).addClass( 'tix-hidden' );
			} );
		} else {
			input_rows.each( function() {
				$( this ).removeClass( 'tix-hidden' );
			} );
		}
	}
}(jQuery));