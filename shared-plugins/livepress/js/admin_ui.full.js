/*! livepress -v1.2.2
 * http://livepress.com/
 * Copyright (c) 2014 LivePress, Inc.
 */
(function (jQuery) {
	jQuery(function () {
		/* WebKit hack */
		if (jQuery.support && !jQuery.support.opacity) {
			var div = document.createElement("div");
			div.style.display = "none";
			div.innerHTML = '<a href="/a" style="color:red;float:left;opacity:.5;">a</a>';
			var a = div.getElementsByTagName("a")[0];
			jQuery.support.opacity = a.style.opacity === "0.5" || a.style.opacity === "0,5";
		}
	});

	/*
	 * Private methods
	 */

	// Backwards compatibility
	var makeCompatible = function () {
		var jQuerys = jQuery.facebox.settings;

		jQuerys.loadingImage = jQuerys.loading_image || jQuerys.loadingImage;
		jQuerys.closeImage = jQuerys.close_image || jQuerys.closeImage;
		jQuerys.imageTypes = jQuerys.image_types || jQuerys.imageTypes;
		jQuerys.faceboxHtml = jQuerys.facebox_html || jQuerys.faceboxHtml;
	};

	// called one time to setup facebox on this page
	var init = function (settings) {
		if (jQuery.facebox.settings.inited) {
			return true;
		}
		else {
			jQuery.facebox.settings.inited = true;
		}

		jQuery(document).trigger('init.facebox');
		makeCompatible();

		var imageTypes = jQuery.facebox.settings.imageTypes.join('|');
		jQuery.facebox.settings.imageTypesRegexp = new RegExp("[.]" + imageTypes + 'jQuery', 'i');

		if (settings) {
			jQuery.extend(jQuery.facebox.settings, settings);
		}
		jQuery('body').append(jQuery.facebox.settings.faceboxHtml);

		var preload = [ new Image(), new Image() ];
		preload[0].src = jQuery.facebox.settings.closeImage;
		preload[1].src = jQuery.facebox.settings.loadingImage;

		jQuery('#facebox').find('.b:first, .bl, .br, .tl, .tr').each(function () {
			preload.push(new Image());
			/*jslint regexp: false */
			preload.slice(-1).src = jQuery(this).css('background-image').replace(/url\(([^)]+)\)/, 'jQuery1');
			/*jslint regexp: true */
		});

		jQuery('#facebox .close').click(jQuery.facebox.close);
		jQuery('#facebox .close_image').attr('src', jQuery.facebox.settings.closeImage);
	};

	// getPageScroll() by quirksmode.com
	var getPageScroll = function () {
		var xScroll, yScroll;
		if (self.pageYOffset) {
			yScroll = self.pageYOffset;
			xScroll = self.pageXOffset;
		} else if (document.documentElement && document.documentElement.scrollTop) { // Explorer 6 Strict
			yScroll = document.documentElement.scrollTop;
			xScroll = document.documentElement.scrollLeft;
		} else if (document.body) {// all other Explorers
			yScroll = document.body.scrollTop;
			xScroll = document.body.scrollLeft;
		}
		return [xScroll, yScroll];
	};

	// Adapted from getPageSize() by quirksmode.com
	var getPageHeight = function () {
		var windowHeight;
		if (self.innerHeight) { // all except Explorer
			windowHeight = self.innerHeight;
		} else if (document.documentElement && document.documentElement.clientHeight) { // Explorer 6 Strict Mode
			windowHeight = document.documentElement.clientHeight;
		} else if (document.body) { // other Explorers
			windowHeight = document.body.clientHeight;
		}
		return windowHeight;
	};

	var fillFaceboxFromImage = function (href, klass) {
		var image = new Image();
		image.onload = function () {
			jQuery.facebox.reveal('<div class="image"><img src="' + image.src + '" /></div>', klass);
		};
		image.src = href;
	};

	var fillFaceboxFromAjax = function (href, klass) {
		jQuery.get(href, function (data) {
			jQuery.facebox.reveal(data, klass);
		});
	};

	// Figures out what you want to display and displays it
	// formats are:
	//     div: #id
	//   image: blah.extension
	//    ajax: anything else
	var fillFaceboxFromHref = function (href, klass) {
		// div
		if (href.match(/#/)) {
			var url = window.location.href.split('#')[0];
			var target = href.replace(url, '');
			jQuery.facebox.reveal(jQuery(target).clone().show(), klass);

			// image
		} else if (href.match(jQuery.facebox.settings.imageTypesRegexp)) {
			fillFaceboxFromImage(href, klass);
			// ajax
		} else {
			fillFaceboxFromAjax(href, klass);
		}
	};

	var skipOverlay = function () {
		return jQuery.facebox.settings.overlay === false || jQuery.facebox.settings.opacity === null;
	};

	var showOverlay = function () {
		if (skipOverlay()) {
			return;
		}

		if (jQuery('facebox_overlay').length === 0) {
			jQuery("body").append('<div id="facebox_overlay" class="facebox_hide"></div>');
		}

		jQuery('#facebox_overlay').hide().addClass("facebox_overlayBG")
			.css('opacity', jQuery.facebox.settings.opacity)
			.click(function () {
				jQuery(document).trigger('close.facebox');
			})
			.fadeIn(200);
		return false;
	};

	var hideOverlay = function () {
		if (skipOverlay()) {
			return;
		}

		jQuery('#facebox_overlay').fadeOut(200, function () {
			jQuery("#facebox_overlay").removeClass("facebox_overlayBG");
			jQuery("#facebox_overlay").addClass("facebox_hide");
			jQuery("#facebox_overlay").remove();
		});

		return false;
	};

	/*
	 * Public methods
	 */

	jQuery.facebox = function (data, klass) {
		jQuery.facebox.loading();

		if (data.ajax) {
			fillFaceboxFromAjax(data.ajax);
		}
		else if (data.image) {
			fillFaceboxFromImage(data.image);
		}
		else if (data.div) {
			fillFaceboxFromHref(data.div);
		}
		else if (jQuery.isFunction(data)) {
			data.call(jQuery);
		}
		else {
			jQuery.facebox.reveal(data, klass);
		}
	};

	/*
	 * Public, jQuery.facebox methods
	 */


	jQuery.extend(jQuery.facebox, {
		settings:{
			opacity:    0,
			overlay:    true,
			// loadingImage : '/facebox/loading.gif',
			// closeImage   : '/facebox/closelabel.gif',
			imageTypes: [ 'png', 'jpg', 'jpeg', 'gif' ],
			faceboxHtml:'' +
				            '<div id="facebox" style="display:none;">' +
				            '<div class="popup">' +
				            '<table>' +
				            '<tbody>' +
				            '<tr>' +
				            '<td class="tl"/><td class="b"/><td class="tr"/>' +
				            '</tr>' +
				            '<tr>' +
				            '<td class="b"/>' +
				            '<td class="body">' +
				            '<div class="content">' +
				            '</div>' +
				            '<div class="footer">' +
				            '<a href="#" class="close">' +
				            'Close Instructions' +
				            '</a>' +
				            '</div>' +
				            '</td>' +
				            '<td class="b"/>' +
				            '</tr>' +
				            '<tr>' +
				            '<td class="bl"/><td class="b"/><td class="br"/>' +
				            '</tr>' +
				            '</tbody>' +
				            '</table>' +
				            '</div>' +
				'</div>'
		},

		setup_images:function (base_path) {
			console.log(base_path);
		},

		loading:function () {
			init();
			if (jQuery('#facebox .loading').length === 1) {
				return true;
			}
			showOverlay();

			jQuery('#facebox .content').empty();
			jQuery('#facebox .body').children().hide().end().append('<div class="loading"><img src="' + jQuery.facebox.settings.loadingImage + '"/></div>');

			jQuery('#facebox').css({
				top: getPageScroll()[1] + (getPageHeight() / 10),
				left:385.5
			}).show();

			jQuery(document).bind('keydown.facebox', function (e) {
				if (e.keyCode === 27) {
					jQuery.facebox.close();
				}
				return true;
			});
			jQuery(document).trigger('loading.facebox');
		},

		reveal:function (data, klass) {
			jQuery(document).trigger('beforeReveal.facebox');
			if (klass) {
				jQuery('#facebox .content').addClass(klass);
			}
			jQuery('#facebox .content').append(data);
			jQuery('#facebox .loading').remove();
			jQuery('#facebox .body').children().fadeIn('normal');
			jQuery('#facebox').css('left', jQuery(window).width() / 2 - (jQuery('#facebox table').width() / 2));
			jQuery(document).trigger('reveal.facebox').trigger('afterReveal.facebox');
		},

		close:function () {
			jQuery(document).trigger('close.facebox');
			return false;
		}
	});

	/*
	 * Public, jQuery.fn methods
	 */

	jQuery.fn.facebox = function (settings) {
		init(settings);

		function clickHandler () {
			jQuery.facebox.loading(true);

			// support for rel="facebox.inline_popup" syntax, to add a class
			// also supports deprecated "facebox[.inline_popup]" syntax
			var klass = this.rel.match(/facebox\[?\.(\w+)\]?/);
			if (klass) {
				klass = klass[1];
			}

			fillFaceboxFromHref(this.href, klass);
			return false;
		}

		return this.click(clickHandler);
	};

	/*
	 * Bindings
	 */

	jQuery(document).bind('close.facebox', function () {
		jQuery(document).unbind('keydown.facebox');
		jQuery('#facebox').fadeOut(function () {
			jQuery('#facebox .content').removeClass().addClass('content');
			hideOverlay();
			jQuery('#facebox .loading').remove();
		});
	});

}(jQuery));
jQuery.fn.placeholder = function (def_width) {
	function placeholder_is_supported () {
		return "placeholder" in document.createElement("input");
	}

	if (!placeholder_is_supported()) {
		var insertPlaceholder = function ($element) {
			var placeholder = jQuery("<input type='text'>").addClass('password-placeholder');
			placeholder.attr('value', $element.attr('placeholder'));
			var width = $element.width();
			if (!width) {
				width = def_width;
			}
			if (!width) {
				return;
			}
			var leftMargin = -width -
				parseInt($element.css('padding-left').replace("px", ""), 10) -
				parseInt($element.css('padding-right').replace("px", ""), 10) -
				parseInt($element.css('border-right-width').replace("px", ""), 10) -
				parseInt($element.css('border-left-width').replace("px", ""), 10);
			if (parseInt(jQuery.browser.version, 10) === 7 && jQuery.browser.msie) {
				width = $element.width() -
					parseInt($element.css('border-right-width').replace("px", ""), 10) -
					parseInt($element.css('border-left-width').replace("px", ""), 10) - 1;
			}
			placeholder.css({
				'width':      width,
				'margin-left':leftMargin
			});
			placeholder.bind('focus', function () {
				var $this = jQuery(this);
				var val = $this.val();
				$this.val('');
				$this.val(val);
				var $input = $this.prev();
				$this.hide();
				$input.trigger('focus');
			});
			$element.after(placeholder);
		};

		jQuery('form').bind('submit', function () {
			jQuery('input').each(function () {
				var $i = jQuery(this);
				if ($i.attr('value') === $i.attr('placeholder')) {
					$i.attr('value', "");
				}
				if ($i.hasClass('password-placeholder')) {
					$i.remove();
				}
			});
		});

		jQuery('input').each(function () {
			var $i = jQuery(this);
			if (typeof($i.attr('placeholder')) !== "undefined" && $i.attr('placeholder') !== "") {
				if (jQuery.trim($i.attr('value')) === "" || jQuery.trim($i.attr('value')) === $i.attr('placeholder')) {
					if ($i.attr('type') === "password") {
						insertPlaceholder($i);
						$i.css({visibility:'hidden'});
					}
					$i.attr('value', $i.attr('placeholder'));
					$i.css('color', '#BBB');
				}

				$i.bind('focus', function () {
					if ($i.attr('value') === $i.attr('placeholder')) {
						$i.attr('value', "");
						$i.css({visibility:'visible'});
						$i.css('color', 'black');
					}
				});

				$i.bind('blur', function () {
					if (jQuery.trim($i.attr('value')) === "") {
						$i.css('color', '#BBB');
						$i.attr('value', $i.attr('placeholder'));
						if ($i.parent().find('.password-placeholder').length === 0) {
							if ($i.attr('type') === "password") {
								insertPlaceholder($i);
							}
						}
						if ($i.next().is('.password-placeholder')) {
							$i.css({visibility:'hidden'});
							$i.parent().find('.password-placeholder').show();
						}
					} else {
						if ($i.next().is('.password-placeholder')) {
							$i.next().remove();
						}
					}
				});
			}
		});
	}
};
jQuery(function () {
	var $api_key = jQuery("#api_key"),
		api_key_button_id = "#api_key_submit",
		filled = false,
		sending_api_key = false;

	if ($api_key.length === 0) {
		return;
	}

	if ( undefined !== window.livepress_twitter_authorized ) {
		var lp_btn = jQuery( '#lp-post-to-twitter-change' ),
			lp_link = jQuery( '#lp-post-to-twitter-change_link' );
		if ( window.livepress_twitter_authorized ) {
			lp_btn.hide();
			lp_link.show();
		} else {
			lp_btn.show();
			lp_link.hide();
		}
	}

	// If there is already an api key, disables the box and displays the remove button
	$api_key.each(function () {
		if (this.value) {
			jQuery(this).attr("disabled", true);
			jQuery(api_key_button_id).attr("value", lp_strings.remove );
			filled = true;
		}
	});

	jQuery(api_key_button_id ).on( 'click', function (e) {
		e.preventDefault();
		if (document.getElementById("api_key").value === "") {
			return false;
		}
		if (sending_api_key === true) {
			return false;
		}
		if (filled) {
			$api_key.attr("disabled", false);
			$api_key.attr("value", "");
			jQuery(api_key_button_id).attr("value", lp_strings.check );
			filled = false;
			jQuery( 'input#submit' ).click();
			return false;
		}

		sending_api_key = true;
		jQuery(api_key_button_id).attr("value", "Sending");

		jQuery.ajax({
			url:      "./admin-ajax.php", //"<?php echo get_option("siteurl") ?>/wp-admin/admin-ajax.php",
			data:     ({
				api_key:     document.getElementById("api_key").value,
				action:      'lp_api_key_validate',
				_ajax_nonce: LivepressConfig.ajax_api_validate_nonce
			}),
			success:  function (msg) {
				if (typeof msg === 'object') {
					var $api_key_status = jQuery('#api_key_status'),
						$opt,
						key;

					if (!msg.error_api_key) {
						$api_key_status
							.html( lp_strings.authenticated )
							.attr("class", "valid_api_key");

						jQuery('.no_key_hidden').show('slow');

						filled = true;
						$api_key.attr("disabled", true);
						jQuery(api_key_button_id).attr("value", "Remove");
					} else {
						$api_key_status
							.html( lp_strings.key_not_valid )
							.attr("class", "invalid_api_key");
						filled = true;
						$api_key.attr("disabled", true);
						jQuery(api_key_button_id).attr("value", "Remove");
					}
					// Update other options in form
					for (key in msg) {
						if (msg.hasOwnProperty(key) && ($opt = jQuery("input[name=" + key + "],textarea[name=" + key + "]")).length) {
							if ($opt.length === 1) {
								if ($opt.is(":checkbox")) {
									$opt.attr("checked", !!(msg[key]));
									$opt.change();
								} else {
									$opt.val(msg[key]);
								}
							} else {
								$opt.val([ msg[key] ]);
							}
						}
					}
				} else {
					jQuery("#api_key_status")
						.html( lp_strings.unexpected )
						.attr("class", "");
					jQuery(api_key_button_id).attr("value", lp_strings.check );
				}
			},
			error:    function (XMLHttpRequest, textStatus, errorThrown) {
				jQuery("#api_key_status")
					.html( lp_strings.connection_problems )
					.attr("class", "");
			},
			complete: function (XMLHttpRequest, textStatus) {
				sending_api_key = false;
			}
		});
		return false;
	});
});

