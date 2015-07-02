(function ($) {
	"use strict";
	$(function () {

		// when a user clicks subscribe
		$("#sailthru-add-subscriber-form").submit( function( e ){

			e.preventDefault();

			var post_to_file = $("#sailthru_ajax_php").val();
			var user_input = $(this).serialize();

			$.post(
				post_to_file,
				user_input,
				function(data) {
					data = jQuery.parseJSON(data);
					if( data.error == true ) {
						$("#sailthru-add-subscriber-errors").html(data.message);
					} else {
						$("#sailthru-add-subscriber-form").html('Thank you for subscribing.');
					}

				}
			);

		});


	});
}(jQuery));
