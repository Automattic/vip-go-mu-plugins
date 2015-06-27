/* thePlatform Video Manager Wordpress Plugin
 Copyright (C) 2013-2014  thePlatform for Media Inc.
 
 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.
 
 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.
 
 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA. */

( function( jQuery ) {
	jQuery.extend( {
		/**
		 @function base64Encode Performs Base 64 Encoding on a string
		 @param {String} data - string to encode
		 @return {Number} encoded string
		 */
		base64Encode: function( data ) {
			var b64 = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";
			var o1, o2, o3, h1, h2, h3, h4, bits, i = 0,
					ac = 0,
					enc = "",
					tmp_arr = [ ];

			if ( !data ) {
				return data;
			}

			do {
				o1 = data.charCodeAt( i++ );
				o2 = data.charCodeAt( i++ );
				o3 = data.charCodeAt( i++ );

				bits = o1 << 16 | o2 << 8 | o3;

				h1 = bits >> 18 & 0x3f;
				h2 = bits >> 12 & 0x3f;
				h3 = bits >> 6 & 0x3f;
				h4 = bits & 0x3f;

				tmp_arr[ac++] = b64.charAt( h1 ) + b64.charAt( h2 ) + b64.charAt( h3 ) + b64.charAt( h4 );
			} while ( i < data.length );

			enc = tmp_arr.join( '' );

			var r = data.length % 3;

			return ( r ? enc.slice( 0, r - 3 ) : enc ) + '==='.slice( r || 3 );
		}
	} );
} )( jQuery );

/**
 * Validate Media data is valid before submitting upload/edit
 * @param  {Object} event click event
 * @return {boolean}       Did validation pass or not
 */
