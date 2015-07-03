/*! livepress -v1.2.2
 * http://livepress.com/
 * Copyright (c) 2014 LivePress, Inc.
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
(function ($) {

	var both = function (val) {
		return typeof val === 'object' ? val : { top:val, left:val };
	};

	var $scrollTo = $.scrollTo = function (target, duration, settings) {
		$(window).scrollTo(target, duration, settings);
	};

	$scrollTo.defaults = {
		axis:    'xy',
		duration:parseFloat($.fn.jquery) >= 1.3 ? 0 : 1
	};

	// Returns the element that needs to be animated to scroll the window.
	// Kept for backwards compatibility (specially for localScroll & serialScroll)
	$scrollTo.window = function (scope) {
		return $(window)._scrollable();
	};

	// Hack, hack, hack :)
	// Returns the real elements to scroll (supports window/iframes, documents and regular nodes)
	$.fn._scrollable = function () {
		return this.map(function () {
			var elem = this,
				isWin = !elem.nodeName || $.inArray(elem.nodeName.toLowerCase(), ['iframe', '#document', 'html', 'body']) !== -1;

			if (!isWin) {
				return elem;
			}

			var doc = (elem.contentWindow || elem).document || elem.ownerDocument || elem;

			return $.browser.safari || doc.compatMode === 'BackCompat' ?
				doc.body :
				doc.documentElement;
		});
	};

	$.fn.scrollTo = function (target, duration, settings) {
		if (typeof duration === 'object') {
			settings = duration;
			duration = 0;
		}
		if (typeof settings === 'function') {
			settings = { onAfter:settings };
		}

		if (target === 'max') {
			target = 9e9;
		}

		settings = $.extend({}, $scrollTo.defaults, settings);
		// Speed is still recognized for backwards compatibility
		duration = duration || settings.speed || settings.duration;
		// Make sure the settings are given right
		settings.queue = settings.queue && settings.axis.length > 1;

		if (settings.queue) {
			// Let's keep the overall duration
			duration /= 2;
		}
		settings.offset = both(settings.offset);
		settings.over = both(settings.over);

		return this._scrollable().each(function () {
			var elem = this,
				$elem = $(elem),
				targ = target, toff, attr = {},
				win = $elem.is('html,body');

			var animate = function (callback) {
				$elem.animate(attr, duration, settings.easing, callback && function () {
					callback.call(this, target, settings);
				});
			};

			if ((typeof targ === 'number' || typeof targ === 'string') &&
				// A number will pass the regex
				( /^([\-+]=)?\d+(\.\d+)?(px|%)?$/.test(targ) )) {
				targ = both(targ);
				// We are done
			} else {
				if (typeof targ === 'number' || typeof targ === 'string') {
					// Relative selector, no break!
					targ = $(targ, this);
				}
				// DOMElement / jQuery
				if (targ.is || targ.style) {
					// Get the real position of the target
					toff = (targ = $(targ)).offset();
				}
			}
			$.each(settings.axis.split(''), function (i, axis) {
				var Pos = axis === 'x' ? 'Left' : 'Top',
					pos = Pos.toLowerCase(),
					key = 'scroll' + Pos,
					old = elem[key],
					max = $scrollTo.max(elem, axis);

				if (toff) {// jQuery / DOMElement
					attr[key] = toff[pos] + ( win ? 0 : old - $elem.offset()[pos] );

					// If it's a dom element, reduce the margin
					if (settings.margin) {
						attr[key] -= parseInt(targ.css('margin' + Pos), 10) || 0;
						attr[key] -= parseInt(targ.css('border' + Pos + 'Width'), 10) || 0;
					}

					attr[key] += settings.offset[pos] || 0;

					if (settings.over[pos]) {
						// Scroll to a fraction of its width/height
						attr[key] += targ[axis === 'x' ? 'width' : 'height']() * settings.over[pos];
					}
				} else {
					var val = targ[pos];
					// Handle percentage values
					attr[key] = val.slice && val.slice(-1) === '%' ?
						parseFloat(val) / 100 * max
						: val;
				}

				// Number or 'number'
				if (/^\d+$/.test(attr[key])) {
					// Check the limits
					attr[key] = attr[key] <= 0 ? 0 : Math.min(attr[key], max);
				}

				// Queueing axes
				if (!i && settings.queue) {
					// Don't waste time animating, if there's no need.
					if (old !== attr[key]) {
						// Intermediate animation
						animate(settings.onAfterFirst);
					}
					// Don't animate this axis again in the next iteration.
					delete attr[key];
				}
			});

			animate(settings.onAfter);

		}).end();
	};

	// Max scrolling position, works on quirks mode
	// It only fails (not too badly) on IE, quirks mode.
	$scrollTo.max = function (elem, axis) {
		var Dim = axis === 'x' ? 'Width' : 'Height',
			scroll = 'scroll' + Dim;

		if (!$(elem).is('html,body')) {
			return elem[scroll] - $(elem)[Dim.toLowerCase()]();
		}

		var size = 'client' + Dim,
			html = elem.ownerDocument.documentElement,
			body = elem.ownerDocument.body;

		return Math.max(html[scroll], body[scroll]) - Math.min(html[size], body[size]);

	};

}(jQuery));
(function (jQuery) {

	// Some named colors to work with
	// From Interface by Stefan Petre
	// http://interface.eyecon.ro/

	var colors = {
		aqua:          [0, 255, 255],
		azure:         [240, 255, 255],
		beige:         [245, 245, 220],
		black:         [0, 0, 0],
		blue:          [0, 0, 255],
		brown:         [165, 42, 42],
		cyan:          [0, 255, 255],
		darkblue:      [0, 0, 139],
		darkcyan:      [0, 139, 139],
		darkgrey:      [169, 169, 169],
		darkgreen:     [0, 100, 0],
		darkkhaki:     [189, 183, 107],
		darkmagenta:   [139, 0, 139],
		darkolivegreen:[85, 107, 47],
		darkorange:    [255, 140, 0],
		darkorchid:    [153, 50, 204],
		darkred:       [139, 0, 0],
		darksalmon:    [233, 150, 122],
		darkviolet:    [148, 0, 211],
		fuchsia:       [255, 0, 255],
		gold:          [255, 215, 0],
		green:         [0, 128, 0],
		indigo:        [75, 0, 130],
		khaki:         [240, 230, 140],
		lightblue:     [173, 216, 230],
		lightcyan:     [224, 255, 255],
		lightgreen:    [144, 238, 144],
		lightgrey:     [211, 211, 211],
		lightpink:     [255, 182, 193],
		lightyellow:   [255, 255, 224],
		lime:          [0, 255, 0],
		magenta:       [255, 0, 255],
		maroon:        [128, 0, 0],
		navy:          [0, 0, 128],
		olive:         [128, 128, 0],
		orange:        [255, 165, 0],
		pink:          [255, 192, 203],
		purple:        [128, 0, 128],
		violet:        [128, 0, 128],
		red:           [255, 0, 0],
		silver:        [192, 192, 192],
		white:         [255, 255, 255],
		yellow:        [255, 255, 0]
	};

	// Color Conversion functions from highlightFade
	// By Blair Mitchelmore
	// http://jquery.offput.ca/highlightFade/

	// Parse strings looking for color tuples [255,255,255]
	var getRGB = function (color) {
		var result;

		// Check if we're already dealing with an array of colors
		if (color && color.constructor === Array && color.length === 3) {
			return color;
		}

		// Look for rgb(num,num,num)
		if ((result = /rgb\(\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*\)/.exec(color))) {
			return [parseInt(result[1], 10), parseInt(result[2], 10), parseInt(result[3], 10)];
		}

		// Look for rgb(num%,num%,num%)
		if ((result = /rgb\(\s*([0-9]+(?:\.[0-9]+)?)\%\s*,\s*([0-9]+(?:\.[0-9]+)?)\%\s*,\s*([0-9]+(?:\.[0-9]+)?)\%\s*\)/.exec(color))) {
			return [parseFloat(result[1]) * 2.55, parseFloat(result[2]) * 2.55, parseFloat(result[3]) * 2.55];
		}

		// Look for #a0b1c2
		if ((result = /#([a-fA-F0-9]{2})([a-fA-F0-9]{2})([a-fA-F0-9]{2})/.exec(color))) {
			return [parseInt(result[1], 16), parseInt(result[2], 16), parseInt(result[3], 16)];
		}

		// Look for #fff
		if ((result = /#([a-fA-F0-9])([a-fA-F0-9])([a-fA-F0-9])/.exec(color))) {
			return [parseInt(result[1] + result[1], 16), parseInt(result[2] + result[2], 16), parseInt(result[3] + result[3], 16)];
		}

		// Otherwise, we're most likely dealing with a named color
		return colors[jQuery.trim(color).toLowerCase()];
	};

	var getColor = function (elem, attr) {
		var color;

		do {
			color = jQuery.curCSS(elem, attr);

			// Keep going until we find an element that has color, or we hit the body
			if (color !== '' && color !== 'transparent' || jQuery.nodeName(elem, "body")) {
				break;
			}

			attr = "backgroundColor";
		} while ((elem = elem.parentNode));

		return getRGB(color);
	};

	// We override the animation for all of these color styles
	jQuery.each(['backgroundColor', 'borderBottomColor', 'borderLeftColor', 'borderRightColor', 'borderTopColor', 'color', 'outlineColor'], function (i, attr) {
		jQuery.fx.step[attr] = function (fx) {
			if (fx.start === undefined) {
				fx.start = getColor(fx.elem, attr);
				fx.end = getRGB(fx.end);
			}

			fx.elem.style[attr] = "rgb(" + [
				Math.max(Math.min(parseInt((fx.pos * (fx.end[0] - fx.start[0])) + fx.start[0], 10), 255), 0),
				Math.max(Math.min(parseInt((fx.pos * (fx.end[1] - fx.start[1])) + fx.start[1], 10), 255), 0),
				Math.max(Math.min(parseInt((fx.pos * (fx.end[2] - fx.start[2])) + fx.start[2], 10), 255), 0)
			].join(",") + ")";
		};
	});

}(jQuery));
/*global window, jQuery, console, alert */
(function($) {
	$.gritter = {};

	$.gritter.options = {
		fade_in_speed: 'fast',
		fade_out_speed: 500,
		time: 1000
	};
	var Gritter = {
		fade_in_speed: '',
		fade_out_speed: '',
		time: '',
		_custom_timer: 0,
		_item_count: 0,
		_is_setup: 0,
		_tpl_control: '<div class = "gritter-control"><div class ="gritter-scroll">Scroll to Comment</div><div class ="gritter-settings"></div><div class="gritter-close"></div></div>',
		_tpl_item: '<div id="gritter-item-[[number]]" class="gritter-item-wrapper gritter-item [[item_class]] [[class_name]] [[comment_container_id]]" style="display:none"><div class="gritter-top"></div><div class="gritter-inner"><div class = "gritter-control"><div class ="gritter-scroll">Scroll to Comment</div><div class ="gritter-settings"></div><div class="gritter-close"></div></div>[[image]]<span class="gritter-title">[[username]]</span><div class="lp-date">[[date]]</div><p>[[text]]</p>[[comments]]<div class="bubble-point"></div></div><div class="gritter-bot"></div></div>',
		_tpl_wrap: '<div id="gritter-notice-wrapper"></div>',
		add: function(params) {
			//if (!params.title || ! params.text) {
				/*throw'You need to fill out the first 2 params: "title" and "text"';*/
			//}
			if (!params.title) {
				params.title = "";
			}
			if (!params.text) {
				params.text = "";
			}
			if (!this._is_setup) {
				this._runSetup();
			}
			var user = params.title,
			commentContainerId = params.commentContainerId,
			text = params.text,
			date = params.date,
			image = params.image,
			comments = params.comments || '',
			sticky = params.sticky || false,
			item_class = params.class_name || '',
			time_alive = params.time || '';
			this._verifyWrapper();
			this._item_count+=1;
			var number = this._item_count,
			tmp = this._tpl_item;
			$(['before_open', 'after_open', 'before_close', 'after_close']).each(function(i, val) {
				Gritter['_' + val + '_' + number] = ($.isFunction(params[val])) ? params[val] : function() {};
			});
			this._custom_timer = 0;
			if (time_alive) {
				this._custom_timer = time_alive;
			}
			if (image === undefined) {
				image = '';
			}
			var image_str = (!image) ? '' : '<img src="' + image + '" class="gritter-image" />',
				class_name = (!image) ? 'gritter-without-image' : 'gritter-with-image';
			tmp = this._str_replace(['[[username]]', '[[text]]', '[[date]]', '[[image]]', '[[number]]', '[[class_name]]', '[[item_class]]', '[[comments]]', '[[comment_container_id]]'], [user, text, date, image_str, this._item_count, class_name, item_class, comments, commentContainerId], tmp);
			this['_before_open_' + number]();
			$('#gritter-notice-wrapper').prepend(tmp);
			var item = $('#gritter-item-' + this._item_count);
			var scrollDiv = $(item.find(".gritter-scroll"));

			/* handle scroll to comment in gritter bubble
  * use passed callback if any. Used on pages where dynamic appearing of
  * comments is turned off. See ui-controller.js comment_update function.
  */
			if (jQuery.isFunction(params.scrollToCallback)) {
				scrollDiv.bind('click', params.scrollToCallback);
				if (params.scrollToText) {
					scrollDiv.text(params.scrollToText);
				}
			} else {
				scrollDiv.bind('click', function(e) {
					var div = jQuery(jQuery(e.target).parents(".gritter-item-wrapper")[0]);
					var classList = div.attr('class').split(/\s+/);

					// looking for class with id of comment it refers to
					jQuery.each(classList, function(index, item) {
						var commentId = item.match(/comment\-/);
						if (commentId !== null) {
							jQuery.scrollTo(jQuery("#" + item), 900);
							return;
						}
					});
				});
			}

			item.fadeIn(this.fade_in_speed, function() {
				Gritter['_after_open_' + number]($(this));
			});
			if (!sticky) {
				this._setFadeTimer(item, number);
			}
			$(item).bind('mouseenter mouseleave', function(event) {
				if (event.type === 'mouseenter') {
					if (!sticky) {
						Gritter._restoreItemIfFading($(this), number);
					}
				}

				else {
					if (!sticky) {
						Gritter._setFadeTimer($(this), number);
					}
				}
				Gritter._hoverState($(this), event.type);
			});
			return number;
		},
		_countRemoveWrapper: function(unique_id, e) {
			e.remove();
			this['_after_close_' + unique_id](e);
			if ($('.gritter-item-wrapper').length === 0) {
				$('#gritter-notice-wrapper').remove();
			}
		},
		_fade: function(e, unique_id, params, unbind_events) {
			params = params || {};
			var fade = (typeof(params.fade) !== 'undefined') ? params.fade: true,
				fade_out_speed = params.speed || this.fade_out_speed;
			this['_before_close_' + unique_id](e);
			if (unbind_events) {
				e.unbind('mouseenter mouseleave');
			}
			if (fade) {
				e.animate({	opacity: 0 }, fade_out_speed, function() {
					e.animate({ height: 0 }, 300, function() {
						Gritter._countRemoveWrapper(unique_id, e);
					});
				});
			}
			else {
				this._countRemoveWrapper(unique_id, e);
			}
		},
		_hoverState: function(e, type) {
			if (type === 'mouseenter') {
				e.addClass('hover');
				var control = e.find('.gritter-control');
				control.show();
				e.find('.gritter-close').click(function() {
					var unique_id = e.attr('id').split('-')[2];
					Gritter.removeSpecific(unique_id, {},
					e, true);
				});
			}
			else {
				e.removeClass('hover');
				e.find('.gritter-control').hide();
			}
		},
		removeSpecific: function(unique_id, params, e, unbind_events) {
			if (!e) {
				e = $('#gritter-item-' + unique_id);
			}
			this._fade(e, unique_id, params || {},
			unbind_events);
		},
		_restoreItemIfFading: function(e, unique_id) {
			clearTimeout(this['_int_id_' + unique_id]);
			e.stop().css({
				opacity: ''
			});
		},
		_runSetup: function() {
			var opt;
			for (opt in $.gritter.options) {
				if ($.gritter.options.hasOwnProperty(opt)) {
					this[opt] = $.gritter.options[opt];
				}
			}
			this._is_setup = 1;
		},
		_setFadeTimer: function(e, unique_id) {
			var timer_str = (this._custom_timer) ? this._custom_timer: this.time;
			this['_int_id_' + unique_id] = setTimeout(function() {
				Gritter._fade(e, unique_id);
			},
			timer_str);
		},
		stop: function(params) {
			var before_close = ($.isFunction(params.before_close)) ? params.before_close: function() {};

			var after_close = ($.isFunction(params.after_close)) ? params.after_close: function() {};

			var wrap = $('#gritter-notice-wrapper');
			before_close(wrap);
			wrap.fadeOut(function() {
				$(this).remove();
				after_close();
			});
		},
		_str_replace: function(search, replace, subject, count) {
			var i = 0,
			j = 0,
			temp = '',
			repl = '',
			sl = 0,
			fl = 0,
			f = [].concat(search),
			r = [].concat(replace),
			s = subject,
			ra = r instanceof Array,
			sa = s instanceof Array;
			s = [].concat(s);
			if (count) {
				this.window[count] = 0;
			}
			for (i = 0, sl = s.length; i < sl; i++) {
				if (s[i] === '') {
					continue;
				}
				for (j = 0, fl = f.length; j < fl; j++) {
					temp = s[i] + '';
					repl = ra ? (r[j] !== undefined ? r[j] : '') : r[0];
					s[i] = (temp).split(f[j]).join(repl);
					if (count && s[i] !== temp) {
						this.window[count] += (temp.length - s[i].length) / f[j].length;
					}
				}
			}
			return sa ? s: s[0];
		},
		_verifyWrapper: function() {
			if ($('#gritter-notice-wrapper').length === 0) {
				$('body').append(this._tpl_wrap);
			}
		}
	};
	$.gritter.add = function(params) {
		try {
			return Gritter.add(params || {});
		} catch(e) {
			var err = 'Gritter Error: ' + e;
			if(typeof(console) !== 'undefined' && console.error) {
				console.error(err, params);
			} else {
			   alert(err);
			}
		}
	};
	$.gritter.remove = function(id, params) {
		Gritter.removeSpecific(id, params || {});
	};
	$.gritter.removeAll = function(params) {
		Gritter.stop(params || {});
	};
} (jQuery));


/*global lp_client_strings, LivepressConfig, Livepress, console */
Livepress.Ui = {};

