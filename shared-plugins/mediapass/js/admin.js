jQuery(document).ready(function($){
	
	// Show/hide form inputs based on subscription model selection on Price Points view
	$('#subscription-model').bind('change', function(e) {
		switch ($(e.target).val()) {
			case 'membership':
				$('div#membership-wrapper').show();
				$('div#single-wrapper').hide();
				break;

			case 'single':
				$('div#membership-wrapper').hide();
				$('div#single-wrapper').show();
				break;
		}
	});
	
	// Benefits logo media management
	$('#upload_image_button').click(function() {
		 formfield = $('#upload_image').attr('name');
		 tb_show('', 'media-upload.php?type=image&amp;TB_iframe=true');
		 return false;
	});
 var wp_send_to_editor = window.send_to_editor;	window.send_to_editor = function(html) {
 wp_send_to_editor(html);		 imgurl = $('img',html).attr('src');
		 $('#upload_image').val(imgurl);
		 tb_remove();
	}
	
	// Redirect based on time period on Summary Report view
	$('#period').bind('change', function(e){
		
		// strip off all GET params, assumes there will be no required GET params beyond 'page'
		if (window.location.href.indexOf("period") != -1) {
			window.location = window.location.href.substring(0, window.location.href.indexOf('&')) + '&period=' + $(this).val();
		} else {
			window.location = window.location.href + '&period=' + $(this).val();
		}
		
	});
	
	// Validate the Benefit logo image (must be .jpeg)
	$('form#benefits-form').submit(function(e){
		if ($('#upload_image').val() != '' && /[^.]+$/.exec($('#upload_image').val()).toLowerCase()  != 'jpeg' && /[^.]+$/.exec($('#upload_image').val()).toLowerCase()  != 'jpg'){
			alert("Benefits logo must be a JPEG (.jpeg) image file.");
			return false;
		};
	});
	
	
});
