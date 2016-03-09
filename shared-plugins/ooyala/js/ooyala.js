/**
 * Main front-end admin code for Ooyala: The WordPress Plugin!
 *
 * @package Ooyala
 * @author  bendoh, thinkoomph
 */

// Turn a number string into a comma-separated triplet human-readable number,
// used a few places throughout.
Number.prototype.commaString = Number.prototype.commaString || function() {
	var parts = this.toString().split('.');

	if(parts[0].length == 0)
		return '';
	var result = parts[0].split('').reverse().join('').match(/(\d{1,3})/g).join(',').split('').reverse().join('');

	if(parts[1]) {
		result += '.' + parts[1];
	}

	return result;
};

// Turn a number of milliseconds into a pretty string representation
// h:mm:ss or m:ss or #s or #ms
Number.prototype.msToString = Number.prototype.msToString || function() {
	var ms = Math.abs( this );
	if ( ms < 100 && ms > 0 ) return ms + 'ms';
	if ( ms < 6e4 ) return ms / 1000 + 's';
	var parts = [];
	// hours, but only if >0
	( parts[0] = Math.floor( ms / 3.6e6 ) ) || parts.pop();
	// mins (definitely >=1 bc we would have already returned otherwise)
	parts.push( Math.floor( ( ms %= 3.6e6 ) / 6e4 ) );
	// secs
	parts.push( ( ms % 6e4 ) / 1000 );
	// h:mm:ss or m:ss, i.e. adds a leading zero only when following a colon
	return parts.join(':').replace(/:(\d)(?=\D|$)/g, ':0$1');
};

// Turn a number of bytes into a pretty string representation
Number.prototype.bytesToString = Number.prototype.bytesToString || function () {
	if (this == 0) { return "0 B"; }
	var e = Math.floor(Math.log(this) / Math.log(1000));
	return (this/Math.pow(1000, Math.min(e,4))).toFixed(2)+' '+['','k','M','G','T'][e]+'B';
};

