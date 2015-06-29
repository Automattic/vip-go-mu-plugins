(jQuery)(document).ready(function(){

	// Site Settings - tags_toggle_triger
	(jQuery)( ".tags_toggle_triger" ).change( function() {
		if ( (jQuery)(this).prop( "checked" ) ) {
			(jQuery)( '.tags_toggle' ).show();
		} else {
			(jQuery)( '.tags_toggle' ).hide();
		}
	}).change();

	// Feedback - submit form
	(jQuery)( "#playbuzz_feedback_form input#submit" ).click( function () {

		if ( ( (jQuery)( "input[name='fullName']" ).val().length > 0 ) && ( (jQuery)( "input[name='email']" ).val().length > 0 ) && ( (jQuery)( "textarea[name='message']" ).val().length > 0 ) ) {

			(jQuery).ajax({
				type    : "POST",
				url     : "http://www.playbuzz.com/contactus",
				data    : (jQuery)( "#playbuzz_feedback_form" ).serialize(),
				success : function ( data ) {
					(jQuery)( ".playbuzz_feedback_message p" ).text( translation.feedback_sent );
					(jQuery)( ".playbuzz_feedback_message" ).removeClass( "error" ).addClass( "updated" );
				},
				error   : function ( data ) {
					(jQuery)( ".playbuzz_feedback_message p" ).text( translation.feedback_error );
					(jQuery)( ".playbuzz_feedback_message" ).removeClass( "updated" ).addClass( "error" );
				}
			});

		} else {

			(jQuery)( ".playbuzz_feedback_message p" ).text( translation.feedback_missing_required_fields );
			(jQuery)( ".playbuzz_feedback_message" ).removeClass( "updated" ).addClass( "error" );

		}

	});

});
