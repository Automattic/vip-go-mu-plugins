function safe_report_comments_flag_comment( comment_id, nonce, result_id ) {
	jQuery.post( 
		SafeCommentsAjax.ajaxurl,
		{
			comment_id : comment_id,
			sc_nonce : nonce,
			result_id : result_id,
			action : 'safe_report_comments_flag_comment',
			xhrFields: {
				withCredentials: true
			}
		},
		function(data) { jQuery( '#'+result_id).html(data); }
	);
	return false;
}

jQuery( document ).ready( function() {
	jQuery( '.hide-if-js' ).hide();
	jQuery( '.hide-if-no-js' ).show();
});
