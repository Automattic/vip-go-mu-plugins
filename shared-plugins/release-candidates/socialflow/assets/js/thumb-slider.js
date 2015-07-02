/*
 * FeatureList - simple and easy creation of an interactive "Featured Items" widget
 * Examples and documentation at: http://jqueryglobe.com/article/feature_list/
 * Version: 1.0.0 (01/09/2009)
 * Copyright (c) 2009 jQueryGlobe
 * Licensed under the MIT License: http://en.wikipedia.org/wiki/MIT_License
 * Requires: jQuery v1.3+
*/
(function($) {
	$.fn.featureList = function(options) {
		var slides	= $(this)
		new jQuery.featureList(slides, options)
		return this
	}

	$.featureList = function(slides, options) {
		var total_items  = slides.length,
			visible_item = options.start_item || 0,
			nav_next     = ( options.nav_next instanceof jQuery ) ? options.nav_next : $(options.nav_next),
			nav_prev     = ( options.nav_prev instanceof jQuery ) ? options.nav_prev : $(options.nav_prev);

		// console.log(nav_next);

		// Hide all slides
		slides.hide().eq( visible_item ).show().trigger('slide');

		nav_next.click(function(){
			slide()
		})

		nav_prev.click(function(){
			slide(visible_item - 1)
		})
		// Main Slide method
		function slide(nr) {
			if (typeof nr == "undefined") {
				nr = visible_item + 1
				nr = ( nr >= total_items ) ? 0 : nr
			}
			if (nr < 0)	{
				nr = total_items - 1
			}
			slides.filter( ':visible' ).hide()
			slides.eq( nr ).show().trigger('slide')
			visible_item = nr
		}
	}
})(jQuery);