jQuery( function($) {
	$( '.fu-upload-form' ).validate({
		submitHandler: function(form) {
		form.submit();
		}
	});
});