Livepress.Ui.View = function (disable_comments) {
	var self = this;

	// LP Bar
	var $livepress = jQuery('#livepress');
	var $commentsCount = $livepress.find('.lp-comments-count');
	var $updatesCount = $livepress.find('.lp-updates-count');
	var $settingsButton = $livepress.find('.lp-settings-button');
	var $lpBarBox = $livepress.find('.lp-bar');
	var $settingsBox = jQuery('#lp-settings');

	this.set_comment_num = function (count) {
		var oldCount = $commentsCount.html();
		$commentsCount.html(parseInt(count, 10));
		if (oldCount !== count) {
			$commentsCount.parent().animate({color:"#ffff66"}, 200).animate({color:"#ffffff"}, 800);
		}
	};

	this.set_live_updates_num = function (count) {
		$updatesCount.html(parseInt(count, 10));
	};

	// Settings Elements
	//$settingsBox.appendTo('body');

	var $exitButton = $settingsBox.find('.lp-settings-close');
	var $settingsTabs = $settingsBox.find('.lp-tab');
	var $settingsPanes = $settingsBox.find('.lp-pane');

	// Settings controls
	var $expandOptionsButton = $settingsBox.find('.lp-button.lp-expand-options');
	var $soundCheckbox = $settingsBox.find('input[name=lp-setting-sound]');
	var $updatesCheckbox = $settingsBox.find('input[name=lp-setting-updates]');
	var $commentsCheckbox = $settingsBox.find('input[name=lp-setting-comments]');
	var $scrollCheckbox = $settingsBox.find('input[name=lp-setting-scroll]');
	var $updateSettingsButton = $settingsBox.find('.lp-button.lp-update-settings');
	var $optionsExtBox = $settingsBox.find('.lp-options-ext');
	var $optionsShortBox = $settingsBox.find('.lp-settings-short');

	if (disable_comments) {
		$lpBarBox.find(".lp-sprite.comments").hide();
		$commentsCount.parent().hide();
		$commentsCheckbox.parent().hide();
	}

	var window_height = function () {
		var de = document.documentElement;
		return self.innerHeight || ( de && de.clientHeight ) || document.body.clientHeight;
	};

	var window_width = function () {
		var de = document.documentElement;
		return self.innerWidth || ( de && de.clientWidth ) || document.body.clientWidth;
	};

	var showSettingsBox = function () {
		var save_window_width = window_width();
		var save_window_height = window_height();
		var barOffset = $lpBarBox.offset();

		$settingsBox.show( 'blind' );
	};

	var hideSettingsBox = function () {
		$settingsBox.hide( 'blind' );
		$optionsShortBox.removeClass('expanded');
	};

	var toggleSettingsBox = function () {
		return $settingsBox.is(':visible') ? hideSettingsBox() : showSettingsBox();
	};

	$settingsButton.click(function () {
		return toggleSettingsBox();
	});
	$exitButton.click(function () {
		return hideSettingsBox();
	});

	var control = function (initial, $checkbox, fOn, fOff) {
		$checkbox.prop('checked', initial).change(function () {
			return $checkbox.is(':checked') ? fOn(1) : fOff(1);
		});
		return initial ? fOn() : fOff();
	};

	this.live_control = function (init, fOn, fOff) {
		control(init, $updatesCheckbox, fOn, fOff);
	};

	this.follow_comments_control = function (init, fOn, fOff) {
		control(init, $commentsCheckbox, fOn, fOff);
	};

	this.scroll_control = function (init, fOn, fOff) {
		control(init, $scrollCheckbox, fOn, fOff);
	};

	// Google Reader handler
	this.add_link_to_greader = function (link) {
		$settingsBox.find('.lp-greader-link').attr("href", link);
	};

	// Im handler
	this.subscribeIm = function (callback) {
		$settingsBox.find('.lp-subscribe .lp-button').click(function () {
			var imType = $settingsBox.find('[name=lp-im-type]').val();
			var userName = $settingsBox.find('[name=lp-im-username]').val();
			callback(userName, imType);
		});
	};

	this.imFeedbackSpin = function (on) {
		$settingsBox.find('.lp-subscribe-im-spin')[ on ? 'show' : 'hide']();
	};

	this.handleImFeedback = function (response, username) {
		var messages = {
			'INVALID_JID':           lp_client_strings.no_google_acct,
			'NOT_IN_ROSTER':         lp_client_strings.no_acct_in_jabber,
			'NOT_AUTHORIZED':        lp_client_strings.username_not_auth,
			'AUTHORIZED':            lp_client_strings.username_authorized,
			'INTERNAL_SERVER_ERROR': lp_client_strings.livepress_err_retry,
			'AUTHORIZATION_SENT':    lp_client_strings.auth_request_sent
		};

		var message = messages[response];

		if (typeof(message) !== 'undefined') {
			message = message.replace("[USERNAME]", username);
		} else {
			message = messages.INTERNAL_SERVER_ERROR;
		}

		if (message.length > 0) {
			this.imFeedbackSpin(false);
			$settingsBox.find('.lp-im-subscription-message').html(message).show();
		}

		//var reset_func = function() {jQuery('#im_following_message').hide();
		//jQuery('#im_following').show(); };
		//setTimeout(reset_func, 5000);
	};

	//
	// Connection status
	//
	var $updatesStatus = $settingsBox.find('.live-updates-status');
	var $counters = $lpBarBox.find('.lp-counter');

	this.connected = function () {
		$updatesStatus.text('ON');
		$counters.removeClass('lp-off').addClass('lp-on');
		$lpBarBox.find('.lp-logo').attr('title', 'Connected');
	};

	this.disconnected = function () {
		$updatesStatus.text('OFF');
		$counters.removeClass('lp-on').addClass('lp-off');
		$lpBarBox.find('.lp-logo').attr('title', 'Not Connected');
	};

	//
	// Live notifications
	//

	var update_gritter_settings_click = function () {
		jQuery('.gritter-settings').click(function () {
			showSettingsBox();
		});
	};

	this.comment_alert = function (options, date) {
		console.log('Comment alert', options);
		var container = jQuery("<div>");
		var dateEl = jQuery("<abbr>").attr("class", "timeago").attr("title", date).text(date.replace(/Z/, " UTC"));
		container.append(dateEl);
		var defaults = {
			class_name:'comment',
			date:      container.html(),
			time:      7000
		};

		jQuery.gritter.add(jQuery.extend({}, defaults, options));
		jQuery(".gritter-comments .add-comment").click(function () {
			jQuery().scrollTo('#respond, #commentform, #submit', 900);
		});
		update_gritter_settings_click();
		jQuery("abbr.livepress-timestamp").timeago().attr( 'title', '' );
	};
};


Livepress.Ui.UpdateBoxView = function (homepage_mode) {
	if (typeof(homepage_mode) === "undefined") {
		homepage_mode = true;
	}

	var update_box_html = [
		'<div class="update-box-content">',
		'<div class="lp-update-count">',
		'<strong class="lp-update-num">0</strong>',
		'<strong class="lp-update-new-update"> ' + lp_client_strings.new_updates + '. </strong>',
		'<a href="javascript:location.reload();" class="lp-refresher">' + lp_client_strings.refresh + '</a> ' + lp_client_strings.to_see + ' <span class="lp-update-it-them">' + lp_client_strings.them + '</span>.',
		'</div>',
		'<div class="lp-balloon">',
		'<img class="lp-close-button" title="Close" />',
		'<ul class="lp-new-posts"></ul>',
		'<a class="lp-more-link" href="javascript:location.reload();">+ more</a>',
		'<div class="lp-balloon-tail">&nbsp;</div>',
		'</div>',
		'</div>',
		'<div class="clear"></div>'
	].join("");

	var update_box_classes = ".livepress lp-update-container";

	var $update_box = jQuery('#lp-update-box');
	$update_box.addClass(update_box_classes);
	$update_box.append(update_box_html);

	var $balloon = $update_box.find('.lp-balloon');

	var $new_posts_list = $update_box.find('.lp-new-posts');
	var $more_link = $update_box.find('.lp-more-link');
	var $update_num = $update_box.find('.lp-update-num');
	var $new_update_phrase = $update_box.find('.lp-update-new-update');
	var $it_them = $update_box.find('.lp-update-it-them');
	var $closeButton = $update_box.find('.lp-close-button');

	var counter = 0;

	$closeButton.click(function () {
		$balloon.fadeOut();
	});

	$closeButton.attr('src', LivepressConfig.lp_plugin_url + '/img/lp-settings-close.png');

	function add_to_update_list (li_content) {
		var item = [
			'<li style="display:none;">',
			li_content,
			'</li>'
		].join('');

		counter += 1;

		if (counter > 1) {
			$new_update_phrase.html(" new updates.");
			$it_them.html("them");
		} else {
			$new_update_phrase.html(" new update.");
			$it_them.html("it");
		}

		if (counter > 7) {
			$more_link.slideDown(300);
		} else {
			$new_posts_list.append(item);
			var $item = $new_posts_list.find('li:last');
			$item.show();

			// TODO: make li items slideDown and remain with the bullet
//        if (counter == 1) {
//          $item.show();
//        } else {
//          $item.slideDown(300);
//        }
		}
		$update_num.text(counter);

		if (!$update_box.is(':visible')) {
			$update_box.slideDown(600);
			$update_box.css('display', 'inline-block');
		}
		jQuery('abbr.livepress-timestamp').timeago().attr( 'title', '' );
	}

	this.reposition_balloon = function () {
		$balloon.css('margin-top', -($balloon.height() + 60));
	};

	this.new_post = function (title, link, author, date) {
		if(date===undefined) {
			return;
		}
		var container = jQuery("<div>");
		var dateEl = jQuery("<abbr>").attr("class", "timeago").attr("title", date).text(date.replace(/Z/, " UTC"));
		var linkEl = jQuery("<a></a>").attr("href", link).text('Update: ' + title);
		container.append(dateEl).append(linkEl);
		add_to_update_list(container.html());
		this.reposition_balloon();
	};

	this.post_update = function (content, date) {
		var bestLen = 25, maxLen = 28;
		var container = jQuery("<div>");
		var dateEl = jQuery("<abbr>").attr("class", "timeago").attr("title", date).text(date.replace(/Z/, " UTC"));
		var cutPos = content.indexOf(' ', bestLen);
		if (cutPos === -1) {
			cutPos = content.length;
		}
		if (cutPos > maxLen) {
			cutPos = content.substr(0, cutPos).lastIndexOf(' ');
		}
		if (cutPos === -1 || cutPos > maxLen) {
			cutPos = maxLen;
		}
		var cut = content.substr(0, cutPos) + (cutPos < content.length ? "&hellip;" : "");
		var update = ' Update: <strong>' + cut + '</strong>';
		container.append(dateEl).append(update);

		add_to_update_list(container.html());
	};

	this.clear = function () {
		$more_link.hide();
		$new_posts_list.html("");
		counter = 0;
		$update_num.text("0");
		if ($update_box.is(':visible')) {
			$update_box.slideUp(600);
		}
	};
};

Livepress.Ui.UpdateView = function ($element, post_link, disable_comment) {
	var update = {};
	var $update_ui;
	var is_sticky = $element.hasClass("pinned-first-live-update");

	var getTitle = function() {
		return $element.find( '.livepress-update-header' ).text();
	};

	var excerpt = function (limit) {
		if ( is_sticky ){
			return LivepressConfig.post_title;
		} else {
			var i;
			var filtered = $element.clone();
			filtered.find(".livepress-meta").remove();
			filtered.find(".live-update-authors").remove();
			filtered.find(".live-update-livetags").remove();
			var text = filtered.text();
			text = filtered.text().replace(/\n/g, "");
			text = text.replace(/\s+/, ' ');

			var spaces = []; // Array of indices to space characters
			spaces.push(0);
			for (i = 1; i < text.length; i += 1) {
				if (text.charAt(i) === ' ') {
					spaces.push(i);
				}
			}

			spaces.push(text.length);

			if (text.length > limit) {
				i = 0;
				var rbound = limit;
				// looking for last space index within length limit
				while ((spaces[i] < limit) && (i < spaces.length - 1)) {
					rbound = spaces[i];
					i += 1;
				}

				text = text.substring(0, rbound) + "\u2026";
			}

			return text.trim();
		}

};

	var share_container = jQuery("<div>").addClass("lp-share");
	if ($update_ui === undefined) {
		update.element = $element;
		update.id = $element.attr('id');

		var metainfo = '';
		update.title = getTitle();

		update.shortExcerpt = excerpt(100);
		update.longExcerpt = excerpt(1000) + " ";


		// TODO: Make this customizable
		//if ( 0 < jQuery( '#' + $element.attr('id') + ' .live-update-authors').length ) {
		// var authors = [];
		// jQuery('#' + $element.attr('id') + ' .live-update-authors .live-update-author .live-author-name').each( function(){
		// authors.push( jQuery(this).text() );
		// });
		//	metainfo += lp_client_strings.by + ' ' + authors.join(', ');
		//}

		// TODO: Make this customizable
		// if ( 0 < jQuery( '#' + $element.attr('id') + ' .live-update-livetags').length ) {
		//	var tags = [];
		//	jQuery('#' + $element.attr('id') + ' .live-update-livetags .live-update-livetag').each( function(){
		//		tags.push( '%23' + jQuery(this).text() );
		//	});
		//	metainfo += ' ' + tags.join(' ');
		// }

		update.shortExcerpt += metainfo;
		update.longExcerpt += metainfo;
	}

	update.link = function() {
		if( is_sticky ){
			return LivepressConfig.post_url;
		} else {
			return Livepress.getUpdatePermalink( update.id );
		}
	};
	update.shortLink = function() {
		if( is_sticky ){
			return LivepressConfig.post_url;
		} else {
			return Livepress.getUpdateShortlink( update.id );
		}
	};

	// Get shortened URL, when done set up the sharing UI
    var types = ["facebook", "twitter", "hyperlink"],
        buttons = Livepress.Ui.ReactButtons( update, types );

    share_container.append( buttons );
    update.element.append( share_container );
    $update_ui = $element.find( '.lp-pre-update-ui' );

    if ($element.get( 0 ) === jQuery( '.livepress-update' ).get( 0 ) ) {
        $update_ui.addClass( 'lp-first-update' );
    }
    $element.addClass( 'ui-container' );
};

