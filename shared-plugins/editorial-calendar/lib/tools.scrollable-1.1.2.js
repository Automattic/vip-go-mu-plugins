/**
 * tools.scrollable 1.1.2 - Scroll your HTML with eye candy.
 * 
 * Copyright (c) 2009 Tero Piirainen
 * http://flowplayer.org/tools/scrollable.html
 *
 * Dual licensed under MIT and GPL 2+ licenses
 * http://www.opensource.org/licenses
 *
 * Launch  : March 2008
 * Date: jQuery{date}
 * Revision: jQuery{revision} 
 */
(function(jQuery) { 
		
	// static constructs
	jQuery.tools = jQuery.tools || {};
	
	jQuery.tools.scrollable = {
		version: '1.1.2',
		
		conf: {
			
			// basics
			size: 5,
			vertical: false,
			speed: 400,
			keyboard: true,		
			
			// by default this is the same as size
			keyboardSteps: null, 
			
			// other
			disabledClass: 'disabled',
			hoverClass: null,		
			clickable: true,
			activeClass: 'active', 
			easing: 'swing',
			loop: false,
			
			items: '.items',
			item: null,
			
			// navigational elements			
			prev: '.prev',
			next: '.next',
			prevPage: '.prevPage',
			nextPage: '.nextPage', 
			api: false
			
			// CALLBACKS: onBeforeSeek, onSeek, onReload
		} 
	};
				
	var current;		
	
	// constructor
	function Scrollable(root, conf) {   
		
		// current instance
		var self = this, jQueryself = jQuery(this),
			 horizontal = !conf.vertical,
			 wrap = root.children(),
			 index = 0,
			 forward;  
		
		
		if (!current) { current = self; }
		
		// bind all callbacks from configuration
		jQuery.each(conf, function(name, fn) {
			if (jQuery.isFunction(fn)) { jQueryself.bind(name, fn); }
		});
		
		if (wrap.length > 1) { wrap = jQuery(conf.items, root); }
		
		// navigational items can be anywhere when globalNav = true
		function find(query) {
			var els = jQuery(query);
			return conf.globalNav ? els : root.parent().find(query);	
		}
		
		// to be used by plugins
		root.data("finder", find);
		
		// get handle to navigational elements
		var prev = find(conf.prev),
			 next = find(conf.next),
			 prevPage = find(conf.prevPage),
			 nextPage = find(conf.nextPage);

		
		// methods
		jQuery.extend(self, {
			
			getIndex: function() {
				return index;	
			},
			
			getClickIndex: function() {
				var items = self.getItems(); 
				return items.index(items.filter("." + conf.activeClass));	
			},
	
			getConf: function() {
				return conf;	
			},
			
			getSize: function() {
				return self.getItems().size();	
			},
	
			getPageAmount: function() {
				return Math.ceil(this.getSize() / conf.size); 	
			},
			
			getPageIndex: function() {
				return Math.ceil(index / conf.size);	
			},

			getNaviButtons: function() {
				return prev.add(next).add(prevPage).add(nextPage);	
			},
			
			getRoot: function() {
				return root;	
			},
			
			getItemWrap: function() {
				return wrap;	
			},
			
			getItems: function() {
				return wrap.children(conf.item);	
			},
			
			getVisibleItems: function() {
				return self.getItems().slice(index, index + conf.size);	
			},
			
			/* all seeking functions depend on this */		
			seekTo: function(i, time, fn) {

				if (i < 0) { i = 0; }				
				
				// nothing happens
				if (index === i) { return self; }				
				
				// function given as second argument
				if (jQuery.isFunction(time)) {
					fn = time;
				}

				// seeking exceeds the end				 
				if (i > self.getSize() - conf.size) { 
					return conf.loop ? self.begin() : this.end(); 
				} 				

				var item = self.getItems().eq(i);					
				if (!item.length) { return self; }				
				
				// onBeforeSeek
				var e = jQuery.Event("onBeforeSeek");

				jQueryself.trigger(e, i > index);				
				if (e.isDefaultPrevented()) { return self; }				
				
				// get the (possibly altered) speed
				if (time === undefined || jQuery.isFunction(time)) { time = conf.speed; }
				
				function callback() {
					if (fn) { fn.call(self, i); }
					jQueryself.trigger("onSeek", [i]);
				}
				
				if (horizontal) {
					wrap.animate({left: -item.position().left}, time, conf.easing, callback);					
				} else {
					wrap.animate({top: -item.position().top}, time, conf.easing, callback);							
				}
				
				
				current = self;
				index = i;				
				
				// onStart
				e = jQuery.Event("onStart");
				jQueryself.trigger(e, [i]);				
				if (e.isDefaultPrevented()) { return self; }				
	
				
				/* default behaviour */
				
				// prev/next buttons disabled flags
				prev.add(prevPage).toggleClass(conf.disabledClass, i === 0);
				next.add(nextPage).toggleClass(conf.disabledClass, i >= self.getSize() - conf.size);
				
				return self; 
			},			
			
				
			move: function(offset, time, fn) {
				forward = offset > 0;
				return this.seekTo(index + offset, time, fn);
			},
			
			next: function(time, fn) {
				return this.move(1, time, fn);	
			},
			
			prev: function(time, fn) {
				return this.move(-1, time, fn);	
			},
			
			movePage: function(offset, time, fn) {
				forward = offset > 0;
				var steps = conf.size * offset;
				
				var i = index % conf.size;
				if (i > 0) {
				 	steps += (offset > 0 ? -i : conf.size - i);
				}
				
				return this.move(steps, time, fn);		
			},
			
			prevPage: function(time, fn) {
				return this.movePage(-1, time, fn);
			},  
	
			nextPage: function(time, fn) {
				return this.movePage(1, time, fn);
			},			
			
			setPage: function(page, time, fn) {
				return this.seekTo(page * conf.size, time, fn);
			},			
			
			begin: function(time, fn) {
				forward = false;
				return this.seekTo(0, time, fn);	
			},
			
			end: function(time, fn) {
				forward = true;
				var to = this.getSize() - conf.size;
				return to > 0 ? this.seekTo(to, time, fn) : self;	
			},
			
			reload: function() {				
				jQueryself.trigger("onReload");
				return self;
			},			
			
			focus: function() {
				current = self;
				return self;
			},
			
			click: function(i) {
				
				var item = self.getItems().eq(i), 
					 klass = conf.activeClass,
					 size = conf.size;			
				
				// check that i is sane
				if (i < 0 || i >= self.getSize()) { return self; }
				
				// size == 1							
				if (size == 1) {
					if (conf.loop) { return self.next(); }
					
					if (i === 0 || i == self.getSize() -1)  { 
						forward = (forward === undefined) ? true : !forward;	 
					}
					return forward === false  ? self.prev() : self.next(); 
				} 
				
				// size == 2
				if (size == 2) {
					if (i == index) { i--; }
					self.getItems().removeClass(klass);
					item.addClass(klass);					
					return self.seekTo(i, time, fn);
				}				
		
				if (!item.hasClass(klass)) {				
					self.getItems().removeClass(klass);
					item.addClass(klass);
					var delta = Math.floor(size / 2);
					var to = i - delta;
		
					// next to last item must work
					if (to > self.getSize() - size) { 
						to = self.getSize() - size; 
					}
		
					if (to !== i) {
						return self.seekTo(to);		
					}
				}
				
				return self;
			},
			
			// bind / unbind
			bind: function(name, fn) {
				jQueryself.bind(name, fn);
				return self;	
			},	
			
			unbind: function(name) {
				jQueryself.unbind(name);
				return self;	
			}			
			
		});
		
		// callbacks	
		jQuery.each("onBeforeSeek,onStart,onSeek,onReload".split(","), function(i, ev) {
			self[ev] = function(fn) {
				return self.bind(ev, fn);	
			};
		});  
			
			
		// prev button		
		prev.addClass(conf.disabledClass).click(function() {
			self.prev(); 
		});
		

		// next button
		next.click(function() { 
			self.next(); 
		});
		
		// prev page button
		nextPage.click(function() { 
			self.nextPage(); 
		});
		
		if (self.getSize() < conf.size) {
			next.add(nextPage).addClass(conf.disabledClass);	
		}
		

		// next page button
		prevPage.addClass(conf.disabledClass).click(function() { 
			self.prevPage(); 
		});		
		
		
		// hover
		var hc = conf.hoverClass, keyId = "keydown." + Math.random().toString().substring(10); 
			
		self.onReload(function() { 

			// hovering
			if (hc) {
				self.getItems().hover(function()  {
					jQuery(this).addClass(hc);		
				}, function() {
					jQuery(this).removeClass(hc);	
				});						
			}
			
			// clickable
			if (conf.clickable) {
				self.getItems().each(function(i) {
					jQuery(this).unbind("click.scrollable").bind("click.scrollable", function(e) {
						if (jQuery(e.target).is("a")) { return; }	
						return self.click(i);
					});
				});
			}				
			
			// keyboard			
			if (conf.keyboard) {				
				
				// keyboard works on one instance at the time. thus we need to unbind first
				jQuery(document).unbind(keyId).bind(keyId, function(evt) {

					// do nothing with CTRL / ALT buttons
					if (evt.altKey || evt.ctrlKey) { return; }
					
					// do nothing for unstatic and unfocused instances
					if (conf.keyboard != 'static' && current != self) { return; }
					
					var s = conf.keyboardSteps;				
					
					if (horizontal && (evt.keyCode == 37 || evt.keyCode == 39)) {					
						self.move(evt.keyCode == 37 ? -s : s);
						return evt.preventDefault();
					}	
					
					if (!horizontal && (evt.keyCode == 38 || evt.keyCode == 40)) {
						self.move(evt.keyCode == 38 ? -s : s);
						return evt.preventDefault();
					}
					
					return true;
					
				});
				
			} else  {
				jQuery(document).unbind(keyId);	
			}				

		});
		
		self.reload(); 
		
	} 

		
	// jQuery plugin implementation
	jQuery.fn.scrollable = function(conf) { 
			
		// already constructed --> return API
		var el = this.eq(typeof conf == 'number' ? conf : 0).data("scrollable");
		if (el) { return el; }		 
 
		var globals = jQuery.extend({}, jQuery.tools.scrollable.conf);
		conf = jQuery.extend(globals, conf);
		
		conf.keyboardSteps = conf.keyboardSteps || conf.size;
		
		this.each(function() {			
			el = new Scrollable(jQuery(this), conf);
			jQuery(this).data("scrollable", el);	
		});
		
		return conf.api ? el: this; 
		
	};
			
	
})(jQuery);
