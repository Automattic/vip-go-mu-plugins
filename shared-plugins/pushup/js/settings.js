( function( window, $, Settings, undefined ) {

	"use strict";
	var document = window.document;

	/**
	 * Internal caching container
	 *
	 * @type {{iconData: null, mediaModalInstance: null, $wrapper: null, domainXHR: null, $basicMode: null, $advancedMode: null, currentMode: string}}
	 */
	var Cache = {
		iconData : null,
		mediaModalInstance : null,
		$wrapper : null,
		domainXHR : null,
		$basicMode : null,
		$advancedMode : null,
		currentMode : 'basic',
		$promptMode : null,
		$promptHelp : null
	};

	/**
	 * Binds all events necessary in order for this JS file to work as intended.
	 */
	function bindEvents() {
		Cache.$wrapper.on( 'click', '.icon-preview input[type="button"], .icon-preview .thumbnail, .icon-preview .thumbnail img', onIconPreviewButtonClick );
		Cache.$promptMode.on( 'change', function(){
			if ( this.value == 'custom' ) {
				Cache.$promptHelp.slideDown(250);
			} else {
				Cache.$promptHelp.slideUp(250);
			}
		});
	}

	/**
	 * Called when the Icon Preview button is clicked.
	 *
	 * Checks which icon type was clicked, caches it for later (so we what was clicked after the user is done editing)
	 * and then opens the Media modal so we can continue the selection process.
	 *
	 * @param event
	 */
	function onIconPreviewButtonClick( event ) {
		event = event || window.event;
		var $target = $( event.target || event.srcElement );
		var iconID = $target.attr( 'data-icon-id' );
		var whiteListed = [ '16x16', '16x16@2x', '32x32', '32x32@2x', '128x128', '128x128@2x' ];

		if ( whiteListed.indexOf( iconID ) === -1 ) {
			return;
		}

		setIconData( {
			iconID : iconID,
			$buttonElement : $target
		} );
		openMediaModal();
	}

	/**
	 * Caches the icon data passed to this function so we can retrieve it later, when needed.
	 *
	 * @param data
	 */
	function setIconData( data ) {
		Cache.iconData = data;
	}

	/**
	 * Gets the icon data that was previously cached. If no cache exists, we simply return false.
	 *
	 * @returns {*}
	 */
	function getIconData() {
		if ( Cache.iconData !== undefined && Cache.iconData !== null ) {
			return Cache.iconData;
		}

		return false;
	}

	/**
	 * Opens a new (or cached) instance of the media modal.
	 */
	function openMediaModal() {
		Cache.mediaModalInstance = getMediaModalInstance().open();
	}

	/**
	 * This function will check to see if there is already a cached instance of the media modal in place. If there isn't,
	 * one is created and cached for the future. It's important to note that any event bindings to the mediaModal instance
	 * itself NEED to have a context of this scope or things won't work appropriately.
	 *
	 * @returns {*}
	 */
	function getMediaModalInstance() {
		if ( Cache.mediaModalInstance !== undefined && Cache.mediaModalInstance !== null ) {
			return Cache.mediaModalInstance;
		}

		var mediaModal = wp.media( {
			title : 'Choose an Image',
			button : {
				text : 'Select Image'
			},
			multiple : false
		} );

		mediaModal.on( 'select',onMediaModalSelected, this );
		return Cache.mediaModalInstance = mediaModal;
	}

	/**
	 * Called when a media object is selected from the mediaModal instance.
	 *
	 * This function will get the previously cached iconData and send it to the server so we can begin processing the
	 * switch for this user's account.
	 */
	function onMediaModalSelected() {
		var attachment = getMediaModalInstance().state().get( 'selection' ).first();
		var attributes = attachment.attributes;

		var iconData = getIconData();
		var ajaxData = {
			iconURL : attributes.url,
			iconID : iconData.iconID,
			currentMode : Cache.currentMode
		};

		showIconLoader();
		sendAJAX( 'update-push-package-icon', ajaxData, onUpdatePushPackageSuccess );
	}

	/**
	 * Shows the icon loader for the user.
	 */
	function showIconLoader() {
		var iconData = getIconData();
		var $parent = iconData.$buttonElement.parent();
		$parent.find( 'img' ).hide();
		$parent.find( '.loader' ).show();
	}

	/**
	 * Hides the icon loader from the user.
	 */
	function hideIconLoader() {
		Cache.$wrapper.find( '.icon-preview img' ).show();
		Cache.$wrapper.find( '.icon-preview .loader' ).hide();
	}

	/**
	 * Callback for `sendAjax` in `onMediaModalSelected`.
	 *
	 * Handles interpreting the JSON data object received. Mainly responsible for reloading the icon on change and hiding
	 * and previously visible loaders.
	 *
	 * @param data
	 */
	function onUpdatePushPackageSuccess( data ) {
		if ( data.error ) {
			hideIconLoader();
			return;
		}

		var iconData = getIconData();
		if ( iconData === false ) {
			return;
		}

		var $parent = iconData.$buttonElement.parent( '.icon-preview' );

		refreshIconThumbnail( $parent, hideIconLoader );
	}

	/**
	 * Refreshes the icon thumbnail for an icon preview. We strip out any question marks and then force a cache reset
	 * so the user sees the change when the thumbnail resets. Note: the callback parameter is used AFTER the image
	 * refresh has finished.
	 *
	 * @param $parentElement
	 * @param callback
	 */
	function refreshIconThumbnail( $parentElement, callback ) {
		var $thumbnail = $parentElement.find( '.thumbnail img' );
		var src = $thumbnail.attr( 'src' );

		// strip the ? params now (if any exist)
		src = src.replace( /\?(.*)/g, '' );

		// adjust the URL with a forced refresh
		src = src + '?time=' + ( new Date() ).getTime();

		// bind callback's context so we don't lose track of `this`
		if ( typeof callback === 'function' ) {
			callback = $.proxy( callback, this );
		}

		// now load the new image
		var img = new Image();
		img.onload = function() {
			$thumbnail.attr( 'src', src );
			if ( typeof callback === 'function' ) {
				callback();
			}
		};
		img.src = src;
	}

	/**
	 * Caches any UI elements on the page that we might need to refer to later.
	 */
	function cacheElements() {
		Cache.$wrapper = $( document.querySelector( '.pushup-notifications-settings' ) );
		Cache.$basicMode = $( document.querySelector( '.basic-mode' ) );
		Cache.$advancedMode = $( document.querySelector( '.advanced-mode' ) );
		Cache.$promptMode = $( document.getElementsByName( 'pushup[prompt]' ) );
		Cache.$promptHelp = $( document.getElementById('pushup_custom_prompt_help') );
	}

	/**
	 * Initiates an AJAX request with our server to perform processing based on the action and data.
	 *
	 * @param action
	 * @param data
	 * @param callback
	 * @returns {null}
	 */
	function sendAJAX( action, data, callback ) {
		if ( Cache.domainXHR && typeof Cache.domainXHR.abort === 'function' ) {
			Cache.domainXHR.abort();
		}

		Cache.domainXHR = $.ajax( {
			dataType : 'JSON',
			type : 'POST',
			url : Settings.ajaxURL,
			data : {
				_wpnonce : Settings.nonce,
				action : action,
				actionData : data
			}
		} );

		Cache.domainXHR.success( callback );
		return Cache.domainXHR;
	}

	/**
	 * Initializes this library by performing the required functions that make this JS work as intended.
	 */
	function initialize() {
		cacheElements();
		bindEvents();
	}

	/**
	 * Since this script is called via the footer, immediately invoke the `initialize` function so we can begin setting
	 * up right away.
	 */
	initialize();

} )( window, jQuery, pushNotificationSettings );