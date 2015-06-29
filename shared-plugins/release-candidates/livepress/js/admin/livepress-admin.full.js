/*! livepress -v1.3
 * http://livepress.com/
 * Copyright (c) 2015 LivePress, Inc.
 */
var Livepress = Livepress || {};

/**
 * Returns object or {} depending if it exists
 * @param object
 */
Livepress.ensureExists = function (object) {
	if (object === undefined) {
		object = {};
	}
	return object;
};

/***************** Utility functions *****************/

// Prevent extra calls to console.log from throwing errors when the console is closed.
var console = console || { log: function () { } };

// Get the permalink for a given update
// The url is used for sharing with an anchor link to the update and
// works for Open Graph and Twitter Cards:
Livepress.getUpdatePermalink = function (update_id) {
	var re = /livepress-update-([0-9]+)/,
		id = re.exec(update_id)[1],
		lpup = '',
		post_link = jQuery(location).attr('protocol') + '//' + jQuery(location).attr('host') + jQuery(location).attr('pathname'),
		query = jQuery(location).attr('search'),
		pid;

	// Check url depending on permalink (default / nice urls)
	if ( null !== ( pid = query.match(/\?p=[0-9]+/) ) ){
		post_link += pid + "&";
	} else {
		post_link += "?";
	}

	lpup += "lpup=";
	return post_link + lpup + id + "#" + update_id;
};
Livepress.updateShortlinksCache = window.LivepressConfig.shortlink || {};
Livepress.getUpdateShortlink = function (upd) {
	var re = /livepress-update-([0-9]+)/,
	update_id = re.exec(upd)[1];
	if( !( update_id in Livepress.updateShortlinksCache ) ) {

		return jQuery.ajax({
			url: window.LivepressConfig.ajax_url,
			xhrFields: {
				withCredentials: true
			},
			type: 'post',
			async: false,
			dataType: 'json',
			data: {
				'action': 'lp_update_shortlink',
				'post_id': window.LivepressConfig.post_id,
				'_ajax_nonce': LivepressConfig.lp_update_shortlink_nonce,
				'update_id': update_id
			}
		}).promise();


	} else {
		return Livepress.updateShortlinksCache[update_id];
	}
};

/*
 * Parse strings date representations into a real timestamp.
 */
String.prototype.parseGMT = function (format) {
	var date = this,
		formatString = format || "h:i:s a",
		parsed,
		timestamp;

	parsed = Date.parse(date.replace(/-/gi, "/"), "Y/m/d H:i:s");
	timestamp = new Date(parsed);

	// fallback to original value when invalid date
	if (timestamp.toString() === "Invalid Date") {
		return this.toString();
	}

	timestamp = timestamp.format(formatString);
	return timestamp;
};

/*
 * Needed for the post update
 */
String.prototype.replaceAll = function (from, to) {
	var str = this;
	str = str.split(from).join(to);
	return str;
};

// Ensure we have a twitter handler, even when the page starts with no embeds
// because they may be added later. Corrects issue where twitter embeds failed on live posts when
// original page load contained no embeds.
if ( 'undefined' === typeof window.twttr ) {
	window.twttr = (function (d,s,id) {
						var t, js, fjs = d.getElementsByTagName(s)[0];
						if (d.getElementById(id)) { return; } js=d.createElement(s); js.id=id;
						js.src="https://platform.twitter.com/widgets.js"; fjs.parentNode.insertBefore(js, fjs);
						return window.twttr || (t = { _e: [], ready: function(f){ t._e.push(f); } });
					}(document, "script", "twitter-wjs"));
}

// Hack for when a user loads a post from a permalink to an update and
// twitter embeds manipulate the DOM and generate a "jump"
if( window.location.hash ){
    twttr.ready( function ( twttr ) {
        twttr.events.bind( 'loaded', function (event) {
            jQuery.scrollTo( jQuery(window.location.hash) );
        });
    });
}
jQuery.fn.getBg = function () {
	var $this = jQuery(this),
		actual_bg, newBackground, color;
	actual_bg = $this.css('background-color');
	if (actual_bg !== 'transparent' && actual_bg !== 'rgba(0, 0, 0, 0)' && actual_bg !== undefined) {
		return actual_bg;
	}

	newBackground = this.parents().filter(function () {
		//return $(this).css('background-color').length > 0;
		color = $this.css('background-color');
		return color !== 'transparent' && color !== 'rgba(0, 0, 0, 0)';
	}).eq(0).css('background-color');

	if (!newBackground) {
		$this.css('background-color', '#ffffff');
	} else {
		$this.css('background-color', newBackground);
	}
};

jQuery.extend({
	getUrlVars:function (loc) {
		var vars = [], hash;
		var href = (loc.href || window.location.href);
		var hashes = href.slice(href.indexOf('?') + 1).split('&');
		for (var i = 0; i < hashes.length; i++) {
			hash = hashes[i].split('=');
			vars.push(hash[0]);
			vars[hash[0]] = hash[1];
		}
		return vars;
	},
	getUrlVar: function (name, loc) {
		return jQuery.getUrlVars(loc || false)[name];
	}
});

jQuery.fn.outerHTML = function (s) {
	return (s) ? this.before(s).remove() : jQuery("<div>").append(this.eq(0).clone()).html();
};

jQuery.fn.autolink = function () {
	return this.each(function () {
		var re = new RegExp('((http|https|ftp)://[\\w?=&./-;#~%-]+(?![\\w\\s?&./;#~%"=-]*>))', "g");
		jQuery(this).html(jQuery(this).html().replace(re, '<a href="$1">$1</a> '));
	});
};

if (typeof document.activeElement === 'undefined') {
	jQuery(document)
		.focusin(function (e) {
			document.activeElement = e.target;
		})
		.focusout(function () {
			document.activeElement = null;
		});
}
jQuery.extend(jQuery.expr[':'], {
	focus:function (element) {
		return element === document.activeElement;
	}
});
Date.prototype.format = function (format) {
	var i, curChar,
		returnStr = '',
		replace = Date.replaceChars;
	for (i = 0; i < format.length; i++) {
		curChar = format.charAt(i);
		if (replace[curChar]) {
			returnStr += replace[curChar].call(this);
		} else {
			returnStr += curChar;
		}
	}
	return returnStr;
};
Date.replaceChars = {
	shortMonths:['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
	longMonths: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
	shortDays:  ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
	longDays:   ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
	d:          function () {
		return (this.getDate() < 10 ? '0' : '') + this.getDate();
	},
	D:          function () {
		return Date.replaceChars.shortDays[this.getDay()];
	},
	j:          function () {
		return this.getDate();
	},
	l:          function () {
		return Date.replaceChars.longDays[this.getDay()];
	},
	N:          function () {
		return this.getDay() + 1;
	},
	S:          function () {
		return (this.getDate() % 10 === 1 && this.getDate() !== 11 ? 'st' : (this.getDate() % 10 === 2 && this.getDate() !== 12 ? 'nd' : (this.getDate() % 10 === 3 && this.getDate() !== 13 ? 'rd' : 'th')));
	},
	w:          function () {
		return this.getDay();
	},
	z:          function () {
		return "Not Yet Supported";
	},
	W:          function () {
		return "Not Yet Supported";
	},
	F:          function () {
		return Date.replaceChars.longMonths[this.getMonth()];
	},
	m:          function () {
		return (this.getMonth() < 9 ? '0' : '') + (this.getMonth() + 1);
	},
	M:          function () {
		return Date.replaceChars.shortMonths[this.getMonth()];
	},
	n:          function () {
		return this.getMonth() + 1;
	},
	t:          function () {
		return "Not Yet Supported";
	},
	L:          function () {
		return (((this.getFullYear() % 4 === 0) && (this.getFullYear() % 100 !== 0)) || (this.getFullYear() % 400 === 0)) ? '1' : '0';
	},
	o:          function () {
		return "Not Supported";
	},
	Y:          function () {
		return this.getFullYear();
	},
	y:          function () {
		return ('' + this.getFullYear()).substr(2);
	},
	a:          function () {
		return this.getHours() < 12 ? 'am' : 'pm';
	},
	A:          function () {
		return this.getHours() < 12 ? 'AM' : 'PM';
	},
	B:          function () {
		return "Not Yet Supported";
	},
	g:          function () {
		return this.getHours() % 12 || 12;
	},
	G:          function () {
		return this.getHours();
	},
	h:          function () {
		return ((this.getHours() % 12 || 12) < 10 ? '0' : '') + (this.getHours() % 12 || 12);
	},
	H:          function () {
		return (this.getHours() < 10 ? '0' : '') + this.getHours();
	},
	i:          function () {
		return (this.getMinutes() < 10 ? '0' : '') + this.getMinutes();
	},
	s:          function () {
		return (this.getSeconds() < 10 ? '0' : '') + this.getSeconds();
	},
	e:          function () {
		return "Not Yet Supported";
	},
	I:          function () {
		return "Not Supported";
	},
	O:          function () {
		return ( -this.getTimezoneOffset() < 0 ? '-' : '+') + (Math.abs(this.getTimezoneOffset() / 60) < 10 ? '0' : '') + (Math.abs(this.getTimezoneOffset() / 60)) + '00';
	},
	P:          function () {
		return ( -this.getTimezoneOffset() < 0 ? '-' : '+') + (Math.abs(this.getTimezoneOffset() / 60) < 10 ? '0' : '') + (Math.abs(this.getTimezoneOffset() / 60)) + ':' + (Math.abs(this.getTimezoneOffset() % 60) < 10 ? '0' : '') + (Math.abs(this.getTimezoneOffset() % 60));
	},
	T:          function () {
		var result, m;
		m = this.getMonth();
		this.setMonth(0);
		/*jslint regexp:false*/
		result = this.toTimeString().replace(/^.+ \(?([^\)]+)\)?$/, '$1');
		/*jslint regexp:true*/
		this.setMonth(m);
		return result;
	},
	Z:          function () {
		return -this.getTimezoneOffset() * 60;
	},
	c:          function () {
		return this.format("Y-m-d") + "T" + this.format("H:i:sP");
	},
	r:          function () {
		return this.toString();
	},
	U:          function () {
		return this.getTime() / 1000;
	}
};
/**
 * Object containing methods pertaining to instance message integration.
 *
 * @namespace
 * @type {Object}
 */
var ImIntegration = {
	CHECK_TIMEOUT_SECONDS: 5,
	CHECK_TIMES:           5
};

/**
 * Check the status of the specified protocol
 *
 * @memberOf ImIntegration
 * @param {String} protocol Protocol to check.
 * @see ImIntegration.__check_status
 */
ImIntegration.check_status = function (protocol) {
	ImIntegration.__check_status(protocol, ImIntegration.CHECK_TIMES);
};

/**
 * Create HTML markup for a loading spinnter.
 *
 * @memberOf ImIntegration
 * @return {Object} jQuery object containing the spinner.
 * @private
 */
ImIntegration.__spin_loading = function () {
	var image_path = LivepressConfig.lp_plugin_url + '/img/spin.gif',
		html_image = jQuery("<img />").attr('src', image_path);

	return html_image;
};

/**
 * Check the status of a specific protocol several times.
 *
 * @memberOf ImIntegration
 * @param {String} protocol Protocol to check.
 * @param {number} tries Number of times to test the protocol.
 * @private
 */
ImIntegration.__check_status = function (protocol, tries) {
	var params = {},
		$check_button = jQuery("#check_" + protocol),
		$check_message = jQuery("#check_message_" + protocol),
		admin_ajax_url = LivepressConfig.site_url + '/wp-admin/admin-ajax.php';

	params.action = "lp_im_integration";
	params._ajax_nonce = LivepressConfig.ajax_lp_im_integration;
	params.im_integration_check_status = true;
	params.im_service = protocol;

	//console.log("Start status check to: protocol=" + protocol);

	$check_button.hide();

	$check_message.css({ 'color': 'black' }).html(ImIntegration.__spin_loading());

	jQuery.post(admin_ajax_url, params, function (response, code) {
		var json_response = JSON.parse(response),
			show_button = false,
			error_msg = "",
			reason;

		if ((json_response.status === 'not_found' ||
			json_response.status === 'offline' ||
			json_response.status === 'failed') && tries > 0) {
			//checked_str = ((LivePress_IM_Integration.CHECK_TIMES + 1) - tries) + "/" + LivePress_IM_Integration.CHECK_TIMES;
			setTimeout(function () {
				ImIntegration.__check_status(params.im_service, tries - 1);
			}, ImIntegration.CHECK_TIMEOUT_SECONDS * 1000);
		} else if (json_response.status === 'not_found') {
			show_button = true;
			$check_message.html( lp_strings.account_not_found ).css({'color':'red'});
		} else if (json_response.status === 'connecting') {
			setTimeout(function () {
				ImIntegration.__check_status(params.im_service, 5);
			}, ImIntegration.CHECK_TIMEOUT_SECONDS * 1000);
			$check_message.html( lp_strings.connecting ).css({'color':'lightgreen'});
		} else if (json_response.status === 'offline') {
			$check_message.html( lp_strings.offline );
		} else if (json_response.status === 'online') {
			$check_message.html( lp_strings.connected ).css({'color':'green'});
		} else if (json_response.status === 'failed') {
			show_button = true;
			reason = json_response.reason;

			if (reason === 'authentication_error') {
				error_msg = lp_strings.user_pass_invalid;
			} else if (reason === "wrong_jid") {
				error_msg = lp_strings.wrong_account_name;
			} else {
				console.log("Im check failure reason: ", reason);
				error_msg = lp_strings.internal_error;
			}

			$check_message.html(error_msg).css({'color':'red'});
		} else {
			show_button = true;
			$check_message.html( lp_strings.unknown_error ).css({'color':'red'});
		}

		if (show_button) {
			$check_button.show();
		}
	});

};

/**
 * Current status of the test message.
 *
 * @memberOf ImIntegration
 * @type {Boolean}
 */
ImIntegration.test_message_sending = false;

/**
 * Send a test message from a given user via a specified protocol.
 *
 * @memberOf ImIntegration
 * @param {String} source Source of the message.
 * @param {String} protocol Protocol to use while sending the message.
 * @see ImIntegration.test_message_sending
 */
ImIntegration.send_test_message = function (source, protocol) {
	var $input = jQuery("#" + source),
		buddy = $input.attr('value'),
		$button,
		$feedback_msg,
		params,
		feedback_msg = "",
		self = this;

	if (buddy.length === 0) {
		return;
	}
	if (this.test_message_sending) {
		return;
	}
	this.test_message_sending = true;
	$input.attr('readOnly', true);

	$button = jQuery("#" + source + "_test_button");
	$button.attr('value', lp_strings.sending + "...");
	$button.attr("disabled", true);

	$feedback_msg = jQuery("#" + protocol + "_message");
	$feedback_msg.html("");

	params = {};
	params.action = 'im_integration';
	params.im_integration_test_message = true;
	params.im_service = protocol;
	params.buddy = buddy;

	//console.log("Sending test message to: " + buddy + " using " + protocol + " protocol");

	jQuery.ajax({
		url:      LivepressConfig.ajax_url,
		type:     'post',
		dataType: 'json',
		data:     params,

		error: function (request) {
			feedback_msg = lp_strings.problem_connecting;
		},

		success: function (data) {
			//console.log("return from test message: %d", data);
			if (data === 200) {
				feedback_msg = lp_strings.test_msg_sent;
			} else {
				feedback_msg = lp_strings.test_msg_failure;
			}
		},

		complete: function (XMLHttpRequest, textStatus) {
			//console.log("feed: %s", feedback_msg);
			$feedback_msg.html(feedback_msg);

			self.test_message_sending = false;
			$input.attr('readOnly', false);

			$button.attr('value', lp_strings.send_again );
			$button.attr("disabled", false);
		}
	});
};
(function ($) {
    if(typeof $.fn.each2 == "undefined") {
        $.extend($.fn, {
            /*
            * 4-10 times faster .each replacement
            * use it carefully, as it overrides jQuery context of element on each iteration
            */
            each2 : function (c) {
                var j = $([0]), i = -1, l = this.length;
                while (
                    ++i < l
                    && (j.context = j[0] = this[i])
                    && c.call(j[0], i, j) !== false //"this"=DOM, i=index, j=jQuery object
                );
                return this;
            }
        });
    }
})(jQuery);

