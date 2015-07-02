/*global jQuery */

/**
 * Manages JS-Enhanced search form
 * @module LiftSearchForm
 */
(function ($) {
  var toggleExpanded = function() {
    $(this).parent('ul').toggleClass('expanded');
  };
  
	"use strict";
  $('.no-js').removeClass('no-js');
  
  $('.lift-filter-expand, .lift-filter-collapse').on('click', toggleExpanded);
  
})(jQuery);