// Im bots check button and im users test message button only works with JS enabled
jQuery(function () {
	var netinfo = {},
		im_hide_edit = function () {
			var $this = jQuery(this),
				net = $this.attr('id').match(/_([a-z]+)/)[1],
				$username = jQuery("#im_bot_username_" + net),
				$password = jQuery("#im_bot_password_" + net);
			if (netinfo[net] === undefined) {
				netinfo[net] = {
					username: $username.val(),
					password: $password.val()
				};
			} else {
				if ($password.next().is('.password-placeholder:visible')) {
					// Placeholder is there, so simulate a focus to put real input
					$password.next().focus();
				}
				$username.val(netinfo[net].username);
				$password.val(netinfo[net].password);
			}

			$username.attr("disabled", true);
			$password.hide();
			jQuery("#im_bot_username_" + net + "_message").hide();
			jQuery("#check_message_" + net).show();
			$this.hide();
			jQuery("#edit_" + net).show();
			jQuery("#check_" + net).show();
		},
		im_show_edit = function () {
			var $this = jQuery(this),
				net = $this.attr('id').match(/_([a-z]+)/)[1],
				$username = jQuery("#im_bot_username_" + net),
				$password = jQuery("#im_bot_password_" + net);

			$username.attr('disabled', false);
			$password.show();
			jQuery("#im_bot_username_" + net + "_message").show();
			jQuery("#check_message_" + net).hide();
			$this.hide();
			jQuery("#cancel_" + net).show();
			jQuery("#check_" + net).hide();
		};

	jQuery(".im_check").show();
	jQuery(".im_edit").click(im_show_edit);
	jQuery(".im_cancel").click(im_hide_edit).each(im_hide_edit);
	jQuery(".im-users-test-message-button").show();
});

