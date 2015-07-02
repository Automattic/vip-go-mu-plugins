window.PushUpNotifications = ( function( window, settings ) {

	"use strict";
	var document = window.document;

	/**
	 * An internal cache used to store settings for our web service.
	 *
	 * @type {{websitePushID: string, webServiceURL: string, userID: int, domain: string}}
	 */
	var Cache = {
		websitePushID : '',
		webServiceURL : '',
		userID : -1,
		domain : '',
		prompt : 1,
		permissionData : '',
		button : null
	};

	/**
	 * Retrieve visit count cookie
	 */
	function getPageViews() {
		var name = "pushup_pv=";
		var ca = document.cookie.split(';');
		for(var i=0; i<ca.length; i++) {
			var c = ca[i].trim();
			if (c.indexOf(name) === 0) {
				return parseInt( c.substring(name.length,c.length) );
			}
		}
		return 0;
	}

	/**
	 * Store this visitor's page views on client side
	 */
	function setPageViews(pv_count) {
		var date = new Date();
		date.setTime( date.getTime() + (60*24*60*60*1000) );
		var expires = date.toUTCString();
		document.cookie = 'pushup_pv=' + pv_count + ';' +
			'expires=' + expires + ';' +
			'path=/;';
	}

	/**
	 * Handles checking safari for permissions to receive push notifications.
	 */
	function offerNotifications(triggered) {
		if ( canOfferNotifications() ) {
			// check number of visits
			if ( !triggered && Cache.prompt > 1 ) {
				var pv = getPageViews() + 1;
				if ( Cache.prompt > pv ) {
					setPageViews(pv);
					return;
				}
			}
			// hide the PushUp prompt button, if it exists
			if ( Cache.button !== null ) {
				Cache.button.style.display = 'none';
			}

			// always delete cookie before prompt, in case changed from a higher value or prompt is triggered by event
			//  in conjunction with a page view counter
			document.cookie = "pushup_pv=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/;";

			// request prompt
			var userInfo = {
				userID : Cache.userID,
				domain : Cache.domain
			};

			try {
				window.safari.pushNotification.requestPermission(
					Cache.webServiceURL, Cache.websitePushID, userInfo, offerNotifications
				);
			} catch (err) {
				Cache.permissionData.permission = 'denied';
			}
		}
	}

	/**
	 * Handles checking safari for permissions to receive push notifications.
	 *
	 * @return bool
	 */
	function canOfferNotifications() {
		/*
		if ( Cache.permissionData.permission === 'denied' ) {
			// The user said no.
		} else if ( Cache.permissionData.permission === 'granted' ) {
			// The web service URL is a valid push provider, and the user said yes.
			// `permissionData.deviceToken` is now available to use.
		}
		*/
		return ( typeof Cache.permissionData.permission !== 'undefined' && Cache.permissionData.permission === 'default' );
	}

	/**
	 * Handles initializing our library and ensuring that things are properly setup.
	 */
	function initialize() {
		// Ensure that the user can receive Safari Push Notifications.
		if ( 'safari' in window && 'pushNotification' in window.safari ) {
			Cache.userID = settings.userID;
			Cache.domain = settings.domain;
			Cache.websitePushID = settings.websitePushID;
			Cache.webServiceURL = settings.webServiceURL;
			Cache.prompt = settings.prompt;

			Cache.permissionData = window.safari.pushNotification.permission( Cache.websitePushID );

			if ( Cache.prompt > 0 ) {
				offerNotifications(false);
			}
		}

		// automatically connect prompts to an element with ID pushup_button (don't break on IE < 8)
		if ( document.querySelector ) {
			Cache.button = document.querySelector('.pushup_button');
			if ( Cache.button !== null ) {
				if ( canOfferNotifications() ) {
					Cache.button.style.display = 'block';
					Cache.button.addEventListener('click', function(e) {
						e.preventDefault();
						offerNotifications(true);
					}, false);
				} else {
					Cache.button.style.display = 'none';
				}
			}
		}
	}

	initialize();

	return {
		offerNotifications: offerNotifications,
		canOfferNotifications: canOfferNotifications
	};

} )( window, PushUpNotificationSettings );

window.PushUpNotifications.version = '1.2.1';
