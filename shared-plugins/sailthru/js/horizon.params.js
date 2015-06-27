(function( $ ) {

	"use strict";

	jQuery(function() {

		jQuery(document).ready( function() {

			if("object" == typeof Concierge) {		    

			    if( Concierge.sailthru_concierge_is_on ) {
			    	
			    	var from = 'top';
			    	if( "undefined" !== typeof(Concierge.sailthru_concierge_from) ) {
			    		from = Concierge.sailthru_concierge_from;
			    	} 

			    	var delay = '0';
			    	if( "undefined" !== typeof(Concierge.sailthru_concierge_delay) ) {
			    		delay = Concierge.sailthru_concierge_delay;
			    	}

			    	var threshold = '500';
			    	if( "undefined" !== typeof(Concierge.sailthru_concierge_threshold) ) {
			    		threshold = Concierge.sailthru_concierge_threshold;
			    	}

			    	var offsetBottom = 20;
			    	if( "undefined" !== typeof(Concierge.sailthru_concierge_offsetBottom) ) {
			    		offsetBottom = Concierge.sailthru_concierge_offsetBottom;
			    	}

			    	var cssPath = 'https://ak.sail-horizon.com/horizon/recommendation.css';
			    	if( "undefined" !== typeof(Concierge.sailthru_concierge_cssPath) ) {
			    		cssPath = Concierge.sailthru_concierge_cssPath;
			    	}	    	

			    	var filter = {};
			    	if( "undefined" !== typeof(Concierge.sailthru_concierge_filter) ){
			    		filter = {
			    			'tags': Concierge.sailthru_concierge_filter
			    		}	
			    	}	 

				    
			    	// conierge is turned on. make it so
				    if (window.Sailthru) {
				        Sailthru.setup({
				            domain:  Horizon.sailthru_horizon_domain,
				            concierge: {
				            	'from': from,
				            	'delay': delay,
				            	'threshold': threshold,
				            	'offsetBottom': offsetBottom,
				            	'cssPath': cssPath,
				            	'filter': filter
				            }
				        });
				    }		    	   		    	

			    } else {

			    	// concierge is not turned on. different setup
				    if (window.Sailthru) {
				        Sailthru.setup({
				            domain:  Horizon.sailthru_horizon_domain
				        });
				    }

				} // end if Concierge is on

			} // end if Concierge is an object
		    
		});

	});


}(jQuery));