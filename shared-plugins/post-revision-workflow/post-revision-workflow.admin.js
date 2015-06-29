jQuery(document).ready( function( $ ) {
	$('.edit-post-modification-notification').click( function() { 
		$('#post-modification-notification').show( 'fast' ); 
		return false; 
	} );
	$('input[name="dpn_notify"]').on( 'click change', function() {
		var dpn_notify_what = $('input[name="dpn_notify"]:checked').val();
		if( dpn_notify_what == '2' || dpn_notify_what == '1' ) {
			$('#dpn-address-field').show();
		} else {
			$('#dpn-address-field').hide();
		}
	} );
	$('.save-post-modification-notification').click( function() { 
		$('#post-modification-notification').hide( 'fast' ); 
		var dpn_notify_what = $('input[name="dpn_notify"]:checked').val();
		if( dpn_notify_what == '1' || dpn_notify_what == '2' ) {
			if( $('input[name="dpn_notify_address"]').val() == '' ) {
				$('#post-modification-notification-status').addClass( 'warning' );
			} else {
				$('#post-modification-notification-status').removeClass( 'warning' );
			}
			if( dpn_notify_what == '2' ) {
				$('#post-modification-notification-status').html( post_revision_workflow.publish_notify );
			} else if( dpn_notify_what == '1' ) {
				$('#post-modification-notification-status').html( post_revision_workflow.draft_notify );
			}
		} else {
			$('#post-modification-notification-status').removeClass( 'warning' );
			if( dpn_notify_what == '3' ) {
				$('#post-modification-notification-status').html( post_revision_workflow.draft_only );
			} else {
				$('#post-modification-notification-status').html( post_revision_workflow.no_notifications );
			}
		}
		return false;
	} );
} );
