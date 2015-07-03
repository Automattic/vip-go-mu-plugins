/*
 * jQuery delegate plug-in v1.0
 *
 * Copyright (c) 2007 Jörn Zaefferer
 *
 * jQueryId: jquery.delegate.js 4786 2008-02-19 20:02:34Z joern.zaefferer jQuery
 *
 * Dual licensed under the MIT and GPL licenses:
 *   http://www.opensource.org/licenses/mit-license.php
 *   http://www.gnu.org/licenses/gpl.html
 */

// provides cross-browser focusin and focusout events
// IE has native support, in other browsers, use event caputuring (neither bubbles)

// provides delegate(type: String, delegate: Selector, handler: Callback) plugin for easier event delegation
// handler is only called when jQuery(event.target).is(delegate), in the scope of the jQuery-object for event.target 

// provides triggerEvent(type: String, target: Element) to trigger delegated events
;(function(jQuery) {
	jQuery.each({
		focus: 'focusin',
		blur: 'focusout'	
	}, function( original, fix ){
		jQuery.event.special[fix] = {
			setup:function() {
				if ( jQuery.browser.msie ) return false;
				this.addEventListener( original, jQuery.event.special[fix].handler, true );
			},
			teardown:function() {
				if ( jQuery.browser.msie ) return false;
				this.removeEventListener( original,
				jQuery.event.special[fix].handler, true );
			},
			handler: function(e) {
				arguments[0] = jQuery.event.fix(e);
				arguments[0].type = fix;
				return jQuery.event.handle.apply(this, arguments);
			}
		};
	});

	jQuery.extend(jQuery.fn, {
		delegate: function(type, delegate, handler) {
			return this.bind(type, function(event) {
				var target = jQuery(event.target);
				if (target.is(delegate)) {
					return handler.apply(target, arguments);
				}
			});
		},
		triggerEvent: function(type, target) {
			return this.triggerHandler(type, [jQuery.event.fix({ type: type, target: target })]);
		}
	})
})(jQuery);
