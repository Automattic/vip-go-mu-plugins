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

TheplatformUploader = ( function() {		

	/**
	@function prepareForUpload Creates a placeholder media and gets all the required information
	to upload a new media file	
	 */
	TheplatformUploader.prototype.prepareForUpload = function() {
		var me = this;

		var file = this.files[this.currentFileIndex];
		this.file = file;		
		this._fileSize = file.size;
		this.filetype = file.type;
		this._filePath = file.name;	

		var jsonFields = JSON.parse(this.fields);	
		if ( this._mediaId != undefined && jsonFields.id == undefined) {
			jsonFields.id = me._mediaId;
			this.fields = JSON.stringify(jsonFields);
		}

		var data = {
			_wpnonce: theplatform_uploader_local.tp_nonce['initialize_media_upload'],
			action: 'initialize_media_upload',
			filesize: file.size,
			filetype: file.type,
			filename: file.name,
			server_id: this.server,
			fields: this.fields,
			custom_fields: this.custom_fields			
		};


		me.message( "Initializing Upload of file " + (me.currentFileIndex + 1) + ' out of ' + (me.lastFileIndex + 1));				

		jQuery.post( theplatform_uploader_local.ajaxurl, data, function( response ) {			
			if ( response.success ) {
				var data = response.data;
				
				if ( me._mediaId == undefined) {
					me.message( "Media created with id: " + data.mediaId );
				} 
				me.uploadUrl = data.uploadUrl				
				me.token = data.token;
				me.account = data.account;
				me._mediaId = data.mediaId;				
				me._guid = me.createUUID();
				me._serverId = data.serverId;
				me.format = data.format;
				me.contentType = data.contentType;				
				
				me.startUpload();
			} else {
				me.error( "Unable to upload media asset at this time. Please try again later." + response.data );
			}
		});		
	};

	/**
	 @function startUpload Inform FMS via the API proxy that we are starting an upload
	 passed to the proxy	 
	 */
	TheplatformUploader.prototype.startUpload = function() {
		var me = this;
		
		var requestUrl = me.uploadUrl + '/web/Upload/startUpload';		
		requestUrl += '?schema=1.1';
		requestUrl += '&token=' + me.token;
		requestUrl += '&account=' + encodeURIComponent( me.account );
		requestUrl += '&_guid=' + me._guid;
		requestUrl += '&_mediaId=' + encodeURIComponent( me._mediaId );
		requestUrl += '&_filePath=' + me._filePath;
		requestUrl += '&_fileSize=' + me._fileSize;
		requestUrl += '&_mediaFileInfo.format=' + me.format;
		requestUrl += '&_serverId=' + encodeURIComponent ( me._serverId );		
		
		me.message( "Starting Upload of " + me._filePath + ' to ' + me.uploadUrl);
		
		var data = {
			url: requestUrl,
			method: 'put',			
			_wpnonce: theplatform_uploader_local.tp_nonce['start_upload'],
			action: 'start_upload'			
		}
		
		jQuery.ajax( {
			url: theplatform_uploader_local.ajaxurl,			
			type: "POST",	
			data: data,		
			xhrFields: {
				withCredentials: true
			},
			success: function( response ) {			
				me.cookie = { name: response.data.cookie.name, value: response.data.cookie.value };				
				me.waitForReady();				
			}
		} );
	};

	/**
	 @function waitForReady Wait for FMS to become ready for the upload	 	 
	 */
	TheplatformUploader.prototype.waitForReady = function() {
		var me = this;

		var requestUrl = me.uploadUrl + '/data/UploadStatus';
		requestUrl += '?schema=1.0';
		requestUrl += '&token=' + me.token;
		requestUrl += '&account=' + encodeURIComponent( me.account );
		requestUrl += '&byGuid=' + me._guid;

		var data = {
			url: requestUrl,
			method: 'get',			
			action: 'upload_status',
			_wpnonce: theplatform_uploader_local.tp_nonce['upload_status'],
			cookie_name: me.cookie['name'],
			cookie_value: me.cookie['value'],
			contentType: "application/json; charset=utf-8"
		}

		me.message('Waiting for Upload server to be ready.');
		jQuery.ajax( {
			url: theplatform_uploader_local.ajaxurl,		
			type: "POST",	
			data: data,					
			xhrFields: {
				withCredentials: true
			},						
			success: function( response ) {				
				if (response.success) {		
					var data = response.data;
					if ( data.entries.length !== 0 ) {
						var state = data.entries[0].state;

						if ( state === "Ready" ) {

							var frags = me.fragFile( me.file );

							me.frags_uploaded = 0;
							me.num_fragments = frags.length;
							me.progressIncrements = 1/frags.length;

							me.message( "Beginning upload of " + frags.length + " fragments" );

							me.uploadFragments( frags, 0 );

						} else {
							setTimeout(function() { me.waitForReady() }, 1000 );
						}
					} else {
						setTimeout(function() { me.waitForReady() }, 1000 );
					}		
				}
				else {					
					me.message( response.data.description, true);					
				}	
			}
		} );
	};

	/**
	 @function uploadFragments Uploads file fragments to FMS
	 
	 @param {Array} fragments - Array of file fragments
	 @param {Integer} index - Index of current fragment to upload
	 */
	TheplatformUploader.prototype.uploadFragments = function( fragments, index ) {
		var me = this;
		
		if ( this.failed ) {
			return;
		}

		NProgress.settings.incLimit = me.progressIncrements * (index+1);
		
		if ( me.frags_uploaded == 0 ) {					
			me.message( 'Uploading file ' + (me.currentFileIndex + 1) + ' out of ' + (me.lastFileIndex + 1), true )							
			NProgress.set(0.00001)
			NProgress.settings.trickle = true;						
			NProgress.settings.trickleRate = me.progressIncrements / 35 ;		
			NProgress.settings.trickleSpeed = 650;			
			NProgress.start();		
		} 

		var requestUrl = me.uploadUrl + '/web/Upload/uploadFragment';
		requestUrl += '?schema=1.1';
		requestUrl += '&token=' + me.token;
		requestUrl += '&account=' + encodeURIComponent( me.account );
		requestUrl += '&_guid=' + me._guid;
		requestUrl += '&_offset=' + ( index * me.fragSize );
		requestUrl += '&_size=' + fragments[index].size;	


		var data = new FormData();
		data.append('file', fragments[index]);
		data.append('url', requestUrl);
		data.append('action', 'upload_fragment');		
		data.append('method', 'put');	
		data.append('_wpnonce', theplatform_uploader_local.tp_nonce['upload_fragment'])
		data.append('cookie_name', me.cookie['name']);
		data.append('cookie_value', me.cookie['value']);

		var lastSegmentStart = Date.now();
		jQuery.ajax( {
			url: theplatform_uploader_local.ajaxurl,			
			data: data,			
			type: "POST",
			processData: false,
  			contentType: false,
			xhrFields: {
				withCredentials: true
			},
			success: function( response ) {
				if (response.success) {													
					me.frags_uploaded++;
				
					if ( me.num_fragments == me.frags_uploaded ) {
						me.message( "Uploaded last fragment. Finishing up" );
						NProgress.inc(me.progressIncrements);
						me.finish();
					} else {	
						var lastSegmentEnd = Date.now();
						if ( NProgress.status < me.progressIncrements * me.frags_uploaded ) {							
							NProgress.set(me.progressIncrements * me.frags_uploaded);
							NProgress.configure({
								trickleRate: me.progressIncrements / ( (lastSegmentEnd - lastSegmentStart) / 1000 )
							})
						}
						me.message( "Finished uploading fragment " + me.frags_uploaded + " of " + me.num_fragments );
						index++;
						me.attempts = 0;
						me.uploadFragments( fragments, index );
					}
				} else {					
					me.message( response.data.description, true);
					setTimeout( function() {
						me.uploadFragments( fragments, index );
					}, 1000 );
				}
				
			}
		} );
	};

	/**
	 @function finish Notify MPX that the upload has finished	 
	 */
	TheplatformUploader.prototype.finish = function() {
		var me = this;		

		var requestUrl = me.uploadUrl + '/web/Upload/finishUpload';
		requestUrl += '?schema=1.1';
		requestUrl += '&token=' + me.token;
		requestUrl += '&account=' + encodeURIComponent( me.account );
		requestUrl += '&_guid=' + me._guid;

		var data = "finished";

		var data = {
			url: requestUrl,
			method: 'post',
			_wpnonce: theplatform_uploader_local.tp_nonce['finish_upload'],
			action: 'finish_upload',
			cookie_name: me.cookie['name'],
			cookie_value: me.cookie['value']		
		}

		me.message('Finishing upload of file ' + (me.currentFileIndex + 1) + ' out of ' + (me.lastFileIndex + 1), true)
		jQuery.ajax( {
			url: theplatform_uploader_local.ajaxurl,
			data: data,			
			type: "POST",
			xhrFields: {
				withCredentials: true
			},
			success: function( response ) {
				me.waitForComplete();
			},
			error: function( response ) {

			}
		} );
	};

	/**
	 @function waitForComplete Poll FMS via the API proxy until upload status is 'Complete'
	 passed to the proxy
	 */
	TheplatformUploader.prototype.waitForComplete = function() {
		var me = this;
		
		var requestUrl = me.uploadUrl + '/data/UploadStatus';
		requestUrl += '?schema=1.0';
		requestUrl += '&token=' + me.token;
		requestUrl += '&account=' + encodeURIComponent( me.account );
		requestUrl += '&byGuid=' + me._guid;

		var data = {
			url: requestUrl,
			method: 'get',
			_wpnonce: theplatform_uploader_local.tp_nonce['upload_status'],
			action: 'upload_status',
			cookie_name: me.cookie['name'],
			cookie_value: me.cookie['value'],
			contentType: "application/json; charset=utf-8"
		}

		me.message('Waiting for complete status');

		jQuery.ajax( {
			url: theplatform_uploader_local.ajaxurl,			
			type: "POST",			
			data: data,
			xhrFields: {
				withCredentials: true
			},
			error: function( response ) {
				setTimeout(function() { me.waitForComplete(); }, 5000)
			},
			success: function( response ) {
				if (response.success) {
					var data = response.data;
					if ( data.entries.length != 0 ) {
						var state = data.entries[0].state;

						if ( state === "Complete" ) {
							var fileID = data.entries[0].fileId;

							me.file_id = fileID;

							// On the last file, finish up.							
							if ( me.currentFileIndex == me.lastFileIndex ) {
								if ( me.publishProfile != "tp_wp_none" ) {								
								me.publishMedia();
								}
								else {
									me.message( "Upload completed. This window will close in 5 seconds.", true);
									window.setTimeout( 'window.close()', 5000 );
								}	
							} else { // We have more files, upload the next file
								me.currentFileIndex++;
								me.prepareForUpload();
							}							
						} else if ( state === "Error" ) {
							me.error( data.entries[0].exception, true );
						} else {
							me.message( state );
							setTimeout(function() { me.waitForComplete(); }, 5000)
						}
					} else {
						setTimeout(function() { me.waitForComplete(); }, 5000)
					}
				}
				else {					
					me.message( response.data.description, true);					
				}	
			}
		} );
	};

	/**
	 @function publishMedia Publishes the uploaded media via the API proxy
	 passed to the proxy
	 */
	TheplatformUploader.prototype.publishMedia = function() {
		var me = this;
		var params =  { 
			mediaId: me._mediaId,
			account: me.account,
			profile: me.publishProfile,
			action: 'publish_media',
			_wpnonce: theplatform_uploader_local.tp_nonce['publish_media'],
			token: me.token
		};
		
		if ( this.publishing ) {
			return;
		}

		this.publishing = true;

		me.message( "Publishing media" );

		jQuery.ajax( {
			url: theplatform_uploader_local.ajaxurl,
			data: params,
			type: "POST",
			success: function( response ) {
				if ( response.success ) {
					me.message( "Media published successfully", true );
					window.setTimeout( 'window.close()', 5000 );
				} else {
					me.message( response.data.description, true);					
				}
			}
		} );
	};

	/**
	 @function cancel Notify the API proxy to cancel the upload process
	 passed to the proxy
	 */
	TheplatformUploader.prototype.cancel = function() {
		var me = this;
		
		var requestUrl = me.uploadUrl + '/web/Upload/cancelUpload';
		requestUrl += '?schema=1.1';
		requestUrl += '&token=' + me.token;
		requestUrl += '&account=' + encodeURIComponent( me.account );
		requestUrl += '&_guid=' + me._guid;
		
		me.failed = true;

		var data = {
			url: requestUrl,
			method: 'put',
			_wpnonce: theplatform_uploader_local.tp_nonce['cancel_upload'],
			action: 'cancel_upload',
			cookie_name: me.cookie['name'],
			cookie_value: me.cookie['value']
		}

		jQuery.ajax( {
			url: requestUrl,		
			type: "POST",
			xhrFields: {
				withCredentials: true
			},
			complete: function() {
				me.message('Upload canceled')
			}
		} );
	};

	/**
	 @function fragFile Slices a file into fragments
	 @param {File} file - file to slice
	 @return {Array} array of file fragments
	 */
	TheplatformUploader.prototype.fragFile = function( file ) {
		
		var i, j, k;
		var ret = [ ];				

		if ( !( this.file.slice || this.file.mozSlice ) ) {
			return this.file;
		}

		for ( i = j = 0, k = Math.ceil( this.file.size / this.fragSize ); 0 <= k ? j < k : j > k; i = 0 <= k ? ++j : --j ) {
			if ( this.file.slice ) {
				ret.push( this.file.slice( i * this.fragSize, ( i + 1 ) * this.fragSize ) );
			} else if ( file.mozSlice ) {
				ret.push( this.file.mozSlice( i * this.fragSize, ( i + 1 ) * this.fragSize ) );
			}
		}

		return ret;
	};	

	TheplatformUploader.prototype.createUUID = function() {
    // http://www.ietf.org/rfc/rfc4122.txt
	    var s = [];
	    var hexDigits = "0123456789abcdef";
	    for (var i = 0; i < 36; i++) {
	        s[i] = hexDigits.substr(Math.floor(Math.random() * 0x10), 1);
	    }
	    s[14] = "4";  // bits 12-15 of the time_hi_and_version field to 0010
	    s[19] = hexDigits.substr((s[19] & 0x3) | 0x8, 1);  // bits 6-7 of the clock_seq_hi_and_reserved to 01
	    s[8] = s[13] = s[18] = s[23] = "-";

	    var uuid = s.join("");
	    return uuid;
	};

	/**
	 @function message Display an informative message to the user
	 @param {String} msg - The message to display
	 @param {Boolean} fade - Whether or not to fade the message div after some delay
	 */
	TheplatformUploader.prototype.message = function( msg, userFacing, isError ) {
		console.log(msg);	
		
		if ( !userFacing ) return;
		jQuery( '.lead' ).removeClass('error');

		if ( isError == true) {
			jQuery( '.lead' ).addClass('error');
		}

		jQuery( '.lead' ).animate( { 'opacity': 0 }, 500, function() {
			jQuery( this ).html( msg );
		} ).animate( { 'opacity': 1 }, 500 );
	};

	/**
	 @function error Display an error message to the user
	 @param {String} msg - The message to display
	 @param {Boolean} fade - Whether or not to fade the message div after some delay
	 */
	TheplatformUploader.prototype.error = function( msg ) {
		this.message( msg, true, true );
	};

	/**
	 @function constructor Inform the API proxy to create placeholder media assets in MPX and begin uploading	 
	 */
	function TheplatformUploader( files, fields, custom_fields, profile, server ) {
		var me = this;
		
		this.fragSize = ( 1024 * 1024 ) * 10;
			
		var splashHtml = '<div class="splash card">' +
		    '<div role="spinner">' +
		        '<div class="spinner-icon"></div>' +
		    '</div>' +		    
		    '<p class="lead" style="text-align:center">Initalizing upload</p>' +
	    	'<div class="progress">' +
	        	'<div class="mybar" role="bar"></div>' +
	    	'</div>' +	    	
		'</div>';

		NProgress.configure({
		    template: splashHtml,
		    trickle: false,		    
		    minimum: 0
		});

		NProgress.start();

		jQuery.ajaxSetup({dataType: 'json'})
		
		this.files = files;
		this.lastFileIndex = files.length - 1;
		this.currentFileIndex = 0;
		this.failed = false;		
		this.publishing = false;
		this.attempts = 0;		
		this.publishProfile = profile;
		this.fields = fields;
		this.custom_fields = custom_fields;
		this.server = server
		
		this.prepareForUpload();
	};

	return TheplatformUploader;
} )();