var validate_media = function( event ) {
	
	//TODO: Validate that file has been selected for upload but not edit
	var validation_error = false;

	jQuery( '.upload_field, .custom_field' ).each( function() {
		var $field = jQuery( this );
		var dataStructure = $field.data( 'structure' );
		var dataType = $field.data( 'type' );
		var value = jQuery( this ).val();
		var fieldError = false;
		// Detect HTML, this runs against all fields regardless of type/structure
		if ( value.match( /<(\w+)((?:\s+\w+(?:\s*=\s*(?:(?:"[^"]*")|(?:'[^']*')|[^>\s]+))?)*)\s*(\/?)>/ ) ) {
			validation_error = true;
		}
		// We're not requiring any fields at the moment,
		// so only test fields which have a value
		else if ( value.length > 0 ) {
			switch ( dataStructure ) {
				case 'Map':
					var values = value.indexOf( ',' ) ? value.split( ',' ) : [ value ];
					for ( var i = 0; i < values.length; i++ ) {
						// Use substring to break apart to avoid issues with values that use colons
						var index = values[i].indexOf( ':' );
						var key = values[i].substr( 0, index ).trim();
						var val = values[i].substr( index + 1 ).trim();
						if ( index === -1 || key.length == 0 || val.length === 0 || validateFormat( val, dataType ) ) {
							fieldError = true;
							break;
						}
					}
					break;
				case 'List':
					var values = value.indexOf( ',' ) ? value.split( ',' ) : [ value ];
					for ( var i = 0; i < values.length; i++ ) {
						if ( validateFormat( values[i].trim(), dataType ) ) {
							fieldError = true;
							break;
						}
					}
					break;
				case 'Single':
				default:
					if ( validateFormat( value, dataType ) ) {
						fieldError = true;
					}
					break;
			}
		}
		if ( fieldError ) {
			$field.parent().addClass('has-error');
			validation_error = fieldError;
		} else {
			$field.parent().removeClass('has-error');
		}
	} );

	var $titleField = jQuery( '#theplatform_upload_title' );
	if ( $titleField.val() === "" ) {
		validation_error = true;
		$titleField.parent().addClass('has-error');
	} else {
		$titleField.parent().removeClass('has-error');
	}

	return validation_error;
};

var validateFormat = function( value, dataType ) {
	var validation_error = false;

	switch ( dataType ) {
		case 'Integer':
			var intRegex = /^-?\d+$/;
			validation_error = !intRegex.test( value )
			break;
		case 'Decimal':
			var decRegex = /^-?(\d+)?(\.[\d]+)?$/;
			validation_error = !decRegex.test( value )
			break;
		case 'Boolean':
			var validValues = [ 'true', 'false', '' ];
			validation_error = validValues.indexOf( value ) < 0;
			break;
		case 'URI':
			var uriRegex = /^([a-z][a-z0-9+.-]*):(?:\/\/((?:(?=((?:[a-z0-9-._~!$&'()*+,;=:]|%[0-9A-F]{2})*))(\3)@)?(?=(\[[0-9A-F:.]{2,}\]|(?:[a-z0-9-._~!$&'()*+,;=]|%[0-9A-F]{2})*))\5(?::(?=(\d*))\6)?)(\/(?=((?:[a-z0-9-._~!$&'()*+,;=:@\/]|%[0-9A-F]{2})*))\8)?|(\/?(?!\/)(?=((?:[a-z0-9-._~!$&'()*+,;=:@\/]|%[0-9A-F]{2})*))\10)?)(?:\?(?=((?:[a-z0-9-._~!$&'()*+,;=:@\/?]|%[0-9A-F]{2})*))\11)?(?:#(?=((?:[a-z0-9-._~!$&'()*+,;=:@\/?]|%[0-9A-F]{2})*))\12)?$/i;
			validation_error = !uriRegex.test( value );
			break;
		case 'Time':
			var timeRegex = /^\d{1,2}:\d{2}$/;
			validation_error = !timeRegex.test( value );
			break;
		case 'Duration':
			var durationRegex = /^(\d+:)?([0-5]?[0-9]:)?([0-5]?[0-9])?$/;
			validation_error = !durationRegex.test( value );
			break;
		case 'DateTime':
			validation_error = !isValidDate( new Date( value ) );
			break;
		case 'Date':
			var dateRegex = /^(\d{4})-(([0][1-9])|([1][0-2]))-(([0][1-9])|([1][0-9])|([2][0-9])|([3][0-1]))$/;
			validation_error = !dateRegex.test( value );
			break;
		case 'Link':
			// @todo: this could do more, right now just checks that the structure is correct
			var linkRegex = /^(((title:)(.*),(\s+)?(href:).*)|((href:)(.*),(\s+)?(title:).*))$/;
			validation_error = !linkRegex.test( value );
			break;
		case 'String':
		default:
			// nothing to do
			break;
	}

	return validation_error;
};

var isValidDate = function( d ) {
	if ( Object.prototype.toString.call( d ) !== "[object Date]" )
		return false;
	return !isNaN( d.getTime() );
}

var parseMediaParams = function() {
	var params = { };
	jQuery( '.upload_field' ).each( function( i ) {
		if ( jQuery( this ).val().length != 0 )
			params[jQuery( this ).attr( 'name' )] = jQuery( this ).val();
	} );

	var categories = [ ]
	var categoryArray = jQuery( '.category_field' ).val();
	for ( i in categoryArray ) {
		var name = categoryArray[i];
		if ( name != '(None)' ) {
			var cat = { };
			cat['name'] = name;
			categories.push( cat );
		}
	}

	params['categories'] = categories;

	return params;
};

var parseCustomParams = function() {
	var custom_params = { };

	jQuery( '.custom_field' ).each( function( i ) {
		if ( jQuery( this ).val().length != 0 ) {
			var $field = jQuery( this );
			var dataStructure = $field.data( 'structure' );
			var dataType = $field.data( 'type' );
			var value = $field.val();

			// Convert maps to object
			if ( dataStructure == 'Map' ) {
				var values = value.indexOf( ',' ) ? value.split( ',' ) : [ value ];
				value = { };
				for ( var i = 0; i < values.length; i++ ) {
					// Use substring to break apart to avoid issues with values that use colons
					var index = values[i].indexOf( ':' );
					var key = values[i].substr( 0, index ).trim();
					var val = values[i].substr( index + 1 ).trim();
					value[key] = parseDataType( val, dataType );
				}
			}
			// Convert lists to array
			else if ( dataStructure == 'List' ) {
				var values = value.indexOf( ',' ) ? value.split( ',' ) : [ value ];
				value = [ ];
				for ( var i = 0; i < values.length; i++ ) {
					value.push( parseDataType( values[i].trim(), dataType ) );
				}
			}
			else {
				value = parseDataType( value, dataType );
			}

			custom_params[jQuery( this ).attr( 'name' )] = value;
		}

	} );

	return custom_params;
};

var parseDataType = function( value, dataType ) {
	switch ( dataType ) {
		case 'Link':
			var titleRegex = /title[\s+]?:[\s+]?([^,]+)/;
			var hrefRegex = /href[\s+]?:[\s+]?([^,]+)/;
			var title = titleRegex.exec( value )[1];
			var href = hrefRegex.exec( value )[1];
			value = { href: href, title: title };
			break;
	}
	return value;
};

var objSize = function( obj ) {
	var size = 0, key;
	for ( key in obj ) {
		if ( obj.hasOwnProperty( key ) )
			size++;
	}
	return size;
};

jQuery( document ).ready( function() {

	// Handle the custom file browser button
	jQuery('.btn-file :file').on('fileselect', function(event, numFiles, label) {
        
        var input = jQuery(this).parents('.input-group').find(':text'),
            log = numFiles > 1 ? numFiles + ' files selected' : label;
        
        if( input.length ) {
            input.val(log);
        } 
        
    });

    jQuery(document).on('change', '.btn-file :file', function() {
	  var input = jQuery(this),
	      numFiles = input.get(0).files ? input.get(0).files.length : 1,
	      label = input.val().replace(/\\/g, '/').replace(/.*\//, '');
	  input.trigger('fileselect', [numFiles, label]);
	});

	// Hide PID option fields in the Settings page
	if ( document.title.indexOf( 'thePlatform Plugin Settings' ) != -1 ) {
		jQuery( '#mpx_account_pid' ).parent().parent().hide();
		jQuery( '#default_player_pid' ).parent().parent().hide();

		if ( jQuery( '#mpx_account_id option:selected' ).length != 0 ) {

			jQuery( '#mpx_account_pid' ).val( jQuery( '#mpx_account_id option:selected' ).val().split( '|' )[1] );
		}
		else
			jQuery( '#mpx_account_id' ).parent().parent().hide();

		if ( jQuery( '#default_player_name option:selected' ).length != 0 ) {
			jQuery( '#default_player_pid' ).val( jQuery( '#default_player_name option:selected' ).val().split( '|' )[1] );
		}
		else
			jQuery( '#default_player_name' ).parent().parent().hide();

		if ( jQuery( '#mpx_server_id option:selected' ).length == 0 ) {
			jQuery( '#mpx_server_id' ).parent().parent().hide();
		}
	}

	//Set up the PID for the MPX account on change in the Settings page	
	jQuery( '#mpx_account_id' ).change( function( e ) {
		jQuery( '#mpx_account_pid' ).val( jQuery( '#mpx_account_id option:selected' ).val().split( '|' )[1] );
	} )

	//Set up the PID for the Player on change in the Settings page
	jQuery( '#default_player_name' ).change( function( e ) {
		jQuery( '#default_player_pid' ).val( jQuery( '#default_player_name option:selected' ).val().split( '|' )[1] );
	} )

	// Validate account information in plugin settings fields by logging in to MPX
	jQuery( "#verify-account-button" ).click( function( $ ) {
		var usr = jQuery( "#mpx_username" ).val();
		var pwd = jQuery( "#mpx_password" ).val();
		var images = theplatform_local.plugin_base_url;

		var hash = jQuery.base64Encode( usr + ":" + pwd );

		var data = {
			action: 'verify_account',
			_wpnonce: theplatform_local.tp_nonce['verify_account'],
			auth_hash: hash
		};

		jQuery.post( theplatform_local.ajaxurl, data, function( response ) {
			if ( jQuery( "#verification_image" ).length > 0 ) {
				jQuery( "#verification_image" ).remove();
			}

			if ( response.success ) {
				jQuery( '#verify-account-dashicon' ).removeClass( 'dashicons-no' ).addClass( 'dashicons-yes' );
			} else {
				jQuery( '#verify-account-dashicon' ).removeClass( 'dashicons-yes' ).addClass( 'dashicons-no' );
			}
		} );
	} );

	//Edit Media Validation	
	jQuery( "#theplatform_edit_button" ).click( function( event ) {
		var validation_error = validate_media( event );
		if ( validation_error )
			return false;
		var params = parseMediaParams();
		var custom_params = parseCustomParams();
		params.id = tpHelper.mediaId;

		var data = {
			_wpnonce: theplatform_local.tp_nonce['theplatform_edit'],
			action: 'theplatform_edit',
			params: JSON.stringify( params ),
			custom_params: JSON.stringify( custom_params )
		}

		jQuery( '#tp-edit-dialog' ).dialog( 'close' );
		jQuery.post( theplatform_local.ajaxurl, data, function( resp ) {
			refreshView();
		} );
	} );

	// Upload media button handler
	jQuery( "#theplatform_upload_button" ).click( function( event ) {
		var files = document.getElementById( 'theplatform_upload_file' ).files;

		var validation_error = validate_media( event );

		if (files[0] === undefined) {
			jQuery('#file-form-group').addClass('has-error');
		} else {
			jQuery('#file-form-group').removeClass('has-error');
		}

		if ( validation_error || files[0] === undefined )
			return false;

		var params = parseMediaParams();
		var custom_params = parseCustomParams();

		var profile = jQuery( '.upload_profile' );
		var server = jQuery( '.server_id' );

		var upload_window = window.open( theplatform_local.ajaxurl + '?action=theplatform_upload&_wpnonce=' + theplatform_local.tp_nonce['theplatform_upload'], '_blank', 'menubar=no,location=no,resizable=no,scrollbars=no,status=no,width=700,height=180' )

	  	var filesArray = [];

        for (var i = 0; i < files.length; i++) {
            filesArray.push(files[i]);
        };
        var uploaderData = {
            files: filesArray,
			params: JSON.stringify( params ),
			custom_params: JSON.stringify( custom_params ),
			profile: profile.val(),
			server: server.val(),
			source: 'theplatform_upload_data'
		}

		window.onmessage = function(e) {	
			if ( e.data == 'theplatform_uploader_ready' ) {
				upload_window.postMessage(uploaderData, '*');	
			}					
		}		

	} );
} );