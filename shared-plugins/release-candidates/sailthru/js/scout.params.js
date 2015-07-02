(function( $ ) {

	"use strict";

	jQuery(function() {
	    
		jQuery(document).ready( function() {

		    if( "object" == typeof(Scout) ) {

			    if( Scout.sailthru_scout_is_on ) {
			    	
			    	var numVisible = 10;
			    	if( "undefined" !== typeof(Scout.sailthru_scout_numVisible) ) {
			    		numVisible = Scout.sailthru_scout_numVisible;
			    	}

			    	var includeConsumed = false;
			    	if( "undefined" !== typeof(Scout.sailthru_scout_includeConsumed) ) {
			    		includeConsumed = Scout.sailthru_scout_includeConsumed;
			    	}

			    	var noPageview = true
			    	if( "undefined" !== typeof(Scout.sailthru_scout_noPageview) ) {
			    		noPageview = Scout.sailthru_scout_noPageview;
			    	}

			    	var renderItem = '';
			    	if( "undefined" !== typeof(Scout.sailthru_scout_renderItem) ) {
			    		renderItem = Scout.sailthru_scout_renderItem;
			    	}	    		    	

			    } else {

			        // Hide "Loading, please wait..." if there's no content
			        $("#sailthru-scout").hide();
			        
			    	
			    }	

			    
				SailthruScout.setup({			
		            'domain': Horizon.sailthru_horizon_domain, 		// this should already be available to us
		            'numVisible': numVisible,
		            'includeConsumed': includeConsumed,
		            'noPageview': noPageview,
		            'renderItem': function( renderItem, pos ) {
		            	return renderItem;
		            }
		        });	

		        // Hide "Loading, please wait..." if there's no content
		        if(SailthruScout.allContent.length == 0) {
		        	$("#sailthru-scout").hide();
		        }
				

	        }        

		});

	});


}(jQuery));