(function($) {
	var media = wp.media;

	/**
	 * Main controller for the Ooyala panel, which
	 * is a state within the MediaFrame.
	 */
	ooyala.State = media.controller.State.extend({
		// Keep track of create / render handlers to ensure that they
		// are all registered / dereigstered whenever this state is
		// activated or deactivated
		handlers: {
			'content:create:ooyala-browse': 'createBrowser',
			'content:activate:ooyala-browse': 'activateBrowser',
			'title:create:ooyala-title-bar': 'createTitleBar',
			'content:create:ooyala-about-text': 'createAboutText',
			'content:create:ooyala-upload-panel': 'createUploadPanel',
			'toolbar:create:ooyala-toolbar': 'createToolbar',
		},

		initialize: function() {
			// Are we stupid? - That is, do we not support CORS?
			// This plugin requires CORS support as found in IE > 10
			// TODO: these functions are deprecated...shall we find a different way?
			if($.browser.msie && $.browser.version < 10) {
				this.set('unsupported', true);
			}

			// store player display options on a per-asset basis
			this._displays = [];

			if(!this.get('library')) {
				this.set('library', new ooyala.model.Attachments(null, {
					controller: this,
					props: { query: true },
				}));
			}
			//constrain selection, aka Recently Viewed, to 10 attachments
			this.get('selection').on('add',function(model,collection){
				// Lop off the end if we're past 10
				while(collection.length > 10) {
					collection.pop();
				}
			});
			$(window).on('beforeunload', this.warnOnNavigate.bind(this));
		},

		turnBindings: function(method) {
			var frame = this.frame;

			_.each(this.handlers, function(handler, event) {
				this.frame[method](event, this[handler], this);
			}, this);
		},

		/**
		 * Activate the Media Manager state for this plugin.
		 */
		activate: function() {
			if(this.get('unsupported')) {
				this.frame.$el.addClass('ooyala-unsupported-browser');
			}

			this.turnBindings('on');
		},

		deactivate: function() {
			this.turnBindings('off');
		},

		createTitleBar: function(title) {
			title.view = new ooyala.view.TitleBar({
				controller: this,
			});
		},

		createAboutText: function(content) {
			content.view = new ooyala.view.About({
				controller: this,
			});
		},

		createUploadPanel: function(content) {
			content.view = new ooyala.view.Upload({
				controller: this,
				selection: this.get('selection'),
			});
		},

		createBrowser: function(content) {
			if(this.get('unsupported')) {
				content.view = new ooyala.view.UnsupportedBrowser();
				return;
			}

			content.view = new ooyala.view.Browser({
				controller: this.frame,
				model:      this,
				collection: this.get('library'),
				selection:  this.get('selection'),
				sortable:   false,
				search:     true,
				filters:    true,
				display:    false,
				dragInfo:   false,
				AttachmentView: ooyala.view.Attachment
			});
		},

		activateBrowser: function(content) {
			this.frame.$el.removeClass('hide-toolbar');
		},

		// This toolbar is for the FRAME, not for the asset browser,
		// which also has a toolbar region
		createToolbar: function(toolbar) {
			if(this.get('unsupported')) {
				return;
			}

			toolbar.view = new ooyala.view.Toolbar({
				controller: this.frame,
				collection: this.get('selection')
			});
		},

		// Insert the shortcode for embed
		insert: function() {
			var asset = this.get('selection').single(),
					embed_code;

			if(!asset) {
				return;
			}

			// Get display options from user
			var display = this.display(asset);

			// basic req'd attributes for shortcode
			var atts = {
				//uses 'code' and 'player_id' to be backwards compatible with the previous ooyala plugin
				code: asset.get('id'),
				player_id: display.get('player_id') || asset.get('player_id'),
				width: display.get('width'),
				height: display.get('height'),
				auto: !!asset.get('auto')
			};

			// get the display options but only if different than the defaults
			_.each( _.pick( display.attributes, Object.keys(ooyala.playerDefaults) ),
				function(val,key){ if (val != ooyala.playerDefaults[key] ) atts[key] = val; } //return only values that are different from the defaults
			);

			// default shortcode options to pass to wp shortcode string function
			var shortcode_options = {
				tag: ooyala.tag,
				attrs: atts,
				type: 'single', //no closing tag by default
			};

			// handle add'l params JSON string, if present
			if ( shortcode_options.attrs.additional_params ) {
				// additional params are the body or 'content' area of the shortcode, NOT an attribute
				shortcode_options.content = shortcode_options.attrs.additional_params;
				delete shortcode_options.attrs.additional_params;
				delete shortcode_options.type; //remove 'single' type so that it will allow content and a closing tag
			}
			// build the shortcode
			shortcode = wp.shortcode.string(shortcode_options);

			media.editor.insert('\n' + shortcode + '\n');

		},

		reset: function() {
			// no-op'd so that user can relaunch Ooyala media panel and asset and player settings will still be selected
			return;
		},

		// get display options associated with given attachment
		display: function( attachment ) {
			var displays = this._displays;

			// create new display options object if nonexistent
			if(!displays[attachment.cid]) {
				displays[attachment.cid] = new ooyala.model.DisplayOptions({ attachment: attachment });
			}

			return displays[attachment.cid];
		},

		// warn the user if they have a pending upload and try to navigate away
		warnOnNavigate: function(ev) {
			if ( this.uploader && this.uploader.state === ooyala.plupload.STARTED ) {
				return ev.returnValue = ooyala.text.uploadWarning;
			}
		},
	});

	// Register handler for ooyala button which brings the
	// user directly to the Ooyala view
	$(document).ready(function() {
		var media = wp.media;

		$(document.body).on('click', '.ooyala-activate', function(e) {
			e.preventDefault();

			if ($(this).hasClass('disabled')) return;

			if(!media.frames.ooyala) {
				media.frames.ooyala = wp.media.editor.open(wpActiveEditor, {
					state: 'ooyala',
					frame: 'post'
				});
			}
			else {
				/* TODO:
				bring user to ooyala screen here */
				media.frames.ooyala.open(wpActiveEditor);
			}
		});
	});
})(jQuery);
