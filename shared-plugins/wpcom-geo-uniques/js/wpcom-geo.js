wpcom_geo = {
	// TODO: don't expose private functions

	detect: function() {
		if ( ! this.are_cookies_enabled() ) {
			return;
		}

		if ( ! window.XMLHttpRequest ) {
			this.gracefully_fail( 'wpcom-geo: Uh oh! This browser doesn\'t support XMLHttpRequest!' );
			return;
		}

		if ( 'function' !== typeof( wpcom_geo_settings.success_callback ) ) {
			this.gracefully_fail( 'wpcom-geo: Uh oh! Looks like you haven\'t defined an error callback using `wpcom_geo.set_detect_success_callback();`' );
			return;
		}
		try {
			var xhr = new XMLHttpRequest();
			xhr.open('GET', wpcom_geo_settings.geolocation_endpoint, false); // we want a synchronous request since we want geo to happen before other things
			xhr.send(null);

			var responseText = xhr.responseText;

			// Only geolocate modern browsers
			if (xhr.status === 200 && window.JSON) {
				var location = wpcom_geo_settings.success_callback.call(undefined, JSON.parse(responseText));

				if (location) {
					wpcom_geo.set_location(location);
				} else {
					wpcom_geo.set_default_location();
				}

				wpcom_geo.refresh();

			} else {
				this.gracefully_fail('wpcom-geo: geolocation request failed :(');
				if ('function' === typeof( wpcom_geo_settings.error_callback )) {
					wpcom_geo_settings.error_callback.call(undefined, responseText);
				}
				return;
			}
		} catch( e ){
			//avoid IE errors
		}
	},

	gracefully_fail: function( message ) {
		console.error( message );
		this.set_default_location();
	},

	set_detect_success_callback: function( callback ) {
		wpcom_geo_settings.success_callback = callback;
	},

	set_detect_error_callback: function( callback ) {
		wpcom_geo_settings.error_callback = callback;
	},

	set_default_location: function() {
		this.set_location( wpcom_geo_settings.default_location );
	},

	set_location: function( location ) {
		if ( ! this.is_valid_location( location ) ) {
			location = wpcom_geo_settings.default_location;
		}

		document.cookie = wpcom_geo_settings.cookie_name + '=' + location + '; expires=' + wpcom_geo_settings.expiry_date + '; max-age=' + wpcom_geo_settings.expiry_time + '; path=/';
		
	},

	is_valid_location: function( location ) {
		var locations = wpcom_geo_settings.locations;
		for( var i = 0; i < locations.length; i++ ) {
			if ( locations[i] == location ) {
				return true;
			}
		}
		return false;
	},

	refresh: function() {
		window.location.reload();
	},

	are_cookies_enabled: function() {
		var cookies_enabled = ( 'undefined' !== navigator.cookieEnabled && navigator.cookieEnabled ) ? true : null;

		if ( ! cookies_enabled ) {
			document.cookie = '__wpcomgeo_testcookie=1';
			if ( -1 !== document.cookie.indexOf( '__wpcomgeo_testcookie=1' ) ) {
				cookies_enabled = true;
			}
			var expired_date = new Date( 2003, 5, 27 );
			document.cookie = '__wpcomgeo_testcookie=1;expires=' + expired_date.toUTCString();
		}

		return cookies_enabled;
	}
}
