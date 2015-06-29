function correct_options(){
	val = jQuery('select[name="gce_options[use_builder]"]').val();

	if(val == 'true'){
		jQuery('.gce-simple-display-options').next('.form-table').hide();
		jQuery('.gce-simple-display-options').hide();
		jQuery('.gce-simple-display-options').prev('h3').hide();

		jQuery('.gce-event-builder').next('.form-table').show();
		jQuery('.gce-event-builder').show();
		jQuery('.gce-event-builder').prev('h3').show();
	}else{
		jQuery('.gce-simple-display-options').next('.form-table').show();
		jQuery('.gce-simple-display-options').show();
		jQuery('.gce-simple-display-options').prev('h3').show();

		jQuery('.gce-event-builder').next('.form-table').hide();
		jQuery('.gce-event-builder').hide();
		jQuery('.gce-event-builder').prev('h3').hide();
	}
}

jQuery(document).ready(function(){
	correct_options();

	jQuery('select[name="gce_options[use_builder]"]').change(function(e){
		correct_options();
	});
});