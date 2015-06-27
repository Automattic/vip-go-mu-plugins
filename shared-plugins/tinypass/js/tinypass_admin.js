var tinypass = {

	doError:function(fieldName, msg){
		jQuery("#tp-error").append("<p> &bull; " + msg + "</p>");
		jQuery('*[name="'+fieldName+'"]').addClass("form-invalid tp-error");
	},

	log:function(msg){
		if(typeof window.console != 'undefined' && console.log)
			console.log(msg);
	}

}