(function ($, undefined) {
    "use strict";
    /*global document, window, jQuery, console */

    if (window.Select2 !== undefined) {
        return;
    }

    var KEY, AbstractSelect2, SingleSelect2, MultiSelect2, nextUid, sizer,
        lastMousePosition={x:0,y:0}, $document, scrollBarDimensions,

    KEY = {
        TAB: 9,
        ENTER: 13,
        ESC: 27,
        SPACE: 32,
        LEFT: 37,
        UP: 38,
        RIGHT: 39,
        DOWN: 40,
        SHIFT: 16,
        CTRL: 17,
        ALT: 18,
        PAGE_UP: 33,
        PAGE_DOWN: 34,
        HOME: 36,
        END: 35,
        BACKSPACE: 8,
        DELETE: 46,
        isArrow: function (k) {
            k = k.which ? k.which : k;
            switch (k) {
            case KEY.LEFT:
            case KEY.RIGHT:
            case KEY.UP:
            case KEY.DOWN:
                return true;
            }
            return false;
        },
        isControl: function (e) {
            var k = e.which;
            switch (k) {
            case KEY.SHIFT:
            case KEY.CTRL:
            case KEY.ALT:
                return true;
            }

            if (e.metaKey) return true;

            return false;
        },
        isFunctionKey: function (k) {
            k = k.which ? k.which : k;
            return k >= 112 && k <= 123;
        }
    },
    MEASURE_SCROLLBAR_TEMPLATE = "<div class='select2-measure-scrollbar'></div>",

    DIACRITICS = {"\u24B6":"A","\uFF21":"A","\u00C0":"A","\u00C1":"A","\u00C2":"A","\u1EA6":"A","\u1EA4":"A","\u1EAA":"A","\u1EA8":"A","\u00C3":"A","\u0100":"A","\u0102":"A","\u1EB0":"A","\u1EAE":"A","\u1EB4":"A","\u1EB2":"A","\u0226":"A","\u01E0":"A","\u00C4":"A","\u01DE":"A","\u1EA2":"A","\u00C5":"A","\u01FA":"A","\u01CD":"A","\u0200":"A","\u0202":"A","\u1EA0":"A","\u1EAC":"A","\u1EB6":"A","\u1E00":"A","\u0104":"A","\u023A":"A","\u2C6F":"A","\uA732":"AA","\u00C6":"AE","\u01FC":"AE","\u01E2":"AE","\uA734":"AO","\uA736":"AU","\uA738":"AV","\uA73A":"AV","\uA73C":"AY","\u24B7":"B","\uFF22":"B","\u1E02":"B","\u1E04":"B","\u1E06":"B","\u0243":"B","\u0182":"B","\u0181":"B","\u24B8":"C","\uFF23":"C","\u0106":"C","\u0108":"C","\u010A":"C","\u010C":"C","\u00C7":"C","\u1E08":"C","\u0187":"C","\u023B":"C","\uA73E":"C","\u24B9":"D","\uFF24":"D","\u1E0A":"D","\u010E":"D","\u1E0C":"D","\u1E10":"D","\u1E12":"D","\u1E0E":"D","\u0110":"D","\u018B":"D","\u018A":"D","\u0189":"D","\uA779":"D","\u01F1":"DZ","\u01C4":"DZ","\u01F2":"Dz","\u01C5":"Dz","\u24BA":"E","\uFF25":"E","\u00C8":"E","\u00C9":"E","\u00CA":"E","\u1EC0":"E","\u1EBE":"E","\u1EC4":"E","\u1EC2":"E","\u1EBC":"E","\u0112":"E","\u1E14":"E","\u1E16":"E","\u0114":"E","\u0116":"E","\u00CB":"E","\u1EBA":"E","\u011A":"E","\u0204":"E","\u0206":"E","\u1EB8":"E","\u1EC6":"E","\u0228":"E","\u1E1C":"E","\u0118":"E","\u1E18":"E","\u1E1A":"E","\u0190":"E","\u018E":"E","\u24BB":"F","\uFF26":"F","\u1E1E":"F","\u0191":"F","\uA77B":"F","\u24BC":"G","\uFF27":"G","\u01F4":"G","\u011C":"G","\u1E20":"G","\u011E":"G","\u0120":"G","\u01E6":"G","\u0122":"G","\u01E4":"G","\u0193":"G","\uA7A0":"G","\uA77D":"G","\uA77E":"G","\u24BD":"H","\uFF28":"H","\u0124":"H","\u1E22":"H","\u1E26":"H","\u021E":"H","\u1E24":"H","\u1E28":"H","\u1E2A":"H","\u0126":"H","\u2C67":"H","\u2C75":"H","\uA78D":"H","\u24BE":"I","\uFF29":"I","\u00CC":"I","\u00CD":"I","\u00CE":"I","\u0128":"I","\u012A":"I","\u012C":"I","\u0130":"I","\u00CF":"I","\u1E2E":"I","\u1EC8":"I","\u01CF":"I","\u0208":"I","\u020A":"I","\u1ECA":"I","\u012E":"I","\u1E2C":"I","\u0197":"I","\u24BF":"J","\uFF2A":"J","\u0134":"J","\u0248":"J","\u24C0":"K","\uFF2B":"K","\u1E30":"K","\u01E8":"K","\u1E32":"K","\u0136":"K","\u1E34":"K","\u0198":"K","\u2C69":"K","\uA740":"K","\uA742":"K","\uA744":"K","\uA7A2":"K","\u24C1":"L","\uFF2C":"L","\u013F":"L","\u0139":"L","\u013D":"L","\u1E36":"L","\u1E38":"L","\u013B":"L","\u1E3C":"L","\u1E3A":"L","\u0141":"L","\u023D":"L","\u2C62":"L","\u2C60":"L","\uA748":"L","\uA746":"L","\uA780":"L","\u01C7":"LJ","\u01C8":"Lj","\u24C2":"M","\uFF2D":"M","\u1E3E":"M","\u1E40":"M","\u1E42":"M","\u2C6E":"M","\u019C":"M","\u24C3":"N","\uFF2E":"N","\u01F8":"N","\u0143":"N","\u00D1":"N","\u1E44":"N","\u0147":"N","\u1E46":"N","\u0145":"N","\u1E4A":"N","\u1E48":"N","\u0220":"N","\u019D":"N","\uA790":"N","\uA7A4":"N","\u01CA":"NJ","\u01CB":"Nj","\u24C4":"O","\uFF2F":"O","\u00D2":"O","\u00D3":"O","\u00D4":"O","\u1ED2":"O","\u1ED0":"O","\u1ED6":"O","\u1ED4":"O","\u00D5":"O","\u1E4C":"O","\u022C":"O","\u1E4E":"O","\u014C":"O","\u1E50":"O","\u1E52":"O","\u014E":"O","\u022E":"O","\u0230":"O","\u00D6":"O","\u022A":"O","\u1ECE":"O","\u0150":"O","\u01D1":"O","\u020C":"O","\u020E":"O","\u01A0":"O","\u1EDC":"O","\u1EDA":"O","\u1EE0":"O","\u1EDE":"O","\u1EE2":"O","\u1ECC":"O","\u1ED8":"O","\u01EA":"O","\u01EC":"O","\u00D8":"O","\u01FE":"O","\u0186":"O","\u019F":"O","\uA74A":"O","\uA74C":"O","\u01A2":"OI","\uA74E":"OO","\u0222":"OU","\u24C5":"P","\uFF30":"P","\u1E54":"P","\u1E56":"P","\u01A4":"P","\u2C63":"P","\uA750":"P","\uA752":"P","\uA754":"P","\u24C6":"Q","\uFF31":"Q","\uA756":"Q","\uA758":"Q","\u024A":"Q","\u24C7":"R","\uFF32":"R","\u0154":"R","\u1E58":"R","\u0158":"R","\u0210":"R","\u0212":"R","\u1E5A":"R","\u1E5C":"R","\u0156":"R","\u1E5E":"R","\u024C":"R","\u2C64":"R","\uA75A":"R","\uA7A6":"R","\uA782":"R","\u24C8":"S","\uFF33":"S","\u1E9E":"S","\u015A":"S","\u1E64":"S","\u015C":"S","\u1E60":"S","\u0160":"S","\u1E66":"S","\u1E62":"S","\u1E68":"S","\u0218":"S","\u015E":"S","\u2C7E":"S","\uA7A8":"S","\uA784":"S","\u24C9":"T","\uFF34":"T","\u1E6A":"T","\u0164":"T","\u1E6C":"T","\u021A":"T","\u0162":"T","\u1E70":"T","\u1E6E":"T","\u0166":"T","\u01AC":"T","\u01AE":"T","\u023E":"T","\uA786":"T","\uA728":"TZ","\u24CA":"U","\uFF35":"U","\u00D9":"U","\u00DA":"U","\u00DB":"U","\u0168":"U","\u1E78":"U","\u016A":"U","\u1E7A":"U","\u016C":"U","\u00DC":"U","\u01DB":"U","\u01D7":"U","\u01D5":"U","\u01D9":"U","\u1EE6":"U","\u016E":"U","\u0170":"U","\u01D3":"U","\u0214":"U","\u0216":"U","\u01AF":"U","\u1EEA":"U","\u1EE8":"U","\u1EEE":"U","\u1EEC":"U","\u1EF0":"U","\u1EE4":"U","\u1E72":"U","\u0172":"U","\u1E76":"U","\u1E74":"U","\u0244":"U","\u24CB":"V","\uFF36":"V","\u1E7C":"V","\u1E7E":"V","\u01B2":"V","\uA75E":"V","\u0245":"V","\uA760":"VY","\u24CC":"W","\uFF37":"W","\u1E80":"W","\u1E82":"W","\u0174":"W","\u1E86":"W","\u1E84":"W","\u1E88":"W","\u2C72":"W","\u24CD":"X","\uFF38":"X","\u1E8A":"X","\u1E8C":"X","\u24CE":"Y","\uFF39":"Y","\u1EF2":"Y","\u00DD":"Y","\u0176":"Y","\u1EF8":"Y","\u0232":"Y","\u1E8E":"Y","\u0178":"Y","\u1EF6":"Y","\u1EF4":"Y","\u01B3":"Y","\u024E":"Y","\u1EFE":"Y","\u24CF":"Z","\uFF3A":"Z","\u0179":"Z","\u1E90":"Z","\u017B":"Z","\u017D":"Z","\u1E92":"Z","\u1E94":"Z","\u01B5":"Z","\u0224":"Z","\u2C7F":"Z","\u2C6B":"Z","\uA762":"Z","\u24D0":"a","\uFF41":"a","\u1E9A":"a","\u00E0":"a","\u00E1":"a","\u00E2":"a","\u1EA7":"a","\u1EA5":"a","\u1EAB":"a","\u1EA9":"a","\u00E3":"a","\u0101":"a","\u0103":"a","\u1EB1":"a","\u1EAF":"a","\u1EB5":"a","\u1EB3":"a","\u0227":"a","\u01E1":"a","\u00E4":"a","\u01DF":"a","\u1EA3":"a","\u00E5":"a","\u01FB":"a","\u01CE":"a","\u0201":"a","\u0203":"a","\u1EA1":"a","\u1EAD":"a","\u1EB7":"a","\u1E01":"a","\u0105":"a","\u2C65":"a","\u0250":"a","\uA733":"aa","\u00E6":"ae","\u01FD":"ae","\u01E3":"ae","\uA735":"ao","\uA737":"au","\uA739":"av","\uA73B":"av","\uA73D":"ay","\u24D1":"b","\uFF42":"b","\u1E03":"b","\u1E05":"b","\u1E07":"b","\u0180":"b","\u0183":"b","\u0253":"b","\u24D2":"c","\uFF43":"c","\u0107":"c","\u0109":"c","\u010B":"c","\u010D":"c","\u00E7":"c","\u1E09":"c","\u0188":"c","\u023C":"c","\uA73F":"c","\u2184":"c","\u24D3":"d","\uFF44":"d","\u1E0B":"d","\u010F":"d","\u1E0D":"d","\u1E11":"d","\u1E13":"d","\u1E0F":"d","\u0111":"d","\u018C":"d","\u0256":"d","\u0257":"d","\uA77A":"d","\u01F3":"dz","\u01C6":"dz","\u24D4":"e","\uFF45":"e","\u00E8":"e","\u00E9":"e","\u00EA":"e","\u1EC1":"e","\u1EBF":"e","\u1EC5":"e","\u1EC3":"e","\u1EBD":"e","\u0113":"e","\u1E15":"e","\u1E17":"e","\u0115":"e","\u0117":"e","\u00EB":"e","\u1EBB":"e","\u011B":"e","\u0205":"e","\u0207":"e","\u1EB9":"e","\u1EC7":"e","\u0229":"e","\u1E1D":"e","\u0119":"e","\u1E19":"e","\u1E1B":"e","\u0247":"e","\u025B":"e","\u01DD":"e","\u24D5":"f","\uFF46":"f","\u1E1F":"f","\u0192":"f","\uA77C":"f","\u24D6":"g","\uFF47":"g","\u01F5":"g","\u011D":"g","\u1E21":"g","\u011F":"g","\u0121":"g","\u01E7":"g","\u0123":"g","\u01E5":"g","\u0260":"g","\uA7A1":"g","\u1D79":"g","\uA77F":"g","\u24D7":"h","\uFF48":"h","\u0125":"h","\u1E23":"h","\u1E27":"h","\u021F":"h","\u1E25":"h","\u1E29":"h","\u1E2B":"h","\u1E96":"h","\u0127":"h","\u2C68":"h","\u2C76":"h","\u0265":"h","\u0195":"hv","\u24D8":"i","\uFF49":"i","\u00EC":"i","\u00ED":"i","\u00EE":"i","\u0129":"i","\u012B":"i","\u012D":"i","\u00EF":"i","\u1E2F":"i","\u1EC9":"i","\u01D0":"i","\u0209":"i","\u020B":"i","\u1ECB":"i","\u012F":"i","\u1E2D":"i","\u0268":"i","\u0131":"i","\u24D9":"j","\uFF4A":"j","\u0135":"j","\u01F0":"j","\u0249":"j","\u24DA":"k","\uFF4B":"k","\u1E31":"k","\u01E9":"k","\u1E33":"k","\u0137":"k","\u1E35":"k","\u0199":"k","\u2C6A":"k","\uA741":"k","\uA743":"k","\uA745":"k","\uA7A3":"k","\u24DB":"l","\uFF4C":"l","\u0140":"l","\u013A":"l","\u013E":"l","\u1E37":"l","\u1E39":"l","\u013C":"l","\u1E3D":"l","\u1E3B":"l","\u017F":"l","\u0142":"l","\u019A":"l","\u026B":"l","\u2C61":"l","\uA749":"l","\uA781":"l","\uA747":"l","\u01C9":"lj","\u24DC":"m","\uFF4D":"m","\u1E3F":"m","\u1E41":"m","\u1E43":"m","\u0271":"m","\u026F":"m","\u24DD":"n","\uFF4E":"n","\u01F9":"n","\u0144":"n","\u00F1":"n","\u1E45":"n","\u0148":"n","\u1E47":"n","\u0146":"n","\u1E4B":"n","\u1E49":"n","\u019E":"n","\u0272":"n","\u0149":"n","\uA791":"n","\uA7A5":"n","\u01CC":"nj","\u24DE":"o","\uFF4F":"o","\u00F2":"o","\u00F3":"o","\u00F4":"o","\u1ED3":"o","\u1ED1":"o","\u1ED7":"o","\u1ED5":"o","\u00F5":"o","\u1E4D":"o","\u022D":"o","\u1E4F":"o","\u014D":"o","\u1E51":"o","\u1E53":"o","\u014F":"o","\u022F":"o","\u0231":"o","\u00F6":"o","\u022B":"o","\u1ECF":"o","\u0151":"o","\u01D2":"o","\u020D":"o","\u020F":"o","\u01A1":"o","\u1EDD":"o","\u1EDB":"o","\u1EE1":"o","\u1EDF":"o","\u1EE3":"o","\u1ECD":"o","\u1ED9":"o","\u01EB":"o","\u01ED":"o","\u00F8":"o","\u01FF":"o","\u0254":"o","\uA74B":"o","\uA74D":"o","\u0275":"o","\u01A3":"oi","\u0223":"ou","\uA74F":"oo","\u24DF":"p","\uFF50":"p","\u1E55":"p","\u1E57":"p","\u01A5":"p","\u1D7D":"p","\uA751":"p","\uA753":"p","\uA755":"p","\u24E0":"q","\uFF51":"q","\u024B":"q","\uA757":"q","\uA759":"q","\u24E1":"r","\uFF52":"r","\u0155":"r","\u1E59":"r","\u0159":"r","\u0211":"r","\u0213":"r","\u1E5B":"r","\u1E5D":"r","\u0157":"r","\u1E5F":"r","\u024D":"r","\u027D":"r","\uA75B":"r","\uA7A7":"r","\uA783":"r","\u24E2":"s","\uFF53":"s","\u00DF":"s","\u015B":"s","\u1E65":"s","\u015D":"s","\u1E61":"s","\u0161":"s","\u1E67":"s","\u1E63":"s","\u1E69":"s","\u0219":"s","\u015F":"s","\u023F":"s","\uA7A9":"s","\uA785":"s","\u1E9B":"s","\u24E3":"t","\uFF54":"t","\u1E6B":"t","\u1E97":"t","\u0165":"t","\u1E6D":"t","\u021B":"t","\u0163":"t","\u1E71":"t","\u1E6F":"t","\u0167":"t","\u01AD":"t","\u0288":"t","\u2C66":"t","\uA787":"t","\uA729":"tz","\u24E4":"u","\uFF55":"u","\u00F9":"u","\u00FA":"u","\u00FB":"u","\u0169":"u","\u1E79":"u","\u016B":"u","\u1E7B":"u","\u016D":"u","\u00FC":"u","\u01DC":"u","\u01D8":"u","\u01D6":"u","\u01DA":"u","\u1EE7":"u","\u016F":"u","\u0171":"u","\u01D4":"u","\u0215":"u","\u0217":"u","\u01B0":"u","\u1EEB":"u","\u1EE9":"u","\u1EEF":"u","\u1EED":"u","\u1EF1":"u","\u1EE5":"u","\u1E73":"u","\u0173":"u","\u1E77":"u","\u1E75":"u","\u0289":"u","\u24E5":"v","\uFF56":"v","\u1E7D":"v","\u1E7F":"v","\u028B":"v","\uA75F":"v","\u028C":"v","\uA761":"vy","\u24E6":"w","\uFF57":"w","\u1E81":"w","\u1E83":"w","\u0175":"w","\u1E87":"w","\u1E85":"w","\u1E98":"w","\u1E89":"w","\u2C73":"w","\u24E7":"x","\uFF58":"x","\u1E8B":"x","\u1E8D":"x","\u24E8":"y","\uFF59":"y","\u1EF3":"y","\u00FD":"y","\u0177":"y","\u1EF9":"y","\u0233":"y","\u1E8F":"y","\u00FF":"y","\u1EF7":"y","\u1E99":"y","\u1EF5":"y","\u01B4":"y","\u024F":"y","\u1EFF":"y","\u24E9":"z","\uFF5A":"z","\u017A":"z","\u1E91":"z","\u017C":"z","\u017E":"z","\u1E93":"z","\u1E95":"z","\u01B6":"z","\u0225":"z","\u0240":"z","\u2C6C":"z","\uA763":"z","\u0386":"\u0391","\u0388":"\u0395","\u0389":"\u0397","\u038A":"\u0399","\u03AA":"\u0399","\u038C":"\u039F","\u038E":"\u03A5","\u03AB":"\u03A5","\u038F":"\u03A9","\u03AC":"\u03B1","\u03AD":"\u03B5","\u03AE":"\u03B7","\u03AF":"\u03B9","\u03CA":"\u03B9","\u0390":"\u03B9","\u03CC":"\u03BF","\u03CD":"\u03C5","\u03CB":"\u03C5","\u03B0":"\u03C5","\u03C9":"\u03C9","\u03C2":"\u03C3"};

    $document = $(document);

    nextUid=(function() { var counter=1; return function() { return counter++; }; }());


    function reinsertElement(element) {
        var placeholder = $(document.createTextNode(''));

        element.before(placeholder);
        placeholder.before(element);
        placeholder.remove();
    }

    function stripDiacritics(str) {
        // Used 'uni range + named function' from http://jsperf.com/diacritics/18
        function match(a) {
            return DIACRITICS[a] || a;
        }

        return str.replace(/[^\u0000-\u007E]/g, match);
    }

    function indexOf(value, array) {
        var i = 0, l = array.length;
        for (; i < l; i = i + 1) {
            if (equal(value, array[i])) return i;
        }
        return -1;
    }

    function measureScrollbar () {
        var $template = $( MEASURE_SCROLLBAR_TEMPLATE );
        $template.appendTo('body');

        var dim = {
            width: $template.width() - $template[0].clientWidth,
            height: $template.height() - $template[0].clientHeight
        };
        $template.remove();

        return dim;
    }

    /**
     * Compares equality of a and b
     * @param a
     * @param b
     */
    function equal(a, b) {
        if (a === b) return true;
        if (a === undefined || b === undefined) return false;
        if (a === null || b === null) return false;
        // Check whether 'a' or 'b' is a string (primitive or object).
        // The concatenation of an empty string (+'') converts its argument to a string's primitive.
        if (a.constructor === String) return a+'' === b+''; // a+'' - in case 'a' is a String object
        if (b.constructor === String) return b+'' === a+''; // b+'' - in case 'b' is a String object
        return false;
    }

    /**
     * Splits the string into an array of values, trimming each value. An empty array is returned for nulls or empty
     * strings
     * @param string
     * @param separator
     */
    function splitVal(string, separator) {
        var val, i, l;
        if (string === null || string.length < 1) return [];
        val = string.split(separator);
        for (i = 0, l = val.length; i < l; i = i + 1) val[i] = $.trim(val[i]);
        return val;
    }

    function getSideBorderPadding(element) {
        return element.outerWidth(false) - element.width();
    }

    function installKeyUpChangeEvent(element) {
        var key="keyup-change-value";
        element.on("keydown", function () {
            if ($.data(element, key) === undefined) {
                $.data(element, key, element.val());
            }
        });
        element.on("keyup", function () {
            var val= $.data(element, key);
            if (val !== undefined && element.val() !== val) {
                $.removeData(element, key);
                element.trigger("keyup-change");
            }
        });
    }


    /**
     * filters mouse events so an event is fired only if the mouse moved.
     *
     * filters out mouse events that occur when mouse is stationary but
     * the elements under the pointer are scrolled.
     */
    function installFilteredMouseMove(element) {
        element.on("mousemove", function (e) {
            var lastpos = lastMousePosition;
            if (lastpos === undefined || lastpos.x !== e.pageX || lastpos.y !== e.pageY) {
                $(e.target).trigger("mousemove-filtered", e);
            }
        });
    }

    /**
     * Debounces a function. Returns a function that calls the original fn function only if no invocations have been made
     * within the last quietMillis milliseconds.
     *
     * @param quietMillis number of milliseconds to wait before invoking fn
     * @param fn function to be debounced
     * @param ctx object to be used as this reference within fn
     * @return debounced version of fn
     */
    function debounce(quietMillis, fn, ctx) {
        ctx = ctx || undefined;
        var timeout;
        return function () {
            var args = arguments;
            window.clearTimeout(timeout);
            timeout = window.setTimeout(function() {
                fn.apply(ctx, args);
            }, quietMillis);
        };
    }

    function installDebouncedScroll(threshold, element) {
        var notify = debounce(threshold, function (e) { element.trigger("scroll-debounced", e);});
        element.on("scroll", function (e) {
            if (indexOf(e.target, element.get()) >= 0) notify(e);
        });
    }

    function focus($el) {
        if ($el[0] === document.activeElement) return;

        /* set the focus in a 0 timeout - that way the focus is set after the processing
            of the current event has finished - which seems like the only reliable way
            to set focus */
        window.setTimeout(function() {
            var el=$el[0], pos=$el.val().length, range;

            $el.focus();

            /* make sure el received focus so we do not error out when trying to manipulate the caret.
                sometimes modals or others listeners may steal it after its set */
            var isVisible = (el.offsetWidth > 0 || el.offsetHeight > 0);
            if (isVisible && el === document.activeElement) {

                /* after the focus is set move the caret to the end, necessary when we val()
                    just before setting focus */
                if(el.setSelectionRange)
                {
                    el.setSelectionRange(pos, pos);
                }
                else if (el.createTextRange) {
                    range = el.createTextRange();
                    range.collapse(false);
                    range.select();
                }
            }
        }, 0);
    }

    function getCursorInfo(el) {
        el = $(el)[0];
        var offset = 0;
        var length = 0;
        if ('selectionStart' in el) {
            offset = el.selectionStart;
            length = el.selectionEnd - offset;
        } else if ('selection' in document) {
            el.focus();
            var sel = document.selection.createRange();
            length = document.selection.createRange().text.length;
            sel.moveStart('character', -el.value.length);
            offset = sel.text.length - length;
        }
        return { offset: offset, length: length };
    }

    function killEvent(event) {
        event.preventDefault();
        event.stopPropagation();
    }
    function killEventImmediately(event) {
        event.preventDefault();
        event.stopImmediatePropagation();
    }

    function measureTextWidth(e) {
        if (!sizer){
            var style = e[0].currentStyle || window.getComputedStyle(e[0], null);
            sizer = $(document.createElement("div")).css({
                position: "absolute",
                left: "-10000px",
                top: "-10000px",
                display: "none",
                fontSize: style.fontSize,
                fontFamily: style.fontFamily,
                fontStyle: style.fontStyle,
                fontWeight: style.fontWeight,
                letterSpacing: style.letterSpacing,
                textTransform: style.textTransform,
                whiteSpace: "nowrap"
            });
            sizer.attr("class","select2-sizer");
            $("body").append(sizer);
        }
        sizer.text(e.val());
        return sizer.width();
    }

    function syncCssClasses(dest, src, adapter) {
        var classes, replacements = [], adapted;

        classes = $.trim(dest.attr("class"));

        if (classes) {
            classes = '' + classes; // for IE which returns object

            $(classes.split(/\s+/)).each2(function() {
                if (this.indexOf("select2-") === 0) {
                    replacements.push(this);
                }
            });
        }

        classes = $.trim(src.attr("class"));

        if (classes) {
            classes = '' + classes; // for IE which returns object

            $(classes.split(/\s+/)).each2(function() {
                if (this.indexOf("select2-") !== 0) {
                    adapted = adapter(this);

                    if (adapted) {
                        replacements.push(adapted);
                    }
                }
            });
        }

        dest.attr("class", replacements.join(" "));
    }


    function markMatch(text, term, markup, escapeMarkup) {
        var match=stripDiacritics(text.toUpperCase()).indexOf(stripDiacritics(term.toUpperCase())),
            tl=term.length;

        if (match<0) {
            markup.push(escapeMarkup(text));
            return;
        }

        markup.push(escapeMarkup(text.substring(0, match)));
        markup.push("<span class='select2-match'>");
        markup.push(escapeMarkup(text.substring(match, match + tl)));
        markup.push("</span>");
        markup.push(escapeMarkup(text.substring(match + tl, text.length)));
    }

    function defaultEscapeMarkup(markup) {
        var replace_map = {
            '\\': '&#92;',
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;',
            "/": '&#47;'
        };

        return String(markup).replace(/[&<>"'\/\\]/g, function (match) {
            return replace_map[match];
        });
    }

    /**
     * Produces an ajax-based query function
     *
     * @param options object containing configuration parameters
     * @param options.params parameter map for the transport ajax call, can contain such options as cache, jsonpCallback, etc. see $.ajax
     * @param options.transport function that will be used to execute the ajax request. must be compatible with parameters supported by $.ajax
     * @param options.url url for the data
     * @param options.data a function(searchTerm, pageNumber, context) that should return an object containing query string parameters for the above url.
     * @param options.dataType request data type: ajax, jsonp, other datatypes supported by jQuery's $.ajax function or the transport function if specified
     * @param options.quietMillis (optional) milliseconds to wait before making the ajaxRequest, helps debounce the ajax function if invoked too often
     * @param options.results a function(remoteData, pageNumber, query) that converts data returned form the remote request to the format expected by Select2.
     *      The expected format is an object containing the following keys:
     *      results array of objects that will be used as choices
     *      more (optional) boolean indicating whether there are more results available
     *      Example: {results:[{id:1, text:'Red'},{id:2, text:'Blue'}], more:true}
     */
    function ajax(options) {
        var timeout, // current scheduled but not yet executed request
            handler = null,
            quietMillis = options.quietMillis || 100,
            ajaxUrl = options.url,
            self = this;

        return function (query) {
            window.clearTimeout(timeout);
            timeout = window.setTimeout(function () {
                var data = options.data, // ajax data function
                    url = ajaxUrl, // ajax url string or function
                    transport = options.transport || $.fn.select2.ajaxDefaults.transport,
                    // deprecated - to be removed in 4.0  - use params instead
                    deprecated = {
                        type: options.type || 'GET', // set type of request (GET or POST)
                        cache: options.cache || false,
                        jsonpCallback: options.jsonpCallback||undefined,
                        dataType: options.dataType||"json"
                    },
                    params = $.extend({}, $.fn.select2.ajaxDefaults.params, deprecated);

                data = data ? data.call(self, query.term, query.page, query.context) : null;
                url = (typeof url === 'function') ? url.call(self, query.term, query.page, query.context) : url;

                if (handler && typeof handler.abort === "function") { handler.abort(); }

                if (options.params) {
                    if ($.isFunction(options.params)) {
                        $.extend(params, options.params.call(self));
                    } else {
                        $.extend(params, options.params);
                    }
                }

                $.extend(params, {
                    url: url,
                    dataType: options.dataType,
                    data: data,
                    success: function (data) {
                        // TODO - replace query.page with query so users have access to term, page, etc.
                        // added query as third paramter to keep backwards compatibility
                        var results = options.results(data, query.page, query);
                        query.callback(results);
                    },
                    error: function(jqXHR, textStatus, errorThrown){
                        var results = {
                            hasError: true,
                            jqXHR: jqXHR,
                            textStatus: textStatus,
                            errorThrown: errorThrown
                        };

                        query.callback(results);
                    }
                });
                handler = transport.call(self, params);
            }, quietMillis);
        };
    }

    /**
     * Produces a query function that works with a local array
     *
     * @param options object containing configuration parameters. The options parameter can either be an array or an
     * object.
     *
     * If the array form is used it is assumed that it contains objects with 'id' and 'text' keys.
     *
     * If the object form is used it is assumed that it contains 'data' and 'text' keys. The 'data' key should contain
     * an array of objects that will be used as choices. These objects must contain at least an 'id' key. The 'text'
     * key can either be a String in which case it is expected that each element in the 'data' array has a key with the
     * value of 'text' which will be used to match choices. Alternatively, text can be a function(item) that can extract
     * the text.
     */
    function local(options) {
        var data = options, // data elements
            dataText,
            tmp,
            text = function (item) { return ""+item.text; }; // function used to retrieve the text portion of a data item that is matched against the search

         if ($.isArray(data)) {
            tmp = data;
            data = { results: tmp };
        }

         if ($.isFunction(data) === false) {
            tmp = data;
            data = function() { return tmp; };
        }

        var dataItem = data();
        if (dataItem.text) {
            text = dataItem.text;
            // if text is not a function we assume it to be a key name
            if (!$.isFunction(text)) {
                dataText = dataItem.text; // we need to store this in a separate variable because in the next step data gets reset and data.text is no longer available
                text = function (item) { return item[dataText]; };
            }
        }

        return function (query) {
            var t = query.term, filtered = { results: [] }, process;
            if (t === "") {
                query.callback(data());
                return;
            }

            process = function(datum, collection) {
                var group, attr;
                datum = datum[0];
                if (datum.children) {
                    group = {};
                    for (attr in datum) {
                        if (datum.hasOwnProperty(attr)) group[attr]=datum[attr];
                    }
                    group.children=[];
                    $(datum.children).each2(function(i, childDatum) { process(childDatum, group.children); });
                    if (group.children.length || query.matcher(t, text(group), datum)) {
                        collection.push(group);
                    }
                } else {
                    if (query.matcher(t, text(datum), datum)) {
                        collection.push(datum);
                    }
                }
            };

            $(data().results).each2(function(i, datum) { process(datum, filtered.results); });
            query.callback(filtered);
        };
    }

    // TODO javadoc
    function tags(data) {
        var isFunc = $.isFunction(data);
        return function (query) {
            var t = query.term, filtered = {results: []};
            var result = isFunc ? data(query) : data;
            if ($.isArray(result)) {
                $(result).each(function () {
                    var isObject = this.text !== undefined,
                        text = isObject ? this.text : this;
                    if (t === "" || query.matcher(t, text)) {
                        filtered.results.push(isObject ? this : {id: this, text: this});
                    }
                });
                query.callback(filtered);
            }
        };
    }

    /**
     * Checks if the formatter function should be used.
     *
     * Throws an error if it is not a function. Returns true if it should be used,
     * false if no formatting should be performed.
     *
     * @param formatter
     */
    function checkFormatter(formatter, formatterName) {
        if ($.isFunction(formatter)) return true;
        if (!formatter) return false;
        if (typeof(formatter) === 'string') return true;
        throw new Error(formatterName +" must be a string, function, or falsy value");
    }

  /**
   * Returns a given value
   * If given a function, returns its output
   *
   * @param val string|function
   * @param context value of "this" to be passed to function
   * @returns {*}
   */
    function evaluate(val, context) {
        if ($.isFunction(val)) {
            var args = Array.prototype.slice.call(arguments, 2);
            return val.apply(context, args);
        }
        return val;
    }

    function countResults(results) {
        var count = 0;
        $.each(results, function(i, item) {
            if (item.children) {
                count += countResults(item.children);
            } else {
                count++;
            }
        });
        return count;
    }

    /**
     * Default tokenizer. This function uses breaks the input on substring match of any string from the
     * opts.tokenSeparators array and uses opts.createSearchChoice to create the choice object. Both of those
     * two options have to be defined in order for the tokenizer to work.
     *
     * @param input text user has typed so far or pasted into the search field
     * @param selection currently selected choices
     * @param selectCallback function(choice) callback tho add the choice to selection
     * @param opts select2's opts
     * @return undefined/null to leave the current input unchanged, or a string to change the input to the returned value
     */
    function defaultTokenizer(input, selection, selectCallback, opts) {
        var original = input, // store the original so we can compare and know if we need to tell the search to update its text
            dupe = false, // check for whether a token we extracted represents a duplicate selected choice
            token, // token
            index, // position at which the separator was found
            i, l, // looping variables
            separator; // the matched separator

        if (!opts.createSearchChoice || !opts.tokenSeparators || opts.tokenSeparators.length < 1) return undefined;

        while (true) {
            index = -1;

            for (i = 0, l = opts.tokenSeparators.length; i < l; i++) {
                separator = opts.tokenSeparators[i];
                index = input.indexOf(separator);
                if (index >= 0) break;
            }

            if (index < 0) break; // did not find any token separator in the input string, bail

            token = input.substring(0, index);
            input = input.substring(index + separator.length);

            if (token.length > 0) {
                token = opts.createSearchChoice.call(this, token, selection);
                if (token !== undefined && token !== null && opts.id(token) !== undefined && opts.id(token) !== null) {
                    dupe = false;
                    for (i = 0, l = selection.length; i < l; i++) {
                        if (equal(opts.id(token), opts.id(selection[i]))) {
                            dupe = true; break;
                        }
                    }

                    if (!dupe) selectCallback(token);
                }
            }
        }

        if (original!==input) return input;
    }

    function cleanupJQueryElements() {
        var self = this;

        $.each(arguments, function (i, element) {
            self[element].remove();
            self[element] = null;
        });
    }

    /**
     * Creates a new class
     *
     * @param superClass
     * @param methods
     */
    function clazz(SuperClass, methods) {
        var constructor = function () {};
        constructor.prototype = new SuperClass;
        constructor.prototype.constructor = constructor;
        constructor.prototype.parent = SuperClass.prototype;
        constructor.prototype = $.extend(constructor.prototype, methods);
        return constructor;
    }

    AbstractSelect2 = clazz(Object, {

        // abstract
        bind: function (func) {
            var self = this;
            return function () {
                func.apply(self, arguments);
            };
        },

        // abstract
        init: function (opts) {
            var results, search, resultsSelector = ".select2-results";

            // prepare options
            this.opts = opts = this.prepareOpts(opts);

            this.id=opts.id;

            // destroy if called on an existing component
            if (opts.element.data("select2") !== undefined &&
                opts.element.data("select2") !== null) {
                opts.element.data("select2").destroy();
            }

            this.container = this.createContainer();

            this.liveRegion = $('.select2-hidden-accessible');
            if (this.liveRegion.length == 0) {
                this.liveRegion = $("<span>", {
                        role: "status",
                        "aria-live": "polite"
                    })
                    .addClass("select2-hidden-accessible")
                    .appendTo(document.body);
            }

            this.containerId="s2id_"+(opts.element.attr("id") || "autogen"+nextUid());
            this.containerEventName= this.containerId
                .replace(/([.])/g, '_')
                .replace(/([;&,\-\.\+\*\~':"\!\^#$%@\[\]\(\)=>\|])/g, '\\$1');
            this.container.attr("id", this.containerId);

            this.container.attr("title", opts.element.attr("title"));

            this.body = $("body");

            syncCssClasses(this.container, this.opts.element, this.opts.adaptContainerCssClass);

            this.container.attr("style", opts.element.attr("style"));
            this.container.css(evaluate(opts.containerCss, this.opts.element));
            this.container.addClass(evaluate(opts.containerCssClass, this.opts.element));

            this.elementTabIndex = this.opts.element.attr("tabindex");

            // swap container for the element
            this.opts.element
                .data("select2", this)
                .attr("tabindex", "-1")
                .before(this.container)
                .on("click.select2", killEvent); // do not leak click events

            this.container.data("select2", this);

            this.dropdown = this.container.find(".select2-drop");

            syncCssClasses(this.dropdown, this.opts.element, this.opts.adaptDropdownCssClass);

            this.dropdown.addClass(evaluate(opts.dropdownCssClass, this.opts.element));
            this.dropdown.data("select2", this);
            this.dropdown.on("click", killEvent);

            this.results = results = this.container.find(resultsSelector);
            this.search = search = this.container.find("input.select2-input");

            this.queryCount = 0;
            this.resultsPage = 0;
            this.context = null;

            // initialize the container
            this.initContainer();

            this.container.on("click", killEvent);

            installFilteredMouseMove(this.results);

            this.dropdown.on("mousemove-filtered", resultsSelector, this.bind(this.highlightUnderEvent));
            this.dropdown.on("touchstart touchmove touchend", resultsSelector, this.bind(function (event) {
                this._touchEvent = true;
                this.highlightUnderEvent(event);
            }));
            this.dropdown.on("touchmove", resultsSelector, this.bind(this.touchMoved));
            this.dropdown.on("touchstart touchend", resultsSelector, this.bind(this.clearTouchMoved));

            // Waiting for a click event on touch devices to select option and hide dropdown
            // otherwise click will be triggered on an underlying element
            this.dropdown.on('click', this.bind(function (event) {
                if (this._touchEvent) {
                    this._touchEvent = false;
                    this.selectHighlighted();
                }
            }));

            installDebouncedScroll(80, this.results);
            this.dropdown.on("scroll-debounced", resultsSelector, this.bind(this.loadMoreIfNeeded));

            // do not propagate change event from the search field out of the component
            $(this.container).on("change", ".select2-input", function(e) {e.stopPropagation();});
            $(this.dropdown).on("change", ".select2-input", function(e) {e.stopPropagation();});

            // if jquery.mousewheel plugin is installed we can prevent out-of-bounds scrolling of results via mousewheel
            if ($.fn.mousewheel) {
                results.mousewheel(function (e, delta, deltaX, deltaY) {
                    var top = results.scrollTop();
                    if (deltaY > 0 && top - deltaY <= 0) {
                        results.scrollTop(0);
                        killEvent(e);
                    } else if (deltaY < 0 && results.get(0).scrollHeight - results.scrollTop() + deltaY <= results.height()) {
                        results.scrollTop(results.get(0).scrollHeight - results.height());
                        killEvent(e);
                    }
                });
            }

            installKeyUpChangeEvent(search);
            search.on("keyup-change input paste", this.bind(this.updateResults));
            search.on("focus", function () { search.addClass("select2-focused"); });
            search.on("blur", function () { search.removeClass("select2-focused");});

            this.dropdown.on("mouseup", resultsSelector, this.bind(function (e) {
                if ($(e.target).closest(".select2-result-selectable").length > 0) {
                    this.highlightUnderEvent(e);
                    this.selectHighlighted(e);
                }
            }));

            // trap all mouse events from leaving the dropdown. sometimes there may be a modal that is listening
            // for mouse events outside of itself so it can close itself. since the dropdown is now outside the select2's
            // dom it will trigger the popup close, which is not what we want
            // focusin can cause focus wars between modals and select2 since the dropdown is outside the modal.
            this.dropdown.on("click mouseup mousedown touchstart touchend focusin", function (e) { e.stopPropagation(); });

            this.nextSearchTerm = undefined;

            if ($.isFunction(this.opts.initSelection)) {
                // initialize selection based on the current value of the source element
                this.initSelection();

                // if the user has provided a function that can set selection based on the value of the source element
                // we monitor the change event on the element and trigger it, allowing for two way synchronization
                this.monitorSource();
            }

            if (opts.maximumInputLength !== null) {
                this.search.attr("maxlength", opts.maximumInputLength);
            }

            var disabled = opts.element.prop("disabled");
            if (disabled === undefined) disabled = false;
            this.enable(!disabled);

            var readonly = opts.element.prop("readonly");
            if (readonly === undefined) readonly = false;
            this.readonly(readonly);

            // Calculate size of scrollbar
            scrollBarDimensions = scrollBarDimensions || measureScrollbar();

            this.autofocus = opts.element.prop("autofocus");
            opts.element.prop("autofocus", false);
            if (this.autofocus) this.focus();

            this.search.attr("placeholder", opts.searchInputPlaceholder);
        },

        // abstract
        destroy: function () {
            var element=this.opts.element, select2 = element.data("select2"), self = this;

            this.close();

            if (element.length && element[0].detachEvent && self._sync) {
                element.each(function () {
                    this.detachEvent("onpropertychange", self._sync);
                });
            }
            if (this.propertyObserver) {
                this.propertyObserver.disconnect();
                this.propertyObserver = null;
            }
            this._sync = null;

            if (select2 !== undefined) {
                select2.container.remove();
                select2.liveRegion.remove();
                select2.dropdown.remove();
                element
                    .removeClass("select2-offscreen")
                    .removeData("select2")
                    .off(".select2")
                    .prop("autofocus", this.autofocus || false);
                if (this.elementTabIndex) {
                    element.attr({tabindex: this.elementTabIndex});
                } else {
                    element.removeAttr("tabindex");
                }
                element.show();
            }

            cleanupJQueryElements.call(this,
                "container",
                "liveRegion",
                "dropdown",
                "results",
                "search"
            );
        },

        // abstract
        optionToData: function(element) {
            if (element.is("option")) {
                return {
                    id:element.prop("value"),
                    text:element.text(),
                    element: element.get(),
                    css: element.attr("class"),
                    disabled: element.prop("disabled"),
                    locked: equal(element.attr("locked"), "locked") || equal(element.data("locked"), true)
                };
            } else if (element.is("optgroup")) {
                return {
                    text:element.attr("label"),
                    children:[],
                    element: element.get(),
                    css: element.attr("class")
                };
            }
        },

        // abstract
        prepareOpts: function (opts) {
            var element, select, idKey, ajaxUrl, self = this;

            element = opts.element;

            if (element.get(0).tagName.toLowerCase() === "select") {
                this.select = select = opts.element;
            }

            if (select) {
                // these options are not allowed when attached to a select because they are picked up off the element itself
                $.each(["id", "multiple", "ajax", "query", "createSearchChoice", "initSelection", "data", "tags"], function () {
                    if (this in opts) {
                        throw new Error("Option '" + this + "' is not allowed for Select2 when attached to a <select> element.");
                    }
                });
            }

            opts = $.extend({}, {
                populateResults: function(container, results, query) {
                    var populate, id=this.opts.id, liveRegion=this.liveRegion;

                    populate=function(results, container, depth) {

                        var i, l, result, selectable, disabled, compound, node, label, innerContainer, formatted;

                        results = opts.sortResults(results, container, query);

                        // collect the created nodes for bulk append
                        var nodes = [];
                        for (i = 0, l = results.length; i < l; i = i + 1) {

                            result=results[i];

                            disabled = (result.disabled === true);
                            selectable = (!disabled) && (id(result) !== undefined);

                            compound=result.children && result.children.length > 0;

                            node=$("<li></li>");
                            node.addClass("select2-results-dept-"+depth);
                            node.addClass("select2-result");
                            node.addClass(selectable ? "select2-result-selectable" : "select2-result-unselectable");
                            if (disabled) { node.addClass("select2-disabled"); }
                            if (compound) { node.addClass("select2-result-with-children"); }
                            node.addClass(self.opts.formatResultCssClass(result));
                            node.attr("role", "presentation");

                            label=$(document.createElement("div"));
                            label.addClass("select2-result-label");
                            label.attr("id", "select2-result-label-" + nextUid());
                            label.attr("role", "option");

                            formatted=opts.formatResult(result, label, query, self.opts.escapeMarkup);
                            if (formatted!==undefined) {
                                label.html(formatted);
                                node.append(label);
                            }


                            if (compound) {

                                innerContainer=$("<ul></ul>");
                                innerContainer.addClass("select2-result-sub");
                                populate(result.children, innerContainer, depth+1);
                                node.append(innerContainer);
                            }

                            node.data("select2-data", result);
                            nodes.push(node[0]);
                        }

                        // bulk append the created nodes
                        container.append(nodes);
                        liveRegion.text(opts.formatMatches(results.length));
                    };

                    populate(results, container, 0);
                }
            }, $.fn.select2.defaults, opts);

            if (typeof(opts.id) !== "function") {
                idKey = opts.id;
                opts.id = function (e) { return e[idKey]; };
            }

            if ($.isArray(opts.element.data("select2Tags"))) {
                if ("tags" in opts) {
                    throw "tags specified as both an attribute 'data-select2-tags' and in options of Select2 " + opts.element.attr("id");
                }
                opts.tags=opts.element.data("select2Tags");
            }

            if (select) {
                opts.query = this.bind(function (query) {
                    var data = { results: [], more: false },
                        term = query.term,
                        children, placeholderOption, process;

                    process=function(element, collection) {
                        var group;
                        if (element.is("option")) {
                            if (query.matcher(term, element.text(), element)) {
                                collection.push(self.optionToData(element));
                            }
                        } else if (element.is("optgroup")) {
                            group=self.optionToData(element);
                            element.children().each2(function(i, elm) { process(elm, group.children); });
                            if (group.children.length>0) {
                                collection.push(group);
                            }
                        }
                    };

                    children=element.children();

                    // ignore the placeholder option if there is one
                    if (this.getPlaceholder() !== undefined && children.length > 0) {
                        placeholderOption = this.getPlaceholderOption();
                        if (placeholderOption) {
                            children=children.not(placeholderOption);
                        }
                    }

                    children.each2(function(i, elm) { process(elm, data.results); });

                    query.callback(data);
                });
                // this is needed because inside val() we construct choices from options and their id is hardcoded
                opts.id=function(e) { return e.id; };
            } else {
                if (!("query" in opts)) {

                    if ("ajax" in opts) {
                        ajaxUrl = opts.element.data("ajax-url");
                        if (ajaxUrl && ajaxUrl.length > 0) {
                            opts.ajax.url = ajaxUrl;
                        }
                        opts.query = ajax.call(opts.element, opts.ajax);
                    } else if ("data" in opts) {
                        opts.query = local(opts.data);
                    } else if ("tags" in opts) {
                        opts.query = tags(opts.tags);
                        if (opts.createSearchChoice === undefined) {
                            opts.createSearchChoice = function (term) { return {id: $.trim(term), text: $.trim(term)}; };
                        }
                        if (opts.initSelection === undefined) {
                            opts.initSelection = function (element, callback) {
                                var data = [];
                                $(splitVal(element.val(), opts.separator)).each(function () {
                                    var obj = { id: this, text: this },
                                        tags = opts.tags;
                                    if ($.isFunction(tags)) tags=tags();
                                    $(tags).each(function() { if (equal(this.id, obj.id)) { obj = this; return false; } });
                                    data.push(obj);
                                });

                                callback(data);
                            };
                        }
                    }
                }
            }
            if (typeof(opts.query) !== "function") {
                throw "query function not defined for Select2 " + opts.element.attr("id");
            }

            if (opts.createSearchChoicePosition === 'top') {
                opts.createSearchChoicePosition = function(list, item) { list.unshift(item); };
            }
            else if (opts.createSearchChoicePosition === 'bottom') {
                opts.createSearchChoicePosition = function(list, item) { list.push(item); };
            }
            else if (typeof(opts.createSearchChoicePosition) !== "function")  {
                throw "invalid createSearchChoicePosition option must be 'top', 'bottom' or a custom function";
            }

            return opts;
        },

        /**
         * Monitor the original element for changes and update select2 accordingly
         */
        // abstract
        monitorSource: function () {
            var el = this.opts.element, observer, self = this;

            el.on("change.select2", this.bind(function (e) {
                if (this.opts.element.data("select2-change-triggered") !== true) {
                    this.initSelection();
                }
            }));

            this._sync = this.bind(function () {

                // sync enabled state
                var disabled = el.prop("disabled");
                if (disabled === undefined) disabled = false;
                this.enable(!disabled);

                var readonly = el.prop("readonly");
                if (readonly === undefined) readonly = false;
                this.readonly(readonly);

                syncCssClasses(this.container, this.opts.element, this.opts.adaptContainerCssClass);
                this.container.addClass(evaluate(this.opts.containerCssClass, this.opts.element));

                syncCssClasses(this.dropdown, this.opts.element, this.opts.adaptDropdownCssClass);
                this.dropdown.addClass(evaluate(this.opts.dropdownCssClass, this.opts.element));

            });

            // IE8-10 (IE9/10 won't fire propertyChange via attachEventListener)
            if (el.length && el[0].attachEvent) {
                el.each(function() {
                    this.attachEvent("onpropertychange", self._sync);
                });
            }

            // safari, chrome, firefox, IE11
            observer = window.MutationObserver || window.WebKitMutationObserver|| window.MozMutationObserver;
            if (observer !== undefined) {
                if (this.propertyObserver) { delete this.propertyObserver; this.propertyObserver = null; }
                this.propertyObserver = new observer(function (mutations) {
                    $.each(mutations, self._sync);
                });
                this.propertyObserver.observe(el.get(0), { attributes:true, subtree:false });
            }
        },

        // abstract
        triggerSelect: function(data) {
            var evt = $.Event("select2-selecting", { val: this.id(data), object: data, choice: data });
            this.opts.element.trigger(evt);
            return !evt.isDefaultPrevented();
        },

        /**
         * Triggers the change event on the source element
         */
        // abstract
        triggerChange: function (details) {

            details = details || {};
            details= $.extend({}, details, { type: "change", val: this.val() });
            // prevents recursive triggering
            this.opts.element.data("select2-change-triggered", true);
            this.opts.element.trigger(details);
            this.opts.element.data("select2-change-triggered", false);

            // some validation frameworks ignore the change event and listen instead to keyup, click for selects
            // so here we trigger the click event manually
            this.opts.element.click();

            // ValidationEngine ignores the change event and listens instead to blur
            // so here we trigger the blur event manually if so desired
            if (this.opts.blurOnChange)
                this.opts.element.blur();
        },

        //abstract
        isInterfaceEnabled: function()
        {
            return this.enabledInterface === true;
        },

        // abstract
        enableInterface: function() {
            var enabled = this._enabled && !this._readonly,
                disabled = !enabled;

            if (enabled === this.enabledInterface) return false;

            this.container.toggleClass("select2-container-disabled", disabled);
            this.close();
            this.enabledInterface = enabled;

            return true;
        },

        // abstract
        enable: function(enabled) {
            if (enabled === undefined) enabled = true;
            if (this._enabled === enabled) return;
            this._enabled = enabled;

            this.opts.element.prop("disabled", !enabled);
            this.enableInterface();
        },

        // abstract
        disable: function() {
            this.enable(false);
        },

        // abstract
        readonly: function(enabled) {
            if (enabled === undefined) enabled = false;
            if (this._readonly === enabled) return;
            this._readonly = enabled;

            this.opts.element.prop("readonly", enabled);
            this.enableInterface();
        },

        // abstract
        opened: function () {
            return (this.container) ? this.container.hasClass("select2-dropdown-open") : false;
        },

        // abstract
        positionDropdown: function() {
            var $dropdown = this.dropdown,
                container = this.container,
                offset = container.offset(),
                height = container.outerHeight(false),
                width = container.outerWidth(false),
                dropHeight = $dropdown.outerHeight(false),
                $window = $(window),
                windowWidth = $window.width(),
                windowHeight = $window.height(),
                viewPortRight = $window.scrollLeft() + windowWidth,
                viewportBottom = $window.scrollTop() + windowHeight,
                dropTop = offset.top + height,
                dropLeft = offset.left,
                enoughRoomBelow = dropTop + dropHeight <= viewportBottom,
                enoughRoomAbove = (offset.top - dropHeight) >= $window.scrollTop(),
                dropWidth = $dropdown.outerWidth(false),
                enoughRoomOnRight = function() {
                    return dropLeft + dropWidth <= viewPortRight;
                },
                enoughRoomOnLeft = function() {
                    return offset.left + viewPortRight + container.outerWidth(false)  > dropWidth;
                },
                aboveNow = $dropdown.hasClass("select2-drop-above"),
                bodyOffset,
                above,
                changeDirection,
                css,
                resultsListNode;

            // always prefer the current above/below alignment, unless there is not enough room
            if (aboveNow) {
                above = true;
                if (!enoughRoomAbove && enoughRoomBelow) {
                    changeDirection = true;
                    above = false;
                }
            } else {
                above = false;
                if (!enoughRoomBelow && enoughRoomAbove) {
                    changeDirection = true;
                    above = true;
                }
            }

            //if we are changing direction we need to get positions when dropdown is hidden;
            if (changeDirection) {
                $dropdown.hide();
                offset = this.container.offset();
                height = this.container.outerHeight(false);
                width = this.container.outerWidth(false);
                dropHeight = $dropdown.outerHeight(false);
                viewPortRight = $window.scrollLeft() + windowWidth;
                viewportBottom = $window.scrollTop() + windowHeight;
                dropTop = offset.top + height;
                dropLeft = offset.left;
                dropWidth = $dropdown.outerWidth(false);
                $dropdown.show();

                // fix so the cursor does not move to the left within the search-textbox in IE
                this.focusSearch();
            }

            if (this.opts.dropdownAutoWidth) {
                resultsListNode = $('.select2-results', $dropdown)[0];
                $dropdown.addClass('select2-drop-auto-width');
                $dropdown.css('width', '');
                // Add scrollbar width to dropdown if vertical scrollbar is present
                dropWidth = $dropdown.outerWidth(false) + (resultsListNode.scrollHeight === resultsListNode.clientHeight ? 0 : scrollBarDimensions.width);
                dropWidth > width ? width = dropWidth : dropWidth = width;
                dropHeight = $dropdown.outerHeight(false);
            }
            else {
                this.container.removeClass('select2-drop-auto-width');
            }

            //console.log("below/ droptop:", dropTop, "dropHeight", dropHeight, "sum", (dropTop+dropHeight)+" viewport bottom", viewportBottom, "enough?", enoughRoomBelow);
            //console.log("above/ offset.top", offset.top, "dropHeight", dropHeight, "top", (offset.top-dropHeight), "scrollTop", this.body.scrollTop(), "enough?", enoughRoomAbove);

            // fix positioning when body has an offset and is not position: static
            if (this.body.css('position') !== 'static') {
                bodyOffset = this.body.offset();
                dropTop -= bodyOffset.top;
                dropLeft -= bodyOffset.left;
            }

            if (!enoughRoomOnRight() && enoughRoomOnLeft()) {
                dropLeft = offset.left + this.container.outerWidth(false) - dropWidth;
            }

            css =  {
                left: dropLeft,
                width: width
            };

            if (above) {
                css.top = offset.top - dropHeight;
                css.bottom = 'auto';
                this.container.addClass("select2-drop-above");
                $dropdown.addClass("select2-drop-above");
            }
            else {
                css.top = dropTop;
                css.bottom = 'auto';
                this.container.removeClass("select2-drop-above");
                $dropdown.removeClass("select2-drop-above");
            }
            css = $.extend(css, evaluate(this.opts.dropdownCss, this.opts.element));

            $dropdown.css(css);
        },

        // abstract
        shouldOpen: function() {
            var event;

            if (this.opened()) return false;

            if (this._enabled === false || this._readonly === true) return false;

            event = $.Event("select2-opening");
            this.opts.element.trigger(event);
            return !event.isDefaultPrevented();
        },

        // abstract
        clearDropdownAlignmentPreference: function() {
            // clear the classes used to figure out the preference of where the dropdown should be opened
            this.container.removeClass("select2-drop-above");
            this.dropdown.removeClass("select2-drop-above");
        },

        /**
         * Opens the dropdown
         *
         * @return {Boolean} whether or not dropdown was opened. This method will return false if, for example,
         * the dropdown is already open, or if the 'open' event listener on the element called preventDefault().
         */
        // abstract
        open: function () {

            if (!this.shouldOpen()) return false;

            this.opening();

            // Only bind the document mousemove when the dropdown is visible
            $document.on("mousemove.select2Event", function (e) {
                lastMousePosition.x = e.pageX;
                lastMousePosition.y = e.pageY;
            });

            return true;
        },

        /**
         * Performs the opening of the dropdown
         */
        // abstract
        opening: function() {
            var cid = this.containerEventName,
                scroll = "scroll." + cid,
                resize = "resize."+cid,
                orient = "orientationchange."+cid,
                mask;

            this.container.addClass("select2-dropdown-open").addClass("select2-container-active");

            this.clearDropdownAlignmentPreference();

            if(this.dropdown[0] !== this.body.children().last()[0]) {
                this.dropdown.detach().appendTo(this.body);
            }

            // create the dropdown mask if doesn't already exist
            mask = $("#select2-drop-mask");
            if (mask.length == 0) {
                mask = $(document.createElement("div"));
                mask.attr("id","select2-drop-mask").attr("class","select2-drop-mask");
                mask.hide();
                mask.appendTo(this.body);
                mask.on("mousedown touchstart click", function (e) {
                    // Prevent IE from generating a click event on the body
                    reinsertElement(mask);

                    var dropdown = $("#select2-drop"), self;
                    if (dropdown.length > 0) {
                        self=dropdown.data("select2");
                        if (self.opts.selectOnBlur) {
                            self.selectHighlighted({noFocus: true});
                        }
                        self.close();
                        e.preventDefault();
                        e.stopPropagation();
                    }
                });
            }

            // ensure the mask is always right before the dropdown
            if (this.dropdown.prev()[0] !== mask[0]) {
                this.dropdown.before(mask);
            }

            // move the global id to the correct dropdown
            $("#select2-drop").removeAttr("id");
            this.dropdown.attr("id", "select2-drop");

            // show the elements
            mask.show();

            this.positionDropdown();
            this.dropdown.show();
            this.positionDropdown();

            this.dropdown.addClass("select2-drop-active");

            // attach listeners to events that can change the position of the container and thus require
            // the position of the dropdown to be updated as well so it does not come unglued from the container
            var that = this;
            this.container.parents().add(window).each(function () {
                $(this).on(resize+" "+scroll+" "+orient, function (e) {
                    if (that.opened()) that.positionDropdown();
                });
            });


        },

        // abstract
        close: function () {
            if (!this.opened()) return;

            var cid = this.containerEventName,
                scroll = "scroll." + cid,
                resize = "resize."+cid,
                orient = "orientationchange."+cid;

            // unbind event listeners
            this.container.parents().add(window).each(function () { $(this).off(scroll).off(resize).off(orient); });

            this.clearDropdownAlignmentPreference();

            $("#select2-drop-mask").hide();
            this.dropdown.removeAttr("id"); // only the active dropdown has the select2-drop id
            this.dropdown.hide();
            this.container.removeClass("select2-dropdown-open").removeClass("select2-container-active");
            this.results.empty();

            // Now that the dropdown is closed, unbind the global document mousemove event
            $document.off("mousemove.select2Event");

            this.clearSearch();
            this.search.removeClass("select2-active");
            this.opts.element.trigger($.Event("select2-close"));
        },

        /**
         * Opens control, sets input value, and updates results.
         */
        // abstract
        externalSearch: function (term) {
            this.open();
            this.search.val(term);
            this.updateResults(false);
        },

        // abstract
        clearSearch: function () {

        },

        //abstract
        getMaximumSelectionSize: function() {
            return evaluate(this.opts.maximumSelectionSize, this.opts.element);
        },

        // abstract
        ensureHighlightVisible: function () {
            var results = this.results, children, index, child, hb, rb, y, more, topOffset;

            index = this.highlight();

            if (index < 0) return;

            if (index == 0) {

                // if the first element is highlighted scroll all the way to the top,
                // that way any unselectable headers above it will also be scrolled
                // into view

                results.scrollTop(0);
                return;
            }

            children = this.findHighlightableChoices().find('.select2-result-label');

            child = $(children[index]);

            topOffset = (child.offset() || {}).top || 0;

            hb = topOffset + child.outerHeight(true);

            // if this is the last child lets also make sure select2-more-results is visible
            if (index === children.length - 1) {
                more = results.find("li.select2-more-results");
                if (more.length > 0) {
                    hb = more.offset().top + more.outerHeight(true);
                }
            }

            rb = results.offset().top + results.outerHeight(true);
            if (hb > rb) {
                results.scrollTop(results.scrollTop() + (hb - rb));
            }
            y = topOffset - results.offset().top;

            // make sure the top of the element is visible
            if (y < 0 && child.css('display') != 'none' ) {
                results.scrollTop(results.scrollTop() + y); // y is negative
            }
        },

        // abstract
        findHighlightableChoices: function() {
            return this.results.find(".select2-result-selectable:not(.select2-disabled):not(.select2-selected)");
        },

        // abstract
        moveHighlight: function (delta) {
            var choices = this.findHighlightableChoices(),
                index = this.highlight();

            while (index > -1 && index < choices.length) {
                index += delta;
                var choice = $(choices[index]);
                if (choice.hasClass("select2-result-selectable") && !choice.hasClass("select2-disabled") && !choice.hasClass("select2-selected")) {
                    this.highlight(index);
                    break;
                }
            }
        },

        // abstract
        highlight: function (index) {
            var choices = this.findHighlightableChoices(),
                choice,
                data;

            if (arguments.length === 0) {
                return indexOf(choices.filter(".select2-highlighted")[0], choices.get());
            }

            if (index >= choices.length) index = choices.length - 1;
            if (index < 0) index = 0;

            this.removeHighlight();

            choice = $(choices[index]);
            choice.addClass("select2-highlighted");

            // ensure assistive technology can determine the active choice
            this.search.attr("aria-activedescendant", choice.find(".select2-result-label").attr("id"));

            this.ensureHighlightVisible();

            this.liveRegion.text(choice.text());

            data = choice.data("select2-data");
            if (data) {
                this.opts.element.trigger({ type: "select2-highlight", val: this.id(data), choice: data });
            }
        },

        removeHighlight: function() {
            this.results.find(".select2-highlighted").removeClass("select2-highlighted");
        },

        touchMoved: function() {
            this._touchMoved = true;
        },

        clearTouchMoved: function() {
          this._touchMoved = false;
        },

        // abstract
        countSelectableResults: function() {
            return this.findHighlightableChoices().length;
        },

        // abstract
        highlightUnderEvent: function (event) {
            var el = $(event.target).closest(".select2-result-selectable");
            if (el.length > 0 && !el.is(".select2-highlighted")) {
                var choices = this.findHighlightableChoices();
                this.highlight(choices.index(el));
            } else if (el.length == 0) {
                // if we are over an unselectable item remove all highlights
                this.removeHighlight();
            }
        },

        // abstract
        loadMoreIfNeeded: function () {
            var results = this.results,
                more = results.find("li.select2-more-results"),
                below, // pixels the element is below the scroll fold, below==0 is when the element is starting to be visible
                page = this.resultsPage + 1,
                self=this,
                term=this.search.val(),
                context=this.context;

            if (more.length === 0) return;
            below = more.offset().top - results.offset().top - results.height();

            if (below <= this.opts.loadMorePadding) {
                more.addClass("select2-active");
                this.opts.query({
                        element: this.opts.element,
                        term: term,
                        page: page,
                        context: context,
                        matcher: this.opts.matcher,
                        callback: this.bind(function (data) {

                    // ignore a response if the select2 has been closed before it was received
                    if (!self.opened()) return;


                    self.opts.populateResults.call(this, results, data.results, {term: term, page: page, context:context});
                    self.postprocessResults(data, false, false);

                    if (data.more===true) {
                        more.detach().appendTo(results).text(evaluate(self.opts.formatLoadMore, self.opts.element, page+1));
                        window.setTimeout(function() { self.loadMoreIfNeeded(); }, 10);
                    } else {
                        more.remove();
                    }
                    self.positionDropdown();
                    self.resultsPage = page;
                    self.context = data.context;
                    this.opts.element.trigger({ type: "select2-loaded", items: data });
                })});
            }
        },

        /**
         * Default tokenizer function which does nothing
         */
        tokenize: function() {

        },

        /**
         * @param initial whether or not this is the call to this method right after the dropdown has been opened
         */
        // abstract
        updateResults: function (initial) {
            var search = this.search,
                results = this.results,
                opts = this.opts,
                data,
                self = this,
                input,
                term = search.val(),
                lastTerm = $.data(this.container, "select2-last-term"),
                // sequence number used to drop out-of-order responses
                queryNumber;

            // prevent duplicate queries against the same term
            if (initial !== true && lastTerm && equal(term, lastTerm)) return;

            $.data(this.container, "select2-last-term", term);

            // if the search is currently hidden we do not alter the results
            if (initial !== true && (this.showSearchInput === false || !this.opened())) {
                return;
            }

            function postRender() {
                search.removeClass("select2-active");
                self.positionDropdown();
                if (results.find('.select2-no-results,.select2-selection-limit,.select2-searching').length) {
                    self.liveRegion.text(results.text());
                }
                else {
                    self.liveRegion.text(self.opts.formatMatches(results.find('.select2-result-selectable').length));
                }
            }

            function render(html) {
                results.html(html);
                postRender();
            }

            queryNumber = ++this.queryCount;

            var maxSelSize = this.getMaximumSelectionSize();
            if (maxSelSize >=1) {
                data = this.data();
                if ($.isArray(data) && data.length >= maxSelSize && checkFormatter(opts.formatSelectionTooBig, "formatSelectionTooBig")) {
                    render("<li class='select2-selection-limit'>" + evaluate(opts.formatSelectionTooBig, opts.element, maxSelSize) + "</li>");
                    return;
                }
            }

            if (search.val().length < opts.minimumInputLength) {
                if (checkFormatter(opts.formatInputTooShort, "formatInputTooShort")) {
                    render("<li class='select2-no-results'>" + evaluate(opts.formatInputTooShort, opts.element, search.val(), opts.minimumInputLength) + "</li>");
                } else {
                    render("");
                }
                if (initial && this.showSearch) this.showSearch(true);
                return;
            }

            if (opts.maximumInputLength && search.val().length > opts.maximumInputLength) {
                if (checkFormatter(opts.formatInputTooLong, "formatInputTooLong")) {
                    render("<li class='select2-no-results'>" + evaluate(opts.formatInputTooLong, opts.element, search.val(), opts.maximumInputLength) + "</li>");
                } else {
                    render("");
                }
                return;
            }

            if (opts.formatSearching && this.findHighlightableChoices().length === 0) {
                render("<li class='select2-searching'>" + evaluate(opts.formatSearching, opts.element) + "</li>");
            }

            search.addClass("select2-active");

            this.removeHighlight();

            // give the tokenizer a chance to pre-process the input
            input = this.tokenize();
            if (input != undefined && input != null) {
                search.val(input);
            }

            this.resultsPage = 1;

            opts.query({
                element: opts.element,
                    term: search.val(),
                    page: this.resultsPage,
                    context: null,
                    matcher: opts.matcher,
                    callback: this.bind(function (data) {
                var def; // default choice

                // ignore old responses
                if (queryNumber != this.queryCount) {
                  return;
                }

                // ignore a response if the select2 has been closed before it was received
                if (!this.opened()) {
                    this.search.removeClass("select2-active");
                    return;
                }

                // handle ajax error
                if(data.hasError !== undefined && checkFormatter(opts.formatAjaxError, "formatAjaxError")) {
                    render("<li class='select2-ajax-error'>" + evaluate(opts.formatAjaxError, opts.element, data.jqXHR, data.textStatus, data.errorThrown) + "</li>");
                    return;
                }

                // save context, if any
                this.context = (data.context===undefined) ? null : data.context;
                // create a default choice and prepend it to the list
                if (this.opts.createSearchChoice && search.val() !== "") {
                    def = this.opts.createSearchChoice.call(self, search.val(), data.results);
                    if (def !== undefined && def !== null && self.id(def) !== undefined && self.id(def) !== null) {
                        if ($(data.results).filter(
                            function () {
                                return equal(self.id(this), self.id(def));
                            }).length === 0) {
                            this.opts.createSearchChoicePosition(data.results, def);
                        }
                    }
                }

                if (data.results.length === 0 && checkFormatter(opts.formatNoMatches, "formatNoMatches")) {
                    render("<li class='select2-no-results'>" + evaluate(opts.formatNoMatches, opts.element, search.val()) + "</li>");
                    return;
                }

                results.empty();
                self.opts.populateResults.call(this, results, data.results, {term: search.val(), page: this.resultsPage, context:null});

                if (data.more === true && checkFormatter(opts.formatLoadMore, "formatLoadMore")) {
                    results.append("<li class='select2-more-results'>" + opts.escapeMarkup(evaluate(opts.formatLoadMore, opts.element, this.resultsPage)) + "</li>");
                    window.setTimeout(function() { self.loadMoreIfNeeded(); }, 10);
                }

                this.postprocessResults(data, initial);

                postRender();

                this.opts.element.trigger({ type: "select2-loaded", items: data });
            })});
        },

        // abstract
        cancel: function () {
            this.close();
        },

        // abstract
        blur: function () {
            // if selectOnBlur == true, select the currently highlighted option
            if (this.opts.selectOnBlur)
                this.selectHighlighted({noFocus: true});

            this.close();
            this.container.removeClass("select2-container-active");
            // synonymous to .is(':focus'), which is available in jquery >= 1.6
            if (this.search[0] === document.activeElement) { this.search.blur(); }
            this.clearSearch();
            this.selection.find(".select2-search-choice-focus").removeClass("select2-search-choice-focus");
        },

        // abstract
        focusSearch: function () {
            focus(this.search);
        },

        // abstract
        selectHighlighted: function (options) {
            if (this._touchMoved) {
              this.clearTouchMoved();
              return;
            }
            var index=this.highlight(),
                highlighted=this.results.find(".select2-highlighted"),
                data = highlighted.closest('.select2-result').data("select2-data");

            if (data) {
                this.highlight(index);
                this.onSelect(data, options);
            } else if (options && options.noFocus) {
                this.close();
            }
        },

        // abstract
        getPlaceholder: function () {
            var placeholderOption;
            return this.opts.element.attr("placeholder") ||
                this.opts.element.attr("data-placeholder") || // jquery 1.4 compat
                this.opts.element.data("placeholder") ||
                this.opts.placeholder ||
                ((placeholderOption = this.getPlaceholderOption()) !== undefined ? placeholderOption.text() : undefined);
        },

        // abstract
        getPlaceholderOption: function() {
            if (this.select) {
                var firstOption = this.select.children('option').first();
                if (this.opts.placeholderOption !== undefined ) {
                    //Determine the placeholder option based on the specified placeholderOption setting
                    return (this.opts.placeholderOption === "first" && firstOption) ||
                           (typeof this.opts.placeholderOption === "function" && this.opts.placeholderOption(this.select));
                } else if ($.trim(firstOption.text()) === "" && firstOption.val() === "") {
                    //No explicit placeholder option specified, use the first if it's blank
                    return firstOption;
                }
            }
        },

        /**
         * Get the desired width for the container element.  This is
         * derived first from option `width` passed to select2, then
         * the inline 'style' on the original element, and finally
         * falls back to the jQuery calculated element width.
         */
        // abstract
        initContainerWidth: function () {
            function resolveContainerWidth() {
                var style, attrs, matches, i, l, attr;

                if (this.opts.width === "off") {
                    return null;
                } else if (this.opts.width === "element"){
                    return this.opts.element.outerWidth(false) === 0 ? 'auto' : this.opts.element.outerWidth(false) + 'px';
                } else if (this.opts.width === "copy" || this.opts.width === "resolve") {
                    // check if there is inline style on the element that contains width
                    style = this.opts.element.attr('style');
                    if (style !== undefined) {
                        attrs = style.split(';');
                        for (i = 0, l = attrs.length; i < l; i = i + 1) {
                            attr = attrs[i].replace(/\s/g, '');
                            matches = attr.match(/^width:(([-+]?([0-9]*\.)?[0-9]+)(px|em|ex|%|in|cm|mm|pt|pc))/i);
                            if (matches !== null && matches.length >= 1)
                                return matches[1];
                        }
                    }

                    if (this.opts.width === "resolve") {
                        // next check if css('width') can resolve a width that is percent based, this is sometimes possible
                        // when attached to input type=hidden or elements hidden via css
                        style = this.opts.element.css('width');
                        if (style.indexOf("%") > 0) return style;

                        // finally, fallback on the calculated width of the element
                        return (this.opts.element.outerWidth(false) === 0 ? 'auto' : this.opts.element.outerWidth(false) + 'px');
                    }

                    return null;
                } else if ($.isFunction(this.opts.width)) {
                    return this.opts.width();
                } else {
                    return this.opts.width;
               }
            };

            var width = resolveContainerWidth.call(this);
            if (width !== null) {
                this.container.css("width", width);
            }
        }
    });

    SingleSelect2 = clazz(AbstractSelect2, {

        // single

        createContainer: function () {
            var container = $(document.createElement("div")).attr({
                "class": "select2-container"
            }).html([
                "<a href='javascript:void(0)' class='select2-choice' tabindex='-1'>",
                "   <span class='select2-chosen'>&#160;</span><abbr class='select2-search-choice-close'></abbr>",
                "   <span class='select2-arrow' role='presentation'><b role='presentation'></b></span>",
                "</a>",
                "<label for='' class='select2-offscreen'></label>",
                "<input class='select2-focusser select2-offscreen' type='text' aria-haspopup='true' role='button' />",
                "<div class='select2-drop select2-display-none'>",
                "   <div class='select2-search'>",
                "       <label for='' class='select2-offscreen'></label>",
                "       <input type='text' autocomplete='off' autocorrect='off' autocapitalize='off' spellcheck='false' class='select2-input' role='combobox' aria-expanded='true'",
                "       aria-autocomplete='list' />",
                "   </div>",
                "   <ul class='select2-results' role='listbox'>",
                "   </ul>",
                "</div>"].join(""));
            return container;
        },

        // single
        enableInterface: function() {
            if (this.parent.enableInterface.apply(this, arguments)) {
                this.focusser.prop("disabled", !this.isInterfaceEnabled());
            }
        },

        // single
        opening: function () {
            var el, range, len;

            if (this.opts.minimumResultsForSearch >= 0) {
                this.showSearch(true);
            }

            this.parent.opening.apply(this, arguments);

            if (this.showSearchInput !== false) {
                // IE appends focusser.val() at the end of field :/ so we manually insert it at the beginning using a range
                // all other browsers handle this just fine

                this.search.val(this.focusser.val());
            }
            if (this.opts.shouldFocusInput(this)) {
                this.search.focus();
                // move the cursor to the end after focussing, otherwise it will be at the beginning and
                // new text will appear *before* focusser.val()
                el = this.search.get(0);
                if (el.createTextRange) {
                    range = el.createTextRange();
                    range.collapse(false);
                    range.select();
                } else if (el.setSelectionRange) {
                    len = this.search.val().length;
                    el.setSelectionRange(len, len);
                }
            }

            // initializes search's value with nextSearchTerm (if defined by user)
            // ignore nextSearchTerm if the dropdown is opened by the user pressing a letter
            if(this.search.val() === "") {
                if(this.nextSearchTerm != undefined){
                    this.search.val(this.nextSearchTerm);
                    this.search.select();
                }
            }

            this.focusser.prop("disabled", true).val("");
            this.updateResults(true);
            this.opts.element.trigger($.Event("select2-open"));
        },

        // single
        close: function () {
            if (!this.opened()) return;
            this.parent.close.apply(this, arguments);

            this.focusser.prop("disabled", false);

            if (this.opts.shouldFocusInput(this)) {
                this.focusser.focus();
            }
        },

        // single
        focus: function () {
            if (this.opened()) {
                this.close();
            } else {
                this.focusser.prop("disabled", false);
                if (this.opts.shouldFocusInput(this)) {
                    this.focusser.focus();
                }
            }
        },

        // single
        isFocused: function () {
            return this.container.hasClass("select2-container-active");
        },

        // single
        cancel: function () {
            this.parent.cancel.apply(this, arguments);
            this.focusser.prop("disabled", false);

            if (this.opts.shouldFocusInput(this)) {
                this.focusser.focus();
            }
        },

        // single
        destroy: function() {
            $("label[for='" + this.focusser.attr('id') + "']")
                .attr('for', this.opts.element.attr("id"));
            this.parent.destroy.apply(this, arguments);

            cleanupJQueryElements.call(this,
                "selection",
                "focusser"
            );
        },

        // single
        initContainer: function () {

            var selection,
                container = this.container,
                dropdown = this.dropdown,
                idSuffix = nextUid(),
                elementLabel;

            if (this.opts.minimumResultsForSearch < 0) {
                this.showSearch(false);
            } else {
                this.showSearch(true);
            }

            this.selection = selection = container.find(".select2-choice");

            this.focusser = container.find(".select2-focusser");

            // add aria associations
            selection.find(".select2-chosen").attr("id", "select2-chosen-"+idSuffix);
            this.focusser.attr("aria-labelledby", "select2-chosen-"+idSuffix);
            this.results.attr("id", "select2-results-"+idSuffix);
            this.search.attr("aria-owns", "select2-results-"+idSuffix);

            // rewrite labels from original element to focusser
            this.focusser.attr("id", "s2id_autogen"+idSuffix);

            elementLabel = $("label[for='" + this.opts.element.attr("id") + "']");

            this.focusser.prev()
                .text(elementLabel.text())
                .attr('for', this.focusser.attr('id'));

            // Ensure the original element retains an accessible name
            var originalTitle = this.opts.element.attr("title");
            this.opts.element.attr("title", (originalTitle || elementLabel.text()));

            this.focusser.attr("tabindex", this.elementTabIndex);

            // write label for search field using the label from the focusser element
            this.search.attr("id", this.focusser.attr('id') + '_search');

            this.search.prev()
                .text($("label[for='" + this.focusser.attr('id') + "']").text())
                .attr('for', this.search.attr('id'));

            this.search.on("keydown", this.bind(function (e) {
                if (!this.isInterfaceEnabled()) return;

                // filter 229 keyCodes (input method editor is processing key input)
                if (229 == e.keyCode) return;

                if (e.which === KEY.PAGE_UP || e.which === KEY.PAGE_DOWN) {
                    // prevent the page from scrolling
                    killEvent(e);
                    return;
                }

                switch (e.which) {
                    case KEY.UP:
                    case KEY.DOWN:
                        this.moveHighlight((e.which === KEY.UP) ? -1 : 1);
                        killEvent(e);
                        return;
                    case KEY.ENTER:
                        this.selectHighlighted();
                        killEvent(e);
                        return;
                    case KEY.TAB:
                        this.selectHighlighted({noFocus: true});
                        return;
                    case KEY.ESC:
                        this.cancel(e);
                        killEvent(e);
                        return;
                }
            }));

            this.search.on("blur", this.bind(function(e) {
                // a workaround for chrome to keep the search field focussed when the scroll bar is used to scroll the dropdown.
                // without this the search field loses focus which is annoying
                if (document.activeElement === this.body.get(0)) {
                    window.setTimeout(this.bind(function() {
                        if (this.opened()) {
                            this.search.focus();
                        }
                    }), 0);
                }
            }));

            this.focusser.on("keydown", this.bind(function (e) {
                if (!this.isInterfaceEnabled()) return;

                if (e.which === KEY.TAB || KEY.isControl(e) || KEY.isFunctionKey(e) || e.which === KEY.ESC) {
                    return;
                }

                if (this.opts.openOnEnter === false && e.which === KEY.ENTER) {
                    killEvent(e);
                    return;
                }

                if (e.which == KEY.DOWN || e.which == KEY.UP
                    || (e.which == KEY.ENTER && this.opts.openOnEnter)) {

                    if (e.altKey || e.ctrlKey || e.shiftKey || e.metaKey) return;

                    this.open();
                    killEvent(e);
                    return;
                }

                if (e.which == KEY.DELETE || e.which == KEY.BACKSPACE) {
                    if (this.opts.allowClear) {
                        this.clear();
                    }
                    killEvent(e);
                    return;
                }
            }));


            installKeyUpChangeEvent(this.focusser);
            this.focusser.on("keyup-change input", this.bind(function(e) {
                if (this.opts.minimumResultsForSearch >= 0) {
                    e.stopPropagation();
                    if (this.opened()) return;
                    this.open();
                }
            }));

            selection.on("mousedown touchstart", "abbr", this.bind(function (e) {
                if (!this.isInterfaceEnabled()) return;
                this.clear();
                killEventImmediately(e);
                this.close();
                this.selection.focus();
            }));

            selection.on("mousedown touchstart", this.bind(function (e) {
                // Prevent IE from generating a click event on the body
                reinsertElement(selection);

                if (!this.container.hasClass("select2-container-active")) {
                    this.opts.element.trigger($.Event("select2-focus"));
                }

                if (this.opened()) {
                    this.close();
                } else if (this.isInterfaceEnabled()) {
                    this.open();
                }

                killEvent(e);
            }));

            dropdown.on("mousedown touchstart", this.bind(function() {
                if (this.opts.shouldFocusInput(this)) {
                    this.search.focus();
                }
            }));

            selection.on("focus", this.bind(function(e) {
                killEvent(e);
            }));

            this.focusser.on("focus", this.bind(function(){
                if (!this.container.hasClass("select2-container-active")) {
                    this.opts.element.trigger($.Event("select2-focus"));
                }
                this.container.addClass("select2-container-active");
            })).on("blur", this.bind(function() {
                if (!this.opened()) {
                    this.container.removeClass("select2-container-active");
                    this.opts.element.trigger($.Event("select2-blur"));
                }
            }));
            this.search.on("focus", this.bind(function(){
                if (!this.container.hasClass("select2-container-active")) {
                    this.opts.element.trigger($.Event("select2-focus"));
                }
                this.container.addClass("select2-container-active");
            }));

            this.initContainerWidth();
            this.opts.element.addClass("select2-offscreen");
            this.setPlaceholder();

        },

        // single
        clear: function(triggerChange) {
            var data=this.selection.data("select2-data");
            if (data) { // guard against queued quick consecutive clicks
                var evt = $.Event("select2-clearing");
                this.opts.element.trigger(evt);
                if (evt.isDefaultPrevented()) {
                    return;
                }
                var placeholderOption = this.getPlaceholderOption();
                this.opts.element.val(placeholderOption ? placeholderOption.val() : "");
                this.selection.find(".select2-chosen").empty();
                this.selection.removeData("select2-data");
                this.setPlaceholder();

                if (triggerChange !== false){
                    this.opts.element.trigger({ type: "select2-removed", val: this.id(data), choice: data });
                    this.triggerChange({removed:data});
                }
            }
        },

        /**
         * Sets selection based on source element's value
         */
        // single
        initSelection: function () {
            var selected;
            if (this.isPlaceholderOptionSelected()) {
                this.updateSelection(null);
                this.close();
                this.setPlaceholder();
            } else {
                var self = this;
                this.opts.initSelection.call(null, this.opts.element, function(selected){
                    if (selected !== undefined && selected !== null) {
                        self.updateSelection(selected);
                        self.close();
                        self.setPlaceholder();
                        self.nextSearchTerm = self.opts.nextSearchTerm(selected, self.search.val());
                    }
                });
            }
        },

        isPlaceholderOptionSelected: function() {
            var placeholderOption;
            if (this.getPlaceholder() === undefined) return false; // no placeholder specified so no option should be considered
            return ((placeholderOption = this.getPlaceholderOption()) !== undefined && placeholderOption.prop("selected"))
                || (this.opts.element.val() === "")
                || (this.opts.element.val() === undefined)
                || (this.opts.element.val() === null);
        },

        // single
        prepareOpts: function () {
            var opts = this.parent.prepareOpts.apply(this, arguments),
                self=this;

            if (opts.element.get(0).tagName.toLowerCase() === "select") {
                // install the selection initializer
                opts.initSelection = function (element, callback) {
                    var selected = element.find("option").filter(function() { return this.selected && !this.disabled });
                    // a single select box always has a value, no need to null check 'selected'
                    callback(self.optionToData(selected));
                };
            } else if ("data" in opts) {
                // install default initSelection when applied to hidden input and data is local
                opts.initSelection = opts.initSelection || function (element, callback) {
                    var id = element.val();
                    //search in data by id, storing the actual matching item
                    var match = null;
                    opts.query({
                        matcher: function(term, text, el){
                            var is_match = equal(id, opts.id(el));
                            if (is_match) {
                                match = el;
                            }
                            return is_match;
                        },
                        callback: !$.isFunction(callback) ? $.noop : function() {
                            callback(match);
                        }
                    });
                };
            }

            return opts;
        },

        // single
        getPlaceholder: function() {
            // if a placeholder is specified on a single select without a valid placeholder option ignore it
            if (this.select) {
                if (this.getPlaceholderOption() === undefined) {
                    return undefined;
                }
            }

            return this.parent.getPlaceholder.apply(this, arguments);
        },

        // single
        setPlaceholder: function () {
            var placeholder = this.getPlaceholder();

            if (this.isPlaceholderOptionSelected() && placeholder !== undefined) {

                // check for a placeholder option if attached to a select
                if (this.select && this.getPlaceholderOption() === undefined) return;

                this.selection.find(".select2-chosen").html(this.opts.escapeMarkup(placeholder));

                this.selection.addClass("select2-default");

                this.container.removeClass("select2-allowclear");
            }
        },

        // single
        postprocessResults: function (data, initial, noHighlightUpdate) {
            var selected = 0, self = this, showSearchInput = true;

            // find the selected element in the result list

            this.findHighlightableChoices().each2(function (i, elm) {
                if (equal(self.id(elm.data("select2-data")), self.opts.element.val())) {
                    selected = i;
                    return false;
                }
            });

            // and highlight it
            if (noHighlightUpdate !== false) {
                if (initial === true && selected >= 0) {
                    this.highlight(selected);
                } else {
                    this.highlight(0);
                }
            }

            // hide the search box if this is the first we got the results and there are enough of them for search

            if (initial === true) {
                var min = this.opts.minimumResultsForSearch;
                if (min >= 0) {
                    this.showSearch(countResults(data.results) >= min);
                }
            }
        },

        // single
        showSearch: function(showSearchInput) {
            if (this.showSearchInput === showSearchInput) return;

            this.showSearchInput = showSearchInput;

            this.dropdown.find(".select2-search").toggleClass("select2-search-hidden", !showSearchInput);
            this.dropdown.find(".select2-search").toggleClass("select2-offscreen", !showSearchInput);
            //add "select2-with-searchbox" to the container if search box is shown
            $(this.dropdown, this.container).toggleClass("select2-with-searchbox", showSearchInput);
        },

        // single
        onSelect: function (data, options) {

            if (!this.triggerSelect(data)) { return; }

            var old = this.opts.element.val(),
                oldData = this.data();

            this.opts.element.val(this.id(data));
            this.updateSelection(data);

            this.opts.element.trigger({ type: "select2-selected", val: this.id(data), choice: data });

            this.nextSearchTerm = this.opts.nextSearchTerm(data, this.search.val());
            this.close();

            if ((!options || !options.noFocus) && this.opts.shouldFocusInput(this)) {
                this.focusser.focus();
            }

            if (!equal(old, this.id(data))) {
                this.triggerChange({ added: data, removed: oldData });
            }
        },

        // single
        updateSelection: function (data) {

            var container=this.selection.find(".select2-chosen"), formatted, cssClass;

            this.selection.data("select2-data", data);

            container.empty();
            if (data !== null) {
                formatted=this.opts.formatSelection(data, container, this.opts.escapeMarkup);
            }
            if (formatted !== undefined) {
                container.append(formatted);
            }
            cssClass=this.opts.formatSelectionCssClass(data, container);
            if (cssClass !== undefined) {
                container.addClass(cssClass);
            }

            this.selection.removeClass("select2-default");

            if (this.opts.allowClear && this.getPlaceholder() !== undefined) {
                this.container.addClass("select2-allowclear");
            }
        },

        // single
        val: function () {
            var val,
                triggerChange = false,
                data = null,
                self = this,
                oldData = this.data();

            if (arguments.length === 0) {
                return this.opts.element.val();
            }

            val = arguments[0];

            if (arguments.length > 1) {
                triggerChange = arguments[1];
            }

            if (this.select) {
                this.select
                    .val(val)
                    .find("option").filter(function() { return this.selected }).each2(function (i, elm) {
                        data = self.optionToData(elm);
                        return false;
                    });
                this.updateSelection(data);
                this.setPlaceholder();
                if (triggerChange) {
                    this.triggerChange({added: data, removed:oldData});
                }
            } else {
                // val is an id. !val is true for [undefined,null,'',0] - 0 is legal
                if (!val && val !== 0) {
                    this.clear(triggerChange);
                    return;
                }
                if (this.opts.initSelection === undefined) {
                    throw new Error("cannot call val() if initSelection() is not defined");
                }
                this.opts.element.val(val);
                this.opts.initSelection(this.opts.element, function(data){
                    self.opts.element.val(!data ? "" : self.id(data));
                    self.updateSelection(data);
                    self.setPlaceholder();
                    if (triggerChange) {
                        self.triggerChange({added: data, removed:oldData});
                    }
                });
            }
        },

        // single
        clearSearch: function () {
            this.search.val("");
            this.focusser.val("");
        },

        // single
        data: function(value) {
            var data,
                triggerChange = false;

            if (arguments.length === 0) {
                data = this.selection.data("select2-data");
                if (data == undefined) data = null;
                return data;
            } else {
                if (arguments.length > 1) {
                    triggerChange = arguments[1];
                }
                if (!value) {
                    this.clear(triggerChange);
                } else {
                    data = this.data();
                    this.opts.element.val(!value ? "" : this.id(value));
                    this.updateSelection(value);
                    if (triggerChange) {
                        this.triggerChange({added: value, removed:data});
                    }
                }
            }
        }
    });

    MultiSelect2 = clazz(AbstractSelect2, {

        // multi
        createContainer: function () {
            var container = $(document.createElement("div")).attr({
                "class": "select2-container select2-container-multi"
            }).html([
                "<ul class='select2-choices'>",
                "  <li class='select2-search-field'>",
                "    <label for='' class='select2-offscreen'></label>",
                "    <input type='text' autocomplete='off' autocorrect='off' autocapitalize='off' spellcheck='false' class='select2-input'>",
                "  </li>",
                "</ul>",
                "<div class='select2-drop select2-drop-multi select2-display-none'>",
                "   <ul class='select2-results'>",
                "   </ul>",
                "</div>"].join(""));
            return container;
        },

        // multi
        prepareOpts: function () {
            var opts = this.parent.prepareOpts.apply(this, arguments),
                self=this;

            // TODO validate placeholder is a string if specified

            if (opts.element.get(0).tagName.toLowerCase() === "select") {
                // install the selection initializer
                opts.initSelection = function (element, callback) {

                    var data = [];

                    element.find("option").filter(function() { return this.selected && !this.disabled }).each2(function (i, elm) {
                        data.push(self.optionToData(elm));
                    });
                    callback(data);
                };
            } else if ("data" in opts) {
                // install default initSelection when applied to hidden input and data is local
                opts.initSelection = opts.initSelection || function (element, callback) {
                    var ids = splitVal(element.val(), opts.separator);
                    //search in data by array of ids, storing matching items in a list
                    var matches = [];
                    opts.query({
                        matcher: function(term, text, el){
                            var is_match = $.grep(ids, function(id) {
                                return equal(id, opts.id(el));
                            }).length;
                            if (is_match) {
                                matches.push(el);
                            }
                            return is_match;
                        },
                        callback: !$.isFunction(callback) ? $.noop : function() {
                            // reorder matches based on the order they appear in the ids array because right now
                            // they are in the order in which they appear in data array
                            var ordered = [];
                            for (var i = 0; i < ids.length; i++) {
                                var id = ids[i];
                                for (var j = 0; j < matches.length; j++) {
                                    var match = matches[j];
                                    if (equal(id, opts.id(match))) {
                                        ordered.push(match);
                                        matches.splice(j, 1);
                                        break;
                                    }
                                }
                            }
                            callback(ordered);
                        }
                    });
                };
            }

            return opts;
        },

        // multi
        selectChoice: function (choice) {

            var selected = this.container.find(".select2-search-choice-focus");
            if (selected.length && choice && choice[0] == selected[0]) {

            } else {
                if (selected.length) {
                    this.opts.element.trigger("choice-deselected", selected);
                }
                selected.removeClass("select2-search-choice-focus");
                if (choice && choice.length) {
                    this.close();
                    choice.addClass("select2-search-choice-focus");
                    this.opts.element.trigger("choice-selected", choice);
                }
            }
        },

        // multi
        destroy: function() {
            $("label[for='" + this.search.attr('id') + "']")
                .attr('for', this.opts.element.attr("id"));
            this.parent.destroy.apply(this, arguments);

            cleanupJQueryElements.call(this,
                "searchContainer",
                "selection"
            );
        },

        // multi
        initContainer: function () {

            var selector = ".select2-choices", selection;

            this.searchContainer = this.container.find(".select2-search-field");
            this.selection = selection = this.container.find(selector);

            var _this = this;
            this.selection.on("click", ".select2-container:not(.select2-container-disabled) .select2-search-choice:not(.select2-locked)", function (e) {
                _this.search[0].focus();
                _this.selectChoice($(this));
            });

            // rewrite labels from original element to focusser
            this.search.attr("id", "s2id_autogen"+nextUid());

            this.search.prev()
                .text($("label[for='" + this.opts.element.attr("id") + "']").text())
                .attr('for', this.search.attr('id'));

            this.search.on("input paste", this.bind(function() {
                if (this.search.attr('placeholder') && this.search.val().length == 0) return;
                if (!this.isInterfaceEnabled()) return;
                if (!this.opened()) {
                    this.open();
                }
            }));

            this.search.attr("tabindex", this.elementTabIndex);

            this.keydowns = 0;
            this.search.on("keydown", this.bind(function (e) {
                if (!this.isInterfaceEnabled()) return;

                ++this.keydowns;
                var selected = selection.find(".select2-search-choice-focus");
                var prev = selected.prev(".select2-search-choice:not(.select2-locked)");
                var next = selected.next(".select2-search-choice:not(.select2-locked)");
                var pos = getCursorInfo(this.search);

                if (selected.length &&
                    (e.which == KEY.LEFT || e.which == KEY.RIGHT || e.which == KEY.BACKSPACE || e.which == KEY.DELETE || e.which == KEY.ENTER)) {
                    var selectedChoice = selected;
                    if (e.which == KEY.LEFT && prev.length) {
                        selectedChoice = prev;
                    }
                    else if (e.which == KEY.RIGHT) {
                        selectedChoice = next.length ? next : null;
                    }
                    else if (e.which === KEY.BACKSPACE) {
                        if (this.unselect(selected.first())) {
                            this.search.width(10);
                            selectedChoice = prev.length ? prev : next;
                        }
                    } else if (e.which == KEY.DELETE) {
                        if (this.unselect(selected.first())) {
                            this.search.width(10);
                            selectedChoice = next.length ? next : null;
                        }
                    } else if (e.which == KEY.ENTER) {
                        selectedChoice = null;
                    }

                    this.selectChoice(selectedChoice);
                    killEvent(e);
                    if (!selectedChoice || !selectedChoice.length) {
                        this.open();
                    }
                    return;
                } else if (((e.which === KEY.BACKSPACE && this.keydowns == 1)
                    || e.which == KEY.LEFT) && (pos.offset == 0 && !pos.length)) {

                    this.selectChoice(selection.find(".select2-search-choice:not(.select2-locked)").last());
                    killEvent(e);
                    return;
                } else {
                    this.selectChoice(null);
                }

                if (this.opened()) {
                    switch (e.which) {
                    case KEY.UP:
                    case KEY.DOWN:
                        this.moveHighlight((e.which === KEY.UP) ? -1 : 1);
                        killEvent(e);
                        return;
                    case KEY.ENTER:
                        this.selectHighlighted();
                        killEvent(e);
                        return;
                    case KEY.TAB:
                        this.selectHighlighted({noFocus:true});
                        this.close();
                        return;
                    case KEY.ESC:
                        this.cancel(e);
                        killEvent(e);
                        return;
                    }
                }

                if (e.which === KEY.TAB || KEY.isControl(e) || KEY.isFunctionKey(e)
                 || e.which === KEY.BACKSPACE || e.which === KEY.ESC) {
                    return;
                }

                if (e.which === KEY.ENTER) {
                    if (this.opts.openOnEnter === false) {
                        return;
                    } else if (e.altKey || e.ctrlKey || e.shiftKey || e.metaKey) {
                        return;
                    }
                }

                this.open();

                if (e.which === KEY.PAGE_UP || e.which === KEY.PAGE_DOWN) {
                    // prevent the page from scrolling
                    killEvent(e);
                }

                if (e.which === KEY.ENTER) {
                    // prevent form from being submitted
                    killEvent(e);
                }

            }));

            this.search.on("keyup", this.bind(function (e) {
                this.keydowns = 0;
                this.resizeSearch();
            })
            );

            this.search.on("blur", this.bind(function(e) {
                this.container.removeClass("select2-container-active");
                this.search.removeClass("select2-focused");
                this.selectChoice(null);
                if (!this.opened()) this.clearSearch();
                e.stopImmediatePropagation();
                this.opts.element.trigger($.Event("select2-blur"));
            }));

            this.container.on("click", selector, this.bind(function (e) {
                if (!this.isInterfaceEnabled()) return;
                if ($(e.target).closest(".select2-search-choice").length > 0) {
                    // clicked inside a select2 search choice, do not open
                    return;
                }
                this.selectChoice(null);
                this.clearPlaceholder();
                if (!this.container.hasClass("select2-container-active")) {
                    this.opts.element.trigger($.Event("select2-focus"));
                }
                this.open();
                this.focusSearch();
                e.preventDefault();
            }));

            this.container.on("focus", selector, this.bind(function () {
                if (!this.isInterfaceEnabled()) return;
                if (!this.container.hasClass("select2-container-active")) {
                    this.opts.element.trigger($.Event("select2-focus"));
                }
                this.container.addClass("select2-container-active");
                this.dropdown.addClass("select2-drop-active");
                this.clearPlaceholder();
            }));

            this.initContainerWidth();
            this.opts.element.addClass("select2-offscreen");

            // set the placeholder if necessary
            this.clearSearch();
        },

        // multi
        enableInterface: function() {
            if (this.parent.enableInterface.apply(this, arguments)) {
                this.search.prop("disabled", !this.isInterfaceEnabled());
            }
        },

        // multi
        initSelection: function () {
            var data;
            if (this.opts.element.val() === "" && this.opts.element.text() === "") {
                this.updateSelection([]);
                this.close();
                // set the placeholder if necessary
                this.clearSearch();
            }
            if (this.select || this.opts.element.val() !== "") {
                var self = this;
                this.opts.initSelection.call(null, this.opts.element, function(data){
                    if (data !== undefined && data !== null) {
                        self.updateSelection(data);
                        self.close();
                        // set the placeholder if necessary
                        self.clearSearch();
                    }
                });
            }
        },

        // multi
        clearSearch: function () {
            var placeholder = this.getPlaceholder(),
                maxWidth = this.getMaxSearchWidth();

            if (placeholder !== undefined  && this.getVal().length === 0 && this.search.hasClass("select2-focused") === false) {
                this.search.val(placeholder).addClass("select2-default");
                // stretch the search box to full width of the container so as much of the placeholder is visible as possible
                // we could call this.resizeSearch(), but we do not because that requires a sizer and we do not want to create one so early because of a firefox bug, see #944
                this.search.width(maxWidth > 0 ? maxWidth : this.container.css("width"));
            } else {
                this.search.val("").width(10);
            }
        },

        // multi
        clearPlaceholder: function () {
            if (this.search.hasClass("select2-default")) {
                this.search.val("").removeClass("select2-default");
            }
        },

        // multi
        opening: function () {
            this.clearPlaceholder(); // should be done before super so placeholder is not used to search
            this.resizeSearch();

            this.parent.opening.apply(this, arguments);

            this.focusSearch();

            // initializes search's value with nextSearchTerm (if defined by user)
            // ignore nextSearchTerm if the dropdown is opened by the user pressing a letter
            if(this.search.val() === "") {
                if(this.nextSearchTerm != undefined){
                    this.search.val(this.nextSearchTerm);
                    this.search.select();
                }
            }

            this.updateResults(true);
            if (this.opts.shouldFocusInput(this)) {
                this.search.focus();
            }
            this.opts.element.trigger($.Event("select2-open"));
        },

        // multi
        close: function () {
            if (!this.opened()) return;
            this.parent.close.apply(this, arguments);
        },

        // multi
        focus: function () {
            this.close();
            this.search.focus();
        },

        // multi
        isFocused: function () {
            return this.search.hasClass("select2-focused");
        },

        // multi
        updateSelection: function (data) {
            var ids = [], filtered = [], self = this;

            // filter out duplicates
            $(data).each(function () {
                if (indexOf(self.id(this), ids) < 0) {
                    ids.push(self.id(this));
                    filtered.push(this);
                }
            });
            data = filtered;

            this.selection.find(".select2-search-choice").remove();
            $(data).each(function () {
                self.addSelectedChoice(this);
            });
            self.postprocessResults();
        },

        // multi
        tokenize: function() {
            var input = this.search.val();
            input = this.opts.tokenizer.call(this, input, this.data(), this.bind(this.onSelect), this.opts);
            if (input != null && input != undefined) {
                this.search.val(input);
                if (input.length > 0) {
                    this.open();
                }
            }

        },

        // multi
        onSelect: function (data, options) {

            if (!this.triggerSelect(data) || data.text === "") { return; }

            this.addSelectedChoice(data);

            this.opts.element.trigger({ type: "selected", val: this.id(data), choice: data });

            // keep track of the search's value before it gets cleared
            this.nextSearchTerm = this.opts.nextSearchTerm(data, this.search.val());

            this.clearSearch();
            this.updateResults();

            if (this.select || !this.opts.closeOnSelect) this.postprocessResults(data, false, this.opts.closeOnSelect===true);

            if (this.opts.closeOnSelect) {
                this.close();
                this.search.width(10);
            } else {
                if (this.countSelectableResults()>0) {
                    this.search.width(10);
                    this.resizeSearch();
                    if (this.getMaximumSelectionSize() > 0 && this.val().length >= this.getMaximumSelectionSize()) {
                        // if we reached max selection size repaint the results so choices
                        // are replaced with the max selection reached message
                        this.updateResults(true);
                    } else {
                        // initializes search's value with nextSearchTerm and update search result
                        if(this.nextSearchTerm != undefined){
                            this.search.val(this.nextSearchTerm);
                            this.updateResults();
                            this.search.select();
                        }
                    }
                    this.positionDropdown();
                } else {
                    // if nothing left to select close
                    this.close();
                    this.search.width(10);
                }
            }

            // since its not possible to select an element that has already been
            // added we do not need to check if this is a new element before firing change
            this.triggerChange({ added: data });

            if (!options || !options.noFocus)
                this.focusSearch();
        },

        // multi
        cancel: function () {
            this.close();
            this.focusSearch();
        },

        addSelectedChoice: function (data) {
            var enableChoice = !data.locked,
                enabledItem = $(
                    "<li class='select2-search-choice'>" +
                    "    <div></div>" +
                    "    <a href='#' class='select2-search-choice-close' tabindex='-1'></a>" +
                    "</li>"),
                disabledItem = $(
                    "<li class='select2-search-choice select2-locked'>" +
                    "<div></div>" +
                    "</li>");
            var choice = enableChoice ? enabledItem : disabledItem,
                id = this.id(data),
                val = this.getVal(),
                formatted,
                cssClass;

            formatted=this.opts.formatSelection(data, choice.find("div"), this.opts.escapeMarkup);
            if (formatted != undefined) {
                choice.find("div").replaceWith("<div>"+formatted+"</div>");
            }
            cssClass=this.opts.formatSelectionCssClass(data, choice.find("div"));
            if (cssClass != undefined) {
                choice.addClass(cssClass);
            }

            if(enableChoice){
              choice.find(".select2-search-choice-close")
                  .on("mousedown", killEvent)
                  .on("click dblclick", this.bind(function (e) {
                  if (!this.isInterfaceEnabled()) return;

                  this.unselect($(e.target));
                  this.selection.find(".select2-search-choice-focus").removeClass("select2-search-choice-focus");
                  killEvent(e);
                  this.close();
                  this.focusSearch();
              })).on("focus", this.bind(function () {
                  if (!this.isInterfaceEnabled()) return;
                  this.container.addClass("select2-container-active");
                  this.dropdown.addClass("select2-drop-active");
              }));
            }

            choice.data("select2-data", data);
            choice.insertBefore(this.searchContainer);

            val.push(id);
            this.setVal(val);
        },

        // multi
        unselect: function (selected) {
            var val = this.getVal(),
                data,
                index;
            selected = selected.closest(".select2-search-choice");

            if (selected.length === 0) {
                throw "Invalid argument: " + selected + ". Must be .select2-search-choice";
            }

            data = selected.data("select2-data");

            if (!data) {
                // prevent a race condition when the 'x' is clicked really fast repeatedly the event can be queued
                // and invoked on an element already removed
                return;
            }

            var evt = $.Event("select2-removing");
            evt.val = this.id(data);
            evt.choice = data;
            this.opts.element.trigger(evt);

            if (evt.isDefaultPrevented()) {
                return false;
            }

            while((index = indexOf(this.id(data), val)) >= 0) {
                val.splice(index, 1);
                this.setVal(val);
                if (this.select) this.postprocessResults();
            }

            selected.remove();

            this.opts.element.trigger({ type: "select2-removed", val: this.id(data), choice: data });
            this.triggerChange({ removed: data });

            return true;
        },

        // multi
        postprocessResults: function (data, initial, noHighlightUpdate) {
            var val = this.getVal(),
                choices = this.results.find(".select2-result"),
                compound = this.results.find(".select2-result-with-children"),
                self = this;

            choices.each2(function (i, choice) {
                var id = self.id(choice.data("select2-data"));
                if (indexOf(id, val) >= 0) {
                    choice.addClass("select2-selected");
                    // mark all children of the selected parent as selected
                    choice.find(".select2-result-selectable").addClass("select2-selected");
                }
            });

            compound.each2(function(i, choice) {
                // hide an optgroup if it doesn't have any selectable children
                if (!choice.is('.select2-result-selectable')
                    && choice.find(".select2-result-selectable:not(.select2-selected)").length === 0) {
                    choice.addClass("select2-selected");
                }
            });

            if (this.highlight() == -1 && noHighlightUpdate !== false){
                self.highlight(0);
            }

            //If all results are chosen render formatNoMatches
            if(!this.opts.createSearchChoice && !choices.filter('.select2-result:not(.select2-selected)').length > 0){
                if(!data || data && !data.more && this.results.find(".select2-no-results").length === 0) {
                    if (checkFormatter(self.opts.formatNoMatches, "formatNoMatches")) {
                        this.results.append("<li class='select2-no-results'>" + evaluate(self.opts.formatNoMatches, self.opts.element, self.search.val()) + "</li>");
                    }
                }
            }

        },

        // multi
        getMaxSearchWidth: function() {
            return this.selection.width() - getSideBorderPadding(this.search);
        },

        // multi
        resizeSearch: function () {
            var minimumWidth, left, maxWidth, containerLeft, searchWidth,
                sideBorderPadding = getSideBorderPadding(this.search);

            minimumWidth = measureTextWidth(this.search) + 10;

            left = this.search.offset().left;

            maxWidth = this.selection.width();
            containerLeft = this.selection.offset().left;

            searchWidth = maxWidth - (left - containerLeft) - sideBorderPadding;

            if (searchWidth < minimumWidth) {
                searchWidth = maxWidth - sideBorderPadding;
            }

            if (searchWidth < 40) {
                searchWidth = maxWidth - sideBorderPadding;
            }

            if (searchWidth <= 0) {
              searchWidth = minimumWidth;
            }

            this.search.width(Math.floor(searchWidth));
        },

        // multi
        getVal: function () {
            var val;
            if (this.select) {
                val = this.select.val();
                return val === null ? [] : val;
            } else {
                val = this.opts.element.val();
                return splitVal(val, this.opts.separator);
            }
        },

        // multi
        setVal: function (val) {
            var unique;
            if (this.select) {
                this.select.val(val);
            } else {
                unique = [];
                // filter out duplicates
                $(val).each(function () {
                    if (indexOf(this, unique) < 0) unique.push(this);
                });
                this.opts.element.val(unique.length === 0 ? "" : unique.join(this.opts.separator));
            }
        },

        // multi
        buildChangeDetails: function (old, current) {
            var current = current.slice(0),
                old = old.slice(0);

            // remove intersection from each array
            for (var i = 0; i < current.length; i++) {
                for (var j = 0; j < old.length; j++) {
                    if (equal(this.opts.id(current[i]), this.opts.id(old[j]))) {
                        current.splice(i, 1);
                        if(i>0){
                            i--;
                        }
                        old.splice(j, 1);
                        j--;
                    }
                }
            }

            return {added: current, removed: old};
        },


        // multi
        val: function (val, triggerChange) {
            var oldData, self=this;

            if (arguments.length === 0) {
                return this.getVal();
            }

            oldData=this.data();
            if (!oldData.length) oldData=[];

            // val is an id. !val is true for [undefined,null,'',0] - 0 is legal
            if (!val && val !== 0) {
                this.opts.element.val("");
                this.updateSelection([]);
                this.clearSearch();
                if (triggerChange) {
                    this.triggerChange({added: this.data(), removed: oldData});
                }
                return;
            }

            // val is a list of ids
            this.setVal(val);

            if (this.select) {
                this.opts.initSelection(this.select, this.bind(this.updateSelection));
                if (triggerChange) {
                    this.triggerChange(this.buildChangeDetails(oldData, this.data()));
                }
            } else {
                if (this.opts.initSelection === undefined) {
                    throw new Error("val() cannot be called if initSelection() is not defined");
                }

                this.opts.initSelection(this.opts.element, function(data){
                    var ids=$.map(data, self.id);
                    self.setVal(ids);
                    self.updateSelection(data);
                    self.clearSearch();
                    if (triggerChange) {
                        self.triggerChange(self.buildChangeDetails(oldData, self.data()));
                    }
                });
            }
            this.clearSearch();
        },

        // multi
        onSortStart: function() {
            if (this.select) {
                throw new Error("Sorting of elements is not supported when attached to <select>. Attach to <input type='hidden'/> instead.");
            }

            // collapse search field into 0 width so its container can be collapsed as well
            this.search.width(0);
            // hide the container
            this.searchContainer.hide();
        },

        // multi
        onSortEnd:function() {

            var val=[], self=this;

            // show search and move it to the end of the list
            this.searchContainer.show();
            // make sure the search container is the last item in the list
            this.searchContainer.appendTo(this.searchContainer.parent());
            // since we collapsed the width in dragStarted, we resize it here
            this.resizeSearch();

            // update selection
            this.selection.find(".select2-search-choice").each(function() {
                val.push(self.opts.id($(this).data("select2-data")));
            });
            this.setVal(val);
            this.triggerChange();
        },

        // multi
        data: function(values, triggerChange) {
            var self=this, ids, old;
            if (arguments.length === 0) {
                 return this.selection
                     .children(".select2-search-choice")
                     .map(function() { return $(this).data("select2-data"); })
                     .get();
            } else {
                old = this.data();
                if (!values) { values = []; }
                ids = $.map(values, function(e) { return self.opts.id(e); });
                this.setVal(ids);
                this.updateSelection(values);
                this.clearSearch();
                if (triggerChange) {
                    this.triggerChange(this.buildChangeDetails(old, this.data()));
                }
            }
        }
    });

    $.fn.select2 = function () {

        var args = Array.prototype.slice.call(arguments, 0),
            opts,
            select2,
            method, value, multiple,
            allowedMethods = ["val", "destroy", "opened", "open", "close", "focus", "isFocused", "container", "dropdown", "onSortStart", "onSortEnd", "enable", "disable", "readonly", "positionDropdown", "data", "search"],
            valueMethods = ["opened", "isFocused", "container", "dropdown"],
            propertyMethods = ["val", "data"],
            methodsMap = { search: "externalSearch" };

        this.each(function () {
            if (args.length === 0 || typeof(args[0]) === "object") {
                opts = args.length === 0 ? {} : $.extend({}, args[0]);
                opts.element = $(this);

                if (opts.element.get(0).tagName.toLowerCase() === "select") {
                    multiple = opts.element.prop("multiple");
                } else {
                    multiple = opts.multiple || false;
                    if ("tags" in opts) {opts.multiple = multiple = true;}
                }

                select2 = multiple ? new window.Select2["class"].multi() : new window.Select2["class"].single();
                select2.init(opts);
            } else if (typeof(args[0]) === "string") {

                if (indexOf(args[0], allowedMethods) < 0) {
                    throw "Unknown method: " + args[0];
                }

                value = undefined;
                select2 = $(this).data("select2");
                if (select2 === undefined) return;

                method=args[0];

                if (method === "container") {
                    value = select2.container;
                } else if (method === "dropdown") {
                    value = select2.dropdown;
                } else {
                    if (methodsMap[method]) method = methodsMap[method];

                    value = select2[method].apply(select2, args.slice(1));
                }
                if (indexOf(args[0], valueMethods) >= 0
                    || (indexOf(args[0], propertyMethods) >= 0 && args.length == 1)) {
                    return false; // abort the iteration, ready to return first matched value
                }
            } else {
                throw "Invalid arguments to select2 plugin: " + args;
            }
        });
        return (value === undefined) ? this : value;
    };

    // plugin defaults, accessible to users
    $.fn.select2.defaults = {
        width: "copy",
        loadMorePadding: 0,
        closeOnSelect: true,
        openOnEnter: true,
        containerCss: {},
        dropdownCss: {},
        containerCssClass: "",
        dropdownCssClass: "",
        formatResult: function(result, container, query, escapeMarkup) {
            var markup=[];
            markMatch(this.text(result), query.term, markup, escapeMarkup);
            return markup.join("");
        },
        formatSelection: function (data, container, escapeMarkup) {
            return data ? escapeMarkup(this.text(data)) : undefined;
        },
        sortResults: function (results, container, query) {
            return results;
        },
        formatResultCssClass: function(data) {return data.css;},
        formatSelectionCssClass: function(data, container) {return undefined;},
        minimumResultsForSearch: 0,
        minimumInputLength: 0,
        maximumInputLength: null,
        maximumSelectionSize: 0,
        id: function (e) { return e == undefined ? null : e.id; },
        text: function (e) {
          if (e && this.data && this.data.text) {
            if ($.isFunction(this.data.text)) {
              return this.data.text(e);
            } else {
              return e[this.data.text];
            }
          } else {
            return e.text;
          }
        },
        matcher: function(term, text) {
            return stripDiacritics(''+text).toUpperCase().indexOf(stripDiacritics(''+term).toUpperCase()) >= 0;
        },
        separator: ",",
        tokenSeparators: [],
        tokenizer: defaultTokenizer,
        escapeMarkup: defaultEscapeMarkup,
        blurOnChange: false,
        selectOnBlur: false,
        adaptContainerCssClass: function(c) { return c; },
        adaptDropdownCssClass: function(c) { return null; },
        nextSearchTerm: function(selectedObject, currentSearchTerm) { return undefined; },
        searchInputPlaceholder: '',
        createSearchChoicePosition: 'top',
        shouldFocusInput: function (instance) {
            // Attempt to detect touch devices
            var supportsTouchEvents = (('ontouchstart' in window) ||
                                       (navigator.msMaxTouchPoints > 0));

            // Only devices which support touch events should be special cased
            if (!supportsTouchEvents) {
                return true;
            }

            // Never focus the input if search is disabled
            if (instance.opts.minimumResultsForSearch < 0) {
                return false;
            }

            return true;
        }
    };

    $.fn.select2.locales = [];

    $.fn.select2.locales['en'] = {
         formatMatches: function (matches) { if (matches === 1) { return "One result is available, press enter to select it."; } return matches + " results are available, use up and down arrow keys to navigate."; },
         formatNoMatches: function () { return "No matches found"; },
         formatAjaxError: function (jqXHR, textStatus, errorThrown) { return "Loading failed"; },
         formatInputTooShort: function (input, min) { var n = min - input.length; return "Please enter " + n + " or more character" + (n == 1 ? "" : "s"); },
         formatInputTooLong: function (input, max) { var n = input.length - max; return "Please delete " + n + " character" + (n == 1 ? "" : "s"); },
         formatSelectionTooBig: function (limit) { return "You can only select " + limit + " item" + (limit == 1 ? "" : "s"); },
         formatLoadMore: function (pageNumber) { return "Loading more results…"; },
         formatSearching: function () { return "Searching…"; }
    };

    $.extend($.fn.select2.defaults, $.fn.select2.locales['en']);

    $.fn.select2.ajaxDefaults = {
        transport: $.ajax,
        params: {
            type: "GET",
            cache: false,
            dataType: "json"
        }
    };

    // exports
    window.Select2 = {
        query: {
            ajax: ajax,
            local: local,
            tags: tags
        }, util: {
            debounce: debounce,
            markMatch: markMatch,
            escapeMarkup: defaultEscapeMarkup,
            stripDiacritics: stripDiacritics
        }, "class": {
            "abstract": AbstractSelect2,
            "single": SingleSelect2,
            "multi": MultiSelect2
        }
    };

}(jQuery));

/*global LivepressConfig, lp_strings, Livepress, Dashboard, Collaboration, tinymce, tinyMCE, console, confirm, switchEditors, livepress_merge_confirmation, LivepressConfig, _wpmejsSettings, FB  */

/**
 * Global object into which we add our other functionality.
 *
 * @namespace
 * @type {Object}
 */
var OORTLE = OORTLE || {};
/**
 * Container for Livepress administration functionality.
 *
 * @memberOf Livepress
 * @namespace
 * @type {Object}
 */
Livepress.Admin = Livepress.Admin || {};

/**
 * Object that checks the live update status of a given post asynchronously.
 *
 * @memberOf Livepress.Admin
 * @namespace
 * @constructor
 * @requires LivepressConfig
 */
Livepress.Admin.PostStatus = function () {
	var CHECK_WAIT_TIME = 4, // Seconds
		MESSAGE_DISPLAY_TIME = 10, // Seconds
		SELF = this,
		spin = false;

	/**
	 * Add a spinner to the post form.
	 *
	 * @private
	 */
	function add_spin () {
		if (spin) {
			return;
		}
		spin = true;
		var $spinner = jQuery("<div class='lp-spinner'></div>")
			.attr('id', "lp_spin_img");
		jQuery("form#post").before($spinner);
	}

	/**
	 * Add a notice to alert the user of some change.
	 *
	 * This implementation is based in the edit-form-advanced.php html structure
	 * @param {String} msg Message to display to the user.
	 * @param {String} kind Type of message. Used as a CSS class for styling.
	 * @private
	 */
	function message (msg, kind) {
		kind = kind || "lp-notice";
		var $el = jQuery('<p/>').text(msg),
			$container = jQuery('<div class="updated ' + kind + '"></div>');
		$container.append($el);
		jQuery("#post").before($container);
		setTimeout(function () {
			$container.fadeOut(2000, function () {
				jQuery(this).remove();
			});
		}, MESSAGE_DISPLAY_TIME * 1000);
	}

	/**
	 * Remove the spinner added by add_spin().
	 *
	 * @private
	 */
	function remove_spin () {
		spin = false;
		jQuery('#lp_spin_img').remove();
	}

	/**
	 * Add an error message for the user.
	 *
	 * @param {String} msg Error message text.
	 * @private
	 */
	function error (msg) {
		message(msg, "lp-error");
	}
    SELF.error = error;

	/**
	 * Add a warning message for the user.
	 *
	 * @param {String} msg Warning message text.
	 * @private
	 */
	function warning (msg) {
		message(msg, "lp-warning");
	}

	/**
	 * Set the status of the post live update attempt.
	 *
	 * @param {String} status Status code.
	 * @private
	 */
	function set_status (status) {
		if (status === "completed") {
			message("Update was published live to users.");
			remove_spin();
		} else if (status === "failed") {
			warning("Update was published NOT live.");
			remove_spin();
		} else if (status === "lp_failed") {
			error("Can't get update status from LivePress.");
			remove_spin();
		} else if (status === "empty" || "0" === status) {
			remove_spin();
		} else if (status === "-1") {
			error("Wrong AJAX nonce.");
			remove_spin();
		} else {
			setTimeout(SELF.check, CHECK_WAIT_TIME * 1000);
		}
	}

	/**
	 * Check the current post's update status.
	 *
	 * @requires LivepressConfig#ajax_nonce
	 * @requires LivepressConfig#post_id
	 */
	SELF.check = function () {
		var nonces = jQuery('#blogging-tool-nonces');
		add_spin();
		jQuery.ajax({
			url:     LivepressConfig.ajax_url,
			data:    {
				livepress_action: true,
				_ajax_nonce:      LivepressConfig.ajax_status_nonce,
				post_id:          LivepressConfig.post_id,
				action:           'lp_status'
			},
			success: set_status,
			error:   function () {
				error("Can't get update status from blog server.");
				remove_spin();
			}
		});
	};

	jQuery( window ).on( 'livepress.post_update', function () {
		add_spin();
		setTimeout(SELF.check, CHECK_WAIT_TIME * 1000);
	} );
};

/**
 * Object containing the logic to post updates to Twitter.
 *
 * @memberOf Livepress.Admin
 * @namespace
 * @constructor
 */
Livepress.Admin.PostToTwitter = function () {
	var sending_post_to_twitter = false,
		msg_span_id = 'post_to_twitter_message',
		check_timeout,
		oauth_popup,
		TIME_BETWEEN_CHECK_OAUTH_REQUESTS = 5;

	/**
	 * Show an OAuth popup if necessary.
	 *
	 * @param {String} url OAuth popup url.
	 * @private
	 */
	function show_oauth_popup( url ) {
		try {
			oauth_popup.close();
		} catch (exc) {
		}
		oauth_popup = window.open( url, '_blank', 'height=600,width=600' );
		jQuery("#post_to_twitter_url")
			.html('<br /><a href="' + url + '">' + url + '</a>')
			.click(function (e) {
				try {
					oauth_popup.close();
				} catch (exc) {
				}
				oauth_popup = undefined;
				oauth_popup = window.open( url, '_blank', 'height=600,width=600' );
				return false;
			})
			.show();
	}

	/**
	 * Check the current status of Twitter OAuth authorization.
	 *
	 * Will poll the Twitter API asynchronously and display an error if needed.
	 *
	 * @param {Number} attempts Number of times to check authorization status.
	 * @private
	 */
	function check_oauth_authorization_status(attempts) {
		var $msg_span = jQuery('#' + msg_span_id),
			times = attempts || 1,
			params = {
						action:      'lp_check_oauth_authorization_status',
						_ajax_nonce: LivepressConfig.ajax_check_oauth
					};

		$msg_span.html( lp_strings.checking_auth + '... (' + times + ')');

		jQuery.ajax({
			url:   "./admin-ajax.php",
			data:  params,
			type:  'post',
			async: true,

			error:   function (XMLHttpRequest) {
				$msg_span.html( lp_strings.failed_to_check );
			},
			success: function (data) {
				if (data.status === 'authorized') {
					$msg_span.html(
						lp_strings.sending_twitter + ': <strong>' + data.username + '</strong>.');
					jQuery("#lp-post-to-twitter-change_link").show();
					jQuery("#lp-post-to-twitter-change").hide();
				} else if (data.status === 'unauthorized') {
					handle_twitter_deauthorization();
				} else if (data.status === 'not_available') {
					var closed = false;
					if (oauth_popup !== undefined) {
						try {
							closed = oauth_popup.closed;
						} catch (e) {
							if (e.name.toString() === "ReferenceError") {
								closed = true;
							} else {
								throw e;
							}
						}
					}
					if (oauth_popup !== undefined && closed) {
						handle_twitter_deauthorization();
					} else {
						$msg_span.html( lp_strings.status_not_avail );
						check_timeout = setTimeout(function () {
							check_oauth_authorization_status(++times);
						}, TIME_BETWEEN_CHECK_OAUTH_REQUESTS * 1000);
					}
				} else {
					$msg_span.html( lp_strings.internal_error );
				}
			}
		});
	}

	/**
	 * Update post on Twitter using current OAuth credentials.
	 *
	 * @param {Boolean} change_oauth_user Change the user making the request.
	 * @param {Boolean} disable Disable OAuth popup.
	 * @returns {Boolean} True when sending, False if already in the process of sending.
	 * @private
	 */
	function update_post_to_twitter(change_oauth_user, disable) {
		var $msg_span,
			params;

		if (sending_post_to_twitter) {
			return false;
		}
		sending_post_to_twitter = true;

		$msg_span = jQuery('#' + msg_span_id);

		params = {};
		params.action = 'lp_post_to_twitter';
		params._ajax_nonce = LivepressConfig.ajax_lp_post_to_twitter;
		params.enable = document.getElementById('lp-remote').checked;
		if (change_oauth_user) {
			params.change_oauth_user = true;
		}

		jQuery("#post_to_twitter_message_change_link").hide();
		jQuery("#post_to_twitter_url").hide();

		jQuery.ajax({
			url:   "./admin-ajax.php",
			data:  params,
			type:  'post',
			async: true,

			error:    function (XMLHttpRequest) {
				if (XMLHttpRequest.status === 409) {
					$msg_span.html('Already ' + ((params.enable) ? 'enabled.' : 'disabled.'));
				} else if (XMLHttpRequest.status === 502) {
					if (XMLHttpRequest.responseText === "404") {
						$msg_span.html( lp_strings.noconnect_twitter );
					} else {
						$msg_span.html(lp_strings.noconnect_livepress );
					}
				} else {
					$msg_span.html(
						lp_strings.failed_unknown + ' (' + lp_strings.return_code + ': ' + XMLHttpRequest.status + ')'
					);
				}
				clearTimeout(check_timeout);
			},
			success:  function (data) {
				if (params.enable) {

					$msg_span.html(
						lp_strings.new_twitter_window
					);
					show_oauth_popup(data);
					check_timeout = setTimeout(check_oauth_authorization_status,
						TIME_BETWEEN_CHECK_OAUTH_REQUESTS * 1000);
				} else {
					clearTimeout(check_timeout);
					if (disable !== true) {
						$msg_span.html('');
					}
				}
			},
			complete: function () {
				sending_post_to_twitter = false;
			}
		});

		return true;
	}

	/**
	 * If Twitter access is unauthorized, show the user an approripate message.
	 *
	 * @private
	 */
	function handle_twitter_deauthorization() {
		var $msg_span = jQuery( '#' + msg_span_id );
		$msg_span.html( lp_strings.twitter_unauthorized );
		update_post_to_twitter( false, true );
	}

	/**
	 * Universal callback function used inside click events.
	 *
	 * @param {Event} e Event passed by the click event.
	 * @param {Boolean} change_oauth_user Flag to change the current OAuth user.
	 * @private
	 */
	function callback( e, change_oauth_user ) {
		e.preventDefault();
		e.stopPropagation();
		update_post_to_twitter( change_oauth_user );
	}

	jQuery( "#post_to_twitter" ).on( 'click', function ( e ) {
		callback( e, false );
	} );

	jQuery( "#lp-post-to-twitter-change, #lp-post-to-twitter-change_link" ).on( 'click', function ( e ) {
		callback( e, true );
	} );

	jQuery( "#lp-post-to-twitter-not-authorized" ).on( 'click', function ( e ) {
		jQuery( '#' + msg_span_id ).html(
			lp_strings.new_twitter_window
		);
		show_oauth_popup( this.href );
		check_timeout = window.setTimeout( check_oauth_authorization_status, TIME_BETWEEN_CHECK_OAUTH_REQUESTS * 1000 );
		return false;
	} );
};

/**
 * Object containing the logic responsible for the Live Blogging Tools palette.
 *
 * @memberOf Livepress.Admin
 * @namespace
 * @constructor
 * @requires OORTLE.Livepress.LivepressHUD.init
 * @requires Collaboration.Edit.initialize
 */
Livepress.Admin.Tools = function () {
	var tools_link_wrap = document.createElement( 'div' );
	tools_link_wrap.className = 'hide-if-no-js screen-meta-toggle';
	tools_link_wrap.setAttribute( 'id', 'blogging-tools-link-wrap' );

	var tools_link = document.createElement( 'a' );
	tools_link.className = 'show-settings';
	tools_link.setAttribute( 'id', 'blogging-tools-link' );
	tools_link.setAttribute( 'href', '#blogging-tools-wrap' );

	var tools_wrap = document.createElement( 'div' );
	tools_wrap.className = 'hidden';
	tools_wrap.setAttribute( 'id', 'blogging-tools-wrap' );

	var $tools_link_wrap = jQuery( tools_link_wrap ),
		$tools_wrap = jQuery( tools_wrap );

	/**
	 * Asynchronously load the markup for the Live Blogging Tools palette from the server and inject it into the page.
	 *
	 * @private
	 */
	function getTabs () {
		jQuery.ajax({
			url:     LivepressConfig.ajax_url,
			data:    {
				action:  'lp_get_blogging_tools',
				_ajax_nonce: LivepressConfig.ajax_render_tabs,
				post_id: LivepressConfig.post_id
			},
			type:    'post',
			success: function (data) {
				tools_wrap.innerHTML = data;

				jQuery('.blogging-tools-tabs').on('click', 'a', function (e) {
					var link = jQuery(this), panel;

					e.preventDefault();

					if (link.is('.active a')) {
						return false;
					}

					jQuery('.blogging-tools-tabs .active').removeClass('active');
					link.parent('li').addClass('active');

					panel = jQuery(link.attr('href'));

					jQuery('.blogging-tools-content').not(panel).removeClass('active').hide();
					panel.addClass('active').show();

					return false;
				});

				wireupDefaults();
			}
		});
	}

  // If we found the Facebook plugin on PHP, load the Facebook js for
  // admin panel post embedding:
  function checkFacebook(){
    if(LivepressConfig.facebook === 'yes'){
      if ( typeof(FB) !== 'undefined'){
        FB.XFBML.parse();
      } else {
        var fb_script = "//connect.facebook.net/" + LivepressConfig.locale + "/all.js";
        jQuery.getScript(fb_script, function(){
          window.FB.init();
          FB.XFBML.parse();
        });
      }
    }
  }

	/**
	 * Configure event handlers for default Live Blogging Tools tabs.
	 *
	 * @private
	 */
	function wireupDefaults () {
		var nonces = jQuery('#blogging-tool-nonces'),
			SELF   = this,
			promises;

		// Save and refresh when clicking the first update sticky toggle
		jQuery( '#pinfirst' ).on( 'click', function() {
			jQuery( 'form#post' ).submit();
		});

		jQuery('#tab-panel-live-notes').on('click', 'input[type="submit"]', function (e) {
			var submit = jQuery( this ),
				status = jQuery( '.live-notes-status' );
			e.preventDefault();

			jQuery.ajax({
				url:     LivepressConfig.ajax_url,
				data:    {
					action:     'lp_update-live-notes',
					post_id:    LivepressConfig.post_id,
					content:    jQuery('#live-notes-text').val(),
					ajax_nonce: nonces.data('live-notes')
				},
				type:    'post',
				success: function (data) {
					status.show();

					setTimeout( function() {
						status.addClass( 'hide-fade' );

						setTimeout( function() {
							status.hide().removeClass( 'hide-fade' );
						}, 3000 );
					}, 2000 );
				}
			});
		});
		jQuery('#publish').on('click', function(e){
			jQuery('#lp-pub-status-bar a').hide();
		});
		jQuery('#lp-pub-status-bar').on('click', 'a.toggle-live', function (e) {
			e.preventDefault();
			// stop working if the main publishing button in play
			if( 0 === jQuery( '#publish.disabled').length ){
				// If already live, warn user
				if ( jQuery( '#livepress_status_meta_box' ).hasClass( 'live' ) ) {
					if ( confirm( lp_strings.confirm_switch ) ){
						Dashboard.Twitter.removeAllTerms();
						Dashboard.Twitter.removeAllTweets();
						promises = toggleLiveStatus();
						jQuery.when( promises ).done( function() {
							// When toggle complete, redirect to complete the merge
							jQuery( 'form#post' )
								.attr( 'action', jQuery( 'form#post' ).attr( 'action' ) +
								'?post=' + LivepressConfig.post_id + '&action=edit' +
								'&merge_action=merge_children&merge_noonce=' + nonces.data( 'merge-post' ) )
								.submit();
						});
					}
				} else {
					// Transitioning to live
					promises = toggleLiveStatus();
					jQuery.when( promises ).done( function() {
						setTimeout( function(){
							jQuery( '#publish' ).click();
						}, 50);
					});
				}
			}


	});

	/**
	 * Toggle live status with server
	 */
	function toggleLiveStatus() {
		var promise1,
			promise2;

		promise1 = jQuery.ajax({
			url:     LivepressConfig.ajax_url,
			data:    {
				action:     'lp_update-live-status',
				post_id:    LivepressConfig.post_id,
				ajax_nonce: nonces.data('live-status')
			},
			type:    'post'
		});

		promise2 = jQuery.ajax({
			url:      LivepressConfig.ajax_url,
			data:     {
				action:  'lp_update-live-comments',
				post_id: LivepressConfig.post_id,
				_ajax_nonce: LivepressConfig.ajax_update_live_comments
			},
			type:     'post',
			dataType: 'json'
		});

		// Return a promise combining callbacks
		return ( jQuery.when( promise1, promise2 ) );
	}
}
	/**
	 * Render the loaded default tabs into the UI.
	 */
	this.render = function () {
		var element = document.getElementById( 'blogging-tools-link-wrap' );
		if ( null !== element ) {
			element.parentNode.removeChild( element );
		}

		element = document.getElementById( 'blogging-tools-link' );
		if ( null !== element ) {
			element.parentNode.removeChild( element );
		}

		element = document.getElementById( 'blogging-tools-wrap' );
		if ( null !== element ) {
			element.parentNode.removeChild( element );
		}

		//tools_link.innerText = 'Live Blogging Tools';
		$tools_link_wrap.append( tools_link );
		jQuery( tools_link ).html( '<span class="icon-livepress-logo"></span> ' + lp_strings.tools_link_text );
		$tools_link_wrap.insertAfter('#screen-options-link-wrap');

		$tools_wrap.insertAfter('#screen-options-wrap');

		getTabs();

		checkFacebook();

		jQuery( window ).trigger( 'livepress.blogging-tools-loaded' );
	};
};

function update_embeds(){
  var settings = {};
  if ( typeof _wpmejsSettings !== 'undefined' ) {
    settings.pluginPath = _wpmejsSettings.pluginPath;
  }
  settings.success = function (mejs) {
    var autoplay = mejs.attributes.autoplay && 'false' !== mejs.attributes.autoplay;
    if ( 'flash' === mejs.pluginType && autoplay ) {
      mejs.addEventListener( 'canplay', function () {
        mejs.play();
      }, false );
    }
  };
  jQuery( ".wp-audio-shortcode, .wp-video-shortcode" ).not('.mejs-container').mediaelementplayer( settings );

  if ( typeof(FB) !== 'undefined'){
    FB.XFBML.parse();
  }
	jQuery('abbr.livepress-timestamp').timeago().attr( 'title', '' );
}


jQuery(function () {
	var pstatus = new Livepress.Admin.PostStatus(),
		post_to_twitter = new Livepress.Admin.PostToTwitter();

	pstatus.check();

	/**
	 * Title: LivePress Plugin Documentation
	 * Details the current LivePress plugin architecture (mostly internal objects).
	 * This plugin works by splitting a blog entry into micro posts (identified by special div tags),
	 * receiving new micro posts from other authors (ajax) while allowing the logged-in
	 * user to post micro post updates (ajax).
	 *
	 * Authors:
	 * Mauvis Ledford <switchstatement@gmail.com>
	 * Filipe Giusti <filipegiusti@gmail.com>
	 *
	 * Section: (global variables)
	 * These are variables accessible in the global Window namespace.
	 *
	 * method: tinyMCE.activeEditor.execCommand('livepress-activate')
	 * Enables Livepress mode. Hides original TinyMCE area and displays LiveCanvas area. (Note that actual method call depends on <namespace>.)
	 *
	 * method: tinyMCE.activeEditor.execCommand('livepress-deactivate')
	 * Disables Livepress mode. Redisplays original TinyMCE area. Hides LiveCanvas area. (Note that actual method call depends on <namespace>.)
	 *
	 * Events: global jQuery events.
	 * See full list. LivePress events let you tie arbitrary code execution to specific happenings.
	 *
	 * start.livepress       -  _(event)_ Triggered when LivePress is starting.
	 * stop.livepress        -  _(event)_ Triggered when LivePress is stopping.
	 * connected.livepress    -  _(event)_ Triggered when LivePress is connected.
	 * disconnected.livepress -  _(event)_ Triggered when LivePress is disconnected.
	 * livepress.post_update -  _(event)_ Triggered when a new post update is made.
	 *
	 * Example: To listen for a livepress event run the following.
	 *
	 * >  $j(document).bind('stop.livepress', function(){
	 * >    alert(1);
	 * >  });
	 */

	/**
	 * Custom animate plugin for animating LivePressHUD.
	 *
	 * @memberOf jQuery
	 * @param {Number} newInt New integer to which we are animating.
	 */
	jQuery.fn.animateTo = function (newInt) {
		var incremental = 1,
			$this = jQuery(this),
			oldInt = parseInt($this.text(), 10),
			t,
			ani;

		if (oldInt === newInt) {
			return;
		}

		if (newInt < oldInt) {
			incremental = incremental * -1;
		}

		ani = (function () {
			return function () {
				oldInt = oldInt + incremental;
				$this.text(oldInt);
				if (oldInt === newInt) {
					clearInterval(t);
				}
			};
		}());
		t = setInterval(ani, 50);
	};

	if (OORTLE.Livepress === undefined && window.tinymce !== undefined) {
		/**
		 * Global Livepress object inside the OORTLE namespace. Distinct from the global window.Livepress object.
		 *
		 * @namespace
		 * @memberOf OORTLE
		 * @type {Object}
		 */
		OORTLE.Livepress = (function () {
			var LIVEPRESS_DEBUG = false,
				LiveCanvas,
				$liveCanvas,
				/**
				 * Object that controls the Micro post form that replaces (hides) the original WordPress TinyMCE form.
				 * It is blue instead of grey and posts (ajax) to the LiveCanvas area.
				 *
				 * @private
				 */
					microPostForm,

				/** Plugin namespace used for DOM ids and CSS classes. */
					namespace = 'livepress',
				/** Name of the current plugin. Must also be the name of the plugin directory. */
					pluginName = 'livepress-wp',
				i18n = tinyMCE.i18n,
				/** Default namespace of the current plugin. */
					pluginNamespace = 'en.livepress.',
				/** Relative folder for the TinyMCE plugin. */
					path = LivepressConfig.lp_plugin_url + 'tinymce/',

				/** Base directory from where the Livepress TinyMCE plugin loads. */
					d = tinyMCE.baseURI.directory,

				/** All configurable options. */
					config = LivepressConfig.PostMetainfo,

				$eDoc,
				$eHTML,
				$eHead,
				$eBody,

				livepressHUD,
				inittedOnce,
				username,
				Helper,
                draft = false,
				livePressChatStarted = false,
				placementOrder = config.placement_of_updates,

				$j = jQuery,
				$ = document.getElementById,

				extend = tinymce.extend,
				each = tinymce.each,

				livepress_init;

			window.eeActive = false;

			// local i18n object, merges into tinyMCE.i18n
			each({
				// HUD
				'start':      lp_strings.start_live,
				'stop':       lp_strings.stop_live,
				'readers':    lp_strings.readers_online,
				'updates':    lp_strings.lp_updates_posted,
				'comments':   lp_strings.comments,

				// TOGGLE
				'toggle':     lp_strings.click_to_toggle,
				'title':      lp_strings.real_time_editor,
				'toggleOff':  lp_strings.off,
				'toggleOn':   lp_strings.on,
				'toggleChat': lp_strings.private_chat
			}, function (val, name, obj) {
				i18n[pluginNamespace + name] = val;
			});

			/**
			 * Creates a stylesheet link for the specified file.
			 *
			 * @param {String} file Filename of the CSS to include.
			 * @return {Object} jQuery object containing the new stylesheet reference.
			 */
			function $makeStyle (file) {
				return $j('<link rel="stylesheet" type="text/css" href="' + path + 'css/' + file + '.css' + '?' + (+new Date()) + '" />');
			}

			/**
			 * Retrieve a string from the i18n object.
			 *
			 * @param {String} string Key to pull from the i18n object.
			 * @returns {String} Value stored in the i18n object.
			 */
			function getLang (string) {
				return i18n[pluginNamespace + string];
			}

			/**
			 * Informs you of current live posters, comments, etc.
			 *
			 * @namespace
			 * @memberOf OORTLE.Livepress
			 * @returns {Object}
			 * @type {LivepressHUD}
			 * @constructor
			 */
			function LivepressHUD () {
				var $livepressHud,
					$posts;

				/** Hide the HUD. */
				this.hide = function () {
					$livepressHud.slideUp('fast');
				};

				/** Hide the HUD. */
				this.show = function () {
					$livepressHud.slideDown('fast');
				};

				/**
				 * Initialize the HUD object.
				 *
				 * @see LivepressHUD#show
				 */
				this.init = function () {
					$livepressHud = $j('.inner-lp-dashboard');
					$posts = $j('#livepress-updates_num');
					this.show();
				};

				/**
				 * Update the number of readers subscribed to the post.
				 *
				 * @param {Number} number New number of readers.
				 * @returns {Object} jQuery object containing the readers display element.
				 */
				this.updateReaders = function (number) {
					var label = ( 1 === number ) ? lp_strings.persons_online : lp_strings.people_online,
						$readers = $j('#livepress-online_num');
					$readers.siblings( '.label' ).text( label );
					return $readers.text(number);
				};

				/**
				 * Update the number of live updates.
				 *
				 * @param {Number} number New number of updates.
				 * @returns {Object} jQuery object containing the updates display element.
				 */
				this.updateLivePosts = function (number) {
					if ($posts.length === 0) {
						$posts = $j('#livepress-updates_num');
					}

					return $posts.text(number);
				};

				/**
				 * Update the number of comments.
				 *
				 * @param {Number} number New number of comments.
				 * @return {Object} jQuery object containing the comments display element.
				 */
				this.updateComments = function (number) {
					var label = ( 1 === number ) ? lp_strings.comment : lp_strings.comments,
						$comments = $j('#livepress-comments_num');

					$comments.siblings( '.label' ).text( label );

					return $comments.text(number);
				};

				/**
				 * Add to the total number of comments.
				 *
				 * @param {Number} number Number to add to the current number of comments.
				 */
				this.sumToComments = function (number) {
					var $comments = $j('#livepress-comments_num'),
						actual = parseInt($comments.text(), 10 ),
						count = actual + number,
						label = ( 1 === count ) ? lp_strings.comment : lp_strings.comments;

					$comments.siblings( '.label' ).text( label );
					$comments.text( count );
				};

				return this;
			}

			jQuery(window).on('start.livechat', function () {
				if (!livePressChatStarted) {
					livePressChatStarted = true;

					$j('<link rel="stylesheet" type="text/css" href="' + LivepressConfig.lp_plugin_url + 'css/collaboration.css' + '?' + (+new Date()) + '" />').appendTo('head');

					// load custom version of jQuery.ui if it doesn't contain the dialogue plugin
					if (!jQuery.fn.dialog) {
						$j.getScript(path + 'jquery.ui.js', function () {
							$makeStyle('flick/jquery-ui-1.7.2.custom').appendTo('head');
							// Out of first LP release
							//          Collaboration.Chat.initialize();
						});
						//} else {
						// Out of first LP release
						//        Collaboration.Chat.initialize();
					}
				} else {
					// Out of first LP release
					//      Collaboration.Chat.initialize();
				}
			});

			$makeStyle('outside').appendTo('head');

			/**
			 * Initialize the Livepress HUD.
			 */
			livepress_init = function () {

				// Ensure we have a twitter handler, even when the page starts with no embeds
				// because they may be added later. Corrects issue where twitter embeds failed on live posts when
				// original page load contained no embeds.
				if ( 'undefined' === typeof window.twttr ) {
					window.twttr = (function (d,s,id) {
										var t, js, fjs = d.getElementsByTagName(s)[0];
										if (d.getElementById(id)) { return; } js=d.createElement(s); js.id=id;
										js.src="https://platform.twitter.com/widgets.js"; fjs.parentNode.insertBefore(js, fjs);
										return window.twttr || (t = { _e: [], ready: function(f){ t._e.push(f); } });
									}(document, "script", "twitter-wjs"));
				}

				// On first init we load in external and internal styles
				// on secondary called we just disable / enable
				if (!inittedOnce) {
					inittedOnce = true;


					var activeEditor = tinyMCE.activeEditor;
					if (activeEditor) {
						$eDoc = $j(tinyMCE.activeEditor.getDoc());
						$eHTML = $eDoc.find('html:first');
						$eHead = $eDoc.find('head:first');
						$eBody = $eDoc.find('body:first');
					} else {
						$eDoc = $eHTML = $eHead = null;
					}

					if (LivepressConfig.debug !== undefined && LivepressConfig.debug) {
						$j(document).find('html:first').addClass('debug');
						if ($eDoc !== null) {
							$eDoc.find('html:first').addClass('debug');
						}
					}

				}

				OORTLE.Livepress.LivepressHUD.show();

				setTimeout(LiveCanvas.init, 1);

				$j(document.body).add($eHTML).addClass(namespace + '-active');
			};

			var toggleTabs = function( $buttonDiv ) {
				$buttonDiv.first().find( 'a' ).each( function () {
					jQuery( this ).toggleClass( 'active' );
				});
			};

			jQuery(window).on('start.livepress', function () {
				window.eeActive = true;
				window.eeTextActive = false;
				// switch to visual mode if in html mode
				if (!tinyMCE.editors.content) {
					switchEditors.go('content', 'tmce');
				}

				addEditorTabControlListeners( jQuery( '.wp-editor-tabs' ), 'content', '.wp-editor-tabs' );

				// remove the old visual editor
				jQuery( '.wp-editor-tabs > a.switch-tmce' ).remove();

				// A timeout is used to give TinyMCE a time to invoke itself and refresh the DOM.
				setTimeout(livepress_init, 5);
			});

			jQuery(window).on('stop.livepress', function () {
				window.eeActive = false;
				OORTLE.Livepress.LivepressHUD.hide();
				// Stops the livepress collaborative edit
				Collaboration.Edit.destroy();

				$j(document.body).add($eHTML).removeClass(namespace + '-active');
				LiveCanvas.hide();
			});

			/**
			 * Add listeners for the text/html Real-time tabs
			 */
			function addEditorTabControlListeners( $buttonDiv, editorID, toggleFinder, editor ) {

				$buttonDiv.find( 'a.switch-livepress' ).off( 'click' ).on( 'click', function( e ) {
					if ( jQuery( this ).hasClass( 'active' ) ){
						return;
					}
                    var current_content = '';
					if ( 'undefined' === typeof editor ) {

                        current_content = tinyMCE.activeEditor.targetElm.value;
						switchEditors.go( 'content', 'toggle');
                        tinyMCE.activeEditor.setContent( current_content );
					} else {
                        var currentEditorId = jQuery(this).attr('data-editor');
                        current_content = tinyMCE.get( currentEditorId ).targetElm.value;

                        e = tinyMCE.get( currentEditorId );
						e.show();
                        e.setContent( current_content );
					}
					window.eeTextActive = false;
					var parent_div = jQuery( this ).parent('.livepress-inline-editor-tabs');
					toggleTabs( ( ( 'undefined' === typeof( toggleFinder ) || '' === toggleFinder ) ? parent_div : jQuery( toggleFinder ) ) );
					return false;
				});

				$buttonDiv.find( 'a.switch-livepress-html' ).off( 'click' ).on( 'click', function( e ) {
					if ( jQuery( this ).hasClass( 'active' ) ){
						return;
					}

					var currentEditorId = jQuery( this).attr('data-editor');
                    currentEditorId = ( 'undefined' === typeof( currentEditorId ) ) ? 'content' : currentEditorId;

                    e = tinyMCE.get( currentEditorId );
                    var originalContent =  e.targetElm.value;

					switchEditors.go( currentEditorId, 'html');

					window.eeTextActive = true;
					var parent_div = jQuery( this ).parent('.livepress-inline-editor-tabs');
					toggleTabs( ( ( 'undefined' === typeof( toggleFinder ) || '' === toggleFinder ) ? parent_div : jQuery( toggleFinder ) ) );
					return false;
				});
			}

			/**
			 * Generic helper object for the real-time editor.
			 *
			 * @returns {Object}
			 * @constructor
			 * @memberOf OORTLE.Livepress
			 * @private
			 */
			function InnerHelper () {
				var SELF = this,
					NONCES = jQuery( '#livepress-nonces' ),
					hasRegex = new RegExp("\\[livepress_metainfo[^\\]]*]"),
					onlyRegex = new RegExp("^\\s*\\[livepress_metainfo[^\\]]*]\\s*$");

				SELF.getGravatars = function() {
					// Set up the gravatar array
					var gravatars = [];
					for ( var i = 0, len = lp_strings.lp_gravatars.length; i < len; i++ ) {
						gravatars[ lp_strings.lp_gravatars[ i ].id ] = lp_strings.lp_gravatars[ i ].avatar;
					}
					return gravatars;
				};

				SELF.getGravatarLinks = function() {
					// Set up the gravatar array
					var links = [];
					for ( var i = 0, len = lp_strings.lp_avatar_links.length; i < len; i++ ) {
						links[ lp_strings.lp_avatar_links[ i ].id ] = lp_strings.lp_avatar_links[ i ].link;
					}
					return links;
				};


				SELF.gravatars   = SELF.getGravatars();
				SELF.avatarLinks = SELF.getGravatarLinks();

				/**
				 * Dispatch a specific action to the server asynchronously.
				 *
				 * @param {String} action Server-side action to invoke.
				 * @param {String} content Content in the current editor window.
				 * @param {Function} callback Function to invoke upon success or failure.
				 * @param {Object} additional Additional parameters to send with the request
				 * @return {Object} jQuery.
				 */
				SELF.ajaxAction = function( action, content, callback, additional ) {
					return jQuery.ajax({
						type:     "POST",
						url:      LivepressConfig.ajax_url,
						data:     jQuery.extend({
							action:  action,
							content: content,
							post_id: LivepressConfig.post_id
						}, additional || {}),
						dataType: "json",
						success:  callback,
						error:    function( jqXHR, textStatus, errorThrown ) {
							callback.apply( jqXHR, [undefined, textStatus, errorThrown] );
						}
					});
				};

				/**
				 * Start the Real-Time Editor.
				 *
				 * @param {String} content Content in the current editor window.
				 * @param {Function} callback Function to invoke upon success or failure,
				 * @returns {Object} jQuery
				 */
				SELF.doStartEE = function (content, callback) {
					return SELF.ajaxAction('start_ee', content, callback);
				};

				/**
				 * Append a new post update.
				 *
				 * @param {String} content Content to append.
				 * @param {Function} callback Function to invoke after processing.
				 * @param {String} title Title of the update.
				 * @returns {Object} jQuery
				 */

				SELF.appendPostUpdate = function (content, callback, title) {
					var args = (title === undefined) ? {} : {title: title};
					args.liveTags    = this.getCurrentLiveTags();
					args.authors    = this.getCurrentAuthors();
					args._ajax_nonce = NONCES.data('append-nonce');

					jQuery('.peeklink span, .peekmessage').removeClass('hidden');
					jQuery('.peek').addClass('live');
					jQuery( window ).trigger( 'livepress.post_update' );
					window.setTimeout( update_embeds, 3000 );
					return SELF.ajaxAction( 'lp_append_post_update', content, callback, args);
				};

                /**
                 * Append a new post update.
                 *
                 * @param {String} content Content to append.
                 * @param {Function} callback Function to invoke after processing.
                 * @param {String} title Title of the update.
                 * @returns {Object} jQuery
                 */
                SELF.appendPostDraft = function (content, callback, title) {
                    var args = (title === undefined) ? {} : {title: title};
                    args.liveTags    = this.getCurrentLiveTags();
                    args.authors    = this.getCurrentAuthors();
                    args._ajax_nonce = NONCES.data('append-nonce');

                    jQuery('.peeklink span, .peekmessage').removeClass('hidden');
                    jQuery('.peek').addClass('live');
                    jQuery( window ).trigger( 'livepress.post_update' );
                    window.setTimeout( update_embeds, 3000 );
                    return SELF.ajaxAction( 'lp_append_post_draft', content, callback, args);
                };

				/**
				 * Modify a specific post update.
				 *
				 * @param {string} updateId ID of the update to change.
				 * @param {String} content Content with which to modify the update.
				 * @param {Function} callback Function to invoke after processing.
				 * @returns {Object} jQuery
				 */
				SELF.changePostUpdate = function (updateId, content, callback) {
					var nonce = NONCES.data('change-nonce');
					return SELF.ajaxAction('lp_change_post_update', content, callback, {update_id: updateId, _ajax_nonce: nonce});
				};

                /**
                 * Modify a specific post update.
                 *
                 * @param {string} updateId ID of the update to change.
                 * @param {String} content Content with which to modify the update.
                 * @param {Function} callback Function to invoke after processing.
                 * @returns {Object} jQuery
                 */
                SELF.changePostDraft = function (updateId, content, callback) {
                    var nonce = NONCES.data('change-nonce');
                    return SELF.ajaxAction('lp_change_post_draft', content, callback, {update_id: updateId, _ajax_nonce: nonce});
                };

				/**
				 * Remove a post update.
				 *
				 * @param {String} updateId ID of the update to remove.
				 * @param {Function} callback Function to invoke after processing.
				 * @returns {Object} jQuery
				 */
				SELF.deletePostUpdate = function (updateId, callback) {
					var nonce = NONCES.data('delete-nonce');
					return SELF.ajaxAction('lp_delete_post_update', "", callback, {update_id: updateId, _ajax_nonce: nonce});
				};

				/**
				 * Get text from the editor, but preprocess it the same way WordPress does first.
                 * ready to be sent to server
				 *
				 * @param {Object} editor TinyMCE editor instance.
				 * @returns {String} Preprocessed text from editor.
				 */
				SELF.getProcessedContent = function (editor) {
					if ( undefined === editor ) {
						return;
					}

                    var html_editor_live = jQuery( 'textarea#'+editor.id ).is(':visible');
                    var originalContent = editor.getContent();

                    if( '' === originalContent || html_editor_live ){
                        originalContent = editor.targetElm.value;
                        editor.targetElm.value = '';
                    }

					originalContent = originalContent.replace( '<p><br data-mce-bogus="1"></p>', '' );
					// Remove the toolbar/dashicons present on images/galleries since WordPress 3.9 and embeds since 4.0
					originalContent = originalContent.replace( '<div class="toolbar"><div class="dashicons dashicons-edit edit"></div><div class="dashicons dashicons-no-alt remove"></div></div>', '' );

					originalContent = originalContent.replace( /^<div class="livepress-update-outer-wrapper lp_avatar_hidden">(.*)<\/div>$/gi, '$1' );

					if ( '' === originalContent.trim() ) {
						editor.undoManager.redo();

						originalContent = editor.getContent( { format: 'raw' } ).replace( '<p><br data-mce-bogus="1"></p>', '' );

						if ( '' === originalContent.trim() ) {
							return;
						}
					}

					/**
					 * Add the live update header bar with timestamp (if option checked)
					 * and update header (if header entered).
					 */
					var editorID    = editor.editorContainer.id,
						$activeForm  = jQuery( '#' + editorID ).closest( '.livepress-update-form' );

					/**
					 * Update header
					 */
					var liveUpdateHeader = $activeForm.find( '.liveupdate-header'),
						processed = switchEditors.pre_wpautop( originalContent ),

						/**
						 * Live Tags Support
						 */

						// Add tags if present
						$livetagField = $activeForm.find( '.livepress-live-tag' ),
						liveTags = SELF.getCurrentLiveTags( $activeForm )						,

						// Save any new tags back to the taxonomy via ajax
						newTags = jQuery( liveTags ).not( LivepressConfig.live_update_tags ).get(),
						$timestampCheckbox = jQuery('.livepress-timestamp-option input[type="checkbox"]'),
						includeTimestamp = ( 0 === $timestampCheckbox.length ) || $timestampCheckbox.is(':checked');
						var authors  = SELF.getCurrentAuthors( $activeForm );

					// Save the existing tags back to the select2 field
					if ( 0 !== newTags.length ) {
						LivepressConfig.live_update_tags = LivepressConfig.live_update_tags.concat( newTags );
						$livetagField.select2( { tags: LivepressConfig.live_update_tags } );
						jQuery.ajax({
							method: 'post',
							url:     LivepressConfig.ajax_url,
							data:    {
								action:           'lp_add_live_update_tags',
								_ajax_nonce:      LivepressConfig.lp_add_live_update_tags_nonce,
								new_tags:         JSON.stringify( newTags ),
								post_id:          LivepressConfig.post_id
							},
							success: function() {
							}
						});
					}
					var avater_class = ( -1 !== jQuery.inArray( 'AVATAR', LivepressConfig.show ) && 0 !== authors.length )?'lp_avatar_shown':'lp_avatar_hidden';
					// edge case if you have backslahes
					processed = processed.replaceAll( '\\', '\\\\' );

					// Wrap the inner update


					if ( -1 === processed.search( 'livepress-update-inner-wrapper' ) ){ /* don't double add */

						if( 'default' !== LivepressConfig.update_format )  {
							processed = '<div class="livepress-update-inner-wrapper ' + avater_class + '">\n\n' + processed + '\n\n</div>';
							if ( -1 !== jQuery.inArray( 'AVATAR', LivepressConfig.show ) ) {
								processed = ( ( 0 !== authors.length ) ? SELF.getAuthorsHTML( authors ) : '' ) + processed;
							}
							processed = SELF.newMetainfoShortcode( includeTimestamp, liveUpdateHeader, processed, authors ) +
							( ( 0 !== liveTags.length ) ? SELF.getLiveTagsHTML( liveTags ) : '' ) + processed;
						}else{

							processed = SELF.newMetainfoShortcode( includeTimestamp, liveUpdateHeader, processed, authors ) +
							( ( 0 !== liveTags.length ) ? SELF.getLiveTagsHTML( liveTags ) : '' );

							if ( -1 !== jQuery.inArray( 'AVATAR', LivepressConfig.show ) ) {
								processed = ( ( 0 !== authors.length ) ? SELF.getAuthorsHTML( authors ) : '' ) + processed;
							}
							if ( -1 === processed.search( 'livepress-update-outer-wrapper' ) ) {
								processed = '<div class="livepress-update-outer-wrapper ' + avater_class + '">\n\n' + processed + '\n\n</div>';
							}
						}
					}

					return processed;
				};

				/**
				 * Get the list of live tags added on this update
				 */
				SELF.getCurrentLiveTags = function( $activeForm ) {
					if ( 'undefined' === typeof $activeForm ) {
						$activeForm  = jQuery( '.livepress-update-form' );
					}
					return $activeForm.find( '.livepress-live-tag' ).select2( 'val' );
				};

				/**
				 * Get the list of authors added on this update
				 */
				SELF.getCurrentAuthors = function( $activeForm ) {
					if ( 'undefined' === typeof $activeForm ) {
						$activeForm  = jQuery( '.livepress-update-form' );
					}
                    var authors = $activeForm.find( '.liveupdate-byline' ).select2( 'data' );

					return authors;
				};

				/**
				 * Wrap the avatar image or name in a link
				 */
				SELF.linkToAvatar = function( html, userid ) {
					if ( 'undefined' !== typeof SELF.avatarLinks[ userid ] && '' !== SELF.avatarLinks[ userid ] ) {
						return '<a href="' + SELF.avatarLinks[ userid ] + '" target="_blank">' + html + '</a>';
					}else{
                        return html;
                    }
				};

				/**
				 * Get the html for displaying the authors for this live update.
				 *
				 * @param  Array  Authors array of authors for this update.
				 *
				 * @return String HTML to display the authors.
				 *
				 */
				SELF.getAuthorsHTML = function( authors ){
					var toReturn = '';

					if ( 0 === authors ) {
						return '';
					}
					// Start the tags div
					toReturn += '<div class="live-update-authors">';

					// add each of the authors as a span
					jQuery.each( authors, function(){
						toReturn += '<span class="live-update-author live-update-author-' +
						this.text.replace(/\s/g, '-').toLowerCase() + '">';
						toReturn += '<span class="lp-authorID">' + this.id + '</span>';
						if ( 'undefined' !== typeof SELF.gravatars[ this.id ] && '' !== SELF.gravatars[ this.id ] ) {
							toReturn += '<span class="live-author-gravatar">' + SELF.linkToAvatar( SELF.gravatars[ this.id ], this.id ) +  '</span>';
						}
						toReturn += '<span class="live-author-name">' + SELF.linkToAvatar( this.text, this.id ) + '</span></span>';
					} );

					// Close the divs tag
					toReturn += '</div>';

					return toReturn;

				};

				/**
				 * Get the html for the live update tags.
				 *
				 * @param  Array An array of tags
				 *
				 * @return The HTML to append to the live update.
				 *
				 */
				SELF.getLiveTagsHTML = function( liveTags ) {
					var toReturn = '';

					if ( 0 === liveTags ) {
						return '';
					}
					// Start the tags div
					toReturn += '<div class="live-update-livetags">';

					// add each of the tags as a span
					jQuery.each( liveTags, function(){
						toReturn += '<span class="live-update-livetag live-update-livetag-' +
						this + '">' +
						this + '</span>';
					} );

					// Close the divs tag
					toReturn += '</div>';

					return toReturn;
				};

				/**
				 * Gets the metainfo shortcode.
				 *
				 * @param {Boolean} showTimstamp Flag to determine whether to show the timestamp.
				 * @returns {String} Metainfo shortcode.
				 */
				SELF.newMetainfoShortcode = function ( showTimstamp, $liveUpdateHeader, content, authors ) {
					var metainfo = "[livepress_metainfo",
						d,
						utc,
						server_time;

					// Used stored meta information when editing an existing update
					var show_timestmp =  $liveUpdateHeader.data( 'show_timestmp' );


					if ( '1' === show_timestmp ) {
						var time      = $liveUpdateHeader.data( 'time' ),
							timestamp = $liveUpdateHeader.data( 'timestamp' );

						metainfo += ' show_timestmp="1"';

						if ( 'undefined' !== typeof time ) {
							metainfo += ' time="' + time + '"';
						}

						if ( 'undefined' !== typeof timestamp ) {
							metainfo += ' timestamp="' + timestamp + '"';
						}


					} else {
						if( showTimstamp ) {
							metainfo += ' show_timestmp="1"';
							d = new Date();
							utc = d.getTime() + (d.getTimezoneOffset() * 60000); // Minutes to milisec
							server_time = utc + (3600000 * LivepressConfig.blog_gmt_offset); // Hours to milisec
							server_time = new Date(server_time);
							if (LivepressConfig.timestamp_template) {
								if (window.eeActive) {
									metainfo += ' time="' + server_time.format( LivepressConfig.timestamp_template ) + '"';
									metainfo += ' timestamp="' + d.toISOString() + '"';
								} else {
									metainfo += ' POSTTIME ';
								}
							}
						}
					}

					if ( LivepressConfig.has_avatar ) {
						metainfo += ' has_avatar="1"';
					}

					if ( 0 <= jQuery.inArray( 'AVATAR', LivepressConfig.show ) ) {
						metainfo += ' avatar_block="shown"';
					}

					if ( 'undefined' !== typeof authors ) {
						var custom_author_names = '';
						var separator = '';
						for (var i = 0; i < authors.length; i++){
							custom_author_names += separator + authors[i]['text'];
							separator = ' - ';
						}
						metainfo += ' authors="' + custom_author_names + '"';
					}

					if ( 'undefined' !== typeof $liveUpdateHeader.val() && '' !== $liveUpdateHeader.val() ) {
                        var post_title = $liveUpdateHeader.val().replace(/#/g,'%23').replace(/%/g,'%25');
						metainfo += ' update_header="' + encodeURI( decodeURI( post_title ) ) + '"';
					}
					metainfo += "]\n\n";

					if( 'default' === LivepressConfig.update_format ){
						metainfo += ' ' + content + ' \n[/livepress_metainfo]\n\n';
					}


					return metainfo;
				};

				/**
				 * Tests if metainfo shortcode included in text.
				 *
				 * @param {String} text Text to test.
				 * @return {Boolean} Whether or not meta info is included in the shortcode.
				 */
				SELF.hasMetainfoShortcode = function (text) {
					return hasRegex.test(text);
				};

				/**
				 * Tests if text is ''only'' the metainfo shortcode.
				 *
				 * @param {String} text Text to test.
				 * @return {Boolean} Whether or not text is only the metainfo shortcode.
				 */
				SELF.hasMetainfoShortcodeOnly = function (text) {
					return onlyRegex.test(text);
				};
			}

			Helper = new InnerHelper();

			/**
			 * Class that creates and controls TinyMCE-enabled form.
			 * - called by the LiveCanvas whenever a new post needs to be added or edited.
			 * - It converts selected element into a tinymce area and adds buttons to performs particular actions.
			 * - It currently has two modes "new" or "editing".
			 *
			 * @param {String} mode Either 'editing' or 'new.' If 'new,' will create a new TinyMCE form after element el.
			 * @param {String} el DOM identifier of element to be removed.
			 * @returns {Object} New Selection object
			 * @memberOf OORTLE.Livepress
			 * @constructor
			 * @private
			 */
			function Selection ( mode, el ) {
				var SELF = this;

				SELF.handle = namespace + '-tiny' + (+new Date());

				SELF.mode = mode;

                SELF.draft = jQuery(el).hasClass( 'livepress-draft');

				if (SELF.mode === 'new') {
					SELF.originalContent = "";
					if (tinyMCE.onRemoveEditor !== undefined) {
						/** As of WordPress 3.9, TinyMCE is upgraded to version 4 and the
						/*  old action methods are deprecated. This check ensures compatibility.
						**/
						if ( '3' === tinymce.majorVersion ){
							tinyMCE.onRemoveEditor.add( function (mgr, ed) {
								if ((ed !== SELF) && (tinyMCE.editors.hasOwnProperty(SELF.handle))) {
									try {
										tinyMCE.execCommand('mceFocus', false, SELF.handle);
									} catch (err) {
										console.log("mceFocus error", err);
									}
								}
								return true;
							});
						} else {
							tinyMCE.on( 'RemoveEditor', function (mgr, ed) {
								if ((ed !== SELF) && (tinyMCE.editors.hasOwnProperty(SELF.handle))) {
									try {
										tinyMCE.execCommand('mceFocus', false, SELF.handle);
										tinyMCE.editors[SELF.handle].focus();
									} catch (err) {
										console.log("mceFocus error", err);
										console.log( SELF.handle );
									}
								}
								return true;
							});
						}
					}
				} else if (SELF.mode === 'editing' || SELF.mode === 'deleting') {
					// saving old content in case of reset

					var $el = $j(el);

                    SELF.originalHtmlContent = ( 'string' === typeof( $el.data("originalHtmlContent")) )? $el.data("originalHtmlContent") : el.outerHTML  || new XMLSerializer().serializeToString( el ) ;

                    SELF.originalContent = $el.data("originalContent") || $el.data("nonExpandedContent") || '';
					SELF.originalId = $el.data('originalId');
					SELF.originalUpdateId = $el.attr('id');
					if (SELF.mode === 'deleting') {
						SELF.newEditContent = null; // Display to user content to be removed by update
					} else {
						SELF.newEditContent = $el.data("nonExpandedContent"); // Display to user current content
					}
				}

				// See switchEditors.go('tinymce') -- wpautop should be processed before running RichEditor
				SELF.newEditContent = SELF.newEditContent ? switchEditors.wpautop(SELF.newEditContent) : SELF.newEditContent;
				SELF.formLayout = SELF.formLayout.replace('{content}', SELF.newEditContent || SELF.originalContent || '');
				SELF.formLayout = SELF.formLayout.replace('{handle}', SELF.handle);
				SELF.$form = $j(SELF.formLayout)
					.find('div.livepress-form-actions:first').on( 'click', function (e) {
						SELF.onFormAction(e);
					} )
					.end()
					// running it this way is required since we don't have Function.bind
					.on( 'submit', function () {
						SELF.onSave();
						SELF.resetFormFieldStates( SELF );
						return false;
					} );
				SELF.$form.attr('id', SELF.originalUpdateId);
				SELF.$form.data('originalId', SELF.originalId);
				SELF.$form.addClass('livepress-update-form');
				if ( mode === 'new' ) {
					SELF.$form.find('input.button.save').remove();
					SELF.$form.find('a.livepress-delete').remove();
					SELF.$form.addClass(namespace + '-newform');
					if ($j('#post input[name=original_post_status]').val() !== 'publish') {
						SELF.$form.find('input.published').remove();
						SELF.$form.find('.quick-publish').remove();
					} else {
						SELF.$form.find('input.notpublished').remove();
					}
				} else {

                    if(SELF.draft){
                        SELF.$form.find('input.save').remove();
                        SELF.$form.find('.primary.published').remove();
                        SELF.$form.addClass('livepress-draft');
                    }else{
                        SELF.$form.find('.primary.button-primary').remove();
                        SELF.$form.find('.button-secondary.livepress-draft').remove();
                    }

					SELF.$form.find('.livepress-timestamp-option').remove();
					if (mode === 'deleting') {
						SELF.$form.addClass(namespace + '-delform');
					}
				}

				// Add tag support to the form
				SELF.$form
					.find( 'input.livepress-live-tag' )
					.select2( {
						placeholder:     lp_strings.live_tags_select_placeholder,
						tokenSeparators: [','],
						tags:            LivepressConfig.live_update_tags
					});

				// Add byline support to the form
				SELF.$form
					.find( 'input.liveupdate-byline' )
					.select2( {
						tokenSeparators: [','],
						tags: lp_strings.lp_authors,
						initSelection: function ( element, callback ) {
							var data = { id: element.data( 'id' ), text: element.data( 'name' ) };
							callback( data );
						}
					});

				return this;
			}

			extend(Selection.prototype, {
				/*
				 * variable: mode
				 * The mode of this Selection object (currently either 'new' or 'editing' or 'deleting')
				 */
				mode:        null,


				/*
				 * variable: formLayout
				 */
				formLayout:  [
					'<form>',
					'<div class="livepress-update-header"><input type="text" placeholder="' + lp_strings.live_update_header + '"value="" autocomplete="off" class="liveupdate-header" name="liveupdate-header" id="liveupdate-header" /></div>',
					'<div class="editorcontainer">',
					'<textarea id="{handle}" class="wp-editor-area">{content}</textarea>',
					'</div>',
					'<div class="editorcontainerend"></div>',
					'<div class="livepress-form-actions">',
					'<div class="livepress-live-tags">' +
						'<input type="hidden" style="width:95%;" class="livepress-live-tag" />' +
					'</div>',
					'<div class="livepress-byline">' + lp_strings.live_update_byline + ' <input type="text" data-name="' + LivepressConfig.author_display_name + '" data-id="' + LivepressConfig.author_id + '" autocomplete="off" style="width: 70%; float: right; margin-left: 5px;" class="liveupdate-byline" name="liveupdate-byline" id="liveupdate-byline" /></div>',
					'<div class="livepress-timestamp-option"><input type="checkbox" checked="checked" /> ' + lp_strings.include_timestamp + '</div>',
					'<div class="livepress-actions"><a href="#" class="livepress-delete" data-action="delete">' + lp_strings.delete_perm + '</a>',
                    '<input class="livepress-draft button button-secondary" type="button" value="' + lp_strings.draft + '" data-action="draft" />',
					'<input class="livepress-cancel button button-secondary" type="button" value="' + lp_strings.cancel + '" data-action="cancel" />',
					'<input class="livepress-update button button-primary save" type="submit" value="' + lp_strings.save + '" data-action="update" />',
					'<input class="livepress-submit published primary button-primary" type="submit" value="' + lp_strings.push_update + '" data-action="update" />',
					'<input class="livepress-submit notpublished primary button-primary" type="submit" value="' + lp_strings.add_update + '" data-action="publish-draft" />',
                    '<br /><span class="quick-publish">' + lp_strings.ctrl_enter + '</span></div>',
					'</div>',
					'<div class="clear"></div>',
					'</form>'
				].join(''),

				/**
				 * Reset all the form buttons and fields to their default status,
				 * called when pushing a live update.
				 */
				resetFormFieldStates: function( SELF ) {
					SELF.$form.find( '.livepress-live-tag' ).select2( 'val', '' ).select2( {
						placeholder:     lp_strings.live_tags_select_placeholder,
						tokenSeparators: [','],
						tags:            LivepressConfig.live_update_tags
					});
					SELF.$form.find( '.liveupdate-header' ).val( '' );
					if ( "true" !== LivepressConfig.use_default_author ) {
						// removed as we wish to keep the author
				//		SELF.$form.find( '.liveupdate-byline' ).select2( 'val', '' );
                   //     SELF.$form.find( '.liveupdate-byline' ).val(null).trigger("change"); // right way to clear
					}

				},

				/*
				 * function: enableTiny
				 */
				enableTiny:  function (optClass) {
					var te,
						selection = this,
						SELF=this,
						/** As of WordPress 3.9, TinyMCE is upgraded to version 4 and the
						/*  old action methods are deprecated. This check ensures compatibility.
						**/
						mceAddCommand = ( '3' === tinymce.majorVersion ) ? 'mceAddControl' : 'mceAddEditor';
					// initialize default editor at required selector
					tinyMCE.execCommand( mceAddCommand, false, this.handle );
					te = tinyMCE.editors[this.handle];

					if (!te) {
						console.log("Unable to get editor for " + this.handle);
						return;
					}
					te.dom.loadCSS(path + 'css/inside.css' + '?' + (+new Date()));
					// only our punymce editors have this class, used for additional styling
					te.dom.addClass(te.dom.getRoot(), 'livepress_editor');
					if (optClass) {
						te.dom.addClass(te.dom.getRoot(), 'livepress-' + optClass + '_editor');
						te.dom.addClass(te.dom.getRoot().parentNode, 'livepress-' + optClass + '_editor');
					}
					if (config.has_avatar) {
						te.dom.addClass(te.dom.getRoot(), 'livepress-has-avatar');
					}
					var metaAuthors = [];
					/**
					 * Pull out existing header, authors and tags when editing
					 * an existing update
					 */
					var $domcontent = jQuery( te.dom.getRoot() ),
						editor      = te.editorContainer.id,
						$activeForm = jQuery( '#' + editor ).closest( '.livepress-update-form' ),
						content     = $domcontent.html();

					var sourceContent = content;
					/**
					 * Handle the livepress_metainfo shortcode if present, including headline and timestamp
					 */
					if ( Helper.hasMetainfoShortcode( content ) ) {

						/**
						 * Remove the meta information from the content
						 */
						var contentParts = content.split('[livepress_metainfo');

						if ('undefined' !== typeof contentParts[1]) {
							content = contentParts[0] + contentParts[1].split(']')[1];
						}
						var metaInfo = contentParts[1].split(']')[0];

						// Extract the header from the metainfo shortcode
						var headerChunks = metaInfo.split('update_header="'),
							$formHeader = $activeForm.find('.liveupdate-header');

						// If a header was found, add it to the editor
						if ('undefined' !== typeof headerChunks[1]) {
							var header = headerChunks[1].split('"')[0];
							$formHeader.val(decodeURI(header));
						}

						// Extract the show_timestmp setting
						headerChunks = metaInfo.split('show_timestmp="');
						if ('undefined' !== typeof headerChunks[1]) {
							var show_timestmp = headerChunks[1].split('"')[0];
							$formHeader.data('show_timestmp', show_timestmp);
						} else {
							// default is on
							$formHeader.data('show_timestmp', '1');
						}

						// Extract the time setting
						headerChunks = metaInfo.split('time="');
						if ('undefined' !== typeof headerChunks[1]) {
							var time = headerChunks[1].split('"')[0];
							$formHeader.data('time', time);
						}

						// Extract the timestamp setting
						headerChunks = metaInfo.split('timestamp="');
						if ('undefined' !== typeof headerChunks[1]) {
							var timestamp = headerChunks[1].split('"')[0];
							$formHeader.data('timestamp', "" + timestamp);
						}
						// Extract the authors setting

						headerChunks = metaInfo.split('authors="');
						if ('undefined' !== typeof headerChunks[1]) {
							var header_authors = headerChunks[1].split('"')[0];
							var stuff = header_authors.split( ' - ' );

							var returnAuthors = [];
							metaAuthors = jQuery( stuff ).each( function( index, name ) {
									returnAuthors[index] = name;

							});
							$formHeader.data('authors', "" + metaAuthors);

						}
					}


					/**
					 * Authors
					 */
					var theAuthors = [],
						authors = jQuery( content ).find( '.live-update-author' );

					if(  0 !== metaAuthors.length  ) {
						jQuery(metaAuthors).each(function (i, author_name) {
                            jQuery(lp_strings.lp_authors).each(function (index, author) {
                                if( author_name === author['text'] ){
                                   var theAuthor = {
                                        'id': author['id'],
                                        'text': author_name
                                    };
                                    theAuthors.push( theAuthor );
                                    return false;
                                }
                            });
						});
					}else{
						// If no authors, use whats in the main author field
						if ( 0 === authors.length ) {
							var $a = SELF.$form.find( 'input.liveupdate-byline' );
							if ( '' !== $a.data( 'name' )  ) {
								theAuthors = [{
									'id':   $a.data( 'id' ),
									'text': $a.data( 'name' )
								}];
							}
						} else {
							jQuery( authors ).each( function( i, a ) {
								var $a = jQuery( a ),
									theAuthor = {
										'id':   $a.find( '.lp-authorID' ).text(),
										'text': $a.find( '.live-author-name' ).text()
									};
								theAuthors.push( theAuthor );
							});
						}
					}

                    $activeForm.find( '.liveupdate-byline' ).select2( 'data', theAuthors );

					var $content = jQuery( jQuery.parseHTML( sourceContent ) );
					/**
					 * Livetags
					 */
					var theTags = [],
						tags = $content.find( '.live-update-livetag' );

					jQuery( tags ).each( function( i, a ) {
						theTags.push( jQuery( a ).text() );
					});

					$activeForm.find( '.livepress-live-tag' ).select2( 'val', theTags );

					// Clear the tags and authors from the content
					// if we have content in the short code get otherwise look for the div
					if( 0 < content.indexOf( '[/livepress_metainfo' ) ){
						var bits = content.split('[/');
                        content = bits[0].replace(/<div.*hidden">/g, '');
					}else{
                        content = $domcontent.find( '.livepress-update-inner-wrapper' ).html();
					}
                    if( undefined !== content ){
                        // Reset the editor with the cleaned content
                        $domcontent.html( content );
                    }

					/**
					 * Add onchange and ctrl-click event handlers for the editor
					 */
					SELF.setupEditorEventHandlers( te, selection );
					SELF.te = te;

					return te;
				},



				/**
				 * Handle key events, save on ctrl-enter
				 */
				handleKeyEvent: function( selection, te, e, ed ) {
					var SELF = this;

					if (e.ctrlKey && e.keyCode === 13) {
						e.preventDefault();
						if ( '3' === tinymce.majorVersion ){
							ed.undoManager.undo();
						}
						te.show();
						selection.onSave();
						SELF.resetFormFieldStates( SELF );
					}

				},

				/**
				 * Set up the event handlers for an editor
				 */
				setupEditorEventHandlers: function( te, selection ) {
					var SELF = this;
					/** As of WordPress 3.9, TinyMCE is upgraded to version 4 and the
					/*  old action methods are deprecated. This check ensures compatibility.
					**/
					if ( '3' === tinymce.majorVersion ){
						te.onKeyDown.add( function (ed, e) {
							SELF.handleKeyEvent( selection, te, e, ed );
						} );
					} else {
						te.on( 'KeyDown', function ( e ) {
							SELF.handleKeyEvent( selection, te, e );
						} );
					}
					jQuery( '.livepress-update-form' ).on( 'keydown', 'textarea', function( e ) {
						SELF.handleKeyEvent( selection, te, e );
					} );
				},

				/*
				 * function: disableTiny
				 */
				disableTiny: function () {
					tinyMCE.execCommand('mceRemoveControl', false, this.handle);
				},

				/*
				 * function: stash
				 */
				stash:       function () {
					this.disableTiny();
					this.$form.remove();
				},


				/*
				 * function: mergeData
				 * merge data from external Edit info with current liveCanvas
				 */
                mergeIncrementalData: function (update) {
                    var $target, gen, $block, dom = new Livepress.DOMManipulator(""),
                        $inside_first = $liveCanvas.find('div.inside:first');
                    switch(update.op) {
                        case "append":
                        case "prepend":
                            // add at top or bottom
                            $target = $inside_first.find('div#livepress-update-'+update.id);
                            if($target.length > 0) {
                                // ignore already added update
                                return;
                            }
                            $block = $j(update.prefix + update.proceed + update.suffix);
							$block.data("nonExpandedContent", update.content);
							$block.data("originalContent", update.orig);
							$block.data("originalHtmlContent", update.origproc);
							$block.data("originalId", update.id);
							$block.attr("editStyle", "");
                            dom.process_twitter($block[0], update.proceed);
                            if(update.op==="append") {
                                $block.appendTo( $inside_first );
                            } else {
                                $block.prependTo( $inside_first );
                            }
                            Collaboration.Edit.update_live_posts_number();
                            break;
                        case "replace":
                            // update update
                            $target = $inside_first.find('div#livepress-update-'+update.id);
                            if($target.length < 1) {
                                // could happen if update come for already deleted update
                                // f.e., in scenario: add update, edit it, delete it, refresh
                                return;
                            }
                            gen = $target.data("lpg");
                            if (update.lpg <= gen) {
                                // will ignore update for own changes, and old updates
                                // received on first page load.
                                return;
                            }
                            $block = $j(update.prefix + update.proceed + update.suffix);
							$block.data("nonExpandedContent", update.content);
							$block.data("originalContent", update.orig);
							$block.data("originalHtmlContent", update.origproc);
							$block.data("originalId", update.id);
							$block.attr("editStyle", "");
                            dom.process_twitter($block[0], update.proceed);
                            $target.replaceWith($block);
                            break;
                        case "delete":
                            // remove update
                            $target = $inside_first.find('div#livepress-update-'+update.id).remove();
                            Collaboration.Edit.update_live_posts_number();
                            break;
                        default:
                            console.log("Unknown incremental operation", update.op, update);
                    }
                    return;
                },
				mergeData:      function (regions) {
                    if("op" in regions) {
                        return this.mergeIncrementalData(regions);
                    }
					var r, c, nc, reg, $block, curr = [], currlink = {}, reglink = {},
						$inside_first = $liveCanvas.find('div.inside:first');
					// Get list of currently visible regions
					$inside_first.children().each(function () {
						var $this = $j(this),
							cnt = $this.data('nonExpandedContent'),
							handle = $this.data('originalId');
						currlink[handle] = curr.length;
						curr[curr.length] = {'id': handle, 'cnt': cnt, 'handle': $this};
					});
					for (r = 0; r < regions.length; r++) {
						reglink[regions[r].id] = r;
					}
					// Merge list with incoming list
					for (r = 0, c = 0; r < regions.length && c < curr.length;) {
						if (regions[r].id === curr[c].id) {
							// nothing
							r++;
							c++;
						} else if (regions[r].id in currlink) {
							nc = currlink[regions[r].id];
							// from c to nc-1 regions are removed
							for (; c < nc; c++) {
								curr[c].handle.remove();
								curr[c].handle = undefined;
							}
							r++;
							c++;
						} else {
							// new region added just before c
							reg = regions[r];
							$block = $j(reg.prefix + reg.proceed + reg.suffix);
							$block.data("nonExpandedContent", reg.content);
							$block.data("originalContent", reg.orig);
							$block.data("originalHtmlContent", reg.origproc);
							$block.data("originalId", reg.id);
							$block.attr("editStyle", "");
							$block.insertBefore(curr[c].handle);
							r++; // left c untouched
						}
					}
					// Remove all regions not existed in received update
					for (; c < curr.length; c++) {
						curr[c].handle.remove();
					}
					// Append all new regions
					for (; r < regions.length; r++) {
						reg = regions[r];
						$block = $j(reg.prefix + reg.proceed + reg.suffix);
						$block.data("nonExpandedContent", reg.content);
						$block.data("originalContent", reg.orig);
						$block.data("originalHtmlContent", reg.origproc);
						$block.data("originalId", reg.id);
						$block.attr("editStyle", "");
						$inside_first.append($block);
					}
					Collaboration.Edit.update_live_posts_number();
				},

				/*
				 * function: onFormAction
				 */
				onFormAction:   function (e) {
					var val = e.target.getAttribute("data-action");
					if (val === 'cancel') {
						this.onCancel();
						return false;
					} else if (val === 'delete') {
						e.preventDefault();
						this.onDelete();
						return false;
					} else if (val === 'draft') {
                        e.preventDefault();
                        this.onDraft();
                        return false;
                    }
					else if (val === 'publish-draft') {
						e.preventDefault();
						this.onPublishDraft();
						return false;
					}
					// skipped (must be a save)...
				},

				/*
				 * function: displayContent
				 * replaces given element with formatted text update
				 */
				displayContent: function (el) {

                    var originalHtmlContent = ( 'string' === typeof( this.originalHtmlContent ) )? this.originalHtmlContent : this.originalHtmlContent.outerHTML  || new XMLSerializer().serializeToString( this.originalHtmlContent ) ;

					var $newPost = $j( originalHtmlContent );
					$newPost.data( 'nonExpandedContent', this.originalContent );
					$newPost.data( 'originalContent', this.originalContent );
                    $newPost.data( 'originalHtmlContent', originalHtmlContent );
                    $newPost.data( 'originalId', this.originalId );
                    $newPost.data( 'draft', this.isDraft );
					$newPost.attr( 'editStyle', ''); // on cancel, disable delete mode
                   if( this.isDraft ){
                        $newPost.addClass( 'livepress-draft' );
                    }

					try { window.twttr.widgets.load( $newPost[0] ); } catch ( e ) {}

					$newPost.insertAfter(el);
					el.remove();
					this.addListeners( $newPost );
					$liveCanvas.data('mode', '');
					jQuery("abbr.livepress-timestamp").timeago().attr( 'title', '' );
					return false;
				},
                /*
                 * function: onDraft
                 * Modifies livepress-tiny.
                 */
                onDraft:         function () {
                    this.onSave( true );
                },

				/*
				 * function: onPublishDraft
				 */
				onPublishDraft:         function () {
					this.onSave( 'publish' );
				},
				/*
				 * function: onSave
				 * Modifies livepress-tiny.
				 */
				onSave:         function ( isDraft ) {
					// First, we need to be sure we're toggling the update indicator if they're disabled
					var $bar = jQuery('#lp-pub-status-bar');

					// If the bar has this class, it means updates are disabled ... so don't do anything
					if (!$bar.hasClass('no-toggle')) {
						$bar.removeClass('not-live').addClass('live');

						$bar.find('a.toggle-live span').removeClass('hidden');
						$bar.find('.icon').addClass('live').removeClass('not-live');
						$bar.find('.first-line').find('.lp-on').removeClass('hidden');
						$bar.find('.first-line').find('.lp-off').addClass('hidden');
						$bar.find('.second-line').find('.inactive').addClass('hidden');
						$bar.find('.recent').removeClass('hidden');
					}

					var newContent = Helper.getProcessedContent(tinyMCE.editors[this.handle]);
					// Check, is update empty
					var hasTextNodes = $j('<div>').append(newContent).contents().filter(function () {
						return this.nodeType === 3 || this.innerHtml !== '' || this.innerText !== '';
					}).length > 0;
					var onlyMeta = Helper.hasMetainfoShortcodeOnly( newContent );
					if ((!hasTextNodes && $j(newContent).text().trim() === '') || onlyMeta) {
						if (this.mode === 'new') {
							var afterTitleUpdate = function (res) {
								return false;
							};
							// If new update empty -- just send title update
							Helper.appendPostUpdate("", afterTitleUpdate, $j('#title').val());
							return false;
						} else {
							// If user tries to save empty update -- that means that he want to delete it
							return this.onDelete();
						}
					} else {
						var $spinner = $j("<div class='lp-spinner'></div>");
						var $spin = $spinner;
						var afterUpdate = ( function ( self ) {
							return function ( region ) {
                                var $target = $liveCanvas.find('div#livepress-update-'+region.id);
                                // Check, what faster: AJAX answer or oortle publish
                                if ($target.length > 0) {
                                    $spin.remove();
                                } else {
                                    self.originalHtmlContent = region.prefix + region.proceed + region.suffix;
                                    self.originalContent = region.content;
                                    self.originalId = region.id;

                                    self.isDraft = ( true === region.update_meta.draft );
                                    self.displayContent($spin);
                                    Collaboration.Edit.update_live_posts_number();
                                }
							};
						}( this ) );
						// Save of non-empty update can be:
						// 1) append from new post form
						if (this.mode === 'new' /*&& this.handle == microPostForm.handle*/) {
							var action = placementOrder === 'bottom' ? 'appendTo' : 'prependTo';
							$spinner[action]($liveCanvas.find('div.inside:first'));
							tinyMCE.editors[this.handle].setContent("");
                            if( true === isDraft ){
                                Helper.appendPostDraft(newContent, afterUpdate, $j('#title').val());
                            }else{
                                Helper.appendPostUpdate(newContent, afterUpdate, $j('#title').val());
                            }

						} else
						// 2) save of newly appended text somewhere [TODO]
						/*if(this.mode === 'new') {
						} else*/
						// 3) change of already existent update
						{
							tinyMCE.execCommand('mceRemoveControl', false, this.handle);
							$spinner.insertAfter(this.$form);
							$spinner.data("nonExpandedContent", newContent); // Make sure syncData works even while spinner active
							this.$form.remove();
                            if( true === isDraft ){
                                Helper.changePostDraft(this.originalId, newContent, afterUpdate);
                            }else if( 'publish' === isDraft ){
								Helper.appendPostUpdate(newContent, afterUpdate, $j('#title').val());
							}else{
								Helper.changePostUpdate(this.originalId, newContent, afterUpdate);
							}

						}
					}

					if ( 'new' !== this.mode ){
						tinyMCE.remove( tinyMCE.editors[this.handle] );
					}

					// Switch back to the text editor if set
					if ( window.eeTextActive ){
                        switchEditors.go( 'content', 'html' );
					}


					return false;
				},

				/*
				 * function: onCancel
				 * Modifies livepress-tiny.
				 */
				onCancel:       function () {

				//	var newContent = Helper.getProcessedContent(tinyMCE.editors[this.handle]);
				//	var check = true;
					tinyMCE.execCommand('mceRemoveControl', false, this.handle);

					this.displayContent(this.$form);
					tinyMCE.remove( tinyMCE.editors[this.handle] );

					return false;
				},

				/*
				 * function: onDelete
				 * Modifies livepress-tiny.
				 */
				onDelete:       function () {
					var check = confirm( lp_strings.confirm_delete );
					if (check) {
						tinyMCE.execCommand('mceRemoveControl', false, this.handle);
						tinyMCE.remove( tinyMCE.editors[this.handle] );
						var $spinner = $j("<div class='lp-spinner'></div>");
						$spinner.insertAfter(this.$form);
						this.$form.remove();

						Helper.deletePostUpdate(this.originalId, function () {
							$spinner.remove();
							Collaboration.Edit.update_live_posts_number();
						});
					}
					return false;
				},

				/*
				 * function: addListeners
				 * Adds hover and click events to new / edited posts.
				 */
				addListeners:   function ( $el ) {
					$el.hover( LiveCanvas.onUpdateHoverIn, LiveCanvas.onUpdateHoverOut );
					// Not trigger editing on mebedded elements.
					$el.find('div').not('.livepress-meta').find('a').on( 'click', function (ev) {
						ev.stopPropagation();
						ev.preventDefault();
					} );
				}

			});


			/*
			 * namespaces: LiveCanvas
			 * _(object)_ Object that controls the LiveCanvas area.
			 */
			LiveCanvas = (function () {
				var inittedOnce = 0;
				var initXhr = null;

				/*
				 * variable: $liveCanvas
				 * _(private)_ jQuery liveCanvas DOM element.
				 */
				$liveCanvas = $j([
					'<div id="livepress-canvas" class="">',
					'<h3><span id="livepress-updates_num">-</span> ' + lp_strings.updates + '</h3>',
					"<div class='inside'></div>",
					"</div>"
				].join(''));

				/*
				 * function: isEditing
				 * _(private)_
				 */
				var isEditing = function () {
					return $liveCanvas.data('mode') === 'editing';
				};

				/*
				 * function: onUpdateHoverIn
				 * _(public)_
				 */
				var onUpdateHoverIn = function () {
					if (!isEditing()) {
						$j(this).addClass('hover');
					}
				};

				/*
				 * function: showMicroPostForm
				 * _(public)_ Builds and enabled main micro post form.
				 */
				var showMicroPostForm = function (cnt) {

					// if MicroPost form doesn't exist
					// add new micropost form
					microPostForm = new Selection('new', cnt);
					$liveCanvas.before(microPostForm.$form);
					microPostForm.enableTiny('main');

					// because (add) media buttons are hard coded to use wordpress TinyMCE.editors['content']
					// we must switch the references while our editor is active
					if (!tinyMCE.editors.originalContent) {
						tinyMCE.editors.originalContent = tinyMCE.editors.content;
						tinyMCE.editors.content = tinyMCE.editors[microPostForm.handle];
					}
				};

				var addPost = function (data) {

					if (data.replace(/<p>\s*<\/p>/gi, "") === '') {
						return false;
					}
					var $newPost = $j(data);
					var action = (LivepressConfig.PostMetainfo.placement_of_updates === 'bottom' ? 'appendTo' : 'prependTo');

					$newPost.hide()[action]($liveCanvas.find('div.inside:first')).animate(
						{
							"height":  "toggle",
							"opacity": "toggle"
						},
						"slow",
						function () {
							$j(this).removeAttr('style');
						});
					return false;
				};

				/*
				 * function: hideMicroPostForm
				 * _(public)_
				 */
				var hideMicroPostForm = function () {
					if (microPostForm) {
						microPostForm.stash();
					}
					// because (add) media buttons are hard coded to use wordpress TinyMCE.editors['content']
					// we must switch the references while our editor is active
					tinyMCE.editors.content = tinyMCE.editors.originalContent;
					delete tinyMCE.editors.originalContent;
				};

				/*
				 * function: hide
				 * _(public)_
				 */
				var hide = function () {
					if (initXhr) {
						var xhr = initXhr;
						xhr.abort();
						initXhr = null;
					} else {
						$j('#post-body-content .livepress-newform,.secondary-editor-tools').hide();
						hideMicroPostForm();
						$liveCanvas.hide();
						$j('#postdivrich').show();
					}
				};

				/*
				 * funtion: mergeData
				 * _(public)_
				 */
				var mergeData = function (regions) {
					if (microPostForm !== undefined) {
						microPostForm.mergeData(regions);
					}
				};

				/*
				 * function: onUpdateHoverOut
				 * _(public)_
				 */
				var onUpdateHoverOut = function () {
					if (!isEditing()) {
						$j(this).removeClass('hover');
					}
				};

				/*
				 * function: livePressDisableCheck
				 * _(private)_ Called when disabling live blog, checks if there are unsaved editors open.
				 */
				var livePressDisableCheck = function () {
					if (isEditing()) {
						if (!confirm( lp_strings.discard_unsaved )) {
							return false;
						}
					}

					hide();
					return false;
				};

				/*
				 * function: init
				 * _(public)_ Hides original canvas and create live one.
				 */
				var init = function () {
					var postdivrich = document.getElementById( 'postdivrich' ),
						$postdivrich = jQuery( postdivrich );

					if (!window.eeActive) {
						return;
					} // break initialization cycle in case of interrupt
					if (!tinyMCE.editors.length || tinyMCE.editors.content === undefined || !tinyMCE.editors.content.initialized) {
						window.setTimeout(init, 50); // tinyMCE not initialized? try a bit later
						return;
					}
					if (!inittedOnce && !$j.fn.live) {
						$j.fn.live = $j.fn.livequery;
						// set a listener on disabling live blog since
						// we need to run checks before turning it off now that it's on
						$j( document.getElementById( 'live-blog-disable' ) ).on( 'click', livePressDisableCheck );
					}

					// hide original tinymce area
					$postdivrich.hide();

					var spinner = document.createElement( 'div' ),
						$spinner = jQuery( spinner );

					var spin_livecanvas = document.createElement( 'div' );
					spin_livecanvas.className = 'lp-spinner';
					spin_livecanvas.setAttribute( 'id', 'lp_spin_livecanvas' );
					spinner.appendChild( spin_livecanvas );
					var spin_p = document.createElement( 'p' );
					spin_p.style.textAlign = 'center';
					spin_p.innerText = lp_strings.loading_content + '...';
					spinner.appendChild( spin_p );

					var container = document.getElementById( 'titlediv' );
					if ( null === container ){
						container = document.getElementById( 'post-body' );
					}
					if ( container.nextSibling ) {
						container.parentNode.insertBefore( spinner, container.nextSibling );
					} else {
						container.parentNode.appendChild( spinner );
					}

					// get content from existing "old" tinymce editor
					var originalContent = Helper.getProcessedContent(tinyMCE.editors.content);

					// regions contains:
					//    orig -- last saved (visible to users) content
					//    user -- content from currently edit content
					//            will be omitted, if not differs
					// every part is array, where every element = region:
					//    id -- ID of region
					//    prefix -- wrapping prefix tag
					//    suffix -- wrapping end tag
					//    content -- original content
					//    proceed -- filtered resulting html
					// initial regions analyse:
					//    we should find:
					//    1. New region prepended
					//    2. New region appended
					//    3. Regions matching, not changed
					//    4. Regions matching, changed
					var analyseStartEE = function (in_regions) {
						var orig = in_regions.orig, user = in_regions.user;
						if (!orig) {
							orig = [];
						}
						if (!user) {
							return {"prepend": [], "append": [], "changed": [], "deleted": [], "regions": orig};
						}
						var prepend = [], append = [], changed = [], deleted = [], regions = [];
						var o = 0, c = 0, state = 0, i;
						// Discover head changes
						for (o = 0; o < orig.length && !state; o++) {
							var id = orig[o].id;
							for (c = 0; c < user.length && !state; c++) {
								if (id === user[c].id) {
									state = 1;
									if (o === 0 && c === 0) {
										// start of arrays equals, nothing was prepended
										state = 1; // do nothing, make JSLint happy
									}
									else if (o === 0 && c === 1) {
										// there one update appended
										prepend[prepend.length] = user[0].id;
										user[0].orig = "";
										user[0].origproc = "";
										regions[regions.length] = user[0];
									}
									else if (o === 1 && c === 1) {
										// there edit conflict: first update fully rewritten.
										prepend[prepend.length] = user[0].id;
										deleted[deleted.length] = orig[0].id;
										user[0].orig = "";
										user[0].origproc = "";
										regions[regions.length] = user[0];
										orig[0].orig = orig[0].content;
										orig[0].origproc = orig[0].prefix + orig[0].proceed + orig[0].suffix;
										orig[0].content = "";
										regions[regions.length] = orig[0];
									}
									else {
										// some weird was happend with post: lot of content changed.
										// better to reload editor page...
										for (i = 0; i < o; i++) {
											deleted[deleted.length] = orig[i].id;
											orig[i].orig = orig[i].content;
											orig[i].proc = orig[i].prefix + orig[i].proceed + orig[i].suffix;
											orig[i].content = "";
											regions[regions.length] = orig[i];
										}
										for (i = 0; i < c; i++) {
											prepend[prepend.length] = user[i].id;
											user[i].orig = "";
											user[i].origproc = "";
											regions[regions.length] = user[i];
										}
									}
									o--;
									c--;
								}
							}
						}
						// Discover body changes
						while (o < orig.length && c < user.length) {
							if (orig[o].id !== user[c].id) {
								// ids not match, possible some middle conflict
								// solve it by find next same IDs (if any)
								var no, nc, s = 0;
								for (no = o; no < orig.length && !s; no++) {
									var ni = orig[no].id;
									for (nc = c; nc < user.length && !s; nc++) {
										if (user[nc].id === ni) {
											// found equals match
											// all between match from user is "changed" (appended in middle)
											for (s = c; s < nc; s++) {
												changed[changed.length] = user[s].id;
												user[s].orig = "";
												user[s].origproc = "";
												regions[regions.length] = user[s];
											}
											// all between match from orig is deleted.
											for (s = o; s < no; s++) {
												deleted[deleted.length] = orig[s].id;
												orig[s].orig = orig[s].content;
												orig[s].origproc = orig[s].prefix + orig[s].proceed + orig[s].suffix;
												orig[s].content = "";
												regions[regions.length] = orig[s];
											}
											// found, continue
											s = 1;
										}
									}
								}
								if (s) {
									o = no - 1;
									c = nc - 1;
								} else {
									break;
								}
							}
							if (orig[o].content !== user[c].content) {
								changed[changed.length] = orig[o].id;
							}
							user[c].orig = orig[o].content;
							user[c].origproc = orig[o].prefix + orig[o].proceed + orig[o].suffix;
							regions[regions.length] = user[c];
							// Advance
							o++;
							c++;
						}
						// end of equals body. anything left in user are appended,
						// anything left in orig are deleted (conflict?)
						for (; c < user.length; c++) {
							append[append.length] = user[c].id;
							user[c].orig = "";
							user[c].origproc = "";
							regions[regions.length] = user[c];
						}
						for (; o < orig.length; o++) {
							deleted[deleted.length] = orig[o].id;
							orig[o].orig = orig[o].content;
							orig[o].origproc = orig[o].prefix + orig[o].proceed + orig[o].suffix;
							orig[o].content = "";
							regions[regions.length] = orig[o];
						}
                        return {
                            "prepend": prepend,
                            "append": append,
                            "changed": changed,
                            "deleted": deleted,
                            "regions": regions
					};
                    };
					var startError = function (error) {
						var ps = new Livepress.Admin.PostStatus();
                        ps.error(error);
						$j('#post-body-content .livepress-newform,.secondary-editor-tools').hide();
						$liveCanvas.hide();
						$postdivrich.show();
						$spinner.remove();
						Collaboration.Edit.destroy();
						var $ls = jQuery('#live-switcher');
						if ($ls.hasClass('on')) {
							$ls.trigger('click');
						}
						return;
					};
					var initAfterStartEE = function (regions, textError, errorThrown) {
						if (this !== initXhr && errorThrown !== initXhr) {
							// Got complete on aborted ajax call
							return;
						}
						if ( 'undefined' === typeof regions ) {
							return;
						}
						var blogContent = "", i = 0;
						if (regions === undefined && 'parsererror' !== textError && 'error' !== textError ) {
							return startError("Error: " + textError + " : " + errorThrown);
						}
						if (regions.edit_uuid) {
							LivepressConfig.post_edit_msg_id = regions.edit_uuid;
						}
						// Start the livepress collaborative edit
						if ("editStartup" in regions) {
							Collaboration.Edit.initialize(regions.editStartup);
						} else {
							Collaboration.Edit.initialize();
						}
						var ee = analyseStartEE(regions);
						// set this content in the livecanvas area but remove
						// livepress-update-stub tags
						// set events
						var $inside_first = $liveCanvas.find('div.inside:first').html("");
						var pr = 0, ap = 0, ch = 0, de = 0, sty = "";
						var microContent = "";
						for (i = 0; i < ee.regions.length; i++) {
							var reg = ee.regions[i];
							if (pr < ee.prepend.length && reg.id === ee.prepend[pr]) {
								// prepended block
								if (!pr && !ap) { // first prepended block come to new edit area
									microContent = reg.content;
									pr++;
									continue; // do not apply it
								}
								sty = "new"; // display block as new (not saved yet)
							} else if (ap < ee.append.length && reg.id === ee.append[ap]) {
								// appended block
								if (!pr && !ap) { // first appended block come to new edit area
									microContent = reg.content;
									ap++;
									continue; // do not apply it
								}
								sty = "new"; // display block as new (not saved yet)
							} else if (ch < ee.changed.length && reg.id === ee.changed[ch]) {
								// changed block
								sty = "edit"; // display block as edited
								ch++;
							} else if (de < ee.deleted.length && reg.id === ee.deleted[de]) {
								// deleted block
								sty = "del"; // display block as deleted
								de++;
							} else {
								// published non touched block
								sty = "";
							}
							var proc = reg.prefix + reg.proceed + reg.suffix;
							var origproc = reg.origproc === undefined ? proc : reg.origproc;
							var $block = $j(proc);
							var orig = reg.orig === undefined ? reg.content : reg.orig;
							$block.data("nonExpandedContent", reg.content);
							$block.data("originalContent", orig);
							$block.data("originalHtmlContent", origproc);
							$block.data("originalId", reg.id);
							if (sty === "new") {
								// FIXME: add some kind of support for that
								return startError( lp_strings.double_added );
							}
							$block.attr("editStyle", sty);
							try { window.twttr.widgets.load($block[0]); }
							catch(exc) { }
							$inside_first.append($block);
						}
						$inside_first
							.find('div.livepress-update')
							.hover(onUpdateHoverIn, onUpdateHoverOut);

						$spinner.remove();
						if (!inittedOnce) {
							if( 1 === jQuery('#titlediv').length ){
								$liveCanvas.insertAfter('#titlediv');
							} else {
								$liveCanvas.insertBefore('#postdivrich');
							}

							var canvas = document.getElementById( 'livepress-canvas' ),
                                $canvas = $j( canvas );

							// live listeners, bound to canvas (since childs are recreated/added/removed dynamically)
							// @todo ensure only one editor open at opnce, disable click when one editor active
							$canvas.on( 'click',  'div.livepress-update', function (ev) {

								var $target = $j(ev.target);
								if ($target.is("a,input,button,textarea") || $target.parents("a,input,button,textarea").length > 0) {
									return true; // do not handle click event from children links (needed to fix live)
								}
								if (!isEditing()) {
									var style = this.getAttribute( 'editStyle' );


									var Sel = new Selection( 'del' === style ? 'deleting' : 'editing', this);
									Sel.$form.insertAfter( this );

									var editor = Sel.enableTiny( style );
									editor.show();
                                    // hack just reload the content to make WP embed hooks in tinyMCE  refesh content to render emeb's
                                    tinyMCE.get(editor.id).setContent( tinyMCE.get(editor.id).getContent() );

									var tab_markup = '<div class="livepress-inline-editor-tabs wp-editor-tabs '+editor.id+'"><a id="content-livepress-html" data-editor="'+editor.id+'" class="hide-if-no-js wp-switch-editor switch-livepress-html"><span class="icon-livepress-logo"></span> ' + lp_strings.text_editor + '</a><a id="content-livepress"  data-editor="'+editor.id+'" class="hide-if-no-js wp-switch-editor switch-livepress active"><span class="icon-livepress-logo"></span> ' + lp_strings.visual_text_editor + '</a></div>';
									jQuery( tab_markup ).prependTo( Sel.$form );
									// Clone the media button
									jQuery( '.wp-media-buttons' )
										.first()
										.clone( true )
										.prependTo( Sel.$form )
										.css( 'margin-left', '10px' )
										.find( 'a' )
										.attr( 'data-editor', editor.id )
                                        .click( function(){window.wp.media.editor.id( editor.id );});

									addEditorTabControlListeners( Sel.$form.parent().find( '.livepress-inline-editor-tabs' ), editor.id, '', editor );
									jQuery( this ).remove();
								}
							});
							$canvas.on( 'click', 'div.livepress-update a', function (ev) {
								ev.stopPropagation();
							} );

							//showMicroPostForm();
						} else {
							$j('#post-body-content .livepress-newform').show();
							$liveCanvas.show();
						}

						var $sec = $j('.secondary-editor-tools');
						if (!$sec.length) {
							var insert_div = "#poststuff";
							if ( 1 === jQuery("#titlediv").length ){
								insert_div = "#titlediv";
							}
							//copy media buttons from orig tinymce
							jQuery('#wp-content-editor-tools')
								.clone(true)
								.insertAfter(insert_div)
								.addClass('secondary-editor-tools')
								// This next line undoes WP 4.0 editor-expand (sticky toolbar/media button)
								.css( { position: "relative", top: "auto", width: "auto" } )
								.find('#content-tmce, #content-html')
								.each(function () {
									jQuery(this)
										.removeAttr('id')
										.removeAttr('onclick')
										.on('click', function () {

										});
								});
						} else {
							$sec.show();
						}

						showMicroPostForm(microContent);
						Collaboration.Edit.update_live_posts_number();
						window.setTimeout(update_embeds, 1000);

						// first micro post
						var $currentPosts = $liveCanvas.find('div.livepress-update:first');

						/**
						 * Add the Live Post Header feature
						 */
						if ( jQuery( '#livepress_status_meta_box' ).hasClass( 'pinned-header' ) ) {

							// Add the 'Live Post Header' box
							$currentPosts
								.wrap( '<div class="pinned-first-livepress-update"></div>' )
								.before( '<div class="pinned-first-livepress-update-header">' +
									'<div class="dashicons dashicons-arrow-down"></div>' +
									lp_strings.live_post_header +
									'</div>');
							// Move the box to above the Update Count
							$liveCanvas.find( '.pinned-first-livepress-update' )
								.prependTo( $liveCanvas )
								.find( '.livepress-update:first' )
								.find( '.livepress-delete')
								.hide();

							$liveCanvas.find( '.pinned-first-livepress-update div.livepress-update' ).hide();

							/**
							 * Add handlers for expanding/contracting the pinned post header
							 */
							// Expand/Show the Live Post Header when triangle is clicked
							$liveCanvas.on( 'click', '.pinned-first-livepress-update-header', function( el ) {
								var $firstUpdate = $liveCanvas.find( '.pinned-first-livepress-update div.livepress-update' ),
									$icon        = $liveCanvas.find( '.dashicons' );

								if ( $firstUpdate.is(":visible") ) {
									$firstUpdate.slideUp( 'fast' );
									$icon.removeClass( 'dashicons-arrow-up' ).addClass( 'dashicons-arrow-down' );
								} else {
									$firstUpdate.slideDown( 'fast' );
									$icon.removeClass( 'dashicons-arrow-down' ).addClass( 'dashicons-arrow-up' );
								}
							});
						}

						var $pub = jQuery('#publish');
						$pub.on( 'click', function () {
							LiveCanvas.hide();
							return true;
						} );

						inittedOnce = 1;
						initXhr = null;
					};
					initXhr = Helper.doStartEE(originalContent, initAfterStartEE);
				};

				/*
				 * function: get
				 * _(public)_
				 */
				var get = function () {
					return $liveCanvas.find('div.inside:first');
				};

				return {
					init:              init,
					get:               get,
					hide:              hide,
					mergeData:         mergeData,
					onUpdateHoverIn:   onUpdateHoverIn,
					onUpdateHoverOut:  onUpdateHoverOut,
					showMicroPostForm: showMicroPostForm,
					hideMicroPostForm: hideMicroPostForm,
					addPost:           addPost
				};
			}());

			// creates live blogging activation tools
			Livepress.Ready = function () {
				OORTLE.Livepress.Dashboard = new Dashboard.Controller();
				OORTLE.Livepress.LivepressHUD.init();
				// Hide the screen option for LivePress live status
				jQuery('.metabox-prefs label[for=livepress_status_meta_box-hide]').hide();
			};

			return {
				LivepressHUD:        new LivepressHUD(),
				startLiveCanvas:     LiveCanvas.init,
				getLiveCanvas:       LiveCanvas.get,
				mergeLiveCanvasData: LiveCanvas.mergeData
			};
		}());
	}

	/**
	 * Optimized code for twitter intents.
	 *
	 * @see <a href="http://dev.twitter.com/pages/intents">External documentation</a>
	 */
	(function () {
		if (window.__twitterIntentHandler) {
			return;
		}
		var intentRegex = /twitter\.com(\:\d{2,4})?\/intent\/(\w+)/,
			windowOptions = 'scrollbars=yes,resizable=yes,toolbar=no,location=yes',
			width = 550,
			height = 420,
			winHeight = screen.height,
			winWidth = screen.width;

		function handleIntent (e) {
			e = e || window.event;
			var target = e.target || e.srcElement,
				m, left, top;

			while (target && target.nodeName.toLowerCase() !== 'a') {
				target = target.parentNode;
			}


			if (target && target.nodeName.toLowerCase() === 'a' && target.href) {
				m = target.href.match(intentRegex);
				if (m) {
					left = Math.round((winWidth / 2) - (width / 2));
					top = 0;

					if (winHeight > height) {
						top = Math.round((winHeight / 2) - (height / 2));
					}

					window.open(target.href, 'intent', windowOptions + ',width=' + width +
						',height=' + height + ',left=' + left + ',top=' + top);
					e.returnValue = false;
					if (e.preventDefault) {
						e.preventDefault();
					}
				}
			}
		}

		if (document.addEventListener) {
			document.addEventListener('click', handleIntent, false);
		} else if (document.attachEvent) {
			document.attachEvent('onclick', handleIntent);
		}
		window.__twitterIntentHandler = true;
	}());

	/**
	 * Once all the other code is loaded, we check to be sure the user is on the post edit screen and load the new Live
	 * Blogging Tools palette if they are.
	 */
	if (LivepressConfig.current_screen !== undefined && LivepressConfig.current_screen.base === 'post' ) {
		var live_blogging_tools = new Livepress.Admin.Tools();
		live_blogging_tools.render();
	}
});
