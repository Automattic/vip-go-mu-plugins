/**
 * JW Platform JavaScript Uploader version 1.01
 *
 * Inspired by Resumable.js and Valums AJAX Upload (version 1).
 */

/**
 * Construct a JWPlayerUpload object.
 *
 * Only the link parameter is required.
 *
 * link:             The link object as returned by the API
 * resumableSession: The resumable session id if the upload should be
 *                   resumable. False otherwise.
 * redirect:         The object describing the redirect location.
 *                   Should be of the form {url: '...', params: ['...', ...]} .
 *                   The params property is optional.
 * id:               A unique identifier to use for the iframe (if used)
 *                   and for the JSON-P calls (if used).
 *                   If left blank, it is randomly generated.
 */
function JWPlayerUpload( link, resumableSession, redirect, id ){
	// Whether we are in resumable mode
	this._resumable = ! ! resumableSession;

	if( this._resumable && ! JWPlayerUpload.resumeSupported() ){
		// A resumable upload was requested, but it is not supported by this
		// client.
		this._log( "Resumable uploads are not supported!" );
	}

	this._redirect = redirect;

	// Build the URL
	this._url = link.protocol + '://' + link.address + link.path
	+ '?api_format=json&key=' + link.query.key;

	// Add the upload token or set the session id
	if( ! this._resumable ){
		this._url += '&token=' + link.query.token;
	}
	else{
		this._sessionId = resumableSession;
	}

	// Generate a URL for automatic redirection
	this._redirectableUrl = this._url;
	if( redirect ){
		this._redirectableUrl += '&redirect_address=' + encodeURI( redirect.url );
		if( redirect.params ){
			for( p in redirect.params ){
				this._redirectableUrl += '&redirect_query.' + encodeURI( p ) + '=' + encodeURI( redirect.params[p] );
			}
		}
	}

	// Build the real url for the redirection
	if( redirect ){
		this._redirectUrl = redirect.url;
		var c = 0;
		if( redirect.params ){
			for( var p in redirect.params ){
				this._redirectUrl += (c ++ === 0) ? '?' : '&';
				this._redirectUrl += p + '=' + redirect.params[p];
			}
		}
	}

	// Some identifier. Used for handling JSON-P.
	// If it is not passed to the constructor, generate one.
	if( ! id ){
		id = this._generateAlphaUid();
	}
	this._id = id;

	// If we are not in resumable mode, also generate the URL for polling
	// the upload progress.
	if( ! this._resumable ){
		this._progressUrl = link.protocol + '://' + link.address + '/progress?token=' + link.query.token + '&callback=' + id + '_poll';
	}
}

