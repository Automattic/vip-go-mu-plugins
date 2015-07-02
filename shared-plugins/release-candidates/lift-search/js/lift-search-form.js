/*global jQuery */

/**
 * Manages JS-Enhanced search form
 * @module LiftSearchForm
 */
(function ($) {
  $('.no-js').removeClass('no-js');

  $('.lift-filter-expand, .lift-filter-collapse').on('click', function() {
    $(this).parent('ul').toggleClass('expanded');
  });

})(jQuery);