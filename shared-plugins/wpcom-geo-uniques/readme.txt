== WP.com Geo ==

Batcache-friendly way to handle geo-targetting of users at a specific set of locations.

Note that this should only be used with a very small list of locations for performance reasons.

= Usage: Simple Mode =

```
wpcom_vip_load_plugin( 'wpcom-geo-uniques' );
wpcom_geo_add_location( 'us' ); // add list of supported countries

if ( 'us' == wpcom_geo_get_user_location() ) {
	echo "USA: A-Okay!";
} else {
	echo "You're not American! No soup for you!";
}
```

= Usage: Advanced Mode =

By default, geo-location happens at a country-level but this can be extended to cities and states in advanced mode. This requires

It requires three pieces:
- enabling advanced mode
- defining your location groups
- writing some js that handles group assignment based on the user's geolocation data

In your theme:

```
// Explicitly enable advanced mode 
add_filter( 'wpcom_geo_simple_mode', '__return_false', 999 );

// Return the region value for use in the cache key
wpcom_geo_add_location( 'san-francisco' );
wpcom_geo_set_default_location( 'national' );

// This is the URL to the js file where we will handle our location assignment
add_filter( 'wpcom_geo_client_js_src', function( $url ) {
	return get_template_directory_uri() . '/js/geo.js';
} );
```

In the JS file:

```
wpcom_geo.set_detect_success_callback( function( geo_data ) {
	// Figure out the user's location based on the returned data.
	// geo_data is an object that includes:
	//  - latitude
	//  - longitude
	//  - country_short
	//  - country_long
	//  - region
	//  - city

	if ( 'SAN FRANCISCO' === geo_data.city ) {
		return 'san-francisco';
	}

	return false; // default

} );

wpcom_geo.set_detect_error_callback( function() {
	// TODO: handle error here; default location is already set.
} );

// Kicks things off;
wpcom_geo.detect();
```

== Changelog ==

= 0.3 =

- Simple and Advanced modes
- simple mode is purely country-based and relies on server-level vars set by nginx
- advanced mode allows for sites to control geolocation set via a JS API

= 0.2 =

- Props Zack Tollman and 10up
- Support for more granular location sets (e.g. city-level)
- New filters for tweaks (wpcom_geo_uniques_return_data, wpcom_geo_gelocate_js_query_args, etc.)
- Fallback data for local testing
 
