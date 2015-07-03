jQuery(function($){
$("#contextual-help-link").click(function(){
	if( ! $(this).hasClass("screen-meta-active") ) {
		fill_history_box();
	}
});

});
function history_createCookie(name, value ) {
	var expires = '';
	document.cookie = name + "=" + value + ";path=/";
}
function history_readCookie(name) {
		var nameEQ = name + "=";
		var ca = document.cookie.split(';');
		for (var i = 0; i < ca.length; i++) {
			var c = ca[i];
			while (c.charAt(0) == ' ') {
				c = c.substring(1, c.length);
			}
			if (c.indexOf(nameEQ) == 0) {
				return c.substring(nameEQ.length, c.length);
			}
		}
		return null;
}

function AddPost_ID( id ) {
	var cookie_name = "history_" + typenow;
	var old_cookie = history_readCookie( cookie_name );
	if( ! old_cookie ) {
		old_cookie = "";
	}
	if( old_cookie.indexOf("|"+ id + "|") > -1 ) {
		old_cookie = old_cookie.replace( "|"+ id + "|", "" );
	} 
	old_cookie += "|" +id + "|";
	//limit the number of visited posts to 10 as that's what the backend usually fetches by default
	if ( old_cookie.match( /\|\d+\|/g ).length > 10 ) {
		old_cookie = old_cookie.match( /\|\d+\|/g ).slice(-10).join("");
	}
	history_createCookie( cookie_name, old_cookie );
}
function fill_history_box(  ) {
	var cookie_name = "history_" + typenow;
	var old_cookie = history_readCookie( cookie_name );
	if( !old_cookie ) {
		return;
	}
	var src = "<img src='"+history_bar_vars.image_src+"' />";
	jQuery("#draw_history").html(src);
	var history_nonce_val = history_bar_vars.nonce;
	jQuery.get( ajaxurl,
				{ action: "user_cookie_history", post_type : typenow, id_list : old_cookie, history_nonce: history_nonce_val },
				 function(data){ 
				 	 jQuery("#draw_history").html(data);
				 } 
			);
}
