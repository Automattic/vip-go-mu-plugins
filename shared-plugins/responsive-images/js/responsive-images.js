(function($) {
	$( document ).ready( responsive_images_init );
	$( 'body' ).bind( 'post-load', responsive_images_init ); // Work with WP.com infinite scroll

	function responsive_images_init() {
		var $window = $(window),
			screen_width = $window.width(),
			screen_height = $window.height();

		jQuery( 'img[data-full-src]' ).each( function( i ) {
			var img = this,
				$img = $(img),
				src = $img.data( 'full-src' ),
				max_width = $img.data( 'full-width' ),
				max_height = $img.data( 'full-height' ),
				size_ratio = max_height / max_width,
				crop = (src.indexOf('crop=1') != -1),
				img_width,
				img_height;

			$img.hide()
				.data( 'responsive-loaded', 'true' );

			// if the image doesn't have a given dimension, set to screen dimension.
			// if the image does have a dimension, set to screen if image dimension is bigger, 
			// proportionately if the crop value is passed in. otherwise default to image dimension.
			img_width = ! max_width || max_width > screen_width ? screen_width : max_width;

			if(max_height && max_width && crop)
				img_height = parseInt( (img_width == max_width) ? max_height : size_ratio * img_width );
			else if(max_height)
				img_height = max_height;

			if ( img_width )
				src = responsive_add_query_arg( 'w', img_width, src );

			if ( img_height )
				src = responsive_add_query_arg( 'h', img_height, src );

			img.src = src;

			$img.fadeIn(); // bring it in smooth and super sexy-like
		} );
	}
})(jQuery);

// This is a lame and fragile way to add params to a URL but it works for now
// by annakata at http://stackoverflow.com/a/487049/169478
function responsive_add_query_arg( key, value, url ) {
    key = escape( key );
	value = escape( value );

    var kvp = url.split( '&' ),
		i = kvp.length,
		x;

	while(i--) {
		x = kvp[i].split('=');

		if ( x[0] == key ) {
				x[1] = value;
				kvp[i] = x.join( '=' );
				break;
		}
	}

    if( i < 0 )
		kvp[kvp.length] = [key,value].join( '=' );

	url = kvp.shift();
	if( -1 == url.indexOf( '?' ) && kvp.length )
		url += '?';
	else if ( kvp.length )
		url += '&';

    url += kvp.join( '&' );

	return url;
}
