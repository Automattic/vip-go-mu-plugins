/*
 * Facebox (for jQuery)
 * version: 1.2 (05/05/2008)
 * @requires jQuery v1.2 or later
 *
 * Examples at http://famspam.com/facebox/
 *
 * Licensed under the MIT:
 *   http://www.opensource.org/licenses/mit-license.php
 *
 * Copyright 2007, 2008 Chris Wanstrath [ chris@ozmm.org ]
 *
 * Usage:
 *
 *  jQuery(document).ready(function() {
 *    jQuery('a[rel*=facebox]').facebox()
 *  })
 *
 *  <a href="#terms" rel="facebox">Terms</a>
 *    Loads the #terms div in the box
 *
 *  <a href="terms.html" rel="facebox">Terms</a>
 *    Loads the terms.html page in the box
 *
 *  <a href="terms.png" rel="facebox">Terms</a>
 *    Loads the terms.png image in the box
 *
 *
 *  You can also use it programmatically:
 *
 *    jQuery.facebox('some html')
 *
 *  The above will open a facebox with "some html" as the content.
 *
 *    jQuery.facebox(function(jQuery) {
 *      jQuery.get('blah.html', function(data) { jQuery.facebox(data) })
 *    })
 *
 *  The above will show a loading screen before the passed function is called,
 *  allowing for a better ajaxy experience.
 *
 *  The facebox function can also display an ajax page or image:
 *
 *    jQuery.facebox({ ajax: 'remote.html' })
 *    jQuery.facebox({ image: 'dude.jpg' })
 *
 *  Want to close the facebox?  Trigger the 'close.facebox' document event:
 *
 *    jQuery(document).trigger('close.facebox')
 *
 *  Facebox also has a bunch of other hooks:
 *
 *    loading.facebox
 *    beforeReveal.facebox
 *    reveal.facebox (aliased as 'afterReveal.facebox')
 *    init.facebox
 *
 *  Simply bind a function to any of these hooks:
 *
 *   jQuery(document).bind('reveal.facebox', function() { ...stuff to do after the facebox and contents are revealed... })
 *
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