Livepress.Ui.ReactButton = function (type, update) {
	var pub = {};
	var priv = {};
	priv.btnDiv = jQuery("<div>").addClass("lp-pu-ui-feature lp-pu-" + type);

	pub.buttonFor = function (type, update) {
		var button = {};
		button.link = update.link;
		button.type = type;
		//button.update_id = update.id;
		priv[type + "Button"](button);
		return button.div;
	};

	priv.constructButtonMarkup = function () {
		// sample markup created:
		// <span class="icon-twitter lp-pu-ico"></span>
		var btnIcon = jQuery("<span>").addClass("lp-icon-" + type + " lp-pu-ico");
		return priv.btnDiv.append(btnIcon);
	};

	priv.twitterButton = function (button) {
		button.div = priv.constructButtonMarkup();
		button.div.click(function () {

			var left = ( screen.width / 2 ) - 300,
				top = ( screen.height / 2 ) - 175,
				options = "width=600,height=350,location=yes,,status=yes,top=" + top + ", left=" + left,
				twitterLink = update.shortLink();

				var shortExcerpt = ( 3 > update.shortExcerpt.length ) ? '' : update.shortExcerpt.replace(/#/g,'%23') + ' ',
					updateTitle = update.title.trim(),
					description = ( '' === updateTitle ? shortExcerpt :  updateTitle + ' ' );

				// Did we get the shortened link or only a promise?
				if ( 'string' === typeof twitterLink ) {
					window.open( 'https://twitter.com/intent/tweet?text=' + description + twitterLink, "Twitter", options );
				} else {
					twitterLink
						.done( function( data ){
							window.open( 'https://twitter.com/intent/tweet?text=' + description + ( ( 'undefined' !== typeof data.data ) ? data.data.shortlink : Livepress.getUpdatePermalink( update.id ) ), "Twitter", options );
							var re = /livepress-update-([0-9]+)/,
								update_id = re.exec(update.id)[1];
							if ( 'undefined' !== typeof data.data ) {
								Livepress.updateShortlinksCache[update_id] = data.data.shortlink;
							}
						})
						// Fallback to full URL
						.fail( function() {
							window.open( 'https://twitter.com/intent/tweet?text=' + description + Livepress.getUpdatePermalink( update.id ), "Twitter", options );
						});
				}
			});
	};

	priv.facebookButton = function (button) {
		button.div = priv.constructButtonMarkup();
		button.div.click(function () {
			// Set the Facebook link to the actual url since the app has to
			// be tied to a specific domain:
			var u = Livepress.getUpdatePermalink(update.id);
			var height = 436;
			var width = 626;
			var left = (screen.width - width) / 2;
			var top = (screen.height - height) / 2;
			var windowParams = 'toolbar=0, status=0, width=' + width + ', height=' + height + ', left=' + left + ', top=' + top;

			if ( "undefined" === typeof LivepressConfig.facebook_app_id ||
					'' === LivepressConfig.facebook_app_id ){
				window.open( 'http://www.facebook.com/sharer.php?u=' +
										encodeURIComponent(u) , ' sharer', windowParams );
			} else {
				window.open(
					'https://www.facebook.com/dialog/share_open_graph?' +
						'app_id=' + LivepressConfig.facebook_app_id +
						'&display=popup' +
						'&action_type=og.likes' +
						'&action_properties=' + encodeURIComponent('{"object":"' + u + '" }') +
						'&redirect_uri=' + encodeURIComponent(u)
				);
			}

			return false;
		});
	};

	priv.hyperlinkButton = function (button) {
		button.div = priv.constructButtonMarkup();
		button.div.click(function () {
			if ( 0 === jQuery("#" + update.id + "-share input.permalink").length ){
				var d = jQuery('<div/>'),
						i = jQuery('<input/>'),
						u = update.shortLink(),
						message = jQuery('<span/>');

				// Did we really get a shortLink? If not, use the full link
				if ( 'string' !== typeof u ) {
					i.hide();
					u
						.done( function( data ) {
							console.log( data.data.shortlink );
							i.show();
							i.attr( 'value', '' + ( 'undefined' !== typeof data.data ) ? data.data.shortlink : Livepress.getUpdatePermalink( update.id ) );
						})
						.fail( function() {
							i.show();
						});
				} else {
					i.attr('value', u);
				}

				// Input to copy the permalink url from:

				i.addClass("permalink");
				i.click(function(){
					i.select();
				});

				message.text(lp_client_strings.copy_permalink);

				d.addClass('lp-permalink-meta');
				d.prepend(message);
				d.prepend(i);
				jQuery('#' + update.id + ' .lp-pre-update-ui').append(d);
				i.select();
			} else {
				jQuery("#" + update.id + "-share").find(".lp-permalink-meta").remove();
			}
			return false;
		});
	};

	return pub.buttonFor(type, update);
};

Livepress.Ui.ReactButtons = function (update, types) {
	var container = jQuery("<div>").addClass("lp-pre-update-ui").attr('id', update.id + "-share");
	jQuery.each(types, function (i, v) {
		var button = Livepress.Ui.ReactButton(v, update);
		container.append(button);
	});
	return container;
};

/**
 * Global object that controls scrolling not directly requested by user.
 * If focus is on an element that has the class lp-no-scroll scroll will
 * be disabled.
 */
(function () {
	var Scroller = function () {
		var temporary_disabled = false;

		this.settings_enabled = false;

		jQuery('input:text, textarea')
			.on('focusin', function (e) {
				temporary_disabled = true;
			})
			.on('focusout', function (e) {
				temporary_disabled = false;
			});

		this.shouldScroll = function () {
			return this.settings_enabled && !temporary_disabled;
		};
	};

	Livepress.Scroll = new Scroller();
}());
/*jslint plusplus:true, vars:true */

Livepress.DOMManipulator = function (containerId, custom_background_color) {
	this.custom_background_color = custom_background_color;

	if (typeof containerId === "string") {
		this.containerJQueryElement = jQuery(containerId);
	} else {
		this.containerJQueryElement = containerId;
	}
	this.containerElement = this.containerJQueryElement[0];
	this.cleaned_ws = false;
};

Livepress.DOMManipulator.prototype = {
	debug: false,

	log: function () {
		if (this.debug) {
			console.log.apply(console, arguments);
		}
	},

	/**
	 *
	 * @param operations
	 * @param options     Can have two options - effects_display, custom_scroll_class
	 */
	update: function (operations, options) {
		options = options || {};

		this.log('Livepress.DOMManipulator.update begin.');
		this.clean_updates();

		this.apply_changes(operations, options);

		// Clean the updates after 1,5s
		var self = this;
		setTimeout(function () {
			self.clean_updates();
		}, 1500);

		this.log('Livepress.DOMManipulator.update end.');
	},

	selector: function (partial) {
		return this.containerJQueryElement.find(partial);
	},

	selectors: function () {
		if (arguments.length === 0) {
			throw 'The method expects arguments.';
		}
		var selector = jQuery.map(arguments, function (partial) {
			return partial;
		});
		return this.containerJQueryElement.find(selector.join(','));
	},

	clean_whitespaces: function () {
        return;
		/* if (this.cleaned_ws) {
			return false;
		}
		this.cleaned_ws = true;

		// Clean whitespace textnodes out of DOM
		var content = this.containerElement;
		this.clean_children_ws(content);

		return true; */
	},

	block_elements: function () {
		return { /* Block elements */
			"address":    1,
			"blockquote": 1,
			"center":     1,
			"dir":        1,
			"dl":         1,
			"fieldset":   1,
			"form":       1,
			"h1":         1,
			"h2":         1,
			"h3":         1,
			"h4":         1,
			"h5":         1,
			"h6":         1,
			"hr":         1,
			"isindex":    1,
			"menu":       1,
			"noframes":   1,
			"noscript":   1,
			"ol":         1,
			"p":          1,
			"pre":        1,
			"table":      1,
			"ul":         1,
			"div":        1,
			"math":       1,
			"caption":    1,
			"colgroup":   1,
			"col":        1,

			/* Considered block elements, because they may contain block elements */
			"dd":         1,
			"dt":         1,
			"frameset":   1,
			"li":         1,
			"tbody":      1,
			"td":         1,
			"thead":      1,
			"tfoot":      1,
			"th":         1,
			"tr":         1
		};
	},

	is_block_element: function (tagName) {
		if (typeof tagName === 'string') {
			return this.block_elements().hasOwnProperty(tagName.toLowerCase());
		}
		return false;
	},

	remove_whitespace: function (node) {
		var remove = false,
			parent = node.parentNode,
			prevSibling;

		if (node === parent.firstChild || node === parent.lastChild) {
			remove = true;
		} else {
			prevSibling = node.previousSibling;
			if (prevSibling !== null && prevSibling.nodeType === 1 && this.is_block_element(prevSibling.tagName)) {
				remove = true;
			}
		}

		return remove;
	},

	clean_children_ws: function (parent) {
		var remove, child;
		for (remove = false, child = parent.firstChild; child !== null; null) {
			if (child.nodeType === 3) {
				if (/^\s*$/.test(child.nodeValue) && this.remove_whitespace(child)) {
					remove = true;
				}
			} else {
				this.clean_children_ws(child);
			}

			if (remove) {
				var wsChild = child;
				child = child.nextSibling;
				parent.removeChild(wsChild);
				remove = false;
			} else {
				child = child.nextSibling;
			}
		}
	},

	clean_updates: function () {
		this.log('DOMManipulator clean_updates.');
		// Replace the <span>...<ins ...></span> by the content of <ins ...>
		jQuery.each(this.selector('span.oortle-diff-text-updated'), function () {
			var replaceWith;
			if (this.childNodes.length > 1) {
				replaceWith = this.childNodes[1];
			} else {
				replaceWith = this.childNodes[0];
			}
			if (replaceWith.nodeType !== 8) { // Comment node
				replaceWith = replaceWith.childNodes[0];
			}
			this.parentNode.replaceChild(replaceWith, this);
		});

		this.selector('.oortle-diff-changed').removeClass('oortle-diff-changed');
		this.selector('.oortle-diff-inserted').removeClass('oortle-diff-inserted');
		this.selector('.oortle-diff-inserted-block').removeClass('oortle-diff-inserted-block');
		this.selector('.oortle-diff-removed').remove();
		this.selector('.oortle-diff-removed-block').remove();
	},

	process_twitter: function(el, html) {
		if ( html.match( /<blockquote[^>]*twitter-tweet/i )) {
			if ( 'twttr' in window ) {
				try {
					window.twttr.events.bind(
						'loaded',
						function (event) {
							jQuery( document ).trigger( 'live_post_update' );
						}
					);
					console.log('loading twitter');
					window.twttr.widgets.load(el);
				} catch ( e ) {}
			} else {
				try {
					if(!document.getElementById('twitter-wjs')) {
						var wg = document.createElement('script');
						wg.src = "https://platform.twitter.com/widgets.js";
						wg.id = "twitter-wjs";
						document.getElementsByTagName('head')[0].appendChild(wg);
					}
				} catch(e) {}
			}
		}
	},

	apply_changes: function (changes, options) {
		var $ = jQuery;
		var display_with_effects = options.effects_display || false,
			registers = [],
			i;

		this.clean_whitespaces();

		for (i = 0; i < changes.length; i++) {
			this.log('apply changes i=', i, ' changes.length = ', changes.length);
			var change = changes[i];
			this.log('change[i] = ', change[i]);
			var parts, node, parent, container, childIndex, el, childRef, parent_path, content, x, inserted;
			switch (change[0]) {

				// ['add_class', 'element xpath', 'class name changed']
				case 'add_class':
					try {
						parts = this.get_path_parts(change[1]);
						node = this.node_at_path(parts);
						this.add_class(node, change[2]);

					} catch (e) {
						this.log('Exception on add_class: ', e);
					}
					break;

				// ['set_attr',  'element xpath', 'attr name', 'attr value']
				case 'set_attr':
					try {
						parts = this.get_path_parts(change[1]);
						node = this.node_at_path(parts);
						this.set_attr(node, change[2], change[3]);
					} catch (esa) {
						this.log('Exception on set_attr: ', esa);
					}
					break;

				// ['del_attr',  'element xpath', 'attr name']
				case 'del_attr':
					try {
						parts = this.get_path_parts(change[1]);
						node = this.node_at_path(parts);
						this.del_attr(node, change[2]);
					} catch (eda) {
						this.log('Exception on del_attr: ', eda);
					}
					break;

				// ['set_text',  'element xpath', '<span><del>old</del><ins>new</ins></span>']
				case 'set_text':
					try {
						this.set_text(change[1], change[2]);
					} catch (est) {
						this.log('Exception on set_text: ', est);
					}
					break;

				// ['del_node',  'element xpath']
				// working fine with path via #elId
				case 'del_node':
					try {
						parts = this.get_path_parts(change[1]);
						node = this.node_at_path(parts);

						if (node.nodeType === 3) { // TextNode
							parent = node.parentNode;
							for (x = 0; x < parent.childNodes.length; x++) {
								if (parent.childNodes[x] === node) {
									container = parent.ownerDocument.createElement('span');
									container.appendChild(node);
									container.className = 'oortle-diff-removed';
									break;
								}
							}
							if (x < parent.childNodes.length) {
								parent.insertBefore(container, parent.childNodes[x]);
							} else {
								parent.appendChild(container);
							}
						} else if (node.nodeType === 8) { // CommentNode
							node.parentNode.removeChild(node);
						} else {
							this.add_class(node, 'oortle-diff-removed');
						}
					} catch (edn) {
						this.log('Exception on del_node: ', edn);
					}

					break;

				// ['push_node', 'element xpath', reg index ]
				case 'push_node':
					try {
						parts = this.get_path_parts(change[1]);
						node = this.node_at_path(parts);

						if (node !== null) {
							var parentNode = node.parentNode;

							this.log('push_node: parentNode = ', parentNode, ', node = ', node);

							registers[change[2]] = parentNode.removeChild(node);
							$( registers[change[2]] ).addClass( 'oortle-diff-inserted' );
						}
					} catch (epn) {
						this.log('Exception on push_node: ', epn);
					}

					break;

				// ['pop_node',  'element xpath', reg index ]
				case 'pop_node':
					try {
						parts = this.get_path_parts(change[1]);
						childIndex = this.get_child_index(parts);
						parent = this.node_at_path(this.get_parent_path(parts));

						if (childIndex > -1 && parent !== null) {
							el = registers[change[2]];
							childRef = parent.childNodes.length <= childIndex ? null : parent.childNodes[childIndex];

							this.log("pop_node", el, 'from register', change[2], 'before element', childRef, 'on index ', childIndex, ' on parent ', parent);
							inserted = parent.insertBefore(el, childRef);
							$( inserted ).addClass( 'oortle-diff-inserted' );
						}
					} catch (epon) {
						this.log('Exception on pop_node: ', epon);
					}

					break;

				// ['ins_node',  'element xpath', content]
				case 'ins_node':
					try {
						parts = this.get_path_parts(change[1]);
						childIndex = this.get_child_index(parts);
						parent_path = this.get_parent_path(parts);
						parent = this.node_at_path(parent_path);
						this.log('ins_node: childIndex = ', childIndex, ', parent = ', parent);

						if (childIndex > -1 && parent !== null) {
							el = document.createElement('span');
							el.innerHTML = change[2];
							content = el.childNodes[0];
                            // Suppress duplicate insert
							if(content.id==="" || document.getElementById(content.id)===null) {
                                this.process_twitter( content, change[2] );
								childRef = parent.childNodes.length <= childIndex ? null : parent.childNodes[childIndex];
								inserted = parent.insertBefore(content, childRef);
								var $inserted = $( inserted );
								$inserted.addClass( 'oortle-diff-inserted' );
								// If the update contains live tags, add the tag ids to the update data
								var $livetags = $( content ).find( 'div.live-update-livetags' );
								if ( ( "undefined" !== typeof $livetags )  && 0 !== $livetags.length ) {
									this.addLiveTagsToUpdate( $inserted, $livetags );

								}
								this.filterUpdate( $inserted, $livetags );
							}
						}
					} catch (ein1) {
						this.log('Exception on ins_node: ', ein1);
                    }
					break;

                // ['append_child', 'parent xpath', content]
                // instead of "insertBefore", "appendChild" on found element called
                case 'append_child':
                    try {
                        // parent is passed path
						parent_path = this.get_path_parts(change[1]);
						parent = this.node_at_path(parent_path);
						if (parent !== null) {
							el = document.createElement('span');
							el.innerHTML = change[2];
							content = el.childNodes[0];
                            // Suppress duplicate append
							if(content.id!=="" && document.getElementById(content.id)!==null) {
                                this.process_twitter( content, change[2] );
								inserted = parent.appendChild(content);
								$( inserted ).addClass( 'oortle-diff-inserted' );
							}
						}
                    } catch (ein1) {
                        this.log('Exception on append_child: ', ein1);
                    }
                    break;

                // ['replace_node', 'node xpath', new_content]
                case 'replace_node':
                    try {
						parts = this.get_path_parts(change[1]);
						node = this.node_at_path(parts);
                        parent = node.parentNode;

						el = document.createElement('span');

						el.innerHTML = change[2];
						content = el.childNodes[0];

                        // suppress duplicates
                        var lpg = $(content).data("lpg");
                        if (lpg!=="" && lpg!==null && lpg<=$(node).data("lpg")) {
                            // duplicate detected, skip silently
                        } else {
                            this.process_twitter( content, change[2] );
                            this.add_class(content, 'oortle-diff-changed');
                            if ( $( node ).hasClass( 'pinned-first-live-update' ) ) {
                              this.add_class( content, 'pinned-first-live-update' );
                              setTimeout( this.scrollToPinnedHeader, 500 );
                            }
                            parent.insertBefore(content, node);

                            // FIXME: call just del_node there
                            if (node.nodeType === 3) { // TextNode
                                for (x = 0; x < parent.childNodes.length; x++) {
                                    if (parent.childNodes[x] === node) {
                                        container = parent.ownerDocument.createElement('span');
                                        container.appendChild(node);
                                        container.className = 'oortle-diff-removed';
                                        break;
                                    }
                                }
                                if (x < parent.childNodes.length) {
                                    parent.insertBefore(container, parent.childNodes[x]);
                                } else {
                                    parent.appendChild(container);
                                }
                            } else if (node.nodeType === 8) { // CommentNode
                                node.parentNode.removeChild(node);
                            } else {
                                this.add_class(node, 'oortle-diff-removed');
                            }
                        }
                    } catch (ein1) {
                        this.log('Exception on append_child: ', ein1);
                    }
                    break;

				default:
					this.log('Operation not implemented yet.');
					throw 'Operation not implemented yet.';
			}

			this.log('i=', i, ' container: ', this.containerElement.childNodes, ' -- registers: ', registers);
		}

		try {
			this.display(display_with_effects);
		} catch (ein2) {
			this.log('Exception on display: ', ein2);
		}

		try {
			if (Livepress.Scroll.shouldScroll()) {
				var scroll_class = (options.custom_scroll_class === undefined) ?
					'.oortle-diff-inserted-block, .oortle-diff-changed, .oortle-diff-inserted' :
					options.custom_scroll_class;
				jQuery.scrollTo(scroll_class, 900, {axis: 'y', offset: -30 });
			}
		} catch (ein) {
			this.log('Exception on scroll ', ein);
		}

		this.log('end apply_changes.');
	},

	scrollToPinnedHeader: function() {
		if ( Livepress.Scroll.shouldScroll() ) {
			jQuery.scrollTo( '.pinned-first-live-update', 900, {axis: 'y', offset: -30 } );
		}
	},

	/**
	 * Filer the update - hide if live tag filtering is active and update not in tag(s)
	 */
	filterUpdate: function( $inserted, $livetags ) {
		// If the livetags are not in the filtered tags, hide the update
		var target,
			theTags,
			$tagcontrol = jQuery( '.live-update-tag-control' ),
			$activelivetags = $tagcontrol.find( '.live-update-tagcontrol.active' );

		if ( 0 !== $activelivetags.length && 0 === $livetags.length ) {
			$inserted.hide().removeClass( 'oortle-diff-inserted' );
			return;
		}

		// Any active tags
		if ( 0 !== $activelivetags.length ){
			var inFilteredList = false,
				$insertedtags  = $livetags.find( '.live-update-livetag' );

			jQuery.each( $insertedtags, function( index, tag ) {
				console.log( tag );
			});
			// iterate thru the update tags, checking if any match any active tag
			jQuery.each( $insertedtags, function( index, tag ) {
				target = jQuery( tag ).attr( 'class' );
				target = target.replace( /live-update-livetag live-update-livetag-/gi, '' );
				target = 'live-update-livetag-' + target.toLowerCase().replace( / /g, '-' );
				target = '.live-update-tagcontrol.active[data-tagclass="' + target + '"]';
				theTags =  $tagcontrol.find( target );
				if ( 0 !== theTags.length ) {
					inFilteredList = true;
				}
			});
			if ( ! inFilteredList ) {
				$inserted.hide().removeClass( 'oortle-diff-inserted' );
			}
		}
	},

	/**
	 * When the live update contains tags, add these to the tag control bar
	 */
	addLiveTagsToUpdate: function( $inserted, $livetags ) {
		var SELF = this, tagSpan, tagclass, $classincontrol, $livepress = jQuery( '#livepress' ),
			theTags = $livetags.find( '.live-update-livetag' ),
			$lpliveupdates = $livetags.parent().parent(),
			$livetagcontrol = $livepress.find( '.live-update-tag-control' );

		// Add the live tag control bar if missing
		if ( 0 === $livetagcontrol.length ) {
			this.addLiveTagControlBar();
		}

		// Parse the tags in the update, adding to the live tag control bar
		theTags.each( function() {
			var livetag = jQuery( this ).attr( 'class' );

			livetag = livetag.replace( /live-update-livetag live-update-livetag-/gi, '' );

			tagclass = 'live-update-livetag-' + livetag.toLowerCase().replace( / /g, '-' );
			$inserted.addClass( tagclass );
			// Add the control class, if missing
			SELF.addLiveTagToControls( livetag );
		});
	},

	addLiveTagToControls: function( livetag ) {
		var tagSpan, $livepress = jQuery( '#livepress' ),
			$livetagcontrol = $livepress.find( '.live-update-tag-control' ),
			$classincontrol = $livetagcontrol.find( '[data-tagclass="live-update-livetag-' + livetag.toLowerCase().replace(/ /g, '-') + '"]' );
			if ( 0 === $classincontrol.length ){
				tagSpan = '<span class="live-update-tagcontrol" data-tagclass="live-update-livetag-' + livetag.toLowerCase().replace(/ /g, '-') + '">' + livetag + '</span>';
				$livetagcontrol.append( tagSpan );
			}
	},

	addLiveTagControlBar: function() {
		var $livepress = jQuery( '#livepress' ),
			$livetagcontrol = $livepress.find( '.live-update-tag-control' );

			$livepress.append( '<div class="live-update-tag-control"><span class="live-update-tag-title">' + lp_client_strings.filter_by_tag + '</span></div>' );
			$livetagcontrol = $livepress.find( '.live-update-tag-control' );
			// Activate handlers after inserting bar
			this.addLiveTagHandlers( $livetagcontrol );
	},

	addLiveTagHandlers: function( $livetagcontrol ) {
		var self = this,
			$lpcontent = jQuery( '.livepress_content' );

		$livetagcontrol.on( 'click', '.live-update-tagcontrol', function() {
			var $this = jQuery( this );

				$this.toggleClass( 'active' );
				self.filterUpdateListbyLiveTag( $livetagcontrol, $lpcontent );
		} );
	},

	filterUpdateListbyLiveTag: function( $livetagcontrol, $lpcontent ) {
		var activeClass,
			$activeLiveTags = $livetagcontrol.find( '.live-update-tagcontrol.active' );

			// If no tags are selected, show all updates
			if ( 0 === $activeLiveTags.length ) {
				$lpcontent.find( '.livepress-update' ).show();
				return;
			}

			// Hide all updates
			$lpcontent.find( '.livepress-update' ).hide();

			// Show updates matching active live tags
			jQuery.each( $activeLiveTags, function( index, tag ) {
				activeClass = '.' + jQuery( tag ).data( 'tagclass' );
				$lpcontent.find( activeClass ).show();
			});
	},

	colorForOperation: function (element) {
		if (element.length === 0) {
			return false;
		}
		var colors = {
			'oortle-diff-inserted':       LivepressConfig.oortle_diff_inserted,
			'oortle-diff-changed':        LivepressConfig.oortle_diff_changed,
			'oortle-diff-inserted-block': LivepressConfig.oortle_diff_inserted_block,
			'oortle-diff-removed-block':  LivepressConfig.oortle_diff_removed_block,
			'oortle-diff-removed':        LivepressConfig.oortle_diff_removed
		};

		var color_hex = "#fff";
		jQuery.each(colors, function (klass, hex) {
			if (element.hasClass(klass)) {
				color_hex = hex;
				return false;
			}
		});

		return color_hex;
	},

	show: function (el) {
		var $el = jQuery(el);

		// if user is not on the page
		if (!LivepressConfig.page_active && LivepressConfig.effects ) {
			$el.getBg();
			$el.data("oldbg", $el.css('background-color'));
			$el.addClass('unfocused-lp-update');
			$el.css("background-color", this.colorForOperation($el));
		}
		$el.show();
	},

	/**
	 * this is a fix for the jQuery s(l)ide effects
	 * Without this element sometimes has inline style of height
	 * set to 0 or 1px. Remember not to use this on collection but
	 * on single elements only.
	 *
	 * @param object node to be displayed/hidden
	 * @param object hash with
	 *  slideType:
	 *   "down" - default, causes element to be animated as if using slideDown
	 *    anything else, is recognised as slideUp
	 *  duration: this value will be passed as duration param to slideDown, slideUp
	 */
	sliderFixed: function (el, options) {
		var $ = jQuery;
		var defaults = {slideType: "down", duration: 250};
		options = $.extend({}, defaults, options);
		var bShow = (options.slideType === "down");
		var $el = $(el), height = $el.data("originalHeight"), visible = $el.is(":visible");
		var originalStyle = $el.data("originalStyle");
		// if the bShow isn't present, get the current visibility and reverse it
		if (arguments.length === 1) {
			bShow = !visible;
		}

		// if the current visiblilty is the same as the requested state, cancel
		if (bShow === visible) {
			return false;
		}

		// get the original height
		if (!height || !originalStyle) {
			// get original height
			height = $el.show().height();
			originalStyle = $el.attr('style');
			$el.data("originalStyle", originalStyle);
			// update the height
			$el.data("originalHeight", height);
			// if the element was hidden, hide it again
			if (!visible) {
				$el.hide();
			}
		}

		// expand the knowledge (instead of slideDown/Up, use custom animation which applies fix)
		if (bShow) {
			$el.show().animate({
				height: height
			}, {
				duration: options.duration,
				complete: function () {
					$el.css({height: $el.data("originalHeight")});
					$el.attr("style", $el.data("originalStyle"));
					$el.show();
				}
			});
		} else {
			$el.animate({
				height: 0
			}, {
				duration: options.duration,
				complete: function () {
					$el.hide();
				}
			});
		}
	},

	show_with_effects: function ($selects, effects) {
		if (this.custom_background_color === "string") {
			$selects.css('background-color', this.custom_background_color);
		}
		$selects.getBg();
		effects($selects, $selects.css('background-color'));
	},


	display: function (display_with_effects) {
		if (display_with_effects) {
			var $els = this.selector('.oortle-diff-inserted-block');
			$els.hide().css("height", "");
			var self = this;
			var blockInsertionEffects = function ($el, old_bg) {
				self.sliderFixed($el, "down");
				$el.animate({backgroundColor: self.colorForOperation($el)}, 200)
					.animate({backgroundColor: old_bg}, 800);

				// Clear background after effects
				setTimeout(function () {
					$el.css('background-color', '');
				}, 1500);
			};

			$els.each(function (index, update) {
				self.show_with_effects(jQuery(update), blockInsertionEffects);
			});

			this.show_with_effects(this.selectors('.oortle-diff-inserted', '.oortle-diff-changed'),
				function ($el, old_bg) {
					$el.slideDown(200);
					try {
						$el.animate({backgroundColor: self.colorForOperation($el)}, 200)
							.animate({backgroundColor: old_bg}, 800);
					} catch (e) {
						console.log('Error when animating new comment div.');
					}

					// Clear background after effects
					setTimeout(function () {
						$el.css('background-color', '');
					}, 1500);
				}
			);

			this.show_with_effects(this.selectors('.oortle-diff-removed-block', '.oortle-diff-removed'),
				function ($el, old_bg) {
					try {
						$el.animate({backgroundColor: self.colorForOperation($el)}, 200)
							.animate({backgroundColor: old_bg}, 800)
							.slideUp(200);
					} catch (e) {
						console.log('Error when removing comment div.');
					}
					// Clear background after effects
					setTimeout(function () {
						$el.css('background-color', '');
					}, 1500);
				}
			);
		} else {
			this.show(this.selectors('.oortle-diff-changed', '.oortle-diff-inserted', '.oortle-diff-removed'));
			this.show(this.selector('.oortle-diff-inserted-block'));
		}
	},

	set_text: function (nodePath, content) {
		var parts = this.get_path_parts(nodePath);
		var childIndex = this.get_child_index(parts);
		var parent = this.node_at_path(this.get_parent_path(parts));

		if (childIndex > -1 && parent !== null) {
			var refNode = parent.childNodes[childIndex];
			var contentArr = jQuery(content);

			for (var i = 0, len = contentArr.length; i < len; i++) {
				parent.insertBefore(contentArr[i], refNode);
			}

			parent.removeChild(refNode);
		}
	},

    // if list of idices passed -- returns array of indexes
    // if #elId passed, return array [Parent, Node]
	get_path_parts: function (nodePath) {
        if(nodePath[0]==='#') {
            var el = jQuery(nodePath, this.containerElement)[0];
            if(el) {
              return [el.parentNode, el];
            } else {
              return [null, null];
            }
        } else {
            var parts = nodePath.split(':');
            var indices = [];
            for (var i = 0, len = parts.length; i < len; i++) {
                indices[i] = parseInt(parts[i], 10);
            }
            return indices;
        }
	},

    // not working with #elId schema
	get_child_index: function (pathParts) {
		if (pathParts.length > 0) {
			return parseInt(pathParts[pathParts.length - 1], 10);
		}
		return -1;
	},

    // working with #elId schema
	get_parent_path: function (pathParts) {
		var parts = pathParts.slice(); // "clone" the array
		parts.splice(-1, 1);
		return parts;
	},

    // in case #elId just return last element
	node_at_path: function (pathParts) {
        if(pathParts[0].nodeType===undefined) {
            return this.get_node_by_path(this.containerElement, pathParts);
        } else {
            return pathParts[pathParts.length-1];
        }
	},

	get_node_by_path: function (root, pathParts) {
		var parts = pathParts.slice();
		parts.splice(0, 1); // take out the first element (the root)
		if (parts.length === 0) {
			return root;
		}
		var i = 0, tmp = root, result = null;
		for (var len = parts.length; i < len; i++) {
			tmp = tmp.childNodes[parts[i]];
			if (typeof(tmp) === 'undefined') {
				break;
			}
		}
		if (i === parts.length) {
			result = tmp;
		}
		return result;
	},

	add_class: function (node, newClass) {
		if (node !== null) {
			node.className += ' ' + newClass;
		}
	},

	set_attr: function (node, attrName, attrValue) {
		if (node !== null) {
			node.setAttribute(attrName, attrValue);
		}
	},

	del_attr: function (node, attrName) {
		if (node !== null) {
			node.removeAttribute(attrName);
		}
	}
};

Livepress.DOMManipulator.clean_updates = function (el) {
	var temp_manipulator = new Livepress.DOMManipulator(el);
	temp_manipulator.clean_updates();
};

//
// Copyright (c) 2008, 2009 Paul Duncan (paul@pablotron.org)
//
// Permission is hereby granted, free of charge, to any person obtaining a copy
// of this software and associated documentation files (the "Software"), to deal
// in the Software without restriction, including without limitation the rights
// to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
// copies of the Software, and to permit persons to whom the Software is
// furnished to do so, subject to the following conditions:
//
// The above copyright notice and this permission notice shall be included in
// all copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
// IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
// FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
// AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
// LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
// OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
// THE SOFTWARE.
//

(function () {
	// We are already defined. Hooray!
	if (window.google && google.gears) {
		return;
	}

	// factory
	var F = null;

	// Firefox
	if (typeof GearsFactory != 'undefined') {
		F = new GearsFactory();
	} else {
		// IE
		try {
			if ( 'undefined' !== typeof window.ActiveXObject ){
				F = new ActiveXObject('Gears.Factory');
				// privateSetGlobalObject is only required and supported on WinCE.
				if (F.getBuildInfo().indexOf('ie_mobile') != -1) {
					F.privateSetGlobalObject(this);
				}
			}
		} catch (e) {
			// Safari
			if ((typeof navigator.mimeTypes != 'undefined') && navigator.mimeTypes["application/x-googlegears"]) {
				F = document.createElement("object");
				F.style.display = "none";
				F.width = 0;
				F.height = 0;
				F.type = "application/x-googlegears";
				document.documentElement.appendChild(F);
			}
		}
	}

	// *Do not* define any objects if Gears is not installed. This mimics the
	// behavior of Gears defining the objects in the future.
	if (!F) {
		return;
	}


	// Now set up the objects, being careful not to overwrite anything.
	//
	// Note: In Internet Explorer for Windows Mobile, you can't add properties to
	// the window object. However, global objects are automatically added as
	// properties of the window object in all browsers.
	if (!window.google) {
		google = {};
	}

	if (!google.gears) {
		google.gears = {factory:F};
	}

})();
/**
* storage.js - Simple namespaced browser storage.
*
* Creates a window.Storage function that gives you an easy API to access localStorage,
* with fallback to cookie storage. Each Storage object is namespaced:
*
* var foo = Storage('foo'), bar = Storage('bar');
* foo.set('test', 'A'); bar.set('test', 'B');
* foo.get('test'); // 'A'
* bar.remove('test');
* foo.get('test'); // still 'A'
*
* Requires jQuery.
* Based on https://github.com/jbalogh/zamboni/blob/master/media/js/zamboni/storage.js
* Everything clever written by Chris Van.
*/
var internalStorage = (function () {
	var cookieStorage = {
			expires: 30,
			get: function ( key ) {
			return jQuery.cookie( key );
			},

			set: function ( key, value ) {
				return jQuery.cookie( key, value, {path: "/", expires: this.expires} );
			},

			remove: function ( key ) {
				return jQuery.cookie( key, null );
			}
		};

	var engine = cookieStorage;
	try {
		if ( 'localStorage' in window && window['localStorage'] !== null ) {
			engine = window.localStorage;
		}
	} catch ( e ) {
		}
	return function ( namespace ) {
		if ( !namespace ) {
			namespace = '';
		}

		return {
			get: function ( key, def ) {
				return engine.getItem( namespace + "-" + key );
			},

			set: function ( key, value ) {
				return engine.setItem( namespace + "-" + key, value );
			},

			remove: function ( key ) {
				return engine.remoteItem( namespace + "-" + key);
			}
		};
	};
})();

Livepress.storage = (function () {
	var storage = new internalStorage('Livepress' );
	return {
		get: function (key, def) {
			var val = storage.get(key);
			return (val === null || typeof val === 'undefined' ) ? def : val;
		},
		set: function (key, value) {
			return storage.set(key, value);
		}
	};
}());

/*global lp_client_strings, Livepress, OORTLE, console, FB, _wpmejsSettings, LivepressConfig*/
/**
 *  Connects to Oortle, apply diff messages and handles the view, playing sounds for each task.
 *
 *  Applies the diffs of content (to '#post_content_livepress') and comments (to '#post_comments_livepress')
 *
 * @param   config  Can have the following options
 *                  * comment_count = the actual comment count (will be updated on each comment update)
 *                  * site_url = site url to use in Oortle' topics
 *                  * ajax_url = url to use in ajax requests
 *                  * post_update_msg_id, comment_msg_id, new_post_msg_id = the id of the last message
 *                      sent to the topics post-{config.post_id}, post-{config.post_id}_comment and
 *                      post-new-update (all 3 starts with livepress|{site_url}|)
 *                  * can_edit_comments = boolean, if true will use the topic
 *                      post-{config.post_id}_comment-logged instead of post-{config.post_id}_comment
 *                  * custom_title_css_selector = if set, will change the topic in the selector
 *                      provided instead of "#post-{config.post_id}"
 *                  * custom_background_color = should be set if the text color background is
 *                      provided by an image. If it's form an image, will get it from the CSS.
 *                  * post_id = the current post_id
 *                  * page_type = [home|single|page|admin], used to choose between partial/full view
 *                      and subscribe to the topics that makes sense
 *                  * feed_sub_link = Link to subscribe to post updates from feed
 *                  * feed_title = Title of the post updates feed
 *                  * disable_comments = Disables all comment related UI
 *                  * comment_live_updates_default = Live comment update should be on/off
 *                  * sounds_default = Sounds should be on/off
 *
 * @param   hooks   Can have the following function hooks:
 *                  * post_comment_update = call after apply the diff operation
 */
Livepress.Ui.Controller = function (config, hooks) {
	var $window = jQuery( window ),
		$livepress = jQuery( document.getElementById( 'livepress' ) );
	var post_dom_manipulator, comments_dom_manipulator;
	var comment_count = config.comment_count;
	var page_type = config.page_type;
	var on_home_page = (page_type === 'home');
	var on_single_page = (page_type === 'single');
	var posts_on_hold = [];
	var paused = false;
	var update_box;
	var widget;
	var comet = OORTLE.instance;
	var sounds = Livepress.sounds;

	function connected () {
		if ( widget !== undefined ) {
			widget.connected();
		}
	}

	function comet_error_callback ( message ) {
		console.log( "Comet error: ", message );
	}

	function call_hook (name) {
		if (typeof(hooks) === 'object') {
			if (typeof(hooks[name]) === 'function') {
				return hooks[name].apply(this, Array.prototype.slice.call(arguments, 1));
			}
		}
	}

	function trigger_action_on_view  () {
		setTimeout(function () {
			if (comet.is_connected()) {
				widget.connected();
			} else {
				widget.disconnected();
			}
		}, 1500);
	}

	function comment_update (data, topic, msg_id) {
		if (config.comment_msg_id === msg_id) {
			return;
		}

		call_hook('before_live_comment', data);
		// WP only: don't attach comments if we're using page split on comments and we're not on first or last
		// page, depending on what option of comment sorting is set.
		var should_attach = call_hook("should_attach_comment", config);
		// should attach if such hook doesn't exist
		should_attach = (should_attach === undefined ? true : should_attach);

		if (should_attach) {
			var result = call_hook('on_comment_update', data, comments_dom_manipulator);
			if (result === undefined) {
				comments_dom_manipulator.update(data.diff);
			}
		}
		trigger_action_on_view();

		// The submit form looses the ajax bind after applying the diff operations
		// so provide this hook to let attach the onClick function again
		call_hook('post_comment_update');

		if (comment_count === 0) {
			sounds.play("firstComment");
		} else {
			sounds.play("commentAdded");
		}
		comment_count += 1;

		if (data.comment_id) {
			var containerId = call_hook("get_comment_container", data.comment_id);
			if (containerId === undefined) {
				for (var i = 0; i < data.diff.length; i += 1) {
					if (data.diff[i][0] === "ins_node" && data.diff[i][2].indexOf(data.content) >= 0) {
						containerId = jQuery(data.diff[i][2]).attr('id');
						break;
					}
				}
			}

			var avatar_src;
			// checking if avatar_url is <img> or just string with url
			if (jQuery(data.avatar_url).length === 0) {
				avatar_src = data.avatar_url;
			} else {
				avatar_src = jQuery(data.avatar_url).attr('src');
			}

			// change the bubble to contain refresh button instead of
			// 'scroll to' if we didn't attach new comment
			var options = {
				title:             data.author,
				text:              data.content,
				commentContainerId:containerId,
				image:             avatar_src
			};

			if (!should_attach) {
				options.scrollToText = lp_client_strings.refresh_page;
				options.scrollToCallback = function () {
					location.reload();
				};
			}
			widget.comment_alert(options, data.comment_gmt);
		}

		widget.set_comment_num(comment_count);
	}

	function update_live_updates () {
		var $live_updates = jQuery( document.querySelectorAll( '#post_content_livepress .livepress-update' ) ).not('.oortle-diff-removed');
		widget.set_live_updates_num($live_updates.length);

		var current_post_link = window.location.href.replace(window.location.hash, "");

		if (on_single_page) {
			$live_updates.addClass('lp-hl-on-hover');
		}

		$live_updates.each(function () {
			var $this = jQuery( this );
			if ( ! $this.is( '.lp-live' ) ) {
				$this.addClass('lp-live');
				if (LivepressConfig.sharing_ui !== 'dont_display'){
					return new Livepress.Ui.UpdateView($this, current_post_link, config.disable_comments);
				}
			}
		});
	}

	// Once we load the content of a post, check if we need to trigger
	// some function to display any of the embeds
	function update_embeds(data){
		// Workaround for Facebook embeds, see if we need to embed any
		// facebook posts once there's an update:
		if ( typeof(FB) !== 'undefined'){
			FB.XFBML.parse();
		}

		// Get the update id in the form 'live-press-update-23423423'
		var re = /id\=\"(livepress-update-[0-9]+)/;
		// data Array: ['ins_node', '0:0', '<div id="livepress-update..."']
		var id = re.exec(data[0][2])[1];

		embed_audio_and_video(id);
	}

	// WordPress' Audio and Video embeds
	// Basically use the same function WP uses to embed audio and video
	// from shortcodes:
	function embed_audio_and_video(update_id){
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
		var search = ["#", update_id, " .wp-audio-shortcode, ", "#", update_id, " .wp-video-shortcode"].join('');
		jQuery( search ).not('.mejs-container').mediaelementplayer( settings );
	}

	function handle_page_title_update (data) {
		// Notify about new post updates by changing the title count. No deletes and edits.
		// Only if the window is not active
		if (window.is_active) {
			return;
		}

		// Only update the post title when we are inserting a new node
		var is_ins_node = false;
		jQuery.each(data, function (k, v) {
			// it's deletion if only del_node operations are in changes array
			is_ins_node = (v[0] === "ins_node");
		});
		if ( ! is_ins_node ) {
			return;
		}

		var title = jQuery("title");
		var updates = title.data("updates");
		updates += 1;
		title.data("updates", updates);
		title.text("(" + updates + ") " + title.data("text"));
		// TODO: change window title too, to match new post title
	}

	function post_title_update (title) {
		trigger_action_on_view();

		if (title.length === 2) {
			var old_title = title[0];
			var new_title = title[1];
			var selector;
			if (typeof(config.custom_title_css_selector) === "string") {
				selector = config.custom_title_css_selector;
			} else {
				selector = "#post-" + config.post_id;
			}
			var $post_title = jQuery(selector);

			var new_title_value = $post_title.html().replaceAll(old_title, new_title);
			$post_title.html(new_title_value);
			var html_title_value = document.title.replace(old_title, new_title);
			document.title = html_title_value;

			//  // Should highlight the title but it's highlighting the whole post
			//  $post_title.addClass('oortle-diff-changed').show();
			//  setTimeout("$post_title.removeClass('oortle-diff-changed');", 3000);
		} else {
			console.log("error -- received data about post = " + title);
		}
	}

	function post_update (data) {
		var date       = new Date(),
			dateString = date.toISOString(),
			abbr       = '<abbr class="livepress-timestamp" title="' + dateString +'"></abbr>';

		console.log("post_update with data = ", data);
        if ('op' in data && data.op === 'broadcast') {
            var broadcast = JSON.parse(data.data);
            if('shortlink' in broadcast) {
                jQuery.each(broadcast['shortlink'], function(k,v) {
                    Livepress.updateShortlinksCache[k] = v;
                });
            }
            return;
        }
		if ('event' in data && data.event === 'post_title') {
			return post_title_update(data.data);
		}
		var paused_data = data.pop();
		handle_page_title_update(data);

		if (paused) {
			posts_on_hold.push(data);
			if (typeof(update_box) !== "undefined") {
				var updated_at = paused_data.updated_at;
				for (var i = 0; i < paused_data.post_updates.length; i += 1) {
					update_box.post_update(paused_data.post_updates[i], updated_at);
				}
			}
		} else {
			post_dom_manipulator.update(data, {effects_display:window.is_active});
			update_live_updates();
		}
		trigger_action_on_view();
		update_embeds(data);
		sounds.play("postUpdated");

		$livepress.find('.lp-updated-counter').html( abbr );
		$livepress.find('.lp-updated-counter').find('.livepress-timestamp').attr('title', dateString );
		$livepress.find('.lp-bar .lp-status').removeClass('lp-off').addClass('lp-on');
		var timestamp = jQuery('abbr.livepress-timestamp').eq( 1 ),
			update_id = timestamp.closest('.livepress-update').attr('id');
		if ( 'timeago' === LivepressConfig.timestamp_format ) {
			timestamp.timeago().attr( 'title', '' );
		} else {
			jQuery('.lp-bar abbr.livepress-timestamp').timeago();
			jQuery('abbr.livepress-timestamp').attr( 'title', '' );
		}
		timestamp.wrap('<a href="' + Livepress.getUpdatePermalink(update_id) + '" ></a>');

		jQuery( document ).trigger( 'live_post_update' ); /*Trigger a post-update event so display can adjust*/
	}

	function new_post_update_box (post, topic, msg_id) {
		if (config.new_post_msg_id === msg_id) {
			return;
		}

		update_box.new_post(post.title, post.link, post.author, post.updated_at_gmt);
		sounds.play("postUpdated");
	}

	var imSubscribing = false;
	function imSubscribeCallback (userName, imType) {

		if (imSubscribing || userName.length === 0 || userName === "username") {
			return;
		}

		imSubscribing = true;
		widget.imFeedbackSpin(true);

		// TODO handle imType on backend
		var postData = { action:'new_im_follower', user_name:userName, im_type:imType, post_id:config.post_id };

		jQuery.post(config.ajax_url, postData, function (response) {
			widget.handleImFeedback(response, userName);
			imSubscribing = false;
		});
	}

	function bindPageActivity () {
		console.log( 'bindPageActivity' );
		var animateLateUpdates = function () {
			var updates = jQuery(".unfocused-lp-update");
			var old_bg = updates.data("oldbg") || "#FFF";
			updates.animate({backgroundColor:old_bg}, 4000, "swing", function () {
				jQuery(this).removeClass("unfocused-lp-update").css('background-color', '');
			});
		};

		var title = jQuery("title");
		title.data("text", title.text());
		title.data("updates", 0);
		$window.focus(function () {
			this.is_active = true;
			title.text(title.data("text"));
			title.data("updates", 0);
			animateLateUpdates();
		});

		$window.blur(function () {
			this.is_active = false;
		});

		var $livediv = jQuery( '#post_content_livepress' ),
			liveTags = $livediv.data( 'livetags' );
			console.log( liveTags );
			if ( '' !== liveTags ) {
				post_dom_manipulator.addLiveTagControlBar();
				var allTags = liveTags.split( ',' );
					allTags.map( function( tag ) {
						post_dom_manipulator.addLiveTagToControls( tag );
					});
			}
		jQuery( document ).trigger( 'live_post_update' );
	}

	window.is_active = true;
	$window.ready(bindPageActivity);

	if ( null !== document.getElementById( 'lp-update-box' ) ) {
		update_box = new Livepress.Ui.UpdateBoxView(on_home_page);
	}

	if ( null !== document.getElementById( 'livepress') ) {
		widget = new Livepress.Ui.View(config.disable_comments);
		widget.set_comment_num(comment_count);
		update_live_updates();
		var feed_link = encodeURIComponent(config.feed_sub_link);
		var feed_title = encodeURIComponent(config.feed_title);
		widget.add_link_to_greader("http://www.google.com/ig/addtoreader?et=gEs490VY&source=ign_pLt&feedurl=" + feed_link + "&feedtitle=" + feed_title);
	}

	// Just connect to LivePress if there is any of the views present
	if ( update_box !== undefined || widget !== undefined ) {
		var connection_id = "#livepress-connection";
		jQuery(document.body).append('<div id="' + connection_id + '"><!-- --></div>');

		var new_post_topic = "|livepress|" + config.site_url + "|post-new-update";

		comet.attachEvent('connected', connected);
		comet.attachEvent('error', comet_error_callback);

		// Subscribe to the post, comments and 'new posts' topics.
		// post_update_msg_id, comment_msg_id and new_post_msg_id have the message hash

		// Handle LivePress update box if present
		if ( update_box !== undefined ) {
			if (on_home_page) {
				var opt1 = config.new_post_msg_id ? {last_id:config.new_post_msg_id} : {fetch_all:true};
				comet.subscribe(new_post_topic, new_post_update_box, opt1);
				comet.connect(); // We always subscribe on main page to get new post notifications
			}
		}

		// Handle LivePress control widget if present
		if ( widget !== undefined ) {
			comet.attachEvent('reconnected', widget.connected);
			comet.attachEvent('disconnected', widget.disconnected);

			var post_update_topic = "|livepress|" + config.site_url + "|post-" + config.post_id;
			var comment_update_topic = "|livepress|" + config.site_url + "|post-" + config.post_id + "_comment";

			if (config.can_edit_comments) {
				comment_update_topic += "-logged";
			}

			// Create dom manipulator of the post and the comments
			post_dom_manipulator = new Livepress.DOMManipulator('#post_content_livepress', config.custom_background_color);
			comments_dom_manipulator = new Livepress.DOMManipulator('#post_comments_livepress', config.custom_background_color);

			var opt = config.new_post_msg_id ? {last_id:config.new_post_msg_id} : {fetch_all:true};
			if (!config.disable_comments && config.comment_live_updates_default) {
				opt = config.comment_msg_id ? {last_id:config.comment_msg_id} : {fetch_all:true};
				comet.subscribe(comment_update_topic, function () {
				}, opt); // just set options there
			}

			opt = config.post_update_msg_id ? {last_id:config.post_update_msg_id} : {fetch_all:true};
			comet.subscribe(post_update_topic, post_update, opt);

			widget.subscribeIm(imSubscribeCallback);

			widget.live_control(
				Livepress.storage.get('settings-live', true),
				function (save) {
					comet.connect();
					if (save) { Livepress.storage.set('settings-live', "1"); }
				},
				function (save) {
					comet.disconnect();
					if (save) { Livepress.storage.set('settings-live', ""); }
				}
			);

			if (!config.disable_comments) {
				widget.follow_comments_control(
					Livepress.storage.get('settings-comments', true),
					function (save) {
						comet.subscribe(comment_update_topic, comment_update);
						if (save) { Livepress.storage.set('settings-comments', "1"); }
					},
					function (save) {
						comet.unsubscribe(comment_update_topic, comment_update);
						if (save) { Livepress.storage.set('settings-comments', ""); }
					}
				);
			}

			widget.scroll_control(
				Livepress.storage.get('settings-scroll', config.autoscroll === undefined || config.autoscroll),
				function (save) {
					Livepress.Scroll.settings_enabled = true;
					if (save) { Livepress.storage.set('settings-scroll', "1"); }
				},
				function (save) {
					Livepress.Scroll.settings_enabled = false;
					if (save) { Livepress.storage.set('settings-scroll', ""); }
				}
			);
		}
	}
};

jQuery(function () {
	Livepress.Comment = (function () {
		var sending = false;

		var set_comment_status = function (status) {
			var $status = jQuery('#oortle-comment-status');
			if ($status.length === 0) {
				jQuery('#submit').after("<span id='oortle-comment-status'></span>");
				$status = jQuery('#oortle-comment-status');
			}
			$status.text(status);
		};

		var unblock_comment_textarea = function (eraseText) {
			var comment_textarea = jQuery("#comment");
			comment_textarea.attr("disabled", false);

			if (eraseText) {
				comment_textarea.val('');
				jQuery("#cancel-comment-reply-link").click();
			}
		};

		var send = function () {
			try {
				if (sending) {
					return false;
				}
				sending = true;

				var $btn = jQuery('#submit');
				var btn_text = $btn.attr("value");
				$btn.attr("value", lp_client_strings.sending + '...' );
				$btn.attr("disabled", true);
				jQuery("textarea#comment").attr("disabled", true);
				set_comment_status("");

				var params = {};
				var form = document.getElementById('commentform') || document.getElementById('comment-form');
				params.comment_post_ID = form.comment_post_ID.value;
				if (typeof(form.comment_parent) !== 'undefined') {
					params.comment_parent = form.comment_parent.value;
				}
				params.comment = form.comment.value;
				form.comment.value = '';
				// FIXME: this won't work when accepting comments without email and name fields
				// sent author is same as comment then. Ex. author:	test!@ comment:	test!@
				params.author = form.elements[0].value;
				params.email = form.elements[1].value;
				params.url = form.elements[2].value;
				params._wp_unfiltered_html_comment = (form._wp_unfiltered_html_comment !== undefined) ? form._wp_unfiltered_html_comment.value : '';
				params.redirect_to = '';
				params.livepress_update = 'true';
				params.action = 'lp_post_comment';
				params._ajax_nonce = LivepressConfig.ajax_comment_nonce;

				Livepress.sounds.play("commented");

				jQuery.ajax({
					url:     LivepressConfig.site_url + '/wp-admin/admin-ajax.php',
					type:    'post',
					dataType:'json',
					data:    params,
					error:   function (request, textStatus, errorThrown) {

						console.log("comment response: " + request.status + ' :: ' + request.statusText);
						console.log("comment ajax failed: %s", textStatus);
						set_comment_status( lp_client_strings.comment_status + ": " + request.responseText );
						unblock_comment_textarea(false);
					},
					success: function (data, textStatus) {
						// TODO: Improve display message that send successed.
						set_comment_status( lp_client_strings.comment_status + ": " + data.msg);
						unblock_comment_textarea(data.code === "200");
					},
					complete:function (request, textStatus) {
						$btn = jQuery('#submit');
						sending = false;
						$btn.attr("value", btn_text);
						$btn.attr("disabled", false);
					}
				});
			} catch (error) {
				console.log("EXCEPTION: %s", error);
				set_comment_status( lp_client_strings.sending_error );
			}

			return false;
		};

		var attach = function () {
			jQuery('#submit').click(send);
		};

		// WP only: we must hide new comment form before making any modifications to dom tree
		// otherwise wp javascripts which handle cancel link won't work anymore
		// we check if new comment is of same author and if user didn't modify it's contents meanwhile
		var before_live_comment = function (comment_data) {
			var comment_textarea = jQuery("#comment");
			if (comment_data.ajax_nonce === LivepressConfig.ajax_nonce && comment_textarea.val() === comment_data.content) {
				unblock_comment_textarea(true);
			}
		};

		var should_attach_comment = function (config) {
			var page_number = config.comment_page_number;
			if (config.comment_order === "asc") {
				return(page_number === 0 || page_number === config.comment_pages_count);
			} else {
				return(page_number <= 1);
			}
		};

		var get_comment_container = function (comment_id) {
			return jQuery("#comment-" + comment_id).parent().attr("id");
		};

		var on_comment_update = function (data, manipulator) {
			var manipulator_options = {
				custom_scroll_class:'#comment-' + data.comment_id
			};
			if (data.comment_parent === '0') {
				manipulator.update(data.diff, manipulator_options);
			} else { // updating threaded comment
				manipulator.update(data.comments_counter_only_diff, manipulator_options);

				var new_comment = jQuery(data.comment_html);
				// we want new comment to be animated as usual by DOMmanipuator.js
				new_comment.addClass('oortle-diff-inserted-block').hide();
				var parent = jQuery("#comment-" + data.comment_parent);
				var children = parent.children(".children");
				if (children.length === 0) {
					children = jQuery("<ul>").addClass("children").appendTo(parent);
				}
				children.append(new_comment);
				manipulator.display(true);
			}

			return true;
		};

		if (!LivepressConfig.disable_comments) {
			attach();
		}

		return {
			send:                 send,
			attach:               attach,
			before_live_comment:  before_live_comment,
			should_attach_comment:should_attach_comment,
			get_comment_container:get_comment_container,
			on_comment_update:    on_comment_update
		};
	}());
});

if (jQuery !== undefined) {
	jQuery.ajax = (function (jQajax) {
		return function () {
			if (OORTLE !== undefined && OORTLE.instance !== undefined && OORTLE.instance) {
				OORTLE.instance.flush();
			}
			return jQajax.apply(this, arguments);
		};
	}(jQuery.ajax));
}
/**
 * Underscore throttle
 */
  // Returns a function, that, when invoked, will only be triggered at most once
  // during a given window of time. Normally, the throttled function will run
  // as much as it can, without ever going more than once per `wait` duration;
  // but if you'd like to disable the execution on the leading edge, pass
  // `{leading: false}`. To disable execution on the trailing edge, ditto.
  // A (possibly faster) way to get the current timestamp as an integer.
var unow = Date.now || function() {
    return new Date().getTime();
  };

var throttle = function(func, wait, options) {
    var context, args, result;
    var timeout = null;
    var previous = 0;
    if (!options){ options = {}; }
    var later = function() {
      previous = options.leading === false ? 0 : unow();
      timeout = null;
      result = func.apply(context, args);
      if (!timeout) { context = args = null; }
    };
    return function() {
      var now = unow();
      if (!previous && options.leading === false) { previous = now; }
      var remaining = wait - (now - previous);
      context = this;
      args = arguments;
      if (remaining <= 0 || remaining > wait) {
        clearTimeout(timeout);
        timeout = null;
        previous = now;
        result = func.apply(context, args);
        if (!timeout) { context = args = null; }
      } else if (!timeout && options.trailing !== false) {
        timeout = setTimeout(later, remaining);
      }
      return result;
    };
  };

Livepress.Ready = function () {

	var $lpcontent, $firstUpdate, $livepressBar, $heightOfFirstUpdate, $firstUpdateContainer, diff,
		hooks = {
			post_comment_update:  Livepress.Comment.attach,
			before_live_comment:  Livepress.Comment.before_live_comment,
			should_attach_comment:Livepress.Comment.should_attach_comment,
			get_comment_container:Livepress.Comment.get_comment_container,
			on_comment_update:    Livepress.Comment.on_comment_update
		};

	// Add update permalink to each timestamp
	jQuery.each(
		jQuery('.livepress-update'),
		function(){
			var timestamp = jQuery(this).find('abbr.livepress-timestamp');
			timestamp.wrap('<a href="' + Livepress.getUpdatePermalink(jQuery(this).attr('id')) + '" ></a>');
			console.log( LivepressConfig.update_format );
			if ( 'timeago' === LivepressConfig.timestamp_format ) {
				jQuery('abbr.livepress-timestamp').timeago().attr( 'title', '' );
			} else {
				jQuery('.lp-bar abbr.livepress-timestamp').timeago();
				jQuery('abbr.livepress-timestamp').attr( 'title', '' );
			}
		}
	);

	if ( jQuery( '.lp-status' ).hasClass( 'livepress-pinned-header' ) ) {

		jQuery( '.livepress_content' ).find( '.livepress-update:first' ).addClass( 'pinned-first-live-update' );
		// Adjust the positioning of the first post to pin it to the top
		var adjustTopPostPositioning = function() {

			window.console.log( 'adjust top' );
			$lpcontent    = jQuery( '.livepress_content' );
			$firstUpdate  = $lpcontent.find( '.pinned-first-live-update' );
			// keep at the top of the list
			$firstUpdate.detach().prependTo( $lpcontent );
			$firstUpdateContainer = $lpcontent.parent();
			$firstUpdate.css( 'marginTop', 0 );
			$livepressBar = jQuery( '#livepress' );
			$livepressBar.css( 'marginTop', 0 );
			diff = $firstUpdate.offset().top - $firstUpdateContainer.offset().top;
			$heightOfFirstUpdate = ( $firstUpdate.outerHeight() + 20 );
			$firstUpdate.css( {
				'margin-top': '-' + ( diff + $heightOfFirstUpdate ) + 'px',
				'position': 'absolute',
				'width' : ( $livepressBar.outerWidth() ) + 'px'
			} );
			$livepressBar.css( { 'margin-top': $heightOfFirstUpdate + 'px' } );
		};

		adjustTopPostPositioning();

		// Adjust the top position whenever the post is updated so it fits properly
		jQuery( document ).on( 'live_post_update', function(){
			console.log ('live_post_update triggered' );
			setTimeout( adjustTopPostPositioning, 50 );
			// Rerun in 2 seconds to account fo resized embeds
			//setTimeout( adjustTopPostPositioning, 2000 );
		});

		// Adjust the top positioning whenever the browser is resized to adjust sizing correctly
		jQuery( window ).on( 'resize', throttle ( function() {
			adjustTopPostPositioning();
		}, 500 ) );
	}
	return new Livepress.Ui.Controller(LivepressConfig, hooks);
};

jQuery.effects || (function($, undefined) {

	$.effects = {};

	// override the animation for color styles
	$.each(['backgroundColor', 'borderBottomColor', 'borderLeftColor',
		'borderRightColor', 'borderTopColor', 'borderColor', 'color', 'outlineColor'],
		function(i, attr) {
			$.fx.step[attr] = function(fx) {
				if (!fx.colorInit) {
					fx.start = getColor(fx.elem, attr);
					fx.end = getRGB(fx.end);
					fx.colorInit = true;
				}

				fx.elem.style[attr] = 'rgb(' +
					Math.max(Math.min(parseInt((fx.pos * (fx.end[0] - fx.start[0])) + fx.start[0], 10), 255), 0) + ',' +
					Math.max(Math.min(parseInt((fx.pos * (fx.end[1] - fx.start[1])) + fx.start[1], 10), 255), 0) + ',' +
					Math.max(Math.min(parseInt((fx.pos * (fx.end[2] - fx.start[2])) + fx.start[2], 10), 255), 0) + ')';
			};
		});

	// Color Conversion functions from highlightFade
	// By Blair Mitchelmore
	// http://jquery.offput.ca/highlightFade/

	// Parse strings looking for color tuples [255,255,255]
	function getRGB(color) {
		var result;

		// Check if we're already dealing with an array of colors
		if ( color && color.constructor == Array && color.length == 3 )
			return color;

		// Look for rgb(num,num,num)
		if (result = /rgb\(\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*\)/.exec(color))
			return [parseInt(result[1],10), parseInt(result[2],10), parseInt(result[3],10)];

		// Look for rgb(num%,num%,num%)
		if (result = /rgb\(\s*([0-9]+(?:\.[0-9]+)?)\%\s*,\s*([0-9]+(?:\.[0-9]+)?)\%\s*,\s*([0-9]+(?:\.[0-9]+)?)\%\s*\)/.exec(color))
			return [parseFloat(result[1])*2.55, parseFloat(result[2])*2.55, parseFloat(result[3])*2.55];

		// Look for #a0b1c2
		if (result = /#([a-fA-F0-9]{2})([a-fA-F0-9]{2})([a-fA-F0-9]{2})/.exec(color))
			return [parseInt(result[1],16), parseInt(result[2],16), parseInt(result[3],16)];

		// Look for #fff
		if (result = /#([a-fA-F0-9])([a-fA-F0-9])([a-fA-F0-9])/.exec(color))
			return [parseInt(result[1]+result[1],16), parseInt(result[2]+result[2],16), parseInt(result[3]+result[3],16)];

		// Look for rgba(0, 0, 0, 0) == transparent in Safari 3
		if (result = /rgba\(0, 0, 0, 0\)/.exec(color))
			return colors['transparent'];

		// Otherwise, we're most likely dealing with a named color
		return colors[$.trim(color).toLowerCase()];
	}

	function getColor(elem, attr) {
		var color;

		do {
			// jQuery <1.4.3 uses curCSS, in 1.4.3 - 1.7.2 curCSS = css, 1.8+ only has css
			color = ($.curCSS || $.css)(elem, attr);

			// Keep going until we find an element that has color, or we hit the body
			if ( color != '' && color != 'transparent' || $.nodeName(elem, "body") )
				break;

			attr = "backgroundColor";
		} while ( elem = elem.parentNode );

		return getRGB(color);
	};

	// Some named colors to work with
	// From Interface by Stefan Petre
	// http://interface.eyecon.ro/

	var colors = {
		aqua:[0,255,255],
		azure:[240,255,255],
		beige:[245,245,220],
		black:[0,0,0],
		blue:[0,0,255],
		brown:[165,42,42],
		cyan:[0,255,255],
		darkblue:[0,0,139],
		darkcyan:[0,139,139],
		darkgrey:[169,169,169],
		darkgreen:[0,100,0],
		darkkhaki:[189,183,107],
		darkmagenta:[139,0,139],
		darkolivegreen:[85,107,47],
		darkorange:[255,140,0],
		darkorchid:[153,50,204],
		darkred:[139,0,0],
		darksalmon:[233,150,122],
		darkviolet:[148,0,211],
		fuchsia:[255,0,255],
		gold:[255,215,0],
		green:[0,128,0],
		indigo:[75,0,130],
		khaki:[240,230,140],
		lightblue:[173,216,230],
		lightcyan:[224,255,255],
		lightgreen:[144,238,144],
		lightgrey:[211,211,211],
		lightpink:[255,182,193],
		lightyellow:[255,255,224],
		lime:[0,255,0],
		magenta:[255,0,255],
		maroon:[128,0,0],
		navy:[0,0,128],
		olive:[128,128,0],
		orange:[255,165,0],
		pink:[255,192,203],
		purple:[128,0,128],
		violet:[128,0,128],
		red:[255,0,0],
		silver:[192,192,192],
		white:[255,255,255],
		yellow:[255,255,0],
		transparent: [255,255,255]
	};

	// Animations

	var classAnimationActions = ['add', 'remove', 'toggle'],
		shorthandStyles = {
			border: 1,
			borderBottom: 1,
			borderColor: 1,
			borderLeft: 1,
			borderRight: 1,
			borderTop: 1,
			borderWidth: 1,
			margin: 1,
			padding: 1
		};

	function getElementStyles() {
		var style = document.defaultView
				? document.defaultView.getComputedStyle(this, null)
				: this.currentStyle,
			newStyle = {},
			key,
			camelCase;

		// webkit enumerates style porperties
		if (style && style.length && style[0] && style[style[0]]) {
			var len = style.length;
			while (len--) {
				key = style[len];
				if (typeof style[key] == 'string') {
					camelCase = key.replace(/\-(\w)/g, function(all, letter){
						return letter.toUpperCase();
					});
					newStyle[camelCase] = style[key];
				}
			}
		} else {
			for (key in style) {
				if (typeof style[key] === 'string') {
					newStyle[key] = style[key];
				}
			}
		}

		return newStyle;
	}

	function filterStyles(styles) {
		var name, value;
		for (name in styles) {
			value = styles[name];
			if (
			// ignore null and undefined values
				value == null ||
					// ignore functions (when does this occur?)
					$.isFunction(value) ||
					// shorthand styles that need to be expanded
					name in shorthandStyles ||
					// ignore scrollbars (break in IE)
					(/scrollbar/).test(name) ||

					// only colors or values that can be converted to numbers
					(!(/color/i).test(name) && isNaN(parseFloat(value)))
				) {
				delete styles[name];
			}
		}

		return styles;
	}

	function styleDifference(oldStyle, newStyle) {
		var diff = { _: 0 }, // http://dev.jquery.com/ticket/5459
			name;

		for (name in newStyle) {
			if (oldStyle[name] != newStyle[name]) {
				diff[name] = newStyle[name];
			}
		}

		return diff;
	}

	$.effects.animateClass = function(value, duration, easing, callback) {
		if ($.isFunction(easing)) {
			callback = easing;
			easing = null;
		}

		return this.queue(function() {
			var that = $(this),
				originalStyleAttr = that.attr('style') || ' ',
				originalStyle = filterStyles(getElementStyles.call(this)),
				newStyle,
				className = that.attr('class') || "";

			$.each(classAnimationActions, function(i, action) {
				if (value[action]) {
					that[action + 'Class'](value[action]);
				}
			});
			newStyle = filterStyles(getElementStyles.call(this));
			that.attr('class', className);

			that.animate(styleDifference(originalStyle, newStyle), {
				queue: false,
				duration: duration,
				easing: easing,
				complete: function() {
					$.each(classAnimationActions, function(i, action) {
						if (value[action]) { that[action + 'Class'](value[action]); }
					});
					// work around bug in IE by clearing the cssText before setting it
					if (typeof that.attr('style') == 'object') {
						that.attr('style').cssText = '';
						that.attr('style').cssText = originalStyleAttr;
					} else {
						that.attr('style', originalStyleAttr);
					}
					if (callback) { callback.apply(this, arguments); }
					$.dequeue( this );
				}
			});
		});
	};

	$.fn.extend({
		_addClass: $.fn.addClass,
		addClass: function(classNames, speed, easing, callback) {
			return speed ? $.effects.animateClass.apply(this, [{ add: classNames },speed,easing,callback]) : this._addClass(classNames);
		},

		_removeClass: $.fn.removeClass,
		removeClass: function(classNames,speed,easing,callback) {
			return speed ? $.effects.animateClass.apply(this, [{ remove: classNames },speed,easing,callback]) : this._removeClass(classNames);
		},

		_toggleClass: $.fn.toggleClass,
		toggleClass: function(classNames, force, speed, easing, callback) {
			if ( typeof force == "boolean" || force === undefined ) {
				if ( !speed ) {
					// without speed parameter;
					return this._toggleClass(classNames, force);
				} else {
					return $.effects.animateClass.apply(this, [(force?{add:classNames}:{remove:classNames}),speed,easing,callback]);
				}
			} else {
				// without switch parameter;
				return $.effects.animateClass.apply(this, [{ toggle: classNames },force,speed,easing]);
			}
		},

		switchClass: function(remove,add,speed,easing,callback) {
			return $.effects.animateClass.apply(this, [{ add: add, remove: remove },speed,easing,callback]);
		}
	});

	// Effects
	$.extend($.effects, {
		version: "1.8.24",

		// Saves a set of properties in a data storage
		save: function(element, set) {
			for(var i=0; i < set.length; i++) {
				if(set[i] !== null) element.data("ec.storage."+set[i], element[0].style[set[i]]);
			}
		},

		// Restores a set of previously saved properties from a data storage
		restore: function(element, set) {
			for(var i=0; i < set.length; i++) {
				if(set[i] !== null) element.css(set[i], element.data("ec.storage."+set[i]));
			}
		},

		setMode: function(el, mode) {
			if (mode == 'toggle') mode = el.is(':hidden') ? 'show' : 'hide'; // Set for toggle
			return mode;
		},

		getBaseline: function(origin, original) { // Translates a [top,left] array into a baseline value
			// this should be a little more flexible in the future to handle a string & hash
			var y, x;
			switch (origin[0]) {
				case 'top': y = 0; break;
				case 'middle': y = 0.5; break;
				case 'bottom': y = 1; break;
				default: y = origin[0] / original.height;
			};
			switch (origin[1]) {
				case 'left': x = 0; break;
				case 'center': x = 0.5; break;
				case 'right': x = 1; break;
				default: x = origin[1] / original.width;
			};
			return {x: x, y: y};
		},

		// Wraps the element around a wrapper that copies position properties
		createWrapper: function(element) {

			// if the element is already wrapped, return it
			if (element.parent().is('.ui-effects-wrapper')) {
				return element.parent();
			}

			// wrap the element
			var props = {
					width: element.outerWidth(true),
					height: element.outerHeight(true),
					'float': element.css('float')
				},
				wrapper = $('<div></div>')
					.addClass('ui-effects-wrapper')
					.css({
						fontSize: '100%',
						background: 'transparent',
						border: 'none',
						margin: 0,
						padding: 0
					}),
				active = document.activeElement;

			// support: Firefox
			// Firefox incorrectly exposes anonymous content
			// https://bugzilla.mozilla.org/show_bug.cgi?id=561664
			try {
				active.id;
			} catch( e ) {
				active = document.body;
			}

			element.wrap( wrapper );

			// Fixes #7595 - Elements lose focus when wrapped.
			if ( element[ 0 ] === active || $.contains( element[ 0 ], active ) ) {
				$( active ).focus();
			}

			wrapper = element.parent(); //Hotfix for jQuery 1.4 since some change in wrap() seems to actually loose the reference to the wrapped element

			// transfer positioning properties to the wrapper
			if (element.css('position') == 'static') {
				wrapper.css({ position: 'relative' });
				element.css({ position: 'relative' });
			} else {
				$.extend(props, {
					position: element.css('position'),
					zIndex: element.css('z-index')
				});
				$.each(['top', 'left', 'bottom', 'right'], function(i, pos) {
					props[pos] = element.css(pos);
					if (isNaN(parseInt(props[pos], 10))) {
						props[pos] = 'auto';
					}
				});
				element.css({position: 'relative', top: 0, left: 0, right: 'auto', bottom: 'auto' });
			}

			return wrapper.css(props).show();
		},

		removeWrapper: function(element) {
			var parent,
				active = document.activeElement;

			if (element.parent().is('.ui-effects-wrapper')) {
				parent = element.parent().replaceWith(element);
				// Fixes #7595 - Elements lose focus when wrapped.
				if ( element[ 0 ] === active || $.contains( element[ 0 ], active ) ) {
					$( active ).focus();
				}
				return parent;
			}

			return element;
		},

		setTransition: function(element, list, factor, value) {
			value = value || {};
			$.each(list, function(i, x){
				var unit = element.cssUnit(x);
				if (unit[0] > 0) value[x] = unit[0] * factor + unit[1];
			});
			return value;
		}
	});

	function _normalizeArguments(effect, options, speed, callback) {
		// shift params for method overloading
		if (typeof effect == 'object') {
			callback = options;
			speed = null;
			options = effect;
			effect = options.effect;
		}
		if ($.isFunction(options)) {
			callback = options;
			speed = null;
			options = {};
		}
		if (typeof options == 'number' || $.fx.speeds[options]) {
			callback = speed;
			speed = options;
			options = {};
		}
		if ($.isFunction(speed)) {
			callback = speed;
			speed = null;
		}

		options = options || {};

		speed = speed || options.duration;
		speed = $.fx.off ? 0 : typeof speed == 'number'
			? speed : speed in $.fx.speeds ? $.fx.speeds[speed] : $.fx.speeds._default;

		callback = callback || options.complete;

		return [effect, options, speed, callback];
	}

	function standardSpeed( speed ) {
		// valid standard speeds
		if ( !speed || typeof speed === "number" || $.fx.speeds[ speed ] ) {
			return true;
		}

		// invalid strings - treat as "normal" speed
		if ( typeof speed === "string" && !$.effects[ speed ] ) {
			return true;
		}

		return false;
	}

	$.fn.extend({
		effect: function(effect, options, speed, callback) {
			var args = _normalizeArguments.apply(this, arguments),
			// TODO: make effects take actual parameters instead of a hash
				args2 = {
					options: args[1],
					duration: args[2],
					callback: args[3]
				},
				mode = args2.options.mode,
				effectMethod = $.effects[effect];

			if ( $.fx.off || !effectMethod ) {
				// delegate to the original method (e.g., .show()) if possible
				if ( mode ) {
					return this[ mode ]( args2.duration, args2.callback );
				} else {
					return this.each(function() {
						if ( args2.callback ) {
							args2.callback.call( this );
						}
					});
				}
			}

			return effectMethod.call(this, args2);
		},

		_show: $.fn.show,
		show: function(speed) {
			if ( standardSpeed( speed ) ) {
				return this._show.apply(this, arguments);
			} else {
				var args = _normalizeArguments.apply(this, arguments);
				args[1].mode = 'show';
				return this.effect.apply(this, args);
			}
		},

		_hide: $.fn.hide,
		hide: function(speed) {
			if ( standardSpeed( speed ) ) {
				return this._hide.apply(this, arguments);
			} else {
				var args = _normalizeArguments.apply(this, arguments);
				args[1].mode = 'hide';
				return this.effect.apply(this, args);
			}
		},

		// jQuery core overloads toggle and creates _toggle
		__toggle: $.fn.toggle,
		toggle: function(speed) {
			if ( standardSpeed( speed ) || typeof speed === "boolean" || $.isFunction( speed ) ) {
				return this.__toggle.apply(this, arguments);
			} else {
				var args = _normalizeArguments.apply(this, arguments);
				args[1].mode = 'toggle';
				return this.effect.apply(this, args);
			}
		},

		// helper functions
		cssUnit: function(key) {
			var style = this.css(key), val = [];
			$.each( ['em','px','%','pt'], function(i, unit){
				if(style.indexOf(unit) > 0)
					val = [parseFloat(style), unit];
			});
			return val;
		}
	});

	var baseEasings = {};

	$.each( [ "Quad", "Cubic", "Quart", "Quint", "Expo" ], function( i, name ) {
		baseEasings[ name ] = function( p ) {
			return Math.pow( p, i + 2 );
		};
	});

	$.extend( baseEasings, {
		Sine: function ( p ) {
			return 1 - Math.cos( p * Math.PI / 2 );
		},
		Circ: function ( p ) {
			return 1 - Math.sqrt( 1 - p * p );
		},
		Elastic: function( p ) {
			return p === 0 || p === 1 ? p :
				-Math.pow( 2, 8 * (p - 1) ) * Math.sin( ( (p - 1) * 80 - 7.5 ) * Math.PI / 15 );
		},
		Back: function( p ) {
			return p * p * ( 3 * p - 2 );
		},
		Bounce: function ( p ) {
			var pow2,
				bounce = 4;

			while ( p < ( ( pow2 = Math.pow( 2, --bounce ) ) - 1 ) / 11 ) {}
			return 1 / Math.pow( 4, 3 - bounce ) - 7.5625 * Math.pow( ( pow2 * 3 - 2 ) / 22 - p, 2 );
		}
	});

	$.each( baseEasings, function( name, easeIn ) {
		$.easing[ "easeIn" + name ] = easeIn;
		$.easing[ "easeOut" + name ] = function( p ) {
			return 1 - easeIn( 1 - p );
		};
		$.easing[ "easeInOut" + name ] = function( p ) {
			return p < .5 ?
				easeIn( p * 2 ) / 2 :
				easeIn( p * -2 + 2 ) / -2 + 1;
		};
	});

	$.effects.blind = function(o) {

		return this.queue(function() {

			// Create element
			var el = $(this), props = ['position','top','bottom','left','right'];

			// Set options
			var mode = $.effects.setMode(el, o.options.mode || 'hide'); // Set Mode
			var direction = o.options.direction || 'vertical'; // Default direction

			// Adjust
			$.effects.save(el, props); el.show(); // Save & Show
			var wrapper = $.effects.createWrapper(el).css({overflow:'hidden'}); // Create Wrapper
			var ref = (direction == 'vertical') ? 'height' : 'width';
			var distance = (direction == 'vertical') ? wrapper.height() : wrapper.width();
			if(mode == 'show') wrapper.css(ref, 0); // Shift

			// Animation
			var animation = {};
			animation[ref] = mode == 'show' ? distance : 0;

			// Animate
			wrapper.animate(animation, o.duration, o.options.easing, function() {
				if(mode == 'hide') el.hide(); // Hide
				$.effects.restore(el, props); $.effects.removeWrapper(el); // Restore
				if(o.callback) o.callback.apply(el[0], arguments); // Callback
				el.dequeue();
			});

		});

	};

})(jQuery);
/*!
* @license SoundJS
* Visit http://createjs.com/ for documentation, updates and examples.
*
* Copyright (c) 2011-2013 gskinner.com, inc.
*
* Distributed under the terms of the MIT license.
* http://www.opensource.org/licenses/mit-license.html
*
* This notice shall be included in all copies or substantial portions of the Software.
*/

/**!
 * SoundJS FlashPlugin also includes swfobject (http://code.google.com/p/swfobject/)
 */

this.createjs=this.createjs||{},function(){var a=createjs.SoundJS=createjs.SoundJS||{};a.version="0.5.2",a.buildDate="Thu, 12 Dec 2013 23:33:37 GMT"}(),this.createjs=this.createjs||{},function(){"use strict";var a=function(){},b=a.prototype;a.initialize=function(a){a.addEventListener=b.addEventListener,a.on=b.on,a.removeEventListener=a.off=b.removeEventListener,a.removeAllEventListeners=b.removeAllEventListeners,a.hasEventListener=b.hasEventListener,a.dispatchEvent=b.dispatchEvent,a._dispatchEvent=b._dispatchEvent,a.willTrigger=b.willTrigger},b._listeners=null,b._captureListeners=null,b.initialize=function(){},b.addEventListener=function(a,b,c){var d;d=c?this._captureListeners=this._captureListeners||{}:this._listeners=this._listeners||{};var e=d[a];return e&&this.removeEventListener(a,b,c),e=d[a],e?e.push(b):d[a]=[b],b},b.on=function(a,b,c,d,e,f){return b.handleEvent&&(c=c||b,b=b.handleEvent),c=c||this,this.addEventListener(a,function(a){b.call(c,a,e),d&&a.remove()},f)},b.removeEventListener=function(a,b,c){var d=c?this._captureListeners:this._listeners;if(d){var e=d[a];if(e)for(var f=0,g=e.length;g>f;f++)if(e[f]==b){1==g?delete d[a]:e.splice(f,1);break}}},b.off=b.removeEventListener,b.removeAllEventListeners=function(a){a?(this._listeners&&delete this._listeners[a],this._captureListeners&&delete this._captureListeners[a]):this._listeners=this._captureListeners=null},b.dispatchEvent=function(a,b){if("string"==typeof a){var c=this._listeners;if(!c||!c[a])return!1;a=new createjs.Event(a)}if(a.target=b||this,a.bubbles&&this.parent){for(var d=this,e=[d];d.parent;)e.push(d=d.parent);var f,g=e.length;for(f=g-1;f>=0&&!a.propagationStopped;f--)e[f]._dispatchEvent(a,1+(0==f));for(f=1;g>f&&!a.propagationStopped;f++)e[f]._dispatchEvent(a,3)}else this._dispatchEvent(a,2);return a.defaultPrevented},b.hasEventListener=function(a){var b=this._listeners,c=this._captureListeners;return!!(b&&b[a]||c&&c[a])},b.willTrigger=function(a){for(var b=this;b;){if(b.hasEventListener(a))return!0;b=b.parent}return!1},b.toString=function(){return"[EventDispatcher]"},b._dispatchEvent=function(a,b){var c,d=1==b?this._captureListeners:this._listeners;if(a&&d){var e=d[a.type];if(!e||!(c=e.length))return;a.currentTarget=this,a.eventPhase=b,a.removed=!1,e=e.slice();for(var f=0;c>f&&!a.immediatePropagationStopped;f++){var g=e[f];g.handleEvent?g.handleEvent(a):g(a),a.removed&&(this.off(a.type,g,1==b),a.removed=!1)}}},createjs.EventDispatcher=a}(),this.createjs=this.createjs||{},function(){"use strict";var a=function(a,b,c){this.initialize(a,b,c)},b=a.prototype;b.type=null,b.target=null,b.currentTarget=null,b.eventPhase=0,b.bubbles=!1,b.cancelable=!1,b.timeStamp=0,b.defaultPrevented=!1,b.propagationStopped=!1,b.immediatePropagationStopped=!1,b.removed=!1,b.initialize=function(a,b,c){this.type=a,this.bubbles=b,this.cancelable=c,this.timeStamp=(new Date).getTime()},b.preventDefault=function(){this.defaultPrevented=!0},b.stopPropagation=function(){this.propagationStopped=!0},b.stopImmediatePropagation=function(){this.immediatePropagationStopped=this.propagationStopped=!0},b.remove=function(){this.removed=!0},b.clone=function(){return new a(this.type,this.bubbles,this.cancelable)},b.toString=function(){return"[Event (type="+this.type+")]"},createjs.Event=a}(),this.createjs=this.createjs||{},function(){"use strict";createjs.indexOf=function(a,b){for(var c=0,d=a.length;d>c;c++)if(b===a[c])return c;return-1}}(),this.createjs=this.createjs||{},function(){"use strict";createjs.proxy=function(a,b){var c=Array.prototype.slice.call(arguments,2);return function(){return a.apply(b,Array.prototype.slice.call(arguments,0).concat(c))}}}(),this.createjs=this.createjs||{},function(){"use strict";function a(){throw"Sound cannot be instantiated"}function b(a,b){this.init(a,b)}function c(){this.isDefault=!0,this.addEventListener=this.removeEventListener=this.removeAllEventListeners=this.dispatchEvent=this.hasEventListener=this._listeners=this._interrupt=this._playFailed=this.pause=this.resume=this.play=this._beginPlaying=this._cleanUp=this.stop=this.setMasterVolume=this.setVolume=this.mute=this.setMute=this.getMute=this.setPan=this.getPosition=this.setPosition=this.playFailed=function(){return!1},this.getVolume=this.getPan=this.getDuration=function(){return 0},this.playState=a.PLAY_FAILED,this.toString=function(){return"[Sound Default Sound Instance]"}}function d(){}var e=a;e.DELIMITER="|",e.INTERRUPT_ANY="any",e.INTERRUPT_EARLY="early",e.INTERRUPT_LATE="late",e.INTERRUPT_NONE="none",e.PLAY_INITED="playInited",e.PLAY_SUCCEEDED="playSucceeded",e.PLAY_INTERRUPTED="playInterrupted",e.PLAY_FINISHED="playFinished",e.PLAY_FAILED="playFailed",e.SUPPORTED_EXTENSIONS=["mp3","ogg","mpeg","wav","m4a","mp4","aiff","wma","mid"],e.EXTENSION_MAP={m4a:"mp4"},e.FILE_PATTERN=/^(?:(\w+:)\/{2}(\w+(?:\.\w+)*\/?))?([/.]*?(?:[^?]+)?\/)?((?:[^/?]+)\.(\w+))(?:\?(\S+)?)?$/,e.defaultInterruptBehavior=e.INTERRUPT_NONE,e.alternateExtensions=[],e._lastID=0,e.activePlugin=null,e._pluginsRegistered=!1,e._masterVolume=1,e._masterMute=!1,e._instances=[],e._idHash={},e._preloadHash={},e._defaultSoundInstance=null,e.addEventListener=null,e.removeEventListener=null,e.removeAllEventListeners=null,e.dispatchEvent=null,e.hasEventListener=null,e._listeners=null,createjs.EventDispatcher.initialize(e),e._sendFileLoadEvent=function(a){if(e._preloadHash[a])for(var b=0,c=e._preloadHash[a].length;c>b;b++){var d=e._preloadHash[a][b];if(e._preloadHash[a][b]=!0,e.hasEventListener("fileload")){var f=new createjs.Event("fileload");f.src=d.src,f.id=d.id,f.data=d.data,e.dispatchEvent(f)}}},e.getPreloadHandlers=function(){return{callback:createjs.proxy(e.initLoad,e),types:["sound"],extensions:e.SUPPORTED_EXTENSIONS}},e.registerPlugin=function(a){try{console.log("createjs.Sound.registerPlugin has been deprecated. Please use registerPlugins.")}catch(b){}return e._registerPlugin(a)},e._registerPlugin=function(a){return e._pluginsRegistered=!0,null==a?!1:a.isSupported()?(e.activePlugin=new a,!0):!1},e.registerPlugins=function(a){for(var b=0,c=a.length;c>b;b++){var d=a[b];if(e._registerPlugin(d))return!0}return!1},e.initializeDefaultPlugins=function(){return null!=e.activePlugin?!0:e._pluginsRegistered?!1:e.registerPlugins([createjs.WebAudioPlugin,createjs.HTMLAudioPlugin])?!0:!1},e.isReady=function(){return null!=e.activePlugin},e.getCapabilities=function(){return null==e.activePlugin?null:e.activePlugin._capabilities},e.getCapability=function(a){return null==e.activePlugin?null:e.activePlugin._capabilities[a]},e.initLoad=function(a,b,c,d,f){a=a.replace(f,"");var g=e.registerSound(a,c,d,!1,f);return null==g?!1:g},e.registerSound=function(a,c,d,f,g){if(!e.initializeDefaultPlugins())return!1;if(a instanceof Object&&(g=c,c=a.id,d=a.data,a=a.src),e.alternateExtensions.length)var h=e._parsePath2(a,"sound",c,d);else var h=e._parsePath(a,"sound",c,d);if(null==h)return!1;null!=g&&(a=g+a,h.src=g+h.src),null!=c&&(e._idHash[c]=h.src);var i=null;null!=d&&(isNaN(d.channels)?isNaN(d)||(i=parseInt(d)):i=parseInt(d.channels));var j=e.activePlugin.register(h.src,i);if(null!=j&&(null!=j.numChannels&&(i=j.numChannels),b.create(h.src,i),null!=d&&isNaN(d)?d.channels=h.data.channels=i||b.maxPerChannel():d=h.data=i||b.maxPerChannel(),null!=j.tag?h.tag=j.tag:j.src&&(h.src=j.src),null!=j.completeHandler&&(h.completeHandler=j.completeHandler),j.type&&(h.type=j.type)),0!=f)if(e._preloadHash[h.src]||(e._preloadHash[h.src]=[]),e._preloadHash[h.src].push({src:a,id:c,data:d}),1==e._preloadHash[h.src].length)e.activePlugin.preload(h.src,j);else if(1==e._preloadHash[h.src][0])return!0;return h},e.registerManifest=function(a,b){for(var c=[],d=0,e=a.length;e>d;d++)c[d]=createjs.Sound.registerSound(a[d].src,a[d].id,a[d].data,a[d].preload,b);return c},e.removeSound=function(a,c){if(null==e.activePlugin)return!1;if(a instanceof Object&&(a=a.src),a=e._getSrcById(a),e.alternateExtensions.length)var d=e._parsePath2(a);else var d=e._parsePath(a);if(null==d)return!1;null!=c&&(d.src=c+d.src),a=d.src;for(var f in e._idHash)e._idHash[f]==a&&delete e._idHash[f];return b.removeSrc(a),delete e._preloadHash[a],e.activePlugin.removeSound(a),!0},e.removeManifest=function(a,b){for(var c=[],d=0,e=a.length;e>d;d++)c[d]=createjs.Sound.removeSound(a[d].src,b);return c},e.removeAllSounds=function(){e._idHash={},e._preloadHash={},b.removeAll(),e.activePlugin.removeAllSounds()},e.loadComplete=function(a){if(e.alternateExtensions.length)var b=e._parsePath2(a,"sound");else var b=e._parsePath(a,"sound");return a=b?e._getSrcById(b.src):e._getSrcById(a),1==e._preloadHash[a][0]},e._parsePath=function(a,b,c,d){"string"!=typeof a&&(a=a.toString());var f=a.split(e.DELIMITER);if(f.length>1)try{console.log('createjs.Sound.DELIMITER "|" loading approach has been deprecated. Please use the new alternateExtensions property.')}catch(g){}for(var h={type:b||"sound",id:c,data:d},i=e.getCapabilities(),j=0,k=f.length;k>j;j++){var l=f[j],m=l.match(e.FILE_PATTERN);if(null==m)return!1;var n=m[4],o=m[5];if(i[o]&&createjs.indexOf(e.SUPPORTED_EXTENSIONS,o)>-1)return h.name=n,h.src=l,h.extension=o,h}return null},e._parsePath2=function(a,b,c,d){"string"!=typeof a&&(a=a.toString());var f=a.match(e.FILE_PATTERN);if(null==f)return!1;for(var g=f[4],h=f[5],i=e.getCapabilities(),j=0;!i[h];)if(h=e.alternateExtensions[j++],j>e.alternateExtensions.length)return null;a=a.replace("."+f[5],"."+h);var k={type:b||"sound",id:c,data:d};return k.name=g,k.src=a,k.extension=h,k},e.play=function(a,b,c,d,f,g,h){var i=e.createInstance(a),j=e._playInstance(i,b,c,d,f,g,h);return j||i.playFailed(),i},e.createInstance=function(c){if(!e.initializeDefaultPlugins())return e._defaultSoundInstance;if(c=e._getSrcById(c),e.alternateExtensions.length)var d=e._parsePath2(c,"sound");else var d=e._parsePath(c,"sound");var f=null;return null!=d&&null!=d.src?(b.create(d.src),f=e.activePlugin.create(d.src)):f=a._defaultSoundInstance,f.uniqueId=e._lastID++,f},e.setVolume=function(a){if(null==Number(a))return!1;if(a=Math.max(0,Math.min(1,a)),e._masterVolume=a,!this.activePlugin||!this.activePlugin.setVolume||!this.activePlugin.setVolume(a))for(var b=this._instances,c=0,d=b.length;d>c;c++)b[c].setMasterVolume(a)},e.getVolume=function(){return e._masterVolume},e.setMute=function(a){if(null==a||void 0==a)return!1;if(this._masterMute=a,!this.activePlugin||!this.activePlugin.setMute||!this.activePlugin.setMute(a))for(var b=this._instances,c=0,d=b.length;d>c;c++)b[c].setMasterMute(a);return!0},e.getMute=function(){return this._masterMute},e.stop=function(){for(var a=this._instances,b=a.length;b--;)a[b].stop()},e._playInstance=function(a,b,c,d,f,g,h){if(b instanceof Object&&(c=b.delay,d=b.offset,f=b.loop,g=b.volume,h=b.pan,b=b.interrupt),b=b||e.defaultInterruptBehavior,null==c&&(c=0),null==d&&(d=a.getPosition()),null==f&&(f=0),null==g&&(g=a.volume),null==h&&(h=a.pan),0==c){var i=e._beginPlaying(a,b,d,f,g,h);if(!i)return!1}else{var j=setTimeout(function(){e._beginPlaying(a,b,d,f,g,h)},c);a._delayTimeoutId=j}return this._instances.push(a),!0},e._beginPlaying=function(a,c,d,e,f,g){if(!b.add(a,c))return!1;var h=a._beginPlaying(d,e,f,g);if(!h){var i=createjs.indexOf(this._instances,a);return i>-1&&this._instances.splice(i,1),!1}return!0},e._getSrcById=function(a){return null==e._idHash||null==e._idHash[a]?a:e._idHash[a]},e._playFinished=function(a){b.remove(a);var c=createjs.indexOf(this._instances,a);c>-1&&this._instances.splice(c,1)},createjs.Sound=a,b.channels={},b.create=function(a,c){var d=b.get(a);return null==d?(b.channels[a]=new b(a,c),!0):!1},b.removeSrc=function(a){var c=b.get(a);return null==c?!1:(c.removeAll(),delete b.channels[a],!0)},b.removeAll=function(){for(var a in b.channels)b.channels[a].removeAll();b.channels={}},b.add=function(a,c){var d=b.get(a.src);return null==d?!1:d.add(a,c)},b.remove=function(a){var c=b.get(a.src);return null==c?!1:(c.remove(a),!0)},b.maxPerChannel=function(){return f.maxDefault},b.get=function(a){return b.channels[a]};var f=b.prototype;f.src=null,f.max=null,f.maxDefault=100,f.length=0,f.init=function(a,b){this.src=a,this.max=b||this.maxDefault,-1==this.max&&(this.max=this.maxDefault),this._instances=[]},f.get=function(a){return this._instances[a]},f.add=function(a,b){return this.getSlot(b,a)?(this._instances.push(a),this.length++,!0):!1},f.remove=function(a){var b=createjs.indexOf(this._instances,a);return-1==b?!1:(this._instances.splice(b,1),this.length--,!0)},f.removeAll=function(){for(var a=this.length-1;a>=0;a--)this._instances[a].stop()},f.getSlot=function(b){for(var c,d,e=0,f=this.max;f>e;e++){if(c=this.get(e),null==c)return!0;(b!=a.INTERRUPT_NONE||c.playState==a.PLAY_FINISHED)&&(0!=e?c.playState==a.PLAY_FINISHED||c.playState==a.PLAY_INTERRUPTED||c.playState==a.PLAY_FAILED?d=c:(b==a.INTERRUPT_EARLY&&c.getPosition()<d.getPosition()||b==a.INTERRUPT_LATE&&c.getPosition()>d.getPosition())&&(d=c):d=c)}return null!=d?(d._interrupt(),this.remove(d),!0):!1},f.toString=function(){return"[Sound SoundChannel]"},a._defaultSoundInstance=new c,d.init=function(){var a=window.navigator.userAgent;d.isFirefox=a.indexOf("Firefox")>-1,d.isOpera=null!=window.opera,d.isChrome=a.indexOf("Chrome")>-1,d.isIOS=a.indexOf("iPod")>-1||a.indexOf("iPhone")>-1||a.indexOf("iPad")>-1,d.isAndroid=a.indexOf("Android")>-1,d.isBlackberry=a.indexOf("Blackberry")>-1},d.init(),createjs.Sound.BrowserDetect=d}(),this.createjs=this.createjs||{},function(){"use strict";function a(){this._init()}var b=a;b._capabilities=null,b.isSupported=function(){var a=createjs.Sound.BrowserDetect.isIOS||createjs.Sound.BrowserDetect.isAndroid||createjs.Sound.BrowserDetect.isBlackberry;return"file:"!=location.protocol||a||this._isFileXHRSupported()?(b._generateCapabilities(),null==b.context?!1:!0):!1},b._isFileXHRSupported=function(){var a=!0,b=new XMLHttpRequest;try{b.open("GET","fail.fail",!1)}catch(c){return a=!1}b.onerror=function(){a=!1},b.onload=function(){a=404==this.status||200==this.status||0==this.status&&""!=this.response};try{b.send()}catch(c){a=!1}return a},b._generateCapabilities=function(){if(null==b._capabilities){var a=document.createElement("audio");if(null==a.canPlayType)return null;if(window.webkitAudioContext)b.context=new webkitAudioContext;else{if(!window.AudioContext)return null;b.context=new AudioContext}b._compatibilitySetUp(),b.playEmptySound(),b._capabilities={panning:!0,volume:!0,tracks:-1};for(var c=createjs.Sound.SUPPORTED_EXTENSIONS,d=createjs.Sound.EXTENSION_MAP,e=0,f=c.length;f>e;e++){var g=c[e],h=d[g]||g;b._capabilities[g]="no"!=a.canPlayType("audio/"+g)&&""!=a.canPlayType("audio/"+g)||"no"!=a.canPlayType("audio/"+h)&&""!=a.canPlayType("audio/"+h)}b.context.destination.numberOfChannels<2&&(b._capabilities.panning=!1),b.dynamicsCompressorNode=b.context.createDynamicsCompressor(),b.dynamicsCompressorNode.connect(b.context.destination),b.gainNode=b.context.createGain(),b.gainNode.connect(b.dynamicsCompressorNode)}},b._compatibilitySetUp=function(){if(!b.context.createGain){b.context.createGain=b.context.createGainNode;var a=b.context.createBufferSource();a.__proto__.start=a.__proto__.noteGrainOn,a.__proto__.stop=a.__proto__.noteOff,this._panningModel=0}},b.playEmptySound=function(){var a=this.context.createBuffer(1,1,22050),b=this.context.createBufferSource();b.buffer=a,b.connect(this.context.destination),b.start(0,0,0)};var c=a.prototype;c._capabilities=null,c._volume=1,c.context=null,c._panningModel="equalpower",c.dynamicsCompressorNode=null,c.gainNode=null,c._arrayBuffers=null,c._init=function(){this._capabilities=b._capabilities,this._arrayBuffers={},this.context=b.context,this.gainNode=b.gainNode,this.dynamicsCompressorNode=b.dynamicsCompressorNode},c.register=function(a){this._arrayBuffers[a]=!0;var b=new createjs.WebAudioPlugin.Loader(a,this);return{tag:b}},c.isPreloadStarted=function(a){return null!=this._arrayBuffers[a]},c.isPreloadComplete=function(a){return!(null==this._arrayBuffers[a]||1==this._arrayBuffers[a])},c.removeSound=function(a){delete this._arrayBuffers[a]},c.removeAllSounds=function(){this._arrayBuffers={}},c.addPreloadResults=function(a,b){this._arrayBuffers[a]=b},c._handlePreloadComplete=function(){createjs.Sound._sendFileLoadEvent(this.src)},c.preload=function(a){this._arrayBuffers[a]=!0;var b=new createjs.WebAudioPlugin.Loader(a,this);b.onload=this._handlePreloadComplete,b.load()},c.create=function(a){return this.isPreloadStarted(a)||this.preload(a),new createjs.WebAudioPlugin.SoundInstance(a,this)},c.setVolume=function(a){return this._volume=a,this._updateVolume(),!0},c._updateVolume=function(){var a=createjs.Sound._masterMute?0:this._volume;a!=this.gainNode.gain.value&&(this.gainNode.gain.value=a)},c.getVolume=function(){return this._volume},c.setMute=function(){return this._updateVolume(),!0},c.toString=function(){return"[WebAudioPlugin]"},createjs.WebAudioPlugin=a}(),function(){"use strict";function a(a,b){this._init(a,b)}var b=a.prototype=new createjs.EventDispatcher;b.src=null,b.uniqueId=-1,b.playState=null,b._owner=null,b._offset=0,b._delay=0,b._volume=1;try{Object.defineProperty(b,"volume",{get:function(){return this._volume},set:function(a){return null==Number(a)?!1:(a=Math.max(0,Math.min(1,a)),this._volume=a,this._updateVolume(),void 0)}})}catch(c){}b._pan=0;try{Object.defineProperty(b,"pan",{get:function(){return this._pan},set:function(a){return this._owner._capabilities.panning&&null!=Number(a)?(a=Math.max(-1,Math.min(1,a)),this._pan=a,this.panNode.setPosition(a,0,-.5),void 0):!1}})}catch(c){}b._duration=0,b._remainingLoops=0,b._delayTimeoutId=null,b._soundCompleteTimeout=null,b.gainNode=null,b.panNode=null,b.sourceNode=null,b._sourceNodeNext=null,b._muted=!1,b._paused=!1,b._startTime=0,b._endedHandler=null,b._sendEvent=function(a){var b=new createjs.Event(a);this.dispatchEvent(b)},b._init=function(a,b){this._owner=b,this.src=a,this.gainNode=this._owner.context.createGain(),this.panNode=this._owner.context.createPanner(),this.panNode.panningModel=this._owner._panningModel,this.panNode.connect(this.gainNode),this._owner.isPreloadComplete(this.src)&&(this._duration=1e3*this._owner._arrayBuffers[this.src].duration),this._endedHandler=createjs.proxy(this._handleSoundComplete,this)},b._cleanUp=function(){this.sourceNode&&this.playState==createjs.Sound.PLAY_SUCCEEDED&&(this.sourceNode=this._cleanUpAudioNode(this.sourceNode),this._sourceNodeNext=this._cleanUpAudioNode(this._sourceNodeNext)),0!=this.gainNode.numberOfOutputs&&this.gainNode.disconnect(0),clearTimeout(this._delayTimeoutId),clearTimeout(this._soundCompleteTimeout),this._startTime=0,null!=window.createjs&&createjs.Sound._playFinished(this)},b._cleanUpAudioNode=function(a){return a&&(a.stop(0),a.disconnect(this.panNode),a=null),a},b._interrupt=function(){this._cleanUp(),this.playState=createjs.Sound.PLAY_INTERRUPTED,this._paused=!1,this._sendEvent("interrupted")},b._handleSoundReady=function(){if(null!=window.createjs){if(1e3*this._offset>this.getDuration())return this.playFailed(),void 0;this._offset<0&&(this._offset=0),this.playState=createjs.Sound.PLAY_SUCCEEDED,this._paused=!1,this.gainNode.connect(this._owner.gainNode);var a=this._owner._arrayBuffers[this.src].duration;this.sourceNode=this._createAndPlayAudioNode(this._owner.context.currentTime-a,this._offset),this._duration=1e3*a,this._startTime=this.sourceNode.startTime-this._offset,this._soundCompleteTimeout=setTimeout(this._endedHandler,1e3*(a-this._offset)),0!=this._remainingLoops&&(this._sourceNodeNext=this._createAndPlayAudioNode(this._startTime,0))}},b._createAndPlayAudioNode=function(a,b){var c=this._owner.context.createBufferSource();return c.buffer=this._owner._arrayBuffers[this.src],c.connect(this.panNode),this._owner.context.currentTime,c.startTime=a+c.buffer.duration,c.start(c.startTime,b,c.buffer.duration-b),c},b.play=function(a,b,c,d,e,f){this._cleanUp(),createjs.Sound._playInstance(this,a,b,c,d,e,f)},b._beginPlaying=function(a,b,c,d){return null!=window.createjs&&this.src?(this._offset=a/1e3,this._remainingLoops=b,this.volume=c,this.pan=d,this._owner.isPreloadComplete(this.src)?(this._handleSoundReady(null),this._sendEvent("succeeded"),1):(this.playFailed(),void 0)):void 0},b.pause=function(){return this._paused||this.playState!=createjs.Sound.PLAY_SUCCEEDED?!1:(this._paused=!0,this._offset=this._owner.context.currentTime-this._startTime,this._cleanUpAudioNode(this.sourceNode),this._cleanUpAudioNode(this._sourceNodeNext),0!=this.gainNode.numberOfOutputs&&this.gainNode.disconnect(),clearTimeout(this._delayTimeoutId),clearTimeout(this._soundCompleteTimeout),!0)},b.resume=function(){return this._paused?(this._handleSoundReady(null),!0):!1},b.stop=function(){return this._cleanUp(),this.playState=createjs.Sound.PLAY_FINISHED,this._offset=0,!0},b.setVolume=function(a){return this.volume=a,!0},b._updateVolume=function(){var a=this._muted?0:this._volume;return a!=this.gainNode.gain.value?(this.gainNode.gain.value=a,!0):!1},b.getVolume=function(){return this.volume},b.setMute=function(a){return null==a||void 0==a?!1:(this._muted=a,this._updateVolume(),!0)},b.getMute=function(){return this._muted},b.setPan=function(a){return this.pan=a,this.pan!=a?!1:void 0},b.getPan=function(){return this.pan},b.getPosition=function(){if(this._paused||null==this.sourceNode)var a=this._offset;else var a=this._owner.context.currentTime-this._startTime;return 1e3*a},b.setPosition=function(a){return this._offset=a/1e3,this.sourceNode&&this.playState==createjs.Sound.PLAY_SUCCEEDED&&(this._cleanUpAudioNode(this.sourceNode),this._cleanUpAudioNode(this._sourceNodeNext),clearTimeout(this._soundCompleteTimeout)),this._paused||this.playState!=createjs.Sound.PLAY_SUCCEEDED||this._handleSoundReady(null),!0},b.getDuration=function(){return this._duration},b._handleSoundComplete=function(){return this._offset=0,0!=this._remainingLoops?(this._remainingLoops--,this._sourceNodeNext?(this._cleanUpAudioNode(this.sourceNode),this.sourceNode=this._sourceNodeNext,this._startTime=this.sourceNode.startTime,this._sourceNodeNext=this._createAndPlayAudioNode(this._startTime,0),this._soundCompleteTimeout=setTimeout(this._endedHandler,this._duration)):this._handleSoundReady(null),this._sendEvent("loop"),void 0):(null!=window.createjs&&(this._cleanUp(),this.playState=createjs.Sound.PLAY_FINISHED,this._sendEvent("complete")),void 0)},b.playFailed=function(){null!=window.createjs&&(this._cleanUp(),this.playState=createjs.Sound.PLAY_FAILED,this._sendEvent("failed"))},b.toString=function(){return"[WebAudioPlugin SoundInstance]"},createjs.WebAudioPlugin.SoundInstance=a}(),function(){"use strict";function a(a,b){this._init(a,b)}var b=a.prototype;b.request=null,b.owner=null,b.progress=-1,b.src=null,b.originalSrc=null,b.result=null,b.onload=null,b.onprogress=null,b.onError=null,b._init=function(a,b){this.src=a,this.originalSrc=a,this.owner=b},b.load=function(a){null!=a&&(this.src=a),this.request=new XMLHttpRequest,this.request.open("GET",this.src,!0),this.request.responseType="arraybuffer",this.request.onload=createjs.proxy(this.handleLoad,this),this.request.onError=createjs.proxy(this.handleError,this),this.request.onprogress=createjs.proxy(this.handleProgress,this),this.request.send()},b.handleProgress=function(a,b){this.progress=a/b,null!=this.onprogress&&this.onprogress({loaded:a,total:b,progress:this.progress})},b.handleLoad=function(){this.owner.context.decodeAudioData(this.request.response,createjs.proxy(this.handleAudioDecoded,this),createjs.proxy(this.handleError,this))},b.handleAudioDecoded=function(a){this.progress=1,this.result=a,this.src=this.originalSrc,this.owner.addPreloadResults(this.src,this.result),this.onload&&this.onload()},b.handleError=function(a){this.owner.removeSound(this.src),this.onerror&&this.onerror(a)},b.toString=function(){return"[WebAudioPlugin Loader]"},createjs.WebAudioPlugin.Loader=a}(),this.createjs=this.createjs||{},function(){"use strict";function a(){this._init()}var b=a;b.MAX_INSTANCES=30,b._AUDIO_READY="canplaythrough",b._AUDIO_ENDED="ended",b._AUDIO_SEEKED="seeked",b._AUDIO_STALLED="stalled",b._capabilities=null,b.enableIOS=!1,b.isSupported=function(){if(createjs.Sound.BrowserDetect.isIOS&&!b.enableIOS)return!1;b._generateCapabilities();var a=b.tag;return null==a||null==b._capabilities?!1:!0},b._generateCapabilities=function(){if(null==b._capabilities){var a=b.tag=document.createElement("audio");if(null==a.canPlayType)return null;b._capabilities={panning:!0,volume:!0,tracks:-1};for(var c=createjs.Sound.SUPPORTED_EXTENSIONS,d=createjs.Sound.EXTENSION_MAP,e=0,f=c.length;f>e;e++){var g=c[e],h=d[g]||g;b._capabilities[g]="no"!=a.canPlayType("audio/"+g)&&""!=a.canPlayType("audio/"+g)||"no"!=a.canPlayType("audio/"+h)&&""!=a.canPlayType("audio/"+h)}}};var c=a.prototype;c._capabilities=null,c._audioSources=null,c.defaultNumChannels=2,c.loadedHandler=null,c._init=function(){this._capabilities=b._capabilities,this._audioSources={}},c.register=function(a,b){this._audioSources[a]=!0;for(var c=createjs.HTMLAudioPlugin.TagPool.get(a),d=null,e=b||this.defaultNumChannels,f=0;e>f;f++)d=this._createTag(a),c.add(d);if(d.id=a,this.loadedHandler=createjs.proxy(this._handleTagLoad,this),d.addEventListener&&d.addEventListener("canplaythrough",this.loadedHandler),null==d.onreadystatechange)d.onreadystatechange=this.loadedHandler;else{var g=d.onreadystatechange;d.onreadystatechange=function(){g(),this.loadedHandler()}}return{tag:d,numChannels:e}},c._handleTagLoad=function(a){a.target.removeEventListener&&a.target.removeEventListener("canplaythrough",this.loadedHandler),a.target.onreadystatechange=null,a.target.src!=a.target.id&&createjs.HTMLAudioPlugin.TagPool.checkSrc(a.target.id)},c._createTag=function(a){var b=document.createElement("audio");return b.autoplay=!1,b.preload="none",b.src=a,b},c.removeSound=function(a){delete this._audioSources[a],createjs.HTMLAudioPlugin.TagPool.remove(a)},c.removeAllSounds=function(){this._audioSources={},createjs.HTMLAudioPlugin.TagPool.removeAll()},c.create=function(a){if(!this.isPreloadStarted(a)){var b=createjs.HTMLAudioPlugin.TagPool.get(a),c=this._createTag(a);c.id=a,b.add(c),this.preload(a,{tag:c})}return new createjs.HTMLAudioPlugin.SoundInstance(a,this)},c.isPreloadStarted=function(a){return null!=this._audioSources[a]},c.preload=function(a,b){this._audioSources[a]=!0,new createjs.HTMLAudioPlugin.Loader(a,b.tag)},c.toString=function(){return"[HTMLAudioPlugin]"},createjs.HTMLAudioPlugin=a}(),function(){"use strict";function a(a,b){this._init(a,b)}var b=a.prototype=new createjs.EventDispatcher;b.src=null,b.uniqueId=-1,b.playState=null,b._owner=null,b.loaded=!1,b._offset=0,b._delay=0,b._volume=1;try{Object.defineProperty(b,"volume",{get:function(){return this._volume},set:function(a){null!=Number(a)&&(a=Math.max(0,Math.min(1,a)),this._volume=a,this._updateVolume())}})}catch(c){}b.pan=0,b._duration=0,b._remainingLoops=0,b._delayTimeoutId=null,b.tag=null,b._muted=!1,b._paused=!1,b._endedHandler=null,b._readyHandler=null,b._stalledHandler=null,b.loopHandler=null,b._init=function(a,b){this.src=a,this._owner=b,this._endedHandler=createjs.proxy(this._handleSoundComplete,this),this._readyHandler=createjs.proxy(this._handleSoundReady,this),this._stalledHandler=createjs.proxy(this._handleSoundStalled,this),this.loopHandler=createjs.proxy(this.handleSoundLoop,this)},b._sendEvent=function(a){var b=new createjs.Event(a);this.dispatchEvent(b)},b._cleanUp=function(){var a=this.tag;if(null!=a){a.pause(),a.removeEventListener(createjs.HTMLAudioPlugin._AUDIO_ENDED,this._endedHandler,!1),a.removeEventListener(createjs.HTMLAudioPlugin._AUDIO_READY,this._readyHandler,!1),a.removeEventListener(createjs.HTMLAudioPlugin._AUDIO_SEEKED,this.loopHandler,!1);try{a.currentTime=0}catch(b){}createjs.HTMLAudioPlugin.TagPool.setInstance(this.src,a),this.tag=null}clearTimeout(this._delayTimeoutId),null!=window.createjs&&createjs.Sound._playFinished(this)},b._interrupt=function(){null!=this.tag&&(this.playState=createjs.Sound.PLAY_INTERRUPTED,this._cleanUp(),this._paused=!1,this._sendEvent("interrupted"))},b.play=function(a,b,c,d,e,f){this._cleanUp(),createjs.Sound._playInstance(this,a,b,c,d,e,f)},b._beginPlaying=function(a,b,c,d){if(null==window.createjs)return-1;var e=this.tag=createjs.HTMLAudioPlugin.TagPool.getInstance(this.src);return null==e?(this.playFailed(),-1):(e.addEventListener(createjs.HTMLAudioPlugin._AUDIO_ENDED,this._endedHandler,!1),this._offset=a,this.volume=c,this.pan=d,this._updateVolume(),this._remainingLoops=b,4!==e.readyState?(e.addEventListener(createjs.HTMLAudioPlugin._AUDIO_READY,this._readyHandler,!1),e.addEventListener(createjs.HTMLAudioPlugin._AUDIO_STALLED,this._stalledHandler,!1),e.preload="auto",e.load()):this._handleSoundReady(null),this._sendEvent("succeeded"),1)},b._handleSoundStalled=function(){this._cleanUp(),this._sendEvent("failed")},b._handleSoundReady=function(){if(null!=window.createjs){if(this._duration=1e3*this.tag.duration,this.playState=createjs.Sound.PLAY_SUCCEEDED,this._paused=!1,this.tag.removeEventListener(createjs.HTMLAudioPlugin._AUDIO_READY,this._readyHandler,!1),this._offset>=this.getDuration())return this.playFailed(),void 0;this._offset>0&&(this.tag.currentTime=.001*this._offset),-1==this._remainingLoops&&(this.tag.loop=!0),0!=this._remainingLoops&&(this.tag.addEventListener(createjs.HTMLAudioPlugin._AUDIO_SEEKED,this.loopHandler,!1),this.tag.loop=!0),this.tag.play()}},b.pause=function(){return this._paused||this.playState!=createjs.Sound.PLAY_SUCCEEDED||null==this.tag?!1:(this._paused=!0,this.tag.pause(),clearTimeout(this._delayTimeoutId),!0)},b.resume=function(){return this._paused&&null!=this.tag?(this._paused=!1,this.tag.play(),!0):!1},b.stop=function(){return this._offset=0,this.pause(),this.playState=createjs.Sound.PLAY_FINISHED,this._cleanUp(),!0},b.setMasterVolume=function(){return this._updateVolume(),!0},b.setVolume=function(a){return this.volume=a,!0},b._updateVolume=function(){if(null!=this.tag){var a=this._muted||createjs.Sound._masterMute?0:this._volume*createjs.Sound._masterVolume;return a!=this.tag.volume&&(this.tag.volume=a),!0}return!1},b.getVolume=function(){return this.volume},b.setMasterMute=function(){return this._updateVolume(),!0},b.setMute=function(a){return null==a||void 0==a?!1:(this._muted=a,this._updateVolume(),!0)},b.getMute=function(){return this._muted},b.setPan=function(){return!1},b.getPan=function(){return 0},b.getPosition=function(){return null==this.tag?this._offset:1e3*this.tag.currentTime},b.setPosition=function(a){if(null==this.tag)this._offset=a;else{this.tag.removeEventListener(createjs.HTMLAudioPlugin._AUDIO_SEEKED,this.loopHandler,!1);try{this.tag.currentTime=.001*a}catch(b){return!1}this.tag.addEventListener(createjs.HTMLAudioPlugin._AUDIO_SEEKED,this.loopHandler,!1)}return!0},b.getDuration=function(){return this._duration},b._handleSoundComplete=function(){this._offset=0,null!=window.createjs&&(this.playState=createjs.Sound.PLAY_FINISHED,this._cleanUp(),this._sendEvent("complete"))},b.handleSoundLoop=function(){this._offset=0,this._remainingLoops--,0==this._remainingLoops&&(this.tag.loop=!1,this.tag.removeEventListener(createjs.HTMLAudioPlugin._AUDIO_SEEKED,this.loopHandler,!1)),this._sendEvent("loop")},b.playFailed=function(){null!=window.createjs&&(this.playState=createjs.Sound.PLAY_FAILED,this._cleanUp(),this._sendEvent("failed"))},b.toString=function(){return"[HTMLAudioPlugin SoundInstance]"},createjs.HTMLAudioPlugin.SoundInstance=a}(),function(){"use strict";function a(a,b){this._init(a,b)}var b=a.prototype;b.src=null,b.tag=null,b.preloadTimer=null,b.loadedHandler=null,b._init=function(a,b){if(this.src=a,this.tag=b,this.preloadTimer=setInterval(createjs.proxy(this.preloadTick,this),200),this.loadedHandler=createjs.proxy(this.sendLoadedEvent,this),this.tag.addEventListener&&this.tag.addEventListener("canplaythrough",this.loadedHandler),null==this.tag.onreadystatechange)this.tag.onreadystatechange=createjs.proxy(this.sendLoadedEvent,this);else{var c=this.tag.onreadystatechange;this.tag.onreadystatechange=function(){c(),this.tag.onreadystatechange=createjs.proxy(this.sendLoadedEvent,this)}
}this.tag.preload="auto",this.tag.load()},b.preloadTick=function(){var a=this.tag.buffered,b=this.tag.duration;a.length>0&&a.end(0)>=b-1&&this.handleTagLoaded()},b.handleTagLoaded=function(){clearInterval(this.preloadTimer)},b.sendLoadedEvent=function(){this.tag.removeEventListener&&this.tag.removeEventListener("canplaythrough",this.loadedHandler),this.tag.onreadystatechange=null,createjs.Sound._sendFileLoadEvent(this.src)},b.toString=function(){return"[HTMLAudioPlugin Loader]"},createjs.HTMLAudioPlugin.Loader=a}(),function(){"use strict";function a(a){this._init(a)}var b=a;b.tags={},b.get=function(c){var d=b.tags[c];return null==d&&(d=b.tags[c]=new a(c)),d},b.remove=function(a){var c=b.tags[a];return null==c?!1:(c.removeAll(),delete b.tags[a],!0)},b.removeAll=function(){for(var a in b.tags)b.tags[a].removeAll();b.tags={}},b.getInstance=function(a){var c=b.tags[a];return null==c?null:c.get()},b.setInstance=function(a,c){var d=b.tags[a];return null==d?null:d.set(c)},b.checkSrc=function(a){var c=b.tags[a];return null==c?null:(c.checkSrcChange(),void 0)};var c=a.prototype;c.src=null,c.length=0,c.available=0,c.tags=null,c._init=function(a){this.src=a,this.tags=[]},c.add=function(a){this.tags.push(a),this.length++,this.available++},c.removeAll=function(){for(;this.length--;)delete this.tags[this.length];this.src=null,this.tags.length=0},c.get=function(){if(0==this.tags.length)return null;this.available=this.tags.length;var a=this.tags.pop();return null==a.parentNode&&document.body.appendChild(a),a},c.set=function(a){var b=createjs.indexOf(this.tags,a);-1==b&&this.tags.push(a),this.available=this.tags.length},c.checkSrcChange=function(){for(var a=this.tags.length-1,b=this.tags[a].src;a--;)this.tags[a].src=b},c.toString=function(){return"[HTMLAudioPlugin TagPool]"},createjs.HTMLAudioPlugin.TagPool=a}();
/*global LivepressConfig, Livepress, soundManager, console */
Livepress.sounds = (function () {
	var soundsBasePath = LivepressConfig.lp_plugin_url + "sounds/";
	var soundOn = ( 1 == LivepressConfig.sounds_default );
	var sounds = {};

	// Sound files
	var vibeslr = 'vibes_04-04LR_02-01.mp3';
	var vibesshort = 'vibes-short_09-08.mp3';
	var piano16 = 'piano_w-pad_01-16M_01-01.mp3';
	var piano17 = 'piano_w-pad_01-17M_01.mp3';

	createjs.Sound.alternateExtensions = ["mp3"];
	createjs.Sound.registerSound(soundsBasePath + vibeslr, "commentAdded");
	createjs.Sound.registerSound(soundsBasePath + vibeslr, "firstComment");
	createjs.Sound.registerSound(soundsBasePath + vibesshort, "commentReplyToUserReceived");
	createjs.Sound.registerSound(soundsBasePath + vibeslr, "commented");
	createjs.Sound.registerSound(soundsBasePath + piano17, "newPost");
	createjs.Sound.registerSound(soundsBasePath + piano16, "postUpdated");

	sounds.on = function () {
		soundOn = true;
	};

	sounds.off = function () {
		soundOn = false;
	};

	sounds.play = function(sound){
		if ( soundOn ){
			createjs.Sound.play(sound);
		}
	};

	return sounds;
}());
