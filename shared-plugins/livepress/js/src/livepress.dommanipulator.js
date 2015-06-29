/*global LivepressConfig, Livepress, console, lp_client_strings */
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
