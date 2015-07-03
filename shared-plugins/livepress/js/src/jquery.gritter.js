/*jslint white: false, onevar: false, nomen: false, plusplus: false, browser: true */
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