// Facebox
jQuery(function () {
	jQuery(".facebox").hide();
	jQuery.facebox.settings.closeImage = LivepressConfig.lp_plugin_url + '/img/facebox/closelabel.gif';
	jQuery.facebox.settings.loadingImage = LivepressConfig.lp_plugin_url + '/img/facebox/loading.gif';

	jQuery('a[rel*=facebox]').facebox({
		loading_image: LivepressConfig.lp_plugin_url + '/img/facebox/loading.gif',
		close_image:   LivepressConfig.lp_plugin_url + '/img/facebox/closelabel.gif'
	});
});

// UI - Show twitter preview avatar button
jQuery(function () {
	if (typeof get_twitter_avatar === 'function') {
		jQuery("#twitter_avatar_preview_button").click(get_twitter_avatar).show();
	}
});

// UI - Aesthesic Effects
jQuery(function () {
	jQuery('.messages').click(function () {
		jQuery(this).fadeOut('fast');
	});

	jQuery(".row:odd").addClass('odd');
	jQuery(".row:even").addClass('even');
});


jQuery(function () {
	jQuery(document).placeholder();

	var tabSwitcher = function (radio, tab) {
		var $radio = jQuery(radio);

		if ($radio.is(":checked")) {
			tab.show();
		} else {
			tab.find("input").each(function () {
				var $input = jQuery(this);
				if ($input.is(":checkbox,:radio")) {
					$input.attr("checked", false);
				}
			});
			tab.hide();
		}
	};

	jQuery("#disable_comments").bind('change', function (e) {
		var show = e.target.checked ? 'none' : 'block';
		jQuery("#comment_live_updates_default").parent().css('display', show);
	});

	jQuery("#avatar_wp").bind('change', function () {
		jQuery("#avatar_twitter").change();
	});


	jQuery("#update_author").bind('change', function () {
		var $tab = jQuery('.tabulation.author_name');
		tabSwitcher(this, $tab);
	});

	jQuery('#twitter_avatar_username').keydown(function (e) {
		if (e.keyCode === 13) {
			e.preventDefault();
			e.stopPropagation();
			if (typeof get_twitter_avatar === 'function') {
				get_twitter_avatar(this);
			}
		}
	});

	jQuery("#avatar_twitter").bind('change', function () {
		var $tab = jQuery('.tabulation.twitter_avatar_choice');
		tabSwitcher(this, $tab);
	});

	jQuery("#include_avatar").bind('change', function () {
		var $tab = jQuery('.tabulation.avatar');
		tabSwitcher(this, $tab);
	});

	jQuery("#timestamp").bind('change', function () {
		var $tab = jQuery('.tabulation.timestamp');
		tabSwitcher(this, $tab);
	});

	jQuery("#comment_notification").bind('change', function () {
		var $tab = jQuery('.tabulation.comment_notification_sound');
		tabSwitcher(this, $tab);
	});

	jQuery("#remote_post").bind('change', function () {
		var $tab = jQuery('.tabulation.remote_post');
		tabSwitcher(this, $tab);
	});

	jQuery('input.im_send_comments').each(function () {
		var $this = jQuery(this),
			net = $this.attr('id').split("_").pop(),
			enabler = function () {
				if (jQuery(this).val() !== "" && jQuery(this).val() !== jQuery(this).attr('placeholder')) {
					$this.attr("disabled", false);
				} else {
					$this.attr("disabled", true);
				}
			};
		jQuery('#im_enabled_user_' + net).change(enabler).trigger('change');
	});
});
