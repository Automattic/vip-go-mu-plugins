// ColorBox v1.3.20.1 - jQuery lightbox plugin
// (c) 2011 Jack Moore - jacklmoore.com
// License: http://www.opensource.org/licenses/mit-license.php
(function ($, document, window) {
	var
	// Default settings object.
	// See http://jacklmoore.com/colorbox for details.
	defaults = {
		transition: "elastic",
		speed: 300,
		width: false,
		initialWidth: "600",
		innerWidth: false,
		maxWidth: false,
		height: false,
		initialHeight: "450",
		innerHeight: false,
		maxHeight: false,
		scalePhotos: true,
		scrolling: true,
		inline: false,
		html: false,
		iframe: false,
		fastIframe: true,
		photo: false,
		href: false,
		title: false,
		rel: false,
		opacity: 0.9,
		preloading: true,

		current: "image {current} of {total}",
		previous: "previous",
		next: "next",
		close: "close",
		xhrError: "This content failed to load.",
		imgError: "This image failed to load.",

		open: false,
		returnFocus: true,
		reposition: true,
		loop: true,
		slideshow: false,
		slideshowAuto: true,
		slideshowSpeed: 2500,
		slideshowStart: "start slideshow",
		slideshowStop: "stop slideshow",
		onOpen: false,
		onLoad: false,
		onComplete: false,
		onCleanup: false,
		onClosed: false,
		overlayClose: true,
		escKey: true,
		arrowKey: true,
		top: false,
		bottom: false,
		left: false,
		right: false,
		fixed: false,
		data: undefined
	},
	
	// Abstracting the HTML and event identifiers for easy rebranding
	colorbox = 'colorbox',
	prefix = 'cbox',
	boxElement = prefix + 'Element',
	
	// Events
	event_open = prefix + '_open',
	event_load = prefix + '_load',
	event_complete = prefix + '_complete',
	event_cleanup = prefix + '_cleanup',
	event_closed = prefix + '_closed',
	event_purge = prefix + '_purge',
	
	// Special Handling for IE
	isIE = !$.support.opacity && !$.support.style, // IE7 & IE8
	isIE6 = isIE && !window.XMLHttpRequest, // IE6
	event_ie6 = prefix + '_IE6',

	// Cached jQuery Object Variables
	$overlay,
	$box,
	$wrap,
	$content,
	$topBorder,
	$leftBorder,
	$rightBorder,
	$bottomBorder,
	$related,
	$window,
	$loaded,
	$loadingBay,
	$loadingOverlay,
	$title,
	$current,
	$slideshow,
	$next,
	$prev,
	$close,
	$groupControls,
	
	// Variables for cached values or use across multiple functions
	settings,
	interfaceHeight,
	interfaceWidth,
	loadedHeight,
	loadedWidth,
	element,
	index,
	photo,
	open,
	active,
	closing,
	loadingTimer,
	publicMethod,
	div = "div",
	init;

	// ****************
	// HELPER FUNCTIONS
	// ****************
	
	// Convience function for creating new jQuery objects
	function $tag(tag, id, css) {
		var element = document.createElement(tag);

		if (id) {
			element.id = prefix + id;
		}

		if (css) {
			element.style.cssText = css;
		}

		return $(element);
	}

	// Determine the next and previous members in a group.
	function getIndex(increment) {
		var
		max = $related.length,
		newIndex = (index + increment) % max;
		
		return (newIndex < 0) ? max + newIndex : newIndex;
	}

	// Convert '%' and 'px' values to integers
	function setSize(size, dimension) {
		return Math.round((/%/.test(size) ? ((dimension === 'x' ? winWidth() : winHeight()) / 100) : 1) * parseInt(size, 10));
	}
	
	// Checks an href to see if it is a photo.
	// There is a force photo option (photo: true) for hrefs that cannot be matched by this regex.
	function isImage(url) {
		return settings.photo || /\.(gif|png|jp(e|g|eg)|bmp|ico)((#|\?).*)?$/i.test(url);
	}
	
	function winWidth() {
		// $(window).width() is incorrect for some mobile browsers, but
		// window.innerWidth is unsupported in IE8 and lower.
		return window.innerWidth || $window.width();
	}

	function winHeight() {
		return window.innerHeight || $window.height();
	}

	// Assigns function results to their respective properties
	function makeSettings() {
		var i,
			data = $.data(element, colorbox);
		
		if (data == null) {
			settings = $.extend({}, defaults);
			if (console && console.log) {
				console.log('Error: cboxElement missing settings object');
			}
		} else {
			settings = $.extend({}, data);
		}
		
		for (i in settings) {
			if ($.isFunction(settings[i]) && i.slice(0, 2) !== 'on') { // checks to make sure the function isn't one of the callbacks, they will be handled at the appropriate time.
				settings[i] = settings[i].call(element);
			}
		}
		
		settings.rel = settings.rel || element.rel || $(element).data('rel') || 'nofollow';
		settings.href = settings.href || $(element).attr('href');
		settings.title = settings.title || element.title;
		
		if (typeof settings.href === "string") {
			settings.href = $.trim(settings.href);
		}
	}

	function trigger(event, callback) {
		$.event.trigger(event);
		if (callback) {
			callback.call(element);
		}
	}

	// Slideshow functionality
	function slideshow() {
		var
		timeOut,
		className = prefix + "Slideshow_",
		click = "click." + prefix,
		start,
		stop,
		clear;
		
		if (settings.slideshow && $related[1]) {
			start = function () {
				$slideshow
					.html(settings.slideshowStop)
					.unbind(click)
					.bind(event_complete, function () {
						if (settings.loop || $related[index + 1]) {
							timeOut = setTimeout(publicMethod.next, settings.slideshowSpeed);
						}
					})
					.bind(event_load, function () {
						clearTimeout(timeOut);
					})
					.one(click + ' ' + event_cleanup, stop);
				$box.removeClass(className + "off").addClass(className + "on");
				timeOut = setTimeout(publicMethod.next, settings.slideshowSpeed);
			};
			
			stop = function () {
				clearTimeout(timeOut);
				$slideshow
					.html(settings.slideshowStart)
					.unbind([event_complete, event_load, event_cleanup, click].join(' '))
					.one(click, function () {
						publicMethod.next();
						start();
					});
				$box.removeClass(className + "on").addClass(className + "off");
			};
			
			if (settings.slideshowAuto) {
				start();
			} else {
				stop();
			}
		} else {
			$box.removeClass(className + "off " + className + "on");
		}
	}

	function launch(target) {
		if (!closing) {
			
			element = target;
			
			makeSettings();
			
			$related = $(element);
			
			index = 0;
			
			if (settings.rel !== 'nofollow') {
				$related = $('.' + boxElement).filter(function () {
					var data = $.data(this, colorbox),
						relRelated;

					if (data) {
						relRelated =  $(this).data('rel') || data.rel || this.rel;
					}
					
					return (relRelated === settings.rel);
				});
				index = $related.index(element);
				
				// Check direct calls to ColorBox.
				if (index === -1) {
					$related = $related.add(element);
					index = $related.length - 1;
				}
			}
			
			if (!open) {
				open = active = true; // Prevents the page-change action from queuing up if the visitor holds down the left or right keys.
				
				$box.show();
				
				if (settings.returnFocus) {
					$(element).blur().one(event_closed, function () {
						$(this).focus();
					});
				}
				
				// +settings.opacity avoids a problem in IE when using non-zero-prefixed-string-values, like '.5'
				$overlay.css({"opacity": +settings.opacity, "cursor": settings.overlayClose ? "pointer" : "auto"}).show();
				
				// Opens inital empty ColorBox prior to content being loaded.
				settings.w = setSize(settings.initialWidth, 'x');
				settings.h = setSize(settings.initialHeight, 'y');
				publicMethod.position();
				
				if (isIE6) {
					$window.bind('resize.' + event_ie6 + ' scroll.' + event_ie6, function () {
						$overlay.css({width: winWidth(), height: winHeight(), top: $window.scrollTop(), left: $window.scrollLeft()});
					}).trigger('resize.' + event_ie6);
				}
				
				trigger(event_open, settings.onOpen);
				
				$groupControls.add($title).hide();
				
				$close.html(settings.close).show();
			}
			
			publicMethod.load(true);
		}
	}

	// ColorBox's markup needs to be added to the DOM prior to being called
	// so that the browser will go ahead and load the CSS background images.
	function appendHTML() {
		if (!$box && document.body) {
			init = false;

			$window = $(window);
			$box = $tag(div).attr({id: colorbox, 'class': isIE ? prefix + (isIE6 ? 'IE6' : 'IE') : ''}).hide();
			$overlay = $tag(div, "Overlay", isIE6 ? 'position:absolute' : '').hide();
			$loadingOverlay = $tag(div, "LoadingOverlay").add($tag(div, "LoadingGraphic"));
			$wrap = $tag(div, "Wrapper");
			$content = $tag(div, "Content").append(
				$loaded = $tag(div, "LoadedContent", 'width:0; height:0; overflow:hidden'),
				$title = $tag(div, "Title"),
				$current = $tag(div, "Current"),
				$next = $tag(div, "Next"),
				$prev = $tag(div, "Previous"),
				$slideshow = $tag(div, "Slideshow").bind(event_open, slideshow),
				$close = $tag(div, "Close")
			);
			
			$wrap.append( // The 3x3 Grid that makes up ColorBox
				$tag(div).append(
					$tag(div, "TopLeft"),
					$topBorder = $tag(div, "TopCenter"),
					$tag(div, "TopRight")
				),
				$tag(div, false, 'clear:left').append(
					$leftBorder = $tag(div, "MiddleLeft"),
					$content,
					$rightBorder = $tag(div, "MiddleRight")
				),
				$tag(div, false, 'clear:left').append(
					$tag(div, "BottomLeft"),
					$bottomBorder = $tag(div, "BottomCenter"),
					$tag(div, "BottomRight")
				)
			).find('div div').css({'float': 'left'});
			
			$loadingBay = $tag(div, false, 'position:absolute; width:9999px; visibility:hidden; display:none');
			
			$groupControls = $next.add($prev).add($current).add($slideshow);

			$(document.body).append($overlay, $box.append($wrap, $loadingBay));
		}
	}

	// Add ColorBox's event bindings
	function addBindings() {
		if ($box) {
			if (!init) {
				init = true;

				// Cache values needed for size calculations
				interfaceHeight = $topBorder.height() + $bottomBorder.height() + $content.outerHeight(true) - $content.height();//Subtraction needed for IE6
				interfaceWidth = $leftBorder.width() + $rightBorder.width() + $content.outerWidth(true) - $content.width();
				loadedHeight = $loaded.outerHeight(true);
				loadedWidth = $loaded.outerWidth(true);
				
				// Setting padding to remove the need to do size conversions during the animation step.
				$box.css({"padding-bottom": interfaceHeight, "padding-right": interfaceWidth});

				// Anonymous functions here keep the public method from being cached, thereby allowing them to be redefined on the fly.
				$next.click(function () {
					publicMethod.next();
				});
				$prev.click(function () {
					publicMethod.prev();
				});
				$close.click(function () {
					publicMethod.close();
				});
				$overlay.click(function () {
					if (settings.overlayClose) {
						publicMethod.close();
					}
				});
				
				// Key Bindings
				$(document).bind('keydown.' + prefix, function (e) {
					var key = e.keyCode;
					if (open && settings.escKey && key === 27) {
						e.preventDefault();
						publicMethod.close();
					}
					if (open && settings.arrowKey && $related[1]) {
						if (key === 37) {
							e.preventDefault();
							$prev.click();
						} else if (key === 39) {
							e.preventDefault();
							$next.click();
						}
					}
				});

				$('.' + boxElement, document).live('click', function (e) {
					// ignore non-left-mouse-clicks and clicks modified with ctrl / command, shift, or alt.
					// See: http://jacklmoore.com/notes/click-events/
					if (!(e.which > 1 || e.shiftKey || e.altKey || e.metaKey)) {
						e.preventDefault();
						launch(this);
					}
				});
			}
			return true;
		}
		return false;
	}

	// Don't do anything if ColorBox already exists.
	if ($.colorbox) {
		return;
	}

	// Append the HTML when the DOM loads
	$(appendHTML);


	// ****************
	// PUBLIC FUNCTIONS
	// Usage format: $.fn.colorbox.close();
	// Usage from within an iframe: parent.$.fn.colorbox.close();
	// ****************
	
	publicMethod = $.fn[colorbox] = $[colorbox] = function (options, callback) {
		var $this = this;
		
		options = options || {};
		
		appendHTML();

		if (addBindings()) {
			if (!$this[0]) {
				if ($this.selector) { // if a selector was given and it didn't match any elements, go ahead and exit.
					return $this;
				}
				// if no selector was given (ie. $.colorbox()), create a temporary element to work with
				$this = $('<a/>');
				options.open = true; // assume an immediate open
			}
			
			if (callback) {
				options.onComplete = callback;
			}
			
			$this.each(function () {
				$.data(this, colorbox, $.extend({}, $.data(this, colorbox) || defaults, options));
			}).addClass(boxElement);
			
			if (($.isFunction(options.open) && options.open.call($this)) || options.open) {
				launch($this[0]);
			}
		}
		
		return $this;
	};

	publicMethod.position = function (speed, loadedCallback) {
		var
		css,
		top = 0,
		left = 0,
		offset = $box.offset(),
		scrollTop,
		scrollLeft;
		
		$window.unbind('resize.' + prefix);

		// remove the modal so that it doesn't influence the document width/height
		$box.css({top: -9e4, left: -9e4});

		scrollTop = $window.scrollTop();
		scrollLeft = $window.scrollLeft();

		if (settings.fixed && !isIE6) {
			offset.top -= scrollTop;
			offset.left -= scrollLeft;
			$box.css({position: 'fixed'});
		} else {
			top = scrollTop;
			left = scrollLeft;
			$box.css({position: 'absolute'});
		}

		// keeps the top and left positions within the browser's viewport.
		if (settings.right !== false) {
			left += Math.max(winWidth() - settings.w - loadedWidth - interfaceWidth - setSize(settings.right, 'x'), 0);
		} else if (settings.left !== false) {
			left += setSize(settings.left, 'x');
		} else {
			left += Math.round(Math.max(winWidth() - settings.w - loadedWidth - interfaceWidth, 0) / 2);
		}
		
		if (settings.bottom !== false) {
			top += Math.max(winHeight() - settings.h - loadedHeight - interfaceHeight - setSize(settings.bottom, 'y'), 0);
		} else if (settings.top !== false) {
			top += setSize(settings.top, 'y');
		} else {
			top += Math.round(Math.max(winHeight() - settings.h - loadedHeight - interfaceHeight, 0) / 2);
		}

		$box.css({top: offset.top, left: offset.left});

		// setting the speed to 0 to reduce the delay between same-sized content.
		speed = ($box.width() === settings.w + loadedWidth && $box.height() === settings.h + loadedHeight) ? 0 : speed || 0;
		
		// this gives the wrapper plenty of breathing room so it's floated contents can move around smoothly,
		// but it has to be shrank down around the size of div#colorbox when it's done.  If not,
		// it can invoke an obscure IE bug when using iframes.
		$wrap[0].style.width = $wrap[0].style.height = "9999px";
		
		function modalDimensions(that) {
			$topBorder[0].style.width = $bottomBorder[0].style.width = $content[0].style.width = that.style.width;
			$content[0].style.height = $leftBorder[0].style.height = $rightBorder[0].style.height = that.style.height;
		}

		css = {width: settings.w + loadedWidth, height: settings.h + loadedHeight, top: top, left: left};
		if(speed===0){ // temporary workaround to side-step jQuery-UI 1.8 bug (http://bugs.jquery.com/ticket/12273)
			$box.css(css);
		}
		$box.dequeue().animate(css, {
			duration: speed,
			complete: function () {
				modalDimensions(this);
				
				active = false;
				
				// shrink the wrapper down to exactly the size of colorbox to avoid a bug in IE's iframe implementation.
				$wrap[0].style.width = (settings.w + loadedWidth + interfaceWidth) + "px";
				$wrap[0].style.height = (settings.h + loadedHeight + interfaceHeight) + "px";
				
				if (settings.reposition) {
					setTimeout(function () {  // small delay before binding onresize due to an IE8 bug.
						$window.bind('resize.' + prefix, publicMethod.position);
					}, 1);
				}

				if (loadedCallback) {
					loadedCallback();
				}
			},
			step: function () {
				modalDimensions(this);
			}
		});
	};

	publicMethod.resize = function (options) {
		if (open) {
			options = options || {};
			
			if (options.width) {
				settings.w = setSize(options.width, 'x') - loadedWidth - interfaceWidth;
			}
			if (options.innerWidth) {
				settings.w = setSize(options.innerWidth, 'x');
			}
			$loaded.css({width: settings.w});
			
			if (options.height) {
				settings.h = setSize(options.height, 'y') - loadedHeight - interfaceHeight;
			}
			if (options.innerHeight) {
				settings.h = setSize(options.innerHeight, 'y');
			}
			if (!options.innerHeight && !options.height) {
				$loaded.css({height: "auto"});
				settings.h = $loaded.height();
			}
			$loaded.css({height: settings.h});
			
			publicMethod.position(settings.transition === "none" ? 0 : settings.speed);
		}
	};

	publicMethod.prep = function (object) {
		if (!open) {
			return;
		}
		
		var callback, speed = settings.transition === "none" ? 0 : settings.speed;
		
		$loaded.remove();
		$loaded = $tag(div, 'LoadedContent').append(object);
		
		function getWidth() {
			settings.w = settings.w || $loaded.width();
			settings.w = settings.mw && settings.mw < settings.w ? settings.mw : settings.w;
			return settings.w;
		}
		function getHeight() {
			settings.h = settings.h || $loaded.height();
			settings.h = settings.mh && settings.mh < settings.h ? settings.mh : settings.h;
			return settings.h;
		}
		
		$loaded.hide()
		.appendTo($loadingBay.show())// content has to be appended to the DOM for accurate size calculations.
		.css({width: getWidth(), overflow: settings.scrolling ? 'auto' : 'hidden'})
		.css({height: getHeight()})// sets the height independently from the width in case the new width influences the value of height.
		.prependTo($content);
		
		$loadingBay.hide();
		
		// floating the IMG removes the bottom line-height and fixed a problem where IE miscalculates the width of the parent element as 100% of the document width.
		//$(photo).css({'float': 'none', marginLeft: 'auto', marginRight: 'auto'});
		
		$(photo).css({'float': 'none'});
		
		// Hides SELECT elements in IE6 because they would otherwise sit on top of the overlay.
		if (isIE6) {
			$('select').not($box.find('select')).filter(function () {
				return this.style.visibility !== 'hidden';
			}).css({'visibility': 'hidden'}).one(event_cleanup, function () {
				this.style.visibility = 'inherit';
			});
		}
		
		callback = function () {
			var preload,
				i,
				total = $related.length,
				iframe,
				frameBorder = 'frameBorder',
				allowTransparency = 'allowTransparency',
				complete,
				src,
				img,
				data;
			
			if (!open) {
				return;
			}
			
			function removeFilter() {
				if (isIE) {
					$box[0].style.removeAttribute('filter');
				}
			}
			
			complete = function () {
				clearTimeout(loadingTimer);
				// Detaching forces Andriod stock browser to redraw the area underneat the loading overlay.  Hiding alone isn't enough.
				$loadingOverlay.detach().hide();
				trigger(event_complete, settings.onComplete);
			};
			
			if (isIE) {
				//This fadeIn helps the bicubic resampling to kick-in.
				if (photo) {
					$loaded.fadeIn(100);
				}
			}
			
			$title.html(settings.title).add($loaded).show();
			
			if (total > 1) { // handle grouping
				if (typeof settings.current === "string") {
					$current.html(settings.current.replace('{current}', index + 1).replace('{total}', total)).show();
				}
				
				$next[(settings.loop || index < total - 1) ? "show" : "hide"]().html(settings.next);
				$prev[(settings.loop || index) ? "show" : "hide"]().html(settings.previous);
				
				if (settings.slideshow) {
					$slideshow.show();
				}
				
				// Preloads images within a rel group
				if (settings.preloading) {
					preload = [
						getIndex(-1),
						getIndex(1)
					];
					while (i = $related[preload.pop()]) {
						data = $.data(i, colorbox);
						
						if (data && data.href) {
							src = data.href;
							if ($.isFunction(src)) {
								src = src.call(i);
							}
						} else {
							src = i.href;
						}

						if (isImage(src)) {
							img = new Image();
							img.src = src;
						}
					}
				}
			} else {
				$groupControls.hide();
			}
			
			if (settings.iframe) {
				iframe = $tag('iframe')[0];
				
				if (frameBorder in iframe) {
					iframe[frameBorder] = 0;
				}
				
				if (allowTransparency in iframe) {
					iframe[allowTransparency] = "true";
				}

				if (!settings.scrolling) {
					iframe.scrolling = "no";
				}
				
				$(iframe)
					.attr({
						src: settings.href,
						name: (new Date()).getTime(), // give the iframe a unique name to prevent caching
						'class': prefix + 'Iframe',
						allowFullScreen : true, // allow HTML5 video to go fullscreen
						webkitAllowFullScreen : true,
						mozallowfullscreen : true
					})
					.one('load', complete)
					.one(event_purge, function () {
						iframe.src = "//about:blank";
					})
					.appendTo($loaded);
				
				if (settings.fastIframe) {
					$(iframe).trigger('load');
				}
			} else {
				complete();
			}
			
			if (settings.transition === 'fade') {
				$box.fadeTo(speed, 1, removeFilter);
			} else {
				removeFilter();
			}
		};
		
		if (settings.transition === 'fade') {
			$box.fadeTo(speed, 0, function () {
				publicMethod.position(0, callback);
			});
		} else {
			publicMethod.position(speed, callback);
		}
	};

	publicMethod.load = function (launched) {
		var href, setResize, prep = publicMethod.prep;
		
		active = true;
		
		photo = false;
		
		element = $related[index];
		
		if (!launched) {
			makeSettings();
		}
		
		trigger(event_purge);
		
		trigger(event_load, settings.onLoad);
		
		settings.h = settings.height ?
				setSize(settings.height, 'y') - loadedHeight - interfaceHeight :
				settings.innerHeight && setSize(settings.innerHeight, 'y');
		
		settings.w = settings.width ?
				setSize(settings.width, 'x') - loadedWidth - interfaceWidth :
				settings.innerWidth && setSize(settings.innerWidth, 'x');
		
		// Sets the minimum dimensions for use in image scaling
		settings.mw = settings.w;
		settings.mh = settings.h;
		
		// Re-evaluate the minimum width and height based on maxWidth and maxHeight values.
		// If the width or height exceed the maxWidth or maxHeight, use the maximum values instead.
		if (settings.maxWidth) {
			settings.mw = setSize(settings.maxWidth, 'x') - loadedWidth - interfaceWidth;
			settings.mw = settings.w && settings.w < settings.mw ? settings.w : settings.mw;
		}
		if (settings.maxHeight) {
			settings.mh = setSize(settings.maxHeight, 'y') - loadedHeight - interfaceHeight;
			settings.mh = settings.h && settings.h < settings.mh ? settings.h : settings.mh;
		}
		
		href = settings.href;
		
		loadingTimer = setTimeout(function () {
			$loadingOverlay.show().appendTo($content);
		}, 100);
		
		if (settings.inline) {
			// Inserts an empty placeholder where inline content is being pulled from.
			// An event is bound to put inline content back when ColorBox closes or loads new content.
			$tag(div).hide().insertBefore($(href)[0]).one(event_purge, function () {
				$(this).replaceWith($loaded.children());
			});
			prep($(href));
		} else if (settings.iframe) {
			// IFrame element won't be added to the DOM until it is ready to be displayed,
			// to avoid problems with DOM-ready JS that might be trying to run in that iframe.
			prep(" ");
		} else if (settings.html) {
			prep(settings.html);
		} else if (isImage(href)) {
			$(photo = new Image())
			.addClass(prefix + 'Photo')
			.error(function () {
				settings.title = false;
				prep($tag(div, 'Error').html(settings.imgError));
			})
			.load(function () {
				var percent;
				photo.onload = null; //stops animated gifs from firing the onload repeatedly.
				
				if (settings.scalePhotos) {
					setResize = function () {
						photo.height -= photo.height * percent;
						photo.width -= photo.width * percent;
					};
					if (settings.mw && photo.width > settings.mw) {
						percent = (photo.width - settings.mw) / photo.width;
						setResize();
					}
					if (settings.mh && photo.height > settings.mh) {
						percent = (photo.height - settings.mh) / photo.height;
						setResize();
					}
				}
				
				if (settings.h) {
					photo.style.marginTop = Math.max(settings.h - photo.height, 0) / 2 + 'px';
				}
				
				if ($related[1] && (settings.loop || $related[index + 1])) {
					photo.style.cursor = 'pointer';
					photo.onclick = function () {
						publicMethod.next();
					};
				}
				
				if (isIE) {
					photo.style.msInterpolationMode = 'bicubic';
				}
				
				setTimeout(function () { // A pause because Chrome will sometimes report a 0 by 0 size otherwise.
					prep(photo);
				}, 1);
			});
			
			setTimeout(function () { // A pause because Opera 10.6+ will sometimes not run the onload function otherwise.
				photo.src = href;
			}, 1);
		} else if (href) {
			$loadingBay.load(href, settings.data, function (data, status, xhr) {
				prep(status === 'error' ? $tag(div, 'Error').html(settings.xhrError) : $(this).contents());
			});
		}
	};
		
	// Navigates to the next page/image in a set.
	publicMethod.next = function () {
		if (!active && $related[1] && (settings.loop || $related[index + 1])) {
			index = getIndex(1);
			publicMethod.load();
		}
	};
	
	publicMethod.prev = function () {
		if (!active && $related[1] && (settings.loop || index)) {
			index = getIndex(-1);
			publicMethod.load();
		}
	};

	// Note: to use this within an iframe use the following format: parent.$.fn.colorbox.close();
	publicMethod.close = function () {
		if (open && !closing) {
			
			closing = true;
			
			open = false;
			
			trigger(event_cleanup, settings.onCleanup);
			
			$window.unbind('.' + prefix + ' .' + event_ie6);
			
			$overlay.fadeTo(200, 0);
			
			$box.stop().fadeTo(300, 0, function () {
			
				$box.add($overlay).css({'opacity': 1, cursor: 'auto'}).hide();
				
				trigger(event_purge);
				
				$loaded.remove();
				
				setTimeout(function () {
					closing = false;
					trigger(event_closed, settings.onClosed);
				}, 1);
			});
		}
	};

	// Removes changes ColorBox made to the document, but does not remove the plugin
	// from jQuery.
	publicMethod.remove = function () {
		$([]).add($box).add($overlay).remove();
		$box = null;
		$('.' + boxElement)
			.removeData(colorbox)
			.removeClass(boxElement)
			.die();
	};

	// A method for fetching the current element ColorBox is referencing.
	// returns a jQuery object.
	publicMethod.element = function () {
		return $(element);
	};

	publicMethod.settings = defaults;

}(jQuery, document, window));
(function (d, f, g, b) {
    var e = "tooltipster", c = {animation:"fade", arrow:true, arrowColor:"", content:"", delay:200, fixedWidth:0, maxWidth:0, functionBefore:function (l, m) {
        m()
    }, functionReady:function (l, m) {
    }, functionAfter:function (l) {
    }, icon:"(?)", iconDesktop:false, iconTouch:false, iconTheme:".tooltipster-icon", interactive:false, interactiveTolerance:350, offsetX:0, offsetY:0, onlyOne:true, position:"top", speed:350, timer:0, theme:".tooltipster-default", touchDevices:true, trigger:"hover", updateAnimation:true};

    function h(m, l) {
        this.element = m;
        this.options = d.extend({}, c, l);
        this._defaults = c;
        this._name = e;
        this.init()
    }

    function j() {
        return !!("ontouchstart" in f)
    }

    function a() {
        var l = g.body || g.documentElement;
        var n = l.style;
        var o = "transition";
        if (typeof n[o] == "string") {
            return true
        }
        v = ["Moz", "Webkit", "Khtml", "O", "ms"], o = o.charAt(0).toUpperCase() + o.substr(1);
        for (var m = 0; m < v.length; m++) {
            if (typeof n[v[m] + o] == "string") {
                return true
            }
        }
        return false
    }

    var k = true;
    if (!a()) {
        k = false
    }
    h.prototype = {init:function () {
        var r = d(this.element);
        var n = this;
        var q = true;
        if ((n.options.touchDevices == false) && (j())) {
            q = false
        }
        if (g.all && !g.querySelector) {
            q = false
        }
        if (q == true) {
            if ((this.options.iconDesktop == true) && (!j()) || ((this.options.iconTouch == true) && (j()))) {
                var m = r.attr("title");
                r.removeAttr("title");
                var p = n.options.iconTheme;
                var o = d('<span class="' + p.replace(".", "") + '" title="' + m + '">' + this.options.icon + "</span>");
                o.insertAfter(r);
                r.data("tooltipsterIcon", o);
                r = o
            }
            var l = d.trim(n.options.content).length > 0 ? n.options.content : r.attr("title");
            r.data("tooltipsterContent", l);
            r.removeAttr("title");
            if ((this.options.touchDevices == true) && (j())) {
                r.bind("touchstart", function (t, s) {
                    n.showTooltip()
                })
            } else {
                if (this.options.trigger == "hover") {
                    r.on("mouseenter.tooltipster", function () {
                        n.showTooltip()
                    });
                    if (this.options.interactive == true) {
                        r.on("mouseleave.tooltipster", function () {
                            var t = r.data("tooltipster");
                            var u = false;
                            if ((t !== b) && (t !== "")) {
                                t.mouseenter(function () {
                                    u = true
                                });
                                t.mouseleave(function () {
                                    u = false
                                });
                                var s = setTimeout(function () {
                                    if (u == true) {
                                        t.mouseleave(function () {
                                            n.hideTooltip()
                                        })
                                    } else {
                                        n.hideTooltip()
                                    }
                                }, n.options.interactiveTolerance)
                            } else {
                                n.hideTooltip()
                            }
                        })
                    } else {
                        r.on("mouseleave.tooltipster", function () {
                            n.hideTooltip()
                        })
                    }
                }
                if (this.options.trigger == "click") {
                    r.on("click.tooltipster", function () {
                        if ((r.data("tooltipster") == "") || (r.data("tooltipster") == b)) {
                            n.showTooltip()
                        } else {
                            n.hideTooltip()
                        }
                    })
                }
            }
        }
    }, showTooltip:function (m) {
        var n = d(this.element);
        var l = this;
        if (n.data("tooltipsterIcon") !== b) {
            n = n.data("tooltipsterIcon")
        }
        if (!n.hasClass("tooltipster-disable")) {
            if ((d(".tooltipster-base").not(".tooltipster-dying").length > 0) && (l.options.onlyOne == true)) {
                d(".tooltipster-base").not(".tooltipster-dying").not(n.data("tooltipster")).each(function () {
                    d(this).addClass("tooltipster-kill");
                    var o = d(this).data("origin");
                    o.data("plugin_tooltipster").hideTooltip()
                })
            }
            n.clearQueue().delay(l.options.delay).queue(function () {
                l.options.functionBefore(n, function () {
                    if ((n.data("tooltipster") !== b) && (n.data("tooltipster") !== "")) {
                        var w = n.data("tooltipster");
                        if (!w.hasClass("tooltipster-kill")) {
                            var s = "tooltipster-" + l.options.animation;
                            w.removeClass("tooltipster-dying");
                            if (k == true) {
                                w.clearQueue().addClass(s + "-show")
                            }
                            if (l.options.timer > 0) {
                                var q = w.data("tooltipsterTimer");
                                clearTimeout(q);
                                q = setTimeout(function () {
                                    w.data("tooltipsterTimer", b);
                                    l.hideTooltip()
                                }, l.options.timer);
                                w.data("tooltipsterTimer", q)
                            }
                            if ((l.options.touchDevices == true) && (j())) {
                                d("body").bind("touchstart", function (B) {
                                    if (l.options.interactive == true) {
                                        var D = d(B.target);
                                        var C = true;
                                        D.parents().each(function () {
                                            if (d(this).hasClass("tooltipster-base")) {
                                                C = false
                                            }
                                        });
                                        if (C == true) {
                                            l.hideTooltip();
                                            d("body").unbind("touchstart")
                                        }
                                    } else {
                                        l.hideTooltip();
                                        d("body").unbind("touchstart")
                                    }
                                })
                            }
                        }
                    } else {
                        d("body").css("overflow-x", "hidden");
                        var x = n.data("tooltipsterContent");
                        var u = l.options.theme;
                        var y = u.replace(".", "");
                        var s = "tooltipster-" + l.options.animation;
                        var r = "-webkit-transition-duration: " + l.options.speed + "ms; -webkit-animation-duration: " + l.options.speed + "ms; -moz-transition-duration: " + l.options.speed + "ms; -moz-animation-duration: " + l.options.speed + "ms; -o-transition-duration: " + l.options.speed + "ms; -o-animation-duration: " + l.options.speed + "ms; -ms-transition-duration: " + l.options.speed + "ms; -ms-animation-duration: " + l.options.speed + "ms; transition-duration: " + l.options.speed + "ms; animation-duration: " + l.options.speed + "ms;";
                        var o = l.options.fixedWidth > 0 ? "width:" + l.options.fixedWidth + "px;" : "";
                        var z = l.options.maxWidth > 0 ? "max-width:" + l.options.maxWidth + "px;" : "";
                        var t = l.options.interactive == true ? "pointer-events: auto;" : "";
                        var w = d('<div class="tooltipster-base ' + y + " " + s + '" style="' + o + " " + z + " " + t + " " + r + '"><div class="tooltipster-content">' + x + "</div></div>");
                        w.appendTo("body");
                        n.data("tooltipster", w);
                        w.data("origin", n);
                        l.positionTooltip();
                        l.options.functionReady(n, w);
                        if (k == true) {
                            w.addClass(s + "-show")
                        } else {
                            w.css("display", "none").removeClass(s).fadeIn(l.options.speed)
                        }
                        var A = x;
                        var p = setInterval(function () {
                            var B = n.data("tooltipsterContent");
                            if (d("body").find(n).length == 0) {
                                w.addClass("tooltipster-dying");
                                l.hideTooltip()
                            } else {
                                if ((A !== B) && (B !== "")) {
                                    A = B;
                                    w.find(".tooltipster-content").html(B);
                                    if (l.options.updateAnimation == true) {
                                        if (a()) {
                                            w.css({width:"", "-webkit-transition":"all " + l.options.speed + "ms, width 0ms, height 0ms, left 0ms, top 0ms", "-moz-transition":"all " + l.options.speed + "ms, width 0ms, height 0ms, left 0ms, top 0ms", "-o-transition":"all " + l.options.speed + "ms, width 0ms, height 0ms, left 0ms, top 0ms", "-ms-transition":"all " + l.options.speed + "ms, width 0ms, height 0ms, left 0ms, top 0ms", transition:"all " + l.options.speed + "ms, width 0ms, height 0ms, left 0ms, top 0ms"}).addClass("tooltipster-content-changing");
                                            setTimeout(function () {
                                                w.removeClass("tooltipster-content-changing");
                                                setTimeout(function () {
                                                    w.css({"-webkit-transition":l.options.speed + "ms", "-moz-transition":l.options.speed + "ms", "-o-transition":l.options.speed + "ms", "-ms-transition":l.options.speed + "ms", transition:l.options.speed + "ms"})
                                                }, l.options.speed)
                                            }, l.options.speed)
                                        } else {
                                            w.fadeTo(l.options.speed, 0.5, function () {
                                                w.fadeTo(l.options.speed, 1)
                                            })
                                        }
                                    }
                                    l.positionTooltip()
                                }
                            }
                            if ((d("body").find(w).length == 0) || (d("body").find(n).length == 0)) {
                                clearInterval(p)
                            }
                        }, 200);
                        if (l.options.timer > 0) {
                            var q = setTimeout(function () {
                                w.data("tooltipsterTimer", b);
                                l.hideTooltip()
                            }, l.options.timer + l.options.speed);
                            w.data("tooltipsterTimer", q)
                        }
                        if ((l.options.touchDevices == true) && (j())) {
                            d("body").bind("touchstart", function (B) {
                                if (l.options.interactive == true) {
                                    var D = d(B.target);
                                    var C = true;
                                    D.parents().each(function () {
                                        if (d(this).hasClass("tooltipster-base")) {
                                            C = false
                                        }
                                    });
                                    if (C == true) {
                                        l.hideTooltip();
                                        d("body").unbind("touchstart")
                                    }
                                } else {
                                    l.hideTooltip();
                                    d("body").unbind("touchstart")
                                }
                            })
                        }
                        w.mouseleave(function () {
                            l.hideTooltip()
                        })
                    }
                });
                n.dequeue()
            })
        }
    }, hideTooltip:function (m) {
        var p = d(this.element);
        var l = this;
        if (p.data("tooltipsterIcon") !== b) {
            p = p.data("tooltipsterIcon")
        }
        var o = p.data("tooltipster");
        if (o == b) {
            o = d(".tooltipster-dying")
        }
        p.clearQueue();
        if ((o !== b) && (o !== "")) {
            var q = o.data("tooltipsterTimer");
            if (q !== b) {
                clearTimeout(q)
            }
            var n = "tooltipster-" + l.options.animation;
            if (k == true) {
                o.clearQueue().removeClass(n + "-show").addClass("tooltipster-dying").delay(l.options.speed).queue(function () {
                    o.remove();
                    p.data("tooltipster", "");
                    d("body").css("verflow-x", "");
                    l.options.functionAfter(p)
                })
            } else {
                o.clearQueue().addClass("tooltipster-dying").fadeOut(l.options.speed, function () {
                    o.remove();
                    p.data("tooltipster", "");
                    d("body").css("verflow-x", "");
                    l.options.functionAfter(p)
                })
            }
        }
    }, positionTooltip:function (O) {
        var A = d(this.element);
        var ab = this;
        if (A.data("tooltipsterIcon") !== b) {
            A = A.data("tooltipsterIcon")
        }
        if ((A.data("tooltipster") !== b) && (A.data("tooltipster") !== "")) {
            var ah = A.data("tooltipster");
            ah.css("width", "");
            var ai = d(f).width();
            var B = A.outerWidth(false);
            var ag = A.outerHeight(false);
            var al = ah.outerWidth(false);
            var m = ah.innerWidth() + 1;
            var M = ah.outerHeight(false);
            var aa = A.offset();
            var Z = aa.top;
            var u = aa.left;
            var y = b;
            if (A.is("area")) {
                var T = A.attr("shape");
                var af = A.parent().attr("name");
                var P = d('img[usemap="#' + af + '"]');
                var n = P.offset().left;
                var L = P.offset().top;
                var W = A.attr("coords") !== b ? A.attr("coords").split(",") : b;
                if (T == "circle") {
                    var N = parseInt(W[0]);
                    var r = parseInt(W[1]);
                    var D = parseInt(W[2]);
                    ag = D * 2;
                    B = D * 2;
                    Z = L + r - D;
                    u = n + N - D
                } else {
                    if (T == "rect") {
                        var N = parseInt(W[0]);
                        var r = parseInt(W[1]);
                        var q = parseInt(W[2]);
                        var J = parseInt(W[3]);
                        ag = J - r;
                        B = q - N;
                        Z = L + r;
                        u = n + N
                    } else {
                        if (T == "poly") {
                            var x = [];
                            var ae = [];
                            var H = 0, G = 0, ad = 0, ac = 0;
                            var aj = "even";
                            for (i = 0; i < W.length; i++) {
                                var F = parseInt(W[i]);
                                if (aj == "even") {
                                    if (F > ad) {
                                        ad = F;
                                        if (i == 0) {
                                            H = ad
                                        }
                                    }
                                    if (F < H) {
                                        H = F
                                    }
                                    aj = "odd"
                                } else {
                                    if (F > ac) {
                                        ac = F;
                                        if (i == 1) {
                                            G = ac
                                        }
                                    }
                                    if (F < G) {
                                        G = F
                                    }
                                    aj = "even"
                                }
                            }
                            ag = ac - G;
                            B = ad - H;
                            Z = L + G;
                            u = n + H
                        } else {
                            ag = P.outerHeight(false);
                            B = P.outerWidth(false);
                            Z = L;
                            u = n
                        }
                    }
                }
            }
            if (ab.options.fixedWidth == 0) {
                ah.css({width:m + "px", "padding-left":"0px", "padding-right":"0px"})
            }
            var s = 0, V = 0;
            var X = parseInt(ab.options.offsetY);
            var Y = parseInt(ab.options.offsetX);
            var p = "";

            function w() {
                var an = d(f).scrollLeft();
                if ((s - an) < 0) {
                    var am = s - an;
                    s = an;
                    ah.data("arrow-reposition", am)
                }
                if (((s + al) - an) > ai) {
                    var am = s - ((ai + an) - al);
                    s = (ai + an) - al;
                    ah.data("arrow-reposition", am)
                }
            }

            function t(an, am) {
                if (((Z - d(f).scrollTop() - M - X - 12) < 0) && (am.indexOf("top") > -1)) {
                    ab.options.position = an;
                    y = am
                }
                if (((Z + ag + M + 12 + X) > (d(f).scrollTop() + d(f).height())) && (am.indexOf("bottom") > -1)) {
                    ab.options.position = an;
                    y = am;
                    V = (Z - M) - X - 12
                }
            }

            if (ab.options.position == "top") {
                var Q = (u + al) - (u + B);
                s = (u + Y) - (Q / 2);
                V = (Z - M) - X - 12;
                w();
                t("bottom", "top")
            }
            if (ab.options.position == "top-left") {
                s = u + Y;
                V = (Z - M) - X - 12;
                w();
                t("bottom-left", "top-left")
            }
            if (ab.options.position == "top-right") {
                s = (u + B + Y) - al;
                V = (Z - M) - X - 12;
                w();
                t("bottom-right", "top-right")
            }
            if (ab.options.position == "bottom") {
                var Q = (u + al) - (u + B);
                s = u - (Q / 2) + Y;
                V = (Z + ag) + X + 12;
                w();
                t("top", "bottom")
            }
            if (ab.options.position == "bottom-left") {
                s = u + Y;
                V = (Z + ag) + X + 12;
                w();
                t("top-left", "bottom-left")
            }
            if (ab.options.position == "bottom-right") {
                s = (u + B + Y) - al;
                V = (Z + ag) + X + 12;
                w();
                t("top-right", "bottom-right")
            }
            if (ab.options.position == "left") {
                s = u - Y - al - 12;
                myLeftMirror = u + Y + B + 12;
                var K = (Z + M) - (Z + A.outerHeight(false));
                V = Z - (K / 2) - X;
                if ((s < 0) && ((myLeftMirror + al) > ai)) {
                    var o = parseFloat(ah.css("border-width")) * 2;
                    var l = (al + s) - o;
                    ah.css("width", l + "px");
                    M = ah.outerHeight(false);
                    s = u - Y - l - 12 - o;
                    K = (Z + M) - (Z + A.outerHeight(false));
                    V = Z - (K / 2) - X
                } else {
                    if (s < 0) {
                        s = u + Y + B + 12;
                        ah.data("arrow-reposition", "left")
                    }
                }
            }
            if (ab.options.position == "right") {
                s = u + Y + B + 12;
                myLeftMirror = u - Y - al - 12;
                var K = (Z + M) - (Z + A.outerHeight(false));
                V = Z - (K / 2) - X;
                if (((s + al) > ai) && (myLeftMirror < 0)) {
                    var o = parseFloat(ah.css("border-width")) * 2;
                    var l = (ai - s) - o;
                    ah.css("width", l + "px");
                    M = ah.outerHeight(false);
                    K = (Z + M) - (Z + A.outerHeight(false));
                    V = Z - (K / 2) - X
                } else {
                    if ((s + al) > ai) {
                        s = u - Y - al - 12;
                        ah.data("arrow-reposition", "right")
                    }
                }
            }
            if (ab.options.arrow == true) {
                var I = "tooltipster-arrow-" + ab.options.position;
                if (ab.options.arrowColor.length < 1) {
                    var R = ah.css("background-color")
                } else {
                    var R = ab.options.arrowColor
                }
                var ak = ah.data("arrow-reposition");
                if (!ak) {
                    ak = ""
                } else {
                    if (ak == "left") {
                        I = "tooltipster-arrow-right";
                        ak = ""
                    } else {
                        if (ak == "right") {
                            I = "tooltipster-arrow-left";
                            ak = ""
                        } else {
                            ak = "left:" + ak + "px;"
                        }
                    }
                }
                if ((ab.options.position == "top") || (ab.options.position == "top-left") || (ab.options.position == "top-right")) {
                    var U = parseFloat(ah.css("border-bottom-width"));
                    var z = ah.css("border-bottom-color")
                } else {
                    if ((ab.options.position == "bottom") || (ab.options.position == "bottom-left") || (ab.options.position == "bottom-right")) {
                        var U = parseFloat(ah.css("border-top-width"));
                        var z = ah.css("border-top-color")
                    } else {
                        if (ab.options.position == "left") {
                            var U = parseFloat(ah.css("border-right-width"));
                            var z = ah.css("border-right-color")
                        } else {
                            if (ab.options.position == "right") {
                                var U = parseFloat(ah.css("border-left-width"));
                                var z = ah.css("border-left-color")
                            } else {
                                var U = parseFloat(ah.css("border-bottom-width"));
                                var z = ah.css("border-bottom-color")
                            }
                        }
                    }
                }
                if (U > 1) {
                    U++
                }
                var E = "";
                if (U !== 0) {
                    var C = "";
                    var S = "border-color: " + z + ";";
                    if (I.indexOf("bottom") !== -1) {
                        C = "margin-top: -" + U + "px;"
                    } else {
                        if (I.indexOf("top") !== -1) {
                            C = "margin-bottom: -" + U + "px;"
                        } else {
                            if (I.indexOf("left") !== -1) {
                                C = "margin-right: -" + U + "px;"
                            } else {
                                if (I.indexOf("right") !== -1) {
                                    C = "margin-left: -" + U + "px;"
                                }
                            }
                        }
                    }
                    E = '<span class="tooltipster-arrow-border" style="' + C + " " + S + ';"></span>'
                }
                ah.find(".tooltipster-arrow").remove();
                p = '<div class="' + I + ' tooltipster-arrow" style="' + ak + '">' + E + '<span style="border-color:' + R + ';"></span></div>';
                ah.append(p)
            }
            ah.css({top:V + "px", left:s + "px"});
            if (y !== b) {
                ab.options.position = y
            }
        }
    }};
    d.fn[e] = function (m) {
        if (typeof m === "string") {
            var o = this;
            var l = arguments[1];
            if (o.data("plugin_tooltipster") == b) {
                var n = o.find("*");
                o = d();
                n.each(function () {
                    if (d(this).data("plugin_tooltipster") !== b) {
                        o.push(d(this))
                    }
                })
            }
            o.each(function () {
                switch (m.toLowerCase()) {
                    case"show":
                        d(this).data("plugin_tooltipster").showTooltip();
                        break;
                    case"hide":
                        d(this).data("plugin_tooltipster").hideTooltip();
                        break;
                    case"disable":
                        d(this).addClass("tooltipster-disable");
                        break;
                    case"enable":
                        d(this).removeClass("tooltipster-disable");
                        break;
                    case"destroy":
                        d(this).data("plugin_tooltipster").hideTooltip();
                        d(this).data("plugin_tooltipster", "").attr("title", o.data("tooltipsterContent")).data("tooltipsterContent", "").data("plugin_tooltipster", "").off("mouseenter.tooltipster mouseleave.tooltipster click.tooltipster");
                        break;
                    case"update":
                        d(this).data("tooltipsterContent", l);
                        break;
                    case"reposition":
                        d(this).data("plugin_tooltipster").positionTooltip();
                        break
                }
            });
            return this
        }
        return this.each(function () {
            if (!d.data(this, "plugin_" + e)) {
                d.data(this, "plugin_" + e, new h(this, m))
            }
            var p = d(this).data("plugin_tooltipster").options;
            if ((p.iconDesktop == true) && (!j()) || ((p.iconTouch == true) && (j()))) {
                var q = d(this).data("plugin_tooltipster");
                d(this).next().data("plugin_tooltipster", q)
            }
        })
    };
    if (j()) {
        f.addEventListener("orientationchange", function () {
            if (d(".tooltipster-base").length > 0) {
                d(".tooltipster-base").each(function () {
                    var l = d(this).data("origin");
                    l.data("plugin_tooltipster").hideTooltip()
                })
            }
        }, false)
    }
    d(f).on("resize.tooltipster", function () {
        var l = d(".tooltipster-base").data("origin");
        if ((l !== null) && (l !== b)) {
            l.tooltipster("reposition")
        }
    })
})(jQuery, window, document);
/*
Copyright 2012 Igor Vaynberg

Version: 3.3.2 Timestamp: Mon Mar 25 12:14:18 PDT 2013

This software is licensed under the Apache License, Version 2.0 (the "Apache License") or the GNU
General Public License version 2 (the "GPL License"). You may choose either license to govern your
use of this software only upon the condition that you accept all of the terms of either the Apache
License or the GPL License.

You may obtain a copy of the Apache License and the GPL License at:

http://www.apache.org/licenses/LICENSE-2.0
http://www.gnu.org/licenses/gpl-2.0.html

Unless required by applicable law or agreed to in writing, software distributed under the Apache License
or the GPL Licesnse is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND,
either express or implied. See the Apache License and the GPL License for the specific language governing
permissions and limitations under the Apache License and the GPL License.
*/
(function(a){a.fn.each2===void 0&&a.fn.extend({each2:function(b){for(var c=a([0]),d=-1,e=this.length;e>++d&&(c.context=c[0]=this[d])&&b.call(c[0],d,c)!==!1;);return this}})})(jQuery),function(a,b){"use strict";function k(a,b){for(var c=0,d=b.length;d>c;c+=1)if(l(a,b[c]))return c;return-1}function l(a,c){return a===c?!0:a===b||c===b?!1:null===a||null===c?!1:a.constructor===String?a+""==c+"":c.constructor===String?c+""==a+"":!1}function m(b,c){var d,e,f;if(null===b||1>b.length)return[];for(d=b.split(c),e=0,f=d.length;f>e;e+=1)d[e]=a.trim(d[e]);return d}function n(a){return a.outerWidth(!1)-a.width()}function o(c){var d="keyup-change-value";c.bind("keydown",function(){a.data(c,d)===b&&a.data(c,d,c.val())}),c.bind("keyup",function(){var e=a.data(c,d);e!==b&&c.val()!==e&&(a.removeData(c,d),c.trigger("keyup-change"))})}function p(c){c.bind("mousemove",function(c){var d=i;(d===b||d.x!==c.pageX||d.y!==c.pageY)&&a(c.target).trigger("mousemove-filtered",c)})}function q(a,c,d){d=d||b;var e;return function(){var b=arguments;window.clearTimeout(e),e=window.setTimeout(function(){c.apply(d,b)},a)}}function r(a){var c,b=!1;return function(){return b===!1&&(c=a(),b=!0),c}}function s(a,b){var c=q(a,function(a){b.trigger("scroll-debounced",a)});b.bind("scroll",function(a){k(a.target,b.get())>=0&&c(a)})}function t(a){a[0]!==document.activeElement&&window.setTimeout(function(){var d,b=a[0],c=a.val().length;a.focus(),a.is(":visible")&&b===document.activeElement&&(b.setSelectionRange?b.setSelectionRange(c,c):b.createTextRange&&(d=b.createTextRange(),d.collapse(!1),d.select()))},0)}function u(a){a.preventDefault(),a.stopPropagation()}function v(a){a.preventDefault(),a.stopImmediatePropagation()}function w(b){if(!h){var c=b[0].currentStyle||window.getComputedStyle(b[0],null);h=a(document.createElement("div")).css({position:"absolute",left:"-10000px",top:"-10000px",display:"none",fontSize:c.fontSize,fontFamily:c.fontFamily,fontStyle:c.fontStyle,fontWeight:c.fontWeight,letterSpacing:c.letterSpacing,textTransform:c.textTransform,whiteSpace:"nowrap"}),h.attr("class","select2-sizer"),a("body").append(h)}return h.text(b.val()),h.width()}function x(b,c,d){var e,g,f=[];e=b.attr("class"),e&&(e=""+e,a(e.split(" ")).each2(function(){0===this.indexOf("select2-")&&f.push(this)})),e=c.attr("class"),e&&(e=""+e,a(e.split(" ")).each2(function(){0!==this.indexOf("select2-")&&(g=d(this),g&&f.push(this))})),b.attr("class",f.join(" "))}function y(a,c,d,e){var f=a.toUpperCase().indexOf(c.toUpperCase()),g=c.length;return 0>f?(d.push(e(a)),b):(d.push(e(a.substring(0,f))),d.push("<span class='select2-match'>"),d.push(e(a.substring(f,f+g))),d.push("</span>"),d.push(e(a.substring(f+g,a.length))),b)}function z(b){var c,d=0,e=null,f=b.quietMillis||100,g=b.url,h=this;return function(i){window.clearTimeout(c),c=window.setTimeout(function(){d+=1;var c=d,f=b.data,j=g,k=b.transport||a.ajax,l=b.type||"GET",m={};f=f?f.call(h,i.term,i.page,i.context):null,j="function"==typeof j?j.call(h,i.term,i.page,i.context):j,null!==e&&e.abort(),b.params&&(a.isFunction(b.params)?a.extend(m,b.params.call(h)):a.extend(m,b.params)),a.extend(m,{url:j,dataType:b.dataType,data:f,type:l,cache:!1,success:function(a){if(!(d>c)){var e=b.results(a,i.page);i.callback(e)}}}),e=k.call(h,m)},f)}}function A(c){var e,f,d=c,g=function(a){return""+a.text};a.isArray(d)&&(f=d,d={results:f}),a.isFunction(d)===!1&&(f=d,d=function(){return f});var h=d();return h.text&&(g=h.text,a.isFunction(g)||(e=d.text,g=function(a){return a[e]})),function(c){var h,e=c.term,f={results:[]};return""===e?(c.callback(d()),b):(h=function(b,d){var f,i;if(b=b[0],b.children){f={};for(i in b)b.hasOwnProperty(i)&&(f[i]=b[i]);f.children=[],a(b.children).each2(function(a,b){h(b,f.children)}),(f.children.length||c.matcher(e,g(f),b))&&d.push(f)}else c.matcher(e,g(b),b)&&d.push(b)},a(d().results).each2(function(a,b){h(b,f.results)}),c.callback(f),b)}}function B(c){var d=a.isFunction(c);return function(e){var f=e.term,g={results:[]};a(d?c():c).each(function(){var a=this.text!==b,c=a?this.text:this;(""===f||e.matcher(f,c))&&g.results.push(a?this:{id:this,text:this})}),e.callback(g)}}function C(b){if(a.isFunction(b))return!0;if(!b)return!1;throw Error("formatterName must be a function or a falsy value")}function D(b){return a.isFunction(b)?b():b}function E(b){var c=0;return a.each(b,function(a,b){b.children?c+=E(b.children):c++}),c}function F(a,c,d,e){var h,i,j,k,m,f=a,g=!1;if(!e.createSearchChoice||!e.tokenSeparators||1>e.tokenSeparators.length)return b;for(;;){for(i=-1,j=0,k=e.tokenSeparators.length;k>j&&(m=e.tokenSeparators[j],i=a.indexOf(m),!(i>=0));j++);if(0>i)break;if(h=a.substring(0,i),a=a.substring(i+m.length),h.length>0&&(h=e.createSearchChoice(h,c),h!==b&&null!==h&&e.id(h)!==b&&null!==e.id(h))){for(g=!1,j=0,k=c.length;k>j;j++)if(l(e.id(h),e.id(c[j]))){g=!0;break}g||d(h)}}return f!==a?a:b}function G(b,c){var d=function(){};return d.prototype=new b,d.prototype.constructor=d,d.prototype.parent=b.prototype,d.prototype=a.extend(d.prototype,c),d}if(window.Select2===b){var c,d,e,f,g,h,i,j;c={TAB:9,ENTER:13,ESC:27,SPACE:32,LEFT:37,UP:38,RIGHT:39,DOWN:40,SHIFT:16,CTRL:17,ALT:18,PAGE_UP:33,PAGE_DOWN:34,HOME:36,END:35,BACKSPACE:8,DELETE:46,isArrow:function(a){switch(a=a.which?a.which:a){case c.LEFT:case c.RIGHT:case c.UP:case c.DOWN:return!0}return!1},isControl:function(a){var b=a.which;switch(b){case c.SHIFT:case c.CTRL:case c.ALT:return!0}return a.metaKey?!0:!1},isFunctionKey:function(a){return a=a.which?a.which:a,a>=112&&123>=a}},j=a(document),g=function(){var a=1;return function(){return a++}}(),j.bind("mousemove",function(a){i={x:a.pageX,y:a.pageY}}),d=G(Object,{bind:function(a){var b=this;return function(){a.apply(b,arguments)}},init:function(c){var d,e,f=".select2-results";this.opts=c=this.prepareOpts(c),this.id=c.id,c.element.data("select2")!==b&&null!==c.element.data("select2")&&this.destroy(),this.enabled=!0,this.container=this.createContainer(),this.containerId="s2id_"+(c.element.attr("id")||"autogen"+g()),this.containerSelector="#"+this.containerId.replace(/([;&,\.\+\*\~':"\!\^#$%@\[\]\(\)=>\|])/g,"\\$1"),this.container.attr("id",this.containerId),this.body=r(function(){return c.element.closest("body")}),x(this.container,this.opts.element,this.opts.adaptContainerCssClass),this.container.css(D(c.containerCss)),this.container.addClass(D(c.containerCssClass)),this.elementTabIndex=this.opts.element.attr("tabIndex"),this.opts.element.data("select2",this).addClass("select2-offscreen").bind("focus.select2",function(){a(this).select2("focus")}).attr("tabIndex","-1").before(this.container),this.container.data("select2",this),this.dropdown=this.container.find(".select2-drop"),this.dropdown.addClass(D(c.dropdownCssClass)),this.dropdown.data("select2",this),this.results=d=this.container.find(f),this.search=e=this.container.find("input.select2-input"),e.attr("tabIndex",this.elementTabIndex),this.resultsPage=0,this.context=null,this.initContainer(),p(this.results),this.dropdown.delegate(f,"mousemove-filtered touchstart touchmove touchend",this.bind(this.highlightUnderEvent)),s(80,this.results),this.dropdown.delegate(f,"scroll-debounced",this.bind(this.loadMoreIfNeeded)),a.fn.mousewheel&&d.mousewheel(function(a,b,c,e){var f=d.scrollTop();e>0&&0>=f-e?(d.scrollTop(0),u(a)):0>e&&d.get(0).scrollHeight-d.scrollTop()+e<=d.height()&&(d.scrollTop(d.get(0).scrollHeight-d.height()),u(a))}),o(e),e.bind("keyup-change input paste",this.bind(this.updateResults)),e.bind("focus",function(){e.addClass("select2-focused")}),e.bind("blur",function(){e.removeClass("select2-focused")}),this.dropdown.delegate(f,"mouseup",this.bind(function(b){a(b.target).closest(".select2-result-selectable").length>0&&(this.highlightUnderEvent(b),this.selectHighlighted(b))})),this.dropdown.bind("click mouseup mousedown",function(a){a.stopPropagation()}),a.isFunction(this.opts.initSelection)&&(this.initSelection(),this.monitorSource()),(c.element.is(":disabled")||c.element.is("[readonly='readonly']"))&&this.disable()},destroy:function(){var a=this.opts.element.data("select2");this.propertyObserver&&(delete this.propertyObserver,this.propertyObserver=null),a!==b&&(a.container.remove(),a.dropdown.remove(),a.opts.element.removeClass("select2-offscreen").removeData("select2").unbind(".select2").attr({tabIndex:this.elementTabIndex}).show())},prepareOpts:function(c){var d,e,f,g;if(d=c.element,"select"===d.get(0).tagName.toLowerCase()&&(this.select=e=c.element),e&&a.each(["id","multiple","ajax","query","createSearchChoice","initSelection","data","tags"],function(){if(this in c)throw Error("Option '"+this+"' is not allowed for Select2 when attached to a <select> element.")}),c=a.extend({},{populateResults:function(d,e,f){var g,k=this.opts.id,l=this;g=function(d,e,h){var i,j,m,n,o,p,q,r,s,t;for(d=c.sortResults(d,e,f),i=0,j=d.length;j>i;i+=1)m=d[i],o=m.disabled===!0,n=!o&&k(m)!==b,p=m.children&&m.children.length>0,q=a("<li></li>"),q.addClass("select2-results-dept-"+h),q.addClass("select2-result"),q.addClass(n?"select2-result-selectable":"select2-result-unselectable"),o&&q.addClass("select2-disabled"),p&&q.addClass("select2-result-with-children"),q.addClass(l.opts.formatResultCssClass(m)),r=a(document.createElement("div")),r.addClass("select2-result-label"),t=c.formatResult(m,r,f,l.opts.escapeMarkup),t!==b&&r.html(t),q.append(r),p&&(s=a("<ul></ul>"),s.addClass("select2-result-sub"),g(m.children,s,h+1),q.append(s)),q.data("select2-data",m),e.append(q)},g(e,d,0)}},a.fn.select2.defaults,c),"function"!=typeof c.id&&(f=c.id,c.id=function(a){return a[f]}),a.isArray(c.element.data("select2Tags"))){if("tags"in c)throw"tags specified as both an attribute 'data-select2-tags' and in options of Select2 "+c.element.attr("id");c.tags=c.element.data("select2Tags")}if(e?(c.query=this.bind(function(c){var g,h,i,e={results:[],more:!1},f=c.term;i=function(a,b){var d;a.is("option")?c.matcher(f,a.text(),a)&&b.push({id:a.attr("value"),text:a.text(),element:a.get(),css:a.attr("class"),disabled:l(a.attr("disabled"),"disabled")}):a.is("optgroup")&&(d={text:a.attr("label"),children:[],element:a.get(),css:a.attr("class")},a.children().each2(function(a,b){i(b,d.children)}),d.children.length>0&&b.push(d))},g=d.children(),this.getPlaceholder()!==b&&g.length>0&&(h=g[0],""===a(h).text()&&(g=g.not(h))),g.each2(function(a,b){i(b,e.results)}),c.callback(e)}),c.id=function(a){return a.id},c.formatResultCssClass=function(a){return a.css}):"query"in c||("ajax"in c?(g=c.element.data("ajax-url"),g&&g.length>0&&(c.ajax.url=g),c.query=z.call(c.element,c.ajax)):"data"in c?c.query=A(c.data):"tags"in c&&(c.query=B(c.tags),c.createSearchChoice===b&&(c.createSearchChoice=function(a){return{id:a,text:a}}),c.initSelection===b&&(c.initSelection=function(d,e){var f=[];a(m(d.val(),c.separator)).each(function(){var d=this,e=this,g=c.tags;a.isFunction(g)&&(g=g()),a(g).each(function(){return l(this.id,d)?(e=this.text,!1):b}),f.push({id:d,text:e})}),e(f)}))),"function"!=typeof c.query)throw"query function not defined for Select2 "+c.element.attr("id");return c},monitorSource:function(){var b,a=this.opts.element;a.bind("change.select2",this.bind(function(){this.opts.element.data("select2-change-triggered")!==!0&&this.initSelection()})),b=this.bind(function(){var a,b;a="disabled"!==this.opts.element.attr("disabled"),b="readonly"===this.opts.element.attr("readonly"),a=a&&!b,this.enabled!==a&&(a?this.enable():this.disable()),x(this.container,this.opts.element,this.opts.adaptContainerCssClass),this.container.addClass(D(this.opts.containerCssClass)),x(this.dropdown,this.opts.element,this.opts.adaptDropdownCssClass),this.dropdown.addClass(D(this.opts.dropdownCssClass))}),a.bind("propertychange.select2 DOMAttrModified.select2",b),"undefined"!=typeof WebKitMutationObserver&&(this.propertyObserver&&(delete this.propertyObserver,this.propertyObserver=null),this.propertyObserver=new WebKitMutationObserver(function(a){a.forEach(b)}),this.propertyObserver.observe(a.get(0),{attributes:!0,subtree:!1}))},triggerChange:function(b){b=b||{},b=a.extend({},b,{type:"change",val:this.val()}),this.opts.element.data("select2-change-triggered",!0),this.opts.element.trigger(b),this.opts.element.data("select2-change-triggered",!1),this.opts.element.click(),this.opts.blurOnChange&&this.opts.element.blur()},enable:function(){this.enabled||(this.enabled=!0,this.container.removeClass("select2-container-disabled"),this.opts.element.removeAttr("disabled"))},disable:function(){this.enabled&&(this.close(),this.enabled=!1,this.container.addClass("select2-container-disabled"),this.opts.element.attr("disabled","disabled"))},opened:function(){return this.container.hasClass("select2-dropdown-open")},positionDropdown:function(){var o,p,q,b=this.container.offset(),c=this.container.outerHeight(!1),d=this.container.outerWidth(!1),e=this.dropdown.outerHeight(!1),f=a(window).scrollLeft()+a(window).width(),g=a(window).scrollTop()+a(window).height(),h=b.top+c,i=b.left,j=g>=h+e,k=b.top-e>=this.body().scrollTop(),l=this.dropdown.outerWidth(!1),m=f>=i+l,n=this.dropdown.hasClass("select2-drop-above");"static"!==this.body().css("position")&&(o=this.body().offset(),h-=o.top,i-=o.left),n?(p=!0,!k&&j&&(p=!1)):(p=!1,!j&&k&&(p=!0)),m||(i=b.left+d-l),p?(h=b.top-e,this.container.addClass("select2-drop-above"),this.dropdown.addClass("select2-drop-above")):(this.container.removeClass("select2-drop-above"),this.dropdown.removeClass("select2-drop-above")),q=a.extend({top:h,left:i,width:d},D(this.opts.dropdownCss)),this.dropdown.css(q)},shouldOpen:function(){var b;return this.opened()?!1:(b=a.Event("opening"),this.opts.element.trigger(b),!b.isDefaultPrevented())},clearDropdownAlignmentPreference:function(){this.container.removeClass("select2-drop-above"),this.dropdown.removeClass("select2-drop-above")},open:function(){return this.shouldOpen()?(window.setTimeout(this.bind(this.opening),1),!0):!1},opening:function(){function h(){return{width:Math.max(document.documentElement.scrollWidth,a(window).width()),height:Math.max(document.documentElement.scrollHeight,a(window).height())}}var f,b=this.containerId,c="scroll."+b,d="resize."+b,e="orientationchange."+b;this.clearDropdownAlignmentPreference(),this.container.addClass("select2-dropdown-open").addClass("select2-container-active"),this.dropdown[0]!==this.body().children().last()[0]&&this.dropdown.detach().appendTo(this.body()),this.updateResults(!0),f=a("#select2-drop-mask"),0==f.length&&(f=a(document.createElement("div")),f.attr("id","select2-drop-mask").attr("class","select2-drop-mask"),f.hide(),f.appendTo(this.body()),f.bind("mousedown touchstart",function(){var d,c=a("#select2-drop");c.length>0&&(d=c.data("select2"),d.opts.selectOnBlur&&d.selectHighlighted({noFocus:!0}),d.close())})),this.dropdown.prev()[0]!==f[0]&&this.dropdown.before(f),a("#select2-drop").removeAttr("id"),this.dropdown.attr("id","select2-drop"),f.css(h()),f.show(),this.dropdown.show(),this.positionDropdown(),this.dropdown.addClass("select2-drop-active"),this.ensureHighlightVisible();var g=this;this.container.parents().add(window).each(function(){a(this).bind(d+" "+c+" "+e,function(){a("#select2-drop-mask").css(h()),g.positionDropdown()})}),this.focusSearch()},close:function(){if(this.opened()){var b=this.containerId,c="scroll."+b,d="resize."+b,e="orientationchange."+b;this.container.parents().add(window).each(function(){a(this).unbind(c).unbind(d).unbind(e)}),this.clearDropdownAlignmentPreference(),a("#select2-drop-mask").hide(),this.dropdown.removeAttr("id"),this.dropdown.hide(),this.container.removeClass("select2-dropdown-open"),this.results.empty(),this.clearSearch(),this.search.removeClass("select2-active"),this.opts.element.trigger(a.Event("close"))}},clearSearch:function(){},getMaximumSelectionSize:function(){return D(this.opts.maximumSelectionSize)},ensureHighlightVisible:function(){var d,e,f,g,h,i,j,c=this.results;if(e=this.highlight(),!(0>e)){if(0==e)return c.scrollTop(0),b;d=this.findHighlightableChoices(),f=a(d[e]),g=f.offset().top+f.outerHeight(!0),e===d.length-1&&(j=c.find("li.select2-more-results"),j.length>0&&(g=j.offset().top+j.outerHeight(!0))),h=c.offset().top+c.outerHeight(!0),g>h&&c.scrollTop(c.scrollTop()+(g-h)),i=f.offset().top-c.offset().top,0>i&&"none"!=f.css("display")&&c.scrollTop(c.scrollTop()+i)}},findHighlightableChoices:function(){return this.results.find(".select2-result-selectable:not(.select2-selected):not(.select2-disabled)"),this.results.find(".select2-result-selectable:not(.select2-selected):not(.select2-disabled)")},moveHighlight:function(b){for(var c=this.findHighlightableChoices(),d=this.highlight();d>-1&&c.length>d;){d+=b;var e=a(c[d]);if(e.hasClass("select2-result-selectable")&&!e.hasClass("select2-disabled")&&!e.hasClass("select2-selected")){this.highlight(d);break}}},highlight:function(c){var e,f,d=this.findHighlightableChoices();return 0===arguments.length?k(d.filter(".select2-highlighted")[0],d.get()):(c>=d.length&&(c=d.length-1),0>c&&(c=0),this.results.find(".select2-highlighted").removeClass("select2-highlighted"),e=a(d[c]),e.addClass("select2-highlighted"),this.ensureHighlightVisible(),f=e.data("select2-data"),f&&this.opts.element.trigger({type:"highlight",val:this.id(f),choice:f}),b)},countSelectableResults:function(){return this.findHighlightableChoices().length},highlightUnderEvent:function(b){var c=a(b.target).closest(".select2-result-selectable");if(c.length>0&&!c.is(".select2-highlighted")){var d=this.findHighlightableChoices();this.highlight(d.index(c))}else 0==c.length&&this.results.find(".select2-highlighted").removeClass("select2-highlighted")},loadMoreIfNeeded:function(){var c,a=this.results,b=a.find("li.select2-more-results"),e=this.resultsPage+1,f=this,g=this.search.val(),h=this.context;0!==b.length&&(c=b.offset().top-a.offset().top-a.height(),this.opts.loadMorePadding>=c&&(b.addClass("select2-active"),this.opts.query({element:this.opts.element,term:g,page:e,context:h,matcher:this.opts.matcher,callback:this.bind(function(c){f.opened()&&(f.opts.populateResults.call(this,a,c.results,{term:g,page:e,context:h}),f.postprocessResults(c,!1,!1),c.more===!0?(b.detach().appendTo(a).text(f.opts.formatLoadMore(e+1)),window.setTimeout(function(){f.loadMoreIfNeeded()},10)):b.remove(),f.positionDropdown(),f.resultsPage=e,f.context=c.context)})})))},tokenize:function(){},updateResults:function(c){function m(){e.scrollTop(0),d.removeClass("select2-active"),h.positionDropdown()}function n(a){e.html(a),m()}var g,i,d=this.search,e=this.results,f=this.opts,h=this,j=d.val(),k=a.data(this.container,"select2-last-term");if((c===!0||!k||!l(j,k))&&(a.data(this.container,"select2-last-term",j),c===!0||this.showSearchInput!==!1&&this.opened())){var o=this.getMaximumSelectionSize();if(o>=1&&(g=this.data(),a.isArray(g)&&g.length>=o&&C(f.formatSelectionTooBig,"formatSelectionTooBig")))return n("<li class='select2-selection-limit'>"+f.formatSelectionTooBig(o)+"</li>"),b;if(d.val().length<f.minimumInputLength)return C(f.formatInputTooShort,"formatInputTooShort")?n("<li class='select2-no-results'>"+f.formatInputTooShort(d.val(),f.minimumInputLength)+"</li>"):n(""),b;if(f.maximumInputLength&&d.val().length>f.maximumInputLength)return C(f.formatInputTooLong,"formatInputTooLong")?n("<li class='select2-no-results'>"+f.formatInputTooLong(d.val(),f.maximumInputLength)+"</li>"):n(""),b;f.formatSearching&&0===this.findHighlightableChoices().length&&n("<li class='select2-searching'>"+f.formatSearching()+"</li>"),d.addClass("select2-active"),i=this.tokenize(),i!=b&&null!=i&&d.val(i),this.resultsPage=1,f.query({element:f.element,term:d.val(),page:this.resultsPage,context:null,matcher:f.matcher,callback:this.bind(function(g){var i;return this.opened()?(this.context=g.context===b?null:g.context,this.opts.createSearchChoice&&""!==d.val()&&(i=this.opts.createSearchChoice.call(null,d.val(),g.results),i!==b&&null!==i&&h.id(i)!==b&&null!==h.id(i)&&0===a(g.results).filter(function(){return l(h.id(this),h.id(i))}).length&&g.results.unshift(i)),0===g.results.length&&C(f.formatNoMatches,"formatNoMatches")?(n("<li class='select2-no-results'>"+f.formatNoMatches(d.val())+"</li>"),b):(e.empty(),h.opts.populateResults.call(this,e,g.results,{term:d.val(),page:this.resultsPage,context:null}),g.more===!0&&C(f.formatLoadMore,"formatLoadMore")&&(e.append("<li class='select2-more-results'>"+h.opts.escapeMarkup(f.formatLoadMore(this.resultsPage))+"</li>"),window.setTimeout(function(){h.loadMoreIfNeeded()},10)),this.postprocessResults(g,c),m(),this.opts.element.trigger({type:"loaded",data:g}),b)):(this.search.removeClass("select2-active"),b)})})}},cancel:function(){this.close()},blur:function(){this.opts.selectOnBlur&&this.selectHighlighted({noFocus:!0}),this.close(),this.container.removeClass("select2-container-active"),this.search[0]===document.activeElement&&this.search.blur(),this.clearSearch(),this.selection.find(".select2-search-choice-focus").removeClass("select2-search-choice-focus")},focusSearch:function(){t(this.search)},selectHighlighted:function(a){var b=this.highlight(),c=this.results.find(".select2-highlighted"),d=c.closest(".select2-result").data("select2-data");d&&(this.highlight(b),this.onSelect(d,a))},getPlaceholder:function(){return this.opts.element.attr("placeholder")||this.opts.element.attr("data-placeholder")||this.opts.element.data("placeholder")||this.opts.placeholder},initContainerWidth:function(){function c(){var c,d,e,f,g;if("off"===this.opts.width)return null;if("element"===this.opts.width)return 0===this.opts.element.outerWidth(!1)?"auto":this.opts.element.outerWidth(!1)+"px";if("copy"===this.opts.width||"resolve"===this.opts.width){if(c=this.opts.element.attr("style"),c!==b)for(d=c.split(";"),f=0,g=d.length;g>f;f+=1)if(e=d[f].replace(/\s/g,"").match(/width:(([-+]?([0-9]*\.)?[0-9]+)(px|em|ex|%|in|cm|mm|pt|pc))/),null!==e&&e.length>=1)return e[1];return"resolve"===this.opts.width?(c=this.opts.element.css("width"),c.indexOf("%")>0?c:0===this.opts.element.outerWidth(!1)?"auto":this.opts.element.outerWidth(!1)+"px"):null}return a.isFunction(this.opts.width)?this.opts.width():this.opts.width}var d=c.call(this);null!==d&&this.container.css("width",d)}}),e=G(d,{createContainer:function(){var b=a(document.createElement("div")).attr({"class":"select2-container"}).html(["<a href='javascript:void(0)' onclick='return false;' class='select2-choice' tabindex='-1'>","   <span></span><abbr class='select2-search-choice-close' style='display:none;'></abbr>","   <div><b></b></div>","</a>","<input class='select2-focusser select2-offscreen' type='text'/>","<div class='select2-drop' style='display:none'>","   <div class='select2-search'>","       <input type='text' autocomplete='off' class='select2-input'/>","   </div>","   <ul class='select2-results'>","   </ul>","</div>"].join(""));return b},disable:function(){this.enabled&&(this.parent.disable.apply(this,arguments),this.focusser.attr("disabled","disabled"))},enable:function(){this.enabled||(this.parent.enable.apply(this,arguments),this.focusser.removeAttr("disabled"))},opening:function(){this.parent.opening.apply(this,arguments),this.focusser.attr("disabled","disabled"),this.opts.element.trigger(a.Event("open"))},close:function(){this.opened()&&(this.parent.close.apply(this,arguments),this.focusser.removeAttr("disabled"),t(this.focusser))},focus:function(){this.opened()?this.close():(this.focusser.removeAttr("disabled"),this.focusser.focus())},isFocused:function(){return this.container.hasClass("select2-container-active")},cancel:function(){this.parent.cancel.apply(this,arguments),this.focusser.removeAttr("disabled"),this.focusser.focus()},initContainer:function(){var d,e=this.container,f=this.dropdown,h=!1;this.showSearch(this.opts.minimumResultsForSearch>=0),this.selection=d=e.find(".select2-choice"),this.focusser=e.find(".select2-focusser"),this.focusser.attr("id","s2id_autogen"+g()),a("label[for='"+this.opts.element.attr("id")+"']").attr("for",this.focusser.attr("id")),this.search.bind("keydown",this.bind(function(a){if(this.enabled){if(a.which===c.PAGE_UP||a.which===c.PAGE_DOWN)return u(a),b;switch(a.which){case c.UP:case c.DOWN:return this.moveHighlight(a.which===c.UP?-1:1),u(a),b;case c.TAB:case c.ENTER:return this.selectHighlighted(),u(a),b;case c.ESC:return this.cancel(a),u(a),b}}})),this.search.bind("blur",this.bind(function(){document.activeElement===this.body().get(0)&&window.setTimeout(this.bind(function(){this.search.focus()}),0)})),this.focusser.bind("keydown",this.bind(function(a){return!this.enabled||a.which===c.TAB||c.isControl(a)||c.isFunctionKey(a)||a.which===c.ESC?b:this.opts.openOnEnter===!1&&a.which===c.ENTER?(u(a),b):a.which==c.DOWN||a.which==c.UP||a.which==c.ENTER&&this.opts.openOnEnter?(this.open(),u(a),b):a.which==c.DELETE||a.which==c.BACKSPACE?(this.opts.allowClear&&this.clear(),u(a),b):b})),o(this.focusser),this.focusser.bind("keyup-change input",this.bind(function(a){this.opened()||(this.open(),this.showSearchInput!==!1&&this.search.val(this.focusser.val()),this.focusser.val(""),u(a))})),d.delegate("abbr","mousedown",this.bind(function(a){this.enabled&&(this.clear(),v(a),this.close(),this.selection.focus())})),d.bind("mousedown",this.bind(function(a){h=!0,this.opened()?this.close():this.enabled&&this.open(),u(a),h=!1})),f.bind("mousedown",this.bind(function(){this.search.focus()})),d.bind("focus",this.bind(function(a){u(a)})),this.focusser.bind("focus",this.bind(function(){this.container.addClass("select2-container-active")})).bind("blur",this.bind(function(){this.opened()||this.container.removeClass("select2-container-active")})),this.search.bind("focus",this.bind(function(){this.container.addClass("select2-container-active")})),this.initContainerWidth(),this.setPlaceholder()},clear:function(a){var b=this.selection.data("select2-data");b&&(this.opts.element.val(""),this.selection.find("span").empty(),this.selection.removeData("select2-data"),this.setPlaceholder(),a!==!1&&(this.opts.element.trigger({type:"removed",val:this.id(b),choice:b}),this.triggerChange({removed:b})))},initSelection:function(){if(""===this.opts.element.val()&&""===this.opts.element.text())this.close(),this.setPlaceholder();else{var c=this;this.opts.initSelection.call(null,this.opts.element,function(a){a!==b&&null!==a&&(c.updateSelection(a),c.close(),c.setPlaceholder())})}},prepareOpts:function(){var b=this.parent.prepareOpts.apply(this,arguments);return"select"===b.element.get(0).tagName.toLowerCase()?b.initSelection=function(b,c){var d=b.find(":selected");a.isFunction(c)&&c({id:d.attr("value"),text:d.text(),element:d})}:"data"in b&&(b.initSelection=b.initSelection||function(c,d){var e=c.val(),f=null;b.query({matcher:function(a,c,d){var g=l(e,b.id(d));return g&&(f=d),g},callback:a.isFunction(d)?function(){d(f)}:a.noop})}),b},getPlaceholder:function(){return this.select&&""!==this.select.find("option").first().text()?b:this.parent.getPlaceholder.apply(this,arguments)},setPlaceholder:function(){var a=this.getPlaceholder();if(""===this.opts.element.val()&&a!==b){if(this.select&&""!==this.select.find("option:first").text())return;this.selection.find("span").html(this.opts.escapeMarkup(a)),this.selection.addClass("select2-default"),this.selection.find("abbr").hide()}},postprocessResults:function(a,c,d){var e=0,f=this,g=!0;if(this.findHighlightableChoices().each2(function(a,c){return l(f.id(c.data("select2-data")),f.opts.element.val())?(e=a,!1):b}),d!==!1&&this.highlight(e),c===!0){var h=this.opts.minimumResultsForSearch;g=0>h?!1:E(a.results)>=h,this.showSearch(g)}},showSearch:function(b){this.showSearchInput=b,this.dropdown.find(".select2-search")[b?"removeClass":"addClass"]("select2-search-hidden"),a(this.dropdown,this.container)[b?"addClass":"removeClass"]("select2-with-searchbox")},onSelect:function(a,b){var c=this.opts.element.val();this.opts.element.val(this.id(a)),this.updateSelection(a),this.opts.element.trigger({type:"selected",val:this.id(a),choice:a}),this.close(),b&&b.noFocus||this.selection.focus(),l(c,this.id(a))||this.triggerChange()},updateSelection:function(a){var d,c=this.selection.find("span");this.selection.data("select2-data",a),c.empty(),d=this.opts.formatSelection(a,c),d!==b&&c.append(this.opts.escapeMarkup(d)),this.selection.removeClass("select2-default"),this.opts.allowClear&&this.getPlaceholder()!==b&&this.selection.find("abbr").show()},val:function(){var a,c=!1,d=null,e=this;if(0===arguments.length)return this.opts.element.val();if(a=arguments[0],arguments.length>1&&(c=arguments[1]),this.select)this.select.val(a).find(":selected").each2(function(a,b){return d={id:b.attr("value"),text:b.text(),element:b.get(0)},!1}),this.updateSelection(d),this.setPlaceholder(),c&&this.triggerChange();else{if(this.opts.initSelection===b)throw Error("cannot call val() if initSelection() is not defined");if(!a&&0!==a)return this.clear(c),c&&this.triggerChange(),b;this.opts.element.val(a),this.opts.initSelection(this.opts.element,function(a){e.opts.element.val(a?e.id(a):""),e.updateSelection(a),e.setPlaceholder(),c&&e.triggerChange()})}},clearSearch:function(){this.search.val(""),this.focusser.val("")},data:function(a){var c;return 0===arguments.length?(c=this.selection.data("select2-data"),c==b&&(c=null),c):(a&&""!==a?(this.opts.element.val(a?this.id(a):""),this.updateSelection(a)):this.clear(),b)}}),f=G(d,{createContainer:function(){var b=a(document.createElement("div")).attr({"class":"select2-container select2-container-multi"}).html(["    <ul class='select2-choices'>","  <li class='select2-search-field'>","    <input type='text' autocomplete='off' class='select2-input'>","  </li>","</ul>","<div class='select2-drop select2-drop-multi' style='display:none;'>","   <ul class='select2-results'>","   </ul>","</div>"].join(""));return b},prepareOpts:function(){var b=this.parent.prepareOpts.apply(this,arguments);return"select"===b.element.get(0).tagName.toLowerCase()?b.initSelection=function(a,b){var c=[];a.find(":selected").each2(function(a,b){c.push({id:b.attr("value"),text:b.text(),element:b[0]})}),b(c)}:"data"in b&&(b.initSelection=b.initSelection||function(c,d){var e=m(c.val(),b.separator),f=[];b.query({matcher:function(c,d,g){var h=a.grep(e,function(a){return l(a,b.id(g))}).length;return h&&f.push(g),h},callback:a.isFunction(d)?function(){d(f)}:a.noop})}),b},initContainer:function(){var e,d=".select2-choices";this.searchContainer=this.container.find(".select2-search-field"),this.selection=e=this.container.find(d),this.search.attr("id","s2id_autogen"+g()),a("label[for='"+this.opts.element.attr("id")+"']").attr("for",this.search.attr("id")),this.search.bind("input paste",this.bind(function(){this.enabled&&(this.opened()||this.open())})),this.search.bind("keydown",this.bind(function(a){if(this.enabled){if(a.which===c.BACKSPACE&&""===this.search.val()){this.close();var d,f=e.find(".select2-search-choice-focus");if(f.length>0)return this.unselect(f.first()),this.search.width(10),u(a),b;d=e.find(".select2-search-choice:not(.select2-locked)"),d.length>0&&d.last().addClass("select2-search-choice-focus")}else e.find(".select2-search-choice-focus").removeClass("select2-search-choice-focus");if(this.opened())switch(a.which){case c.UP:case c.DOWN:return this.moveHighlight(a.which===c.UP?-1:1),u(a),b;case c.ENTER:case c.TAB:return this.selectHighlighted(),u(a),b;case c.ESC:return this.cancel(a),u(a),b}if(a.which!==c.TAB&&!c.isControl(a)&&!c.isFunctionKey(a)&&a.which!==c.BACKSPACE&&a.which!==c.ESC){if(a.which===c.ENTER){if(this.opts.openOnEnter===!1)return;if(a.altKey||a.ctrlKey||a.shiftKey||a.metaKey)return}this.open(),(a.which===c.PAGE_UP||a.which===c.PAGE_DOWN)&&u(a),a.which===c.ENTER&&u(a)}}})),this.search.bind("keyup",this.bind(this.resizeSearch)),this.search.bind("blur",this.bind(function(a){this.container.removeClass("select2-container-active"),this.search.removeClass("select2-focused"),this.opened()||this.clearSearch(),a.stopImmediatePropagation()})),this.container.delegate(d,"mousedown",this.bind(function(b){this.enabled&&(a(b.target).closest(".select2-search-choice").length>0||(this.clearPlaceholder(),this.open(),this.focusSearch(),b.preventDefault()))
})),this.container.delegate(d,"focus",this.bind(function(){this.enabled&&(this.container.addClass("select2-container-active"),this.dropdown.addClass("select2-drop-active"),this.clearPlaceholder())})),this.initContainerWidth(),this.clearSearch()},enable:function(){this.enabled||(this.parent.enable.apply(this,arguments),this.search.removeAttr("disabled"))},disable:function(){this.enabled&&(this.parent.disable.apply(this,arguments),this.search.attr("disabled",!0))},initSelection:function(){if(""===this.opts.element.val()&&""===this.opts.element.text()&&(this.updateSelection([]),this.close(),this.clearSearch()),this.select||""!==this.opts.element.val()){var c=this;this.opts.initSelection.call(null,this.opts.element,function(a){a!==b&&null!==a&&(c.updateSelection(a),c.close(),c.clearSearch())})}},clearSearch:function(){var a=this.getPlaceholder();a!==b&&0===this.getVal().length&&this.search.hasClass("select2-focused")===!1?(this.search.val(a).addClass("select2-default"),this.search.width(this.getMaxSearchWidth())):this.search.val("").width(10)},clearPlaceholder:function(){this.search.hasClass("select2-default")&&this.search.val("").removeClass("select2-default")},opening:function(){this.clearPlaceholder(),this.resizeSearch(),this.parent.opening.apply(this,arguments),this.focusSearch(),this.opts.element.trigger(a.Event("open"))},close:function(){this.opened()&&this.parent.close.apply(this,arguments)},focus:function(){this.close(),this.search.focus()},isFocused:function(){return this.search.hasClass("select2-focused")},updateSelection:function(b){var c=[],d=[],e=this;a(b).each(function(){0>k(e.id(this),c)&&(c.push(e.id(this)),d.push(this))}),b=d,this.selection.find(".select2-search-choice").remove(),a(b).each(function(){e.addSelectedChoice(this)}),e.postprocessResults()},tokenize:function(){var a=this.search.val();a=this.opts.tokenizer(a,this.data(),this.bind(this.onSelect),this.opts),null!=a&&a!=b&&(this.search.val(a),a.length>0&&this.open())},onSelect:function(a,b){this.addSelectedChoice(a),this.opts.element.trigger({type:"selected",val:this.id(a),choice:a}),(this.select||!this.opts.closeOnSelect)&&this.postprocessResults(),this.opts.closeOnSelect?(this.close(),this.search.width(10)):this.countSelectableResults()>0?(this.search.width(10),this.resizeSearch(),this.getMaximumSelectionSize()>0&&this.val().length>=this.getMaximumSelectionSize()&&this.updateResults(!0),this.positionDropdown()):(this.close(),this.search.width(10)),this.triggerChange({added:a}),b&&b.noFocus||this.focusSearch()},cancel:function(){this.close(),this.focusSearch()},addSelectedChoice:function(c){var j,d=!c.locked,e=a("<li class='select2-search-choice'>    <div></div>    <a href='#' onclick='return false;' class='select2-search-choice-close' tabindex='-1'></a></li>"),f=a("<li class='select2-search-choice select2-locked'><div></div></li>"),g=d?e:f,h=this.id(c),i=this.getVal();j=this.opts.formatSelection(c,g.find("div")),j!=b&&g.find("div").replaceWith("<div>"+this.opts.escapeMarkup(j)+"</div>"),d&&g.find(".select2-search-choice-close").bind("mousedown",u).bind("click dblclick",this.bind(function(b){this.enabled&&(a(b.target).closest(".select2-search-choice").fadeOut("fast",this.bind(function(){this.unselect(a(b.target)),this.selection.find(".select2-search-choice-focus").removeClass("select2-search-choice-focus"),this.close(),this.focusSearch()})).dequeue(),u(b))})).bind("focus",this.bind(function(){this.enabled&&(this.container.addClass("select2-container-active"),this.dropdown.addClass("select2-drop-active"))})),g.data("select2-data",c),g.insertBefore(this.searchContainer),i.push(h),this.setVal(i)},unselect:function(a){var c,d,b=this.getVal();if(a=a.closest(".select2-search-choice"),0===a.length)throw"Invalid argument: "+a+". Must be .select2-search-choice";c=a.data("select2-data"),c&&(d=k(this.id(c),b),d>=0&&(b.splice(d,1),this.setVal(b),this.select&&this.postprocessResults()),a.remove(),this.opts.element.trigger({type:"removed",val:this.id(c),choice:c}),this.triggerChange({removed:c}))},postprocessResults:function(){var a=this.getVal(),b=this.results.find(".select2-result"),c=this.results.find(".select2-result-with-children"),d=this;b.each2(function(b,c){var e=d.id(c.data("select2-data"));k(e,a)>=0&&(c.addClass("select2-selected"),c.find(".select2-result-selectable").addClass("select2-selected"))}),c.each2(function(a,b){b.is(".select2-result-selectable")||0!==b.find(".select2-result-selectable:not(.select2-selected)").length||b.addClass("select2-selected")}),-1==this.highlight()&&d.highlight(0)},getMaxSearchWidth:function(){return this.selection.width()-n(this.search)},resizeSearch:function(){var a,b,c,d,e,f=n(this.search);a=w(this.search)+10,b=this.search.offset().left,c=this.selection.width(),d=this.selection.offset().left,e=c-(b-d)-f,a>e&&(e=c-f),40>e&&(e=c-f),0>=e&&(e=a),this.search.width(e)},getVal:function(){var a;return this.select?(a=this.select.val(),null===a?[]:a):(a=this.opts.element.val(),m(a,this.opts.separator))},setVal:function(b){var c;this.select?this.select.val(b):(c=[],a(b).each(function(){0>k(this,c)&&c.push(this)}),this.opts.element.val(0===c.length?"":c.join(this.opts.separator)))},val:function(){var c,d=!1,f=this;if(0===arguments.length)return this.getVal();if(c=arguments[0],arguments.length>1&&(d=arguments[1]),!c&&0!==c)return this.opts.element.val(""),this.updateSelection([]),this.clearSearch(),d&&this.triggerChange(),b;if(this.setVal(c),this.select)this.opts.initSelection(this.select,this.bind(this.updateSelection)),d&&this.triggerChange();else{if(this.opts.initSelection===b)throw Error("val() cannot be called if initSelection() is not defined");this.opts.initSelection(this.opts.element,function(b){var c=a(b).map(f.id);f.setVal(c),f.updateSelection(b),f.clearSearch(),d&&f.triggerChange()})}this.clearSearch()},onSortStart:function(){if(this.select)throw Error("Sorting of elements is not supported when attached to <select>. Attach to <input type='hidden'/> instead.");this.search.width(0),this.searchContainer.hide()},onSortEnd:function(){var b=[],c=this;this.searchContainer.show(),this.searchContainer.appendTo(this.searchContainer.parent()),this.resizeSearch(),this.selection.find(".select2-search-choice").each(function(){b.push(c.opts.id(a(this).data("select2-data")))}),this.setVal(b),this.triggerChange()},data:function(c){var e,d=this;return 0===arguments.length?this.selection.find(".select2-search-choice").map(function(){return a(this).data("select2-data")}).get():(c||(c=[]),e=a.map(c,function(a){return d.opts.id(a)}),this.setVal(e),this.updateSelection(c),this.clearSearch(),b)}}),a.fn.select2=function(){var d,g,h,i,c=Array.prototype.slice.call(arguments,0),j=["val","destroy","opened","open","close","focus","isFocused","container","onSortStart","onSortEnd","enable","disable","positionDropdown","data"];return this.each(function(){if(0===c.length||"object"==typeof c[0])d=0===c.length?{}:a.extend({},c[0]),d.element=a(this),"select"===d.element.get(0).tagName.toLowerCase()?i=d.element.attr("multiple"):(i=d.multiple||!1,"tags"in d&&(d.multiple=i=!0)),g=i?new f:new e,g.init(d);else{if("string"!=typeof c[0])throw"Invalid arguments to select2 plugin: "+c;if(0>k(c[0],j))throw"Unknown method: "+c[0];if(h=b,g=a(this).data("select2"),g===b)return;if(h="container"===c[0]?g.container:g[c[0]].apply(g,c.slice(1)),h!==b)return!1}}),h===b?this:h},a.fn.select2.defaults={width:"copy",loadMorePadding:0,closeOnSelect:!0,openOnEnter:!0,containerCss:{},dropdownCss:{},containerCssClass:"",dropdownCssClass:"",formatResult:function(a,b,c,d){var e=[];return y(a.text,c.term,e,d),e.join("")},formatSelection:function(a){return a?a.text:b},sortResults:function(a){return a},formatResultCssClass:function(){return b},formatNoMatches:function(){return"No matches found"},formatInputTooShort:function(a,b){var c=b-a.length;return"Please enter "+c+" more character"+(1==c?"":"s")},formatInputTooLong:function(a,b){var c=a.length-b;return"Please delete "+c+" character"+(1==c?"":"s")},formatSelectionTooBig:function(a){return"You can only select "+a+" item"+(1==a?"":"s")},formatLoadMore:function(){return"Loading more results..."},formatSearching:function(){return"Searching..."},minimumResultsForSearch:0,minimumInputLength:0,maximumInputLength:null,maximumSelectionSize:0,id:function(a){return a.id},matcher:function(a,b){return(""+b).toUpperCase().indexOf((""+a).toUpperCase())>=0},separator:",",tokenSeparators:[],tokenizer:F,escapeMarkup:function(a){var b={"\\":"&#92;","&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&apos;","/":"&#47;"};return(a+"").replace(/[&<>"'\/\\]/g,function(a){return b[a[0]]})},blurOnChange:!1,selectOnBlur:!1,adaptContainerCssClass:function(a){return a},adaptDropdownCssClass:function(){return null}},window.Select2={query:{ajax:z,local:A,tags:B},util:{debounce:q,markMatch:y},"class":{"abstract":d,single:e,multi:f}}}}(jQuery);