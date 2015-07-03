/**
 * Main front-end admin code for Getty Images
 *
 * @package Getty Images
 * @author  bendoh, thinkoomph
 */

(function($) {
	var media = wp.media;
	var getty = gettyImages;

	// The Getty User session, which handles authentication needs and maintains
	// application tokens for anonymous searches
	getty.user = new media.model.GettyUser();
	getty.user.restore();

	// Omniture tracking
	if ( getty.isWPcom ) {
		getty.t = function() {
			var s = window.getty_s;
			if(getty.user.settings.get('omniture-opt-in') && s && s.t) {
				s.t();
			}
		};
		getty.tl = function() {
			var s = window.getty_s;
			if(getty.user.settings.get('omniture-opt-in') && s && s.tl) {
				s.tl();
			}
		};
	} else {
		getty.t = function() {
			var s = window.getty_s;
			if(s && s.t) {
				s.t();
			}
		};
		getty.tl = function() {
			var s = window.getty_s;
			if(s && s.tl) {
				s.tl();
			}
		};
	}

	/**
	 * Main controller for the Getty Images panel, which
	 * is a state within the MediaFrame.
	 */
	media.controller.GettyImages = media.controller.State.extend({
		// Keep track of create / render handlers to ensure that they
		// are all registered / dereigstered whenever this state is
		// activated or deactivated
		handlers: {
			'content:create:getty-images-browse': 'createBrowser',
			'title:create:getty-title-bar': 'createTitleBar',
			'content:create:getty-about-text': 'createAboutText',
			'content:activate:getty-images-browse': 'activateBrowser',
			'toolbar:create:getty-images-toolbar': 'createToolbar',
			'content:create:getty-welcome': 'createWelcome',
			'content:create:getty-mode-select': 'createModeSelect',
			'toolbar:create:blank-toolbar': 'createBlankToolbar',
		},

		initialize: function() {
			// Are we stupid?
			if($.browser.msie && $.browser.version < 10) {
				this.set('unsupported', true);
			}

			this._displays = [];

			if(!this.get('library')) {
				this.set('library', new media.model.GettyAttachments(null, {
					controller: this,
					props: { query: true },
				}));
			}

			if(!this.get('refinements')) {
				this.set('refinements', new Backbone.Collection());
			}

			if(!this.get('categories')) {
				this.set('categories', new Backbone.Collection());
			}

			getty.user.settings.on('change:mode change:omniture-opt-in', this.setMode, this);
			getty.user.on('change:loggedIn', this.setMode, this);
		},

		turnBindings: function(method) {
			var frame = this.frame;

			_.each(this.handlers, function(handler, event) {
				this.frame[method](event, this[handler], this);
			}, this);
		},

		activate: function() {
			if(this.get('unsupported')) {
				this.frame.$el.addClass('getty-unsupported-browser');
				return;
			}

			this.turnBindings('on');

			getty.t();

			this.setMode();
		},

		setMode: function() {
			if(getty.isWPcom) {
				if(getty.user.settings.get('omniture-opt-in') === undefined) {
					this.set('content', 'getty-welcome');
					this.set('toolbar', 'blank-toolbar');
				}
				else {
					getty.user.settings.set('mode', 'login');
					this.set('content', 'getty-images-browse');
					this.set('toolbar', 'getty-images-toolbar');
				}

				return;
			}

			this.set('mode', getty.user.settings.get('mode'));

			var mode = this.get('mode');

			switch(mode) {
				case 'login':
					if(!getty.user.get('loggedIn')) {
						this.set('content', 'getty-mode-select');
						this.set('toolbar', 'blank-toolbar');
					}
					else {
						this.set('content', 'getty-images-browse');
					}

					break;

				case 'embed':
					this.set('content', 'getty-images-browse');
					this.set('toolbar', 'getty-images-toolbar');

					break;

				default:
					this.set('content', 'getty-mode-select');
					this.set('toolbar', 'blank-toolbar');
			}
		},

		createModeSelect: function(content) {
			if(this.get('unsupported')) {
				content.view = new media.view.GettyUnsupportedBrowser();
				return;
			}

			content.view = new media.view.GettyModeSelect({
				controller: this.frame,
				model:      this
			});
		},

		createWelcome: function(content) {
			content.view = new media.view.GettyWelcome({
				controller: this.frame,
				model:      this
			});
		},

		createEmptyToolbar: function(toolbar) {
			toolbar.view = new media.View();
		},

		deactivate: function() {
			this.turnBindings('off');
		},

		createTitleBar: function(title) {
			title.view = new media.view.GettyTitleBar({
				controller: this,
			});
		},

		createAboutText: function(content) {
			content.view = new media.view.GettyAbout({
				controller: this,
			});
		},

		createBrowser: function(content) {
			if(this.get('unsupported')) {
				content.view = new media.view.GettyUnsupportedBrowser();
				return;
			}

			content.view = new media.view.GettyBrowser({
				controller: this.frame,
				model:      this,
				collection: this.get('library'),
				selection:  this.get('selection'),
				refinements: this.get('refinements'),
				categories: this.get('categories'),
				sortable:   false,
				search:     true,
				filters:    true,
				display:    false,
				dragInfo:   false,
				AttachmentView: media.view.GettyAttachment
			});
		},

		activateBrowser: function(content) {
			this.frame.$el.removeClass('hide-toolbar');
		},

		// This toolbar is for the FRAME, not for the image browser,
		// which also has a toolbar region
		createToolbar: function(toolbar) {
			if(this.get('unsupported')) {
				return;
			}

			toolbar.view = new media.view.GettyToolbar({
				controller: this.frame,
				collection: this.get('selection')
			});
		},

		insert: function() {
			var image = this.get('selection').single(), 
					embed_code;

			if(!image) {
				return;
			}

			if(this.get('mode') == 'embed') {
				//Build the Embed code and insert it
				embed_code = 'http://gty.im/' + image.get('ImageId');

				wp.media.editor.insert("\n" + embed_code + "\n");
			} else {

				// Get display options from user
				var display = this.display(image);

				var align = display.get('align') || 'none';
				var alt = display.get('alt');
				var caption = display.get('caption');

				var sizeSlug = display.get('size');
				var sizes = display.get('sizes');
				var size = sizes[sizeSlug];

				if(!size) {
					return;
				}

				// Build image tag with those options
				var $img = $('<img>');

				$img.attr('src', size.url);

				if(alt) {
					$img.attr('alt', alt);
				}

				if(align != 'none') {
					$img.addClass('align' + align);
				}

				$img.addClass('size-' + sizeSlug);
				$img.attr('width', size.width);
				$img.attr('height', size.height);

				var $container = $('<div>').append($img);

				if(caption) {
					$container.html( '[caption align="align' + align + '" width="' + size.width + '"]' + $container.html() + caption + '[/caption]' );
				}

				wp.media.editor.insert($container.html());

			}
		},

		reset: function() {
			this.get('selection').reset();
			this._displays = [];
			this.refreshContent();
		},

		display: function( attachment ) {
			var displays = this._displays;
			var defaultProps = media.view.settings.defaultProps;

			if(!displays[attachment.cid]) {
				displays[attachment.cid] = new media.model.GettyDisplayOptions(_.extend({
					type: 'image',
					align: defaultProps.align || getUserSetting( 'getty_align', 'none' ),
					size:  defaultProps.size  || getUserSetting( 'getty_imgsize', 'medium' ),
				}, { attachment: attachment }));
			}

			return displays[attachment.cid];
		}
	});

	// Register handler for getty-images button which brings the
	// user directly to the Getty Images view
	$(document).ready(function() {
		var media = wp.media;

		$(document.body).on('click', '.getty-images-activate', function(e) {
			e.preventDefault();

			if(!media.frames.getty) {
				media.frames.getty = wp.media.editor.open(wpActiveEditor, {
					state: 'getty-images',
					frame: 'post'
				});
			}
			else {
				media.frames.getty.open(wpActiveEditor);
			}
		});
	});
})(jQuery);