JWPlayerUpload.prototype = {
	constructor:JWPlayerUpload,

	/**
	 * Boolean determining whether a submission has already taken place.
	 * It is not possible to use the same uploader for uploading twice.
	 *
	 * The reason for this is that the upload links are for single use
	 * only.
	 */
	_submitted:false,
	/**
	 * Whether we are currently uploading.
	 * False if:
	 * - We haven't started yet
	 * - We paused (for resumable uploads)
	 *   Note that we might still be uploading one chunk
	 * - The upload is canceled
	 * - We are done
	 */
	_running:false,
	/**
	 * Whether the upload is complete.
	 *
	 * We don't permit starting a completed upload.
	 */
	_completed:false,

	/**
	 * The size of each chunk (slice) of the file being uploaded at once
	 * in resumable mode.
	 */
	chunkSize:1024 * 1024 * 2,
	/**
	 * The interval between two progress poll operations in non-resumable
	 * mode.
	 */
	pollInterval:500,
	/**
	 * The length of the unique identifier that is used for a random
	 * function name in JSON-P requests in non-resumable mode.
	 */
	UID_LENGTH:32,

	/**
	 * Whether the upload is resumable.
	 */
	isResumable:function(){
		return this._resumable;
	},

	/**
	 * The handler for the "selected" event, which fires after a file was
	 * picked for uploading.
	 *
	 * Using this, it is possible to upload immediately after picking a
	 * file.
	 */
	onSelected:function(){
		this._log( "File was selected." );
	},
	/**
	 * The handler for the "start" event, which is fired as soon as
	 * submission of the form is started.
	 */
	onStart:function(){
		this._log( "Started upload." );
	},
	/**
	 * The handler for the "progress" event, which is fired at intervals
	 * while the file is being uploaded. It is guaranteed that this fires
	 * only after a "start" event and before an "error" or "completed"
	 * event.
	 */
	onProgress:function( bytes, total ){
		this._log( "Uploaded " + bytes + " bytes of " + total );
	},
	/**
	 * The handler for the "completed" event, which is fired after the
	 * file has been uploaded successfully.
	 *
	 * Default behavior is to follow the redirect link if it is present.
	 *
	 * Warning: In non-resumable mode, the size is determined during
	 * upload. When uploading small files, it is possible that the size
	 * could not be determined before the upload completes.
	 * In this case, the value of size is undefined.
	 */
	onCompleted:function( size, redirect ){
		this._log( "Finished uploading " + size + " bytes." );
		if( redirect ){
			this._log( "Redirecting to " + redirect + "." );
			document.location.href = redirect;
		}
	},
	/**
	 * The handler for the "error" event, which is fired after an error
	 * occurs in the file transfer.
	 *
	 * This usually means that the transfer did not complete successfully.
	 */
	onError:function( msg ){
		this._log( msg );
	},

	/**
	 * Start the file transfer.
	 * This is only allowed after a file has been selected.
	 */
	start:function(){
		if( this._completed ){
			this._log( "Attempting to start an upload which has already finished." );
			return;
		}
		if( this._form ){
			// Do nothing if we are already running.
			if( this._running ){
				return;
			}
			this._running = true;
			// Fire the start event
			this.onStart();
			if( this._resumable ){
				// Start reading the file and upload piece by piece.
				this._file = this._input.files[0];
				this._currentChunk = 0;
				this._uploadChunk();
			}
			else{
				// Simply submit the form and start polling for progress.
				this._form.submit();
				this._submitted = true;
				this._poll();
			}
		}
	},

	/**
	 * Pause a transfer.
	 * This only works in resumable mode.
	 */
	pause:function(){
		if( ! this._resumable ){
			this._log( "Attempting to pause a non-resumable upload." );
			return;
		}
		this._running = false;
	},

	/**
	 * Cancel a transfer.
	 * This works in both resumable and non-resumable mode.
	 */
	cancel:function(){
		if( ! this._resumable ){
			var ifr = this._iframe;
			if( ifr ){
				if( typeof(ifr.stop) !== 'undefined' ){
					ifr.stop();
				}
				else{
					// Internet Explorer
					// For some reason, execCommand('Stop') does not seem to work,
					// so let's just redirect.
					ifr.src = 'about:blank';
				}
			}
			else{
				// No iframe, so just cancel the current page load
				if( typeof(window.stop) !== 'undefined' ){
					window.stop();
				}
				else if( typeof(document.execCommand) !== 'undefined' ){
					document.execCommand( 'Stop' );
				}
			}
		}
		this._running = false;
	},

	/**
	 * Get a hidden iframe and target the submission form to it.
	 * Using this approach, file uploads can be done in the background,
	 * and will not necessarily redirect the page after submission of the
	 * file.
	 *
	 * This function returns a hidden HTML DOM <iframe> element, which can
	 * be appended anywhere on the page.
	 *
	 * This method should not be used in resumable mode, but it should not
	 * break. Resumable uploads are always done in the background.
	 *
	 * If you want to make use of the useForm function, it must be called
	 * _before_ getIframe.
	 */
	getIframe:function(){
		if( ! this._iframe ){
			// In IE7, we can not change the name of an iframe.
			// We hack around it by creating the iframe together with the
			// name.
			var ifr;
			{
				var div = document.createElement( 'div' );
				div.innerHTML = '<iframe name="' + this._id + '_iframe">';
				ifr = div.firstChild;
				div.removeChild( ifr );
			}
			this._iframe = ifr;
			ifr.style.display = 'none';

			this._attachEvent( ifr, 'load', function(){
				if( ! this._submitted || this._completed || ! this.running ){
					// Empty iframe has loaded
				}
				else{
					// We have sent the complete file.
					// Unfortunately, we can not read the response because of
					// cross origin browser restrictions, so let the poller take
					// care of the upload status.
					this._running = false;
					this._completed = true;
					this._iframe.parentNode.removeChild( this._iframe );
				}
			}, this );
			this.getForm().setAttribute( 'target', ifr.name );
			// Redirect the iframe to a blank page after the request is done.
			// This way, we prevent the JS "Download" dialog.
			this.getForm().setAttribute( 'action', this._url + '&redirect_address=about:blank' );
		}
		return this._iframe;
	},
	/**
	 * Get a simple submission form.
	 * The form contains only a file input element.
	 * There is no submit button.
	 *
	 * The return value of this function is an HTML DOM <form> element.
	 * It is not hidden, and can be embedded anywhere on the page.
	 * It is possible to add additional elements to the form, such as a
	 * submit button.
	 *
	 * Form submission safely uploads the file, similarly to a call to the
	 * start function.
	 *
	 * This function works for both resumable and non-resumable uploads.
	 */
	getForm:function(){
		if( ! this._form ){
			// Create the form elements.
			// In IE7, we can not change the name of an input element
			// apparently, and we can not change the enctype and target of a
			// form. We hack around it by generating the elements from HTML
			// code.
			var f, i;
			{
				var div = document.createElement( 'div' );
				div.innerHTML = '<form method="post" enctype="multipart/form-data">';
				f = div.firstChild;
				div.removeChild( f );
				div.innerHTML = '<input type="file" name="file">';
				i = div.firstChild;
				div.removeChild( i );
			}
			f.appendChild( i );
			// Use them as our form
			this.useForm( i );
		}
		return this._form;
	},

	/**
	 * Use an existing input element as a submission form for the file.
	 *
	 * It is required that the given element is an input element of type
	 * file and is contained inside a form element with method post and
	 * enctype multipart/form-data.
	 *
	 * Both the form and input elements are adapted for use in the upload
	 * process.
	 *
	 * Form submission safely uploads the file, similarly to a call to the
	 * start function.
	 *
	 * This function works for both resumable and non-resumable uploads.
	 */
	useForm:function( element ){
		// Do some parameter checks
		if( this._form ){
			this._log( "Already using a form." );
			return false;
		}
		if( element.tagName.toUpperCase() !== 'INPUT'
			|| element.getAttribute( 'type' ) !== 'file' ){
			this._log( "Invalid element." );
			return false;
		}
		var f = element.form;
		if( ! f ){
			// Konqueror does not understand element.form
			do{
				if( element.parentNode.tagName.toUpperCase() === 'FORM' ){
					f = element.parentNode;
				}
			}
			while( f );
		}
		if( ! f ){
			this._log( "Element is not part of a form." );
			return false;
		}
		// Change the parameters of the form for correct submission.
		f.setAttribute( 'action', this._redirectableUrl );
		f.setAttribute( 'method', 'post' );
		f.setAttribute( 'enctype', 'multipart/form-data' );
		// Change the name of the input element to "file", as required by
		// the API.
		element.setAttribute( 'name', 'file' );
		// Start using the form.
		this._form = f;
		this._input = element;
		// Make sure submission and selection are handled.
		this._attachEvent( element, 'change', this.onSelected, this );
		this._attachEvent( f, 'submit', function(){
			this.start();
			return false;
		}, this );
	},

	/**
	 * Log to the JavaScript console.
	 * Used by default for various pieces of information, as well as the
	 * display of error messages.
	 *
	 * The uploader can be silenced by replacing this with an empty
	 * function.
	 */
	_log:function( msg ){
		if( typeof(console) !== 'undefined' ){
			if( console.log ){
				console.log( msg );
			}
		}
	},

	/**
	 * Simple, cross-browser function to add event handlers to DOM
	 * elements.
	 *
	 * The optional self parameter is used for overriding which object is
	 * represented by "this" in the callback.
	 */
	_attachEvent:function( element, event, callback, self ){
		var cb = callback;
		if( self ){
			cb = function(){
				callback.call( self );
			};
		}
		if( element.addEventListener ){
			element.addEventListener( event, cb, false );
		}
		else if( element.attachEvent ){
			element.attachEvent( 'on' + event, cb );
		}
	},

	/**
	 * Poll the API for the upload progress.
	 *
	 * After a single call, this will keep polling with interval
	 * pollInterval between response and request until either the upload
	 * is completed or an error has occurred.
	 *
	 * While the upload is in progress, it will fire the "progress" event.
	 * If an upload is finished, it will fire the "completed" event once.
	 * If an error occurs, it will fire the "error" event once.
	 *
	 * This is only used in non-resumable mode.
	 */
	_poll:function( data ){
		// Remove the previous JSON-P script tag if it exists.
		if( this._pollElt ){
			this._pollElt.parentNode.removeChild( this._pollElt );
			this._pollElt = undefined;
		}

		if( data ){
			var done = false;
			// Handle received data
			switch(data.state){
				case 'starting':
					// Just keep polling. Progress updates are not necessary, as
					// we are at 0%.
					break;
				case 'uploading':
					// Fire the "progress" event with the current upload status.
					this._size = parseInt( data.size );
					this.onProgress( data.received, data.size );
					break;
				case 'done':
					// Upload is complete. Fire the appropriate event and stop
					// polling.
					this._completed = done = true;
					this._running = false;
					this.onCompleted( this._size, this._redirectUrl );
					break;
				case 'error':
					// An error has occurred. Fire the appropriate event and stop
					// polling.
					done = true;
					this.onError( "Error occurred with code " + data.status );
					break;
				default:
					done = true;
					this.onError( "Unknown error occurred." );
			}
			// Continue polling if the upload is not yet completed.
			if( ! done && this._running ){
				var self = this;
				setTimeout( function(){
					self._poll.call( self );
				}, this.pollInterval );
			}
			else{
				// If we are finished, remove the JSON-P callback function.
				window[this._id + '_poll'] = undefined;
			}
		}
		else{
			// Perform a JSON-P call.
			var elt = this._pollElt = document.createElement( 'script' );
			// Add a random number, to prevent caching by Safari and IE
			elt.setAttribute( 'src', this._progressUrl + '&nocache=' + Math.random() );
			var self = this;
			// Add a simple callback function, which merely transfers its data
			// to this function.
			window[this._id + '_poll'] = function( data ){
				self._poll.call( self, data );
			};
			// IE does not have document.head
			var head = document.head || document.getElementsByTagName( 'head' )[0];
			head.appendChild( elt );
		}
	},

	/**
	 * Upload the next chunk of the file and fire a progress update
	 * afterwards.
	 * This will continue until this._running is false.
	 */
	_uploadChunk:function(){
		// Calculate the chunk size and position
		var start = this._currentChunk * this.chunkSize;
		var size = this._file.size;
		var end = Math.min( (this._currentChunk + 1) * this.chunkSize, size );
		// Create the XHR
		var xhr = new XMLHttpRequest();
		xhr.open( 'POST', this._url );
		xhr.setRequestHeader( 'Content-Disposition', 'attachment; filename="' + this._file.name + '"' );
		xhr.setRequestHeader( 'Content-Type', 'application/octet-stream' );
		xhr.setRequestHeader( 'X-Content-Range', 'bytes ' + start + '-' + (end - 1) + '/' + size );
		xhr.setRequestHeader( 'X-Session-ID', this._sessionId );
		// Handle a completed XHR
		var self = this;
		xhr.onreadystatechange = function(){
			if( xhr.readyState === 4 ){
				// Done
				if( xhr.status === 200 ){
					// The file is completely uploaded.
					// The response is in JSON format.
					var response = eval( '(' + xhr.responseText + ')' );
					// We do not send the redirect in the API call, because then
					// the final XHR gets redirected itself.
					self._running = false;
					self._completed = true;
					self.onCompleted( parseInt( response.file.size ), self._redirectUrl );
				}
				else if( xhr.status === 201 ){
					// The chunk was succesfully uploaded.
					// The response is plain text
					var m = xhr.responseText.trim().match( /^(\d+)-(\d+)\/(\d+)$/ );
					if( ! m ){
						self.onError( "Received invalid response from the server." );
						return;
					}
					self._currentChunk = Math.floor( (parseInt( m[2] ) + 1) / self.chunkSize );
					self.onProgress( parseInt( m[2] ), parseInt( m[3] ) );
					if( self._running ){
						// Upload the next chunk
						self._uploadChunk();
					}
				}
				else{
					// Error response
					self.onError( "Error response: " + xhr.statusText );
					return;
				}
			}
		};
		var f;
		if( this._file.mozSlice ){
			f = this._file.mozSlice;
		}
		else if( this._file.webkitSlice ){
			f = this._file.webkitSlice;
		}
		else{
			f = this._file.slice;
		}
		xhr.send( f.call( this._file, start, end, 'application/octet-stream' ) );
	},

	/**
	 * Generate a simple alpha-only random string of length UID_LENGTH.
	 */
	_generateAlphaUid:function(){
		var res = '';
		for( var i = 0; i < this.UID_LENGTH; ++ i ){
			var rand = Math.floor( Math.random() * 52 );
			// 0x41 == 'A', 0x61 == 'a'
			if( rand > 25 ){
				rand += 0x20 - 26;
			}
			rand += 0x41;
			res += String.fromCharCode( rand );
		}
		return res;
	}
}

/**
 * Whether resumable uploads are supported.
 */
JWPlayerUpload.resumeSupported = function(){
	return (
		// File object supported
	(typeof(File) !== 'undefined') &&
		// Blob object supported
	(typeof(Blob) !== 'undefined') &&
		// FileList object supported
	(typeof(FileList) !== 'undefined') &&
		// Slice supported
	( ! ! Blob.prototype.webkitSlice || ! ! Blob.prototype.mozSlice || ! ! Blob.prototype.slice)
	);
}