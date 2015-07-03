/*
	HUMANIZED MESSAGES 1.0
	idea - http://www.humanized.com/weblog/2006/09/11/monolog_boxes_and_transparent_messages
	home - http://humanmsg.googlecode.com
*/

var humanMsg = {
	setup: function(appendTo, logName, msgOpacity) {
		humanMsg.msgID = 'humanMsg';
		humanMsg.logID = 'humanMsgLog';

		// appendTo is the element the msg is appended to
        appendTo = 'body';

		// The text on the Log tab
		if (logName == undefined) {
			if (humanMsg.logTitle == undefined) {
				logName = 'Message Log';
			} else {
				logName = humanMsg.logTitle;
			}
		}
		
		humanMsg.logTop = jQuery('#'+humanMsg.logID).css("top");

		// Opacity of the message
		humanMsg.msgOpacity = 0.9;

		if (msgOpacity != undefined) 
			humanMsg.msgOpacity = parseFloat(msgOpacity);

		// Inject the message structure
		jQuery(appendTo).prepend('<div id="'+humanMsg.msgID+'" class="humanMsg"><div class="round"></div><p></p><div class="round"></div></div>');
		jQuery(appendTo).append('<div id="'+humanMsg.logID+'"><p>'+logName+'</p><a href="#" id="humanMsgClose">x</a><ul></ul></div>');

        jQuery('#humanMsgClose').click(
            function() {
                jQuery('#'+humanMsg.logID+' p').addClass('minimized');
                jQuery('#humanMsgClose').hide();
                return false;
        });

		jQuery('#'+humanMsg.logID+' p').click(
            function() {
                humanMsg.activateLog();

                if (jQuery('#'+humanMsg.logID+' p').hasClass('minimized')) {
                    jQuery('#humanMsgClose').show();
                    jQuery('#'+humanMsg.logID+' p').removeClass('minimized');
                    return;
                } else if (jQuery(this).siblings('ul').css('display') != 'none') {
                    jQuery('#'+humanMsg.logID).css("top", humanMsg.logTop);
					jQuery(this).siblings('ul').hide();
                    jQuery('#humanMsgClose').show();
				} else {
                    humanMsg.setLogHeight();
					jQuery(this).siblings('ul').slideToggle();
                    jQuery('#humanMsgClose').slideToggle();
				}
			}
		)
	},
	
	setLogTitle: function(/*string*/ title) {
		 humanMsg.logTitle = title;
		 jQuery("#" + humanMsg.logID + " > p").text(title);
	},

	displayMsg: function(msg, /*boolean*/doLog) {
		if (msg == '')
			return;
        
        humanMsg.activateLog();

        clearTimeout(humanMsg.t2);
        
		/*
		 * IE doesn't really support CSS fixed position so we need to set the position manually
		 */
		jQuery('#' + humanMsg.msgID).css('top', (jQuery(window).scrollTop() + 75) + 'px');

		// Inject message
		jQuery('#'+humanMsg.msgID+' p').html(msg)
	
		// Show message
		jQuery('#'+humanMsg.msgID+'').show().animate({ opacity: humanMsg.msgOpacity}, 200, function() {
			if (doLog) {
				humanMsg.log(msg);
			}
			
		});

        /*
         * If we are starting a new message and have an existing
         * message then we want to restart the timers so the 
         * existing message doesn't get hidden very fast.
         */
        if (humanMsg.t1) {
            clearTimeout(humanMsg.t1);
        }

        if (humanMsg.t2) {
            clearTimeout(humanMsg.t2);
        }

		// Watch for mouse & keyboard in .5s
		humanMsg.t1 = setTimeout(function() {humanMsg.bindEvents();}, 3000);
		// Remove message after 5s
		humanMsg.t2 = setTimeout(function() {humanMsg.removeMsg();}, 5000);
	},
	
	log: function(msg) {
		 jQuery('#'+humanMsg.logID)
			.show().children('ul').prepend('<li>'+msg+'</li>')	// Prepend message to log
			.children('li:first').slideDown(200)				// Slide it down
		 
		 if (jQuery('#'+humanMsg.logID+' ul').css('display') == 'none') {
			 jQuery('#'+humanMsg.logID+' p').animate({ bottom: 40 }, 200, 'linear', function() {
				jQuery(this).animate({ bottom: 0 }, 300, 'swing', function() { jQuery(this).css({ bottom: 0 }) })
			})
		 } else {
			 humanMsg.setLogHeight();
		 }

         jQuery('#humanMsgClose').show();
         jQuery('#'+humanMsg.logID+' p').removeClass('minimized');
	},

    activateLog: function() {
         jQuery('#'+humanMsg.logID+' p').removeClass("faded");

         clearTimeout(humanMsg.t3);

         //fadeout the error log after 15s
         humanMsg.t3 = setTimeout(humanMsg.fadeLog, 15000)
    },
	
	setLogHeight: function() {
         /*
		  * When items are added to the log we want to adjust the top of the log (where
		  * the pull tab goes) and the height of the log list dynamically so the log is
		  * always a reasonable size.
		  */
         if (navigator.appName === "Microsoft Internet Explorer") {
             /*
              * IE just calculates the size of these list items differently thn every 
              * other browser.  There isn't a good cross-browser way to set the height,
              * so we tweak it for IE.
              */
             var height = Math.min(jQuery('#'+humanMsg.logID + ' ul').children('li').length * 48, 112);
             jQuery('#'+humanMsg.logID + ' ul').css("height", (height + 10) + "px");
		 
             jQuery('#'+humanMsg.logID).css("top", "-" + ((height) + 56 + humanMsg.logTop) + "px");
         } else {
             var height = Math.min(jQuery('#'+humanMsg.logID + ' ul').children('li').length * 14, 56);
             jQuery('#'+humanMsg.logID + ' ul').css("height", (height + 10) + "px");
             jQuery('#'+humanMsg.logID).css("top", "-" + ((height) + 112 + humanMsg.logTop) + "px");
         }

		 
	},

	bindEvents: function() {
	// Remove message if mouse is moved or key is pressed
		jQuery(window)
			.mousemove(humanMsg.removeMsg)
			.click(humanMsg.removeMsg)
			.keypress(humanMsg.removeMsg)
	},

	removeMsg: function() {
         // Unbind mouse & keyboard
		jQuery(window)
			.unbind('mousemove', humanMsg.removeMsg)
			.unbind('click', humanMsg.removeMsg)
			.unbind('keypress', humanMsg.removeMsg)

        jQuery('#'+humanMsg.msgID).fadeOut();
	},

    fadeLog: function() {
         if(jQuery('#'+humanMsg.logID+' ul').css('display') === "none") {
             jQuery('#'+humanMsg.logID+' p').addClass("faded");
         }
    }
};

jQuery(document).ready(function(){
	humanMsg.setup();
})
