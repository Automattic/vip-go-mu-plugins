/**
 * Views for the Ooyala plugin
 *
 * @package Ooyala
 * @author  bendoh, thinkoomph
 */
(function($) {
	var media = wp.media;

	/**
	 * Extend both top-level media frames with an additional mode for searching
	 * and embedding videos from Ooyala
	 */
	var OoyalaFrame = function(parent) {
		return {
			createStates: function() {
				parent.prototype.createStates.apply(this, arguments);

				this.states.add([
					new ooyala.State({
						id: 'ooyala',
						title: ooyala.text.title,
						titleMode: 'ooyala-title-bar',
						multiple: true,
						content: 'ooyala-browse',
						router: false,
						menu: 'default',
						toolbar: 'ooyala-toolbar',
						selection: new media.model.Selection(null, { multiple: true }),
						edge: 120,
						gutter: 8
					}),
				]);
			},
		}
	};

	media.view.MediaFrame.Post = media.view.MediaFrame.Post.extend(OoyalaFrame(media.view.MediaFrame.Post));

	/**
	 * The Ooyala Browser view
	 */
	ooyala.view.Browser = media.view.AttachmentsBrowser.extend({
		template: media.template('ooyala-attachments-browser'),
		className: 'attachments-browser ooyala-attachments-browser',

		events: {
			'keyup .search': 'searchOnEnter',
			'click .ooyala-more': 'more',
			'click .ooyala-label': 'refineLabel',
			'click .ooyala-clear-label': 'clearLabel',
			'click .ooyala-set-featured': 'setFeatured',
		},

		initialize: function() {
			this.render();

			// Set various flags whenever the results description model  changes
			this.collection.results.on('change:searched change:refinements', this.updateFlags, this);
			this.collection.results.on('change:searching', this.updateSearching, this);

			// Set .have-more flag when there're more results to load
			this.collection.on('add remove reset', this.updateHaveMore, this);
			// Update the running totals label after each search
			this.collection.results.on('change:searching', this.updateTotal, this);

			this.controller.state().on('activate', this.ready, this);

			// update visual identifier of label refinement on change
			this.collection.propsQueue.on('change:label', this.labelState, this);

			media.view.AttachmentsBrowser.prototype.initialize.apply(this, arguments);
		},

		/**
		 * Set featured image to the live preview image
		 */
		setFeatured: function() {
			var single = this.options.selection.single()
			  , $thumbnail = this.$el.find('.thumbnail')
			  , attachment = single.id ? ooyala.model.Attachments.all.get(single.id) : false
				, view = this
			;

			if(attachment) {
				$thumbnail.addClass('featured-loading');
				$thumbnail.find('button').prop('disabled', true);

				attachment.setFeatured().done(function() {
					view.sidebar.get('details').render();
				});
			}

		},

		/**
		 * When activated, restore any injected content, focus on search field
		 */
		ready: function() {
			this.updateFlags(this.collection.results);
			this.updateTotal();
			// load a blank search to show the newest #(page) results
			this.search(); //TODO: maybe not do this on *every* ready event, but only the first ever
			// focus on the search field
			this.$el.find('.search-primary').focus();
			// load up the players associated with the account
			ooyala.model.DisplayOptions.players();
			$('.ooyala-browse-link').addClass('ooyala-browsing');
			// update label state
			this.labelState();
		},

		// Create attachments view
		updateContent: function() {
			if( !this.attachments )
				this.createAttachments();
		},

		// Start a new search with a label and current search term
		refineLabel: function(ev) {
			ev.preventDefault();
			this.collection.propsQueue.set('label', _.unescape($(ev.target).text()));
			this.search();
		},

		// Clear the currently selected label
		clearLabel: function(ev) {
			ev.preventDefault();
			this.collection.propsQueue.unset('label');
			this.search();
		},

		// update the label state
		labelState: function() {
			var label = this.collection.propsQueue.get('label');
			this.$('.ooyala-toolbar').toggleClass('has-label',!!label);
			this.$('.ooyala-selected-label').text(label);
		},

		// Create sidebar and register hooks on selection
		createSidebar: function() {
			var options = this.options,
				selection = options.selection,
				sidebar = this.sidebar = new media.view.Sidebar({
					controller: this.controller
				});

			this.views.set('.ooyala-sidebar', sidebar);

			selection.on( 'selection:single', this.createSingle, this );
			selection.on( 'selection:unsingle', this.disposeSingle, this );

			if( selection.single() )
				this.createSingle();
		},

		// Create single attachment detail view for sidebar
		createSingle: function() {
			var sidebar = this.sidebar,
				single = this.options.selection.single();

			var attachment = single.id ? ooyala.model.Attachments.all.get(single.id) : false;

			if(attachment) {
				var details = new ooyala.view.Details({
					controller: this.controller,
					model: attachment,
					priority:   80
				});

				sidebar.set('details', details);

				var displayOptions = {
					model: this.model.display(attachment),
					priority: 200,
				};

				sidebar.set('display', new ooyala.view.AttachmentDisplaySettings(displayOptions));

				this.$('.ooyala-set-featured').blur();
			}
		},

		createUploader: function() {}, // we aren't using the WP uploader for ooyala

		// A 'running' total of search results
		updateTotal: function() {
			var total = this.collection.length || 0;

			// add a plus sign if there are more results that may be incoming
			var separated = new Number(total).commaString() + ( this.haveMore() ? '+' : '' );

			if(total > 0) {
				if(total == 1) {
					this.total.$el.text(ooyala.text.oneResult.replace('%d', separated));
				}
				else {
					this.total.$el.text(ooyala.text.results.replace('%d', separated));
				}
			}
			else {
				this.total.$el.text(ooyala.text.noResults);
			}
		},

		// Handle return key in primary search or click on "Search" button
		// This starts a new top-level search
		searchOnEnter: function(ev) {
			if(ev.keyCode == 13) {
				this.search();
			}
		},

		// Start a new search
		search: function() {
			this.collection.search();
		},

		// Create the Search Toolbar, containing filters
		createToolbar: function() {
			var self = this;

			// Create the toolbar
			this.toolbar = new media.view.Toolbar({
				className: 'ooyala-toolbar',
				controller: this.controller,
				model: this.collection
			});

			// Make primary come first.
			this.toolbar.views.set([ this.toolbar.primary, this.toolbar.secondary ]);

			this.total = new media.View({
				tagName: 'span',
				className: 'search-results-total',
				priority: -20
			});

			this.label = new ooyala.view.LabelRefinement({
				priority: -30
			});

			// Wrap search input in container because IE10 ignores left/right absolute
			// positioning on input[type="text"]... because.
			this.searchContainer = new media.View({ className: 'ooyala-search-input-container' });

			this.searchContainer.views.add(new ooyala.view.Search({
				className: 'search search-primary',
				model:      this.collection.propsQueue,
				controller: this,
				priority:   0,
				attributes: {
					type: 'search',
					placeholder: ooyala.text.searchPlaceholder,
					tabindex: 10
				}
			}));

			// Views with negative priority get put in secondary toolbar area
			this.toolbar.set({
				search: this.searchContainer,

				// Plain objects get turned into buttons
				searchButton: {
					text: ooyala.text.search,
					click: function() { self.search() },
					priority: 10,
				},

				total: this.total,

				label: this.label,
			});

			this.views.set('.ooyala-search-toolbar', this.toolbar);
		},

		createAttachments: function() {
			// No-op the scroll handler, making paging manual.
			// works better with a slow API.
			// >> TODO: doesn't this no op the scroll handler universally for all attachment views??
			media.view.Attachments.prototype.scroll = function(ev) {
				// Don't bubble upwards and out of the modal
				if(ev) {
					ev.stopPropagation();
				}
			};

			this.attachments = new ooyala.view.Attachments({
				controller: this.controller,
				collection: this.collection,
				selection:  this.options.selection,
				model:      this.model,
				sortable:   this.options.sortable,
				refreshThreshold: 2,

				// The single `Attachment` view to be used in the `Attachments` view.
				AttachmentView: this.options.AttachmentView
			});

			this.views.set('.ooyala-results', this.attachments);

			// Create "More" button, because automatic paging is just too slow with
			// this API.
			this.moreButton = new ooyala.view.More();
			this.moreButton.render(); // Load in the template, just once.

			this.collection.on('add reset', this.updateMore, this);
			this.collection.results.on('change', this.updateMore, this);

			this.updateMore();
		},

		// keep "More..." link at the end of the collection
		updateMore: function(model, collection, options) {
			// Always keep add more link at the end of the list
			if(this.attachments) {
				this.attachments.$el.append(this.moreButton.$el);
			}

			this.updateHaveMore();
		},

		// Load more results
		more: function() {
			this.collection.more();
		},

		/**
		 * Show loading state
		 */
		updateSearching: function(model) {
			this.$el.toggleClass('search-loading', model.get('searching'));

			var button = this.controller.toolbar.view.$el.find('.media-button-searchButton');
			if(model.get('searching')) {
				button.attr('disabled', 'disabled');

				if(model.spinner) {
					model.spinner.stop();
				}

				var opts = { className: 'ooyala-spinner', color: '#888' };
				var $el;

				if(this.haveMore()) {
					opts.color = '#ddd';
					$el = this.moreButton.$el.find('.ooyala-more-spinner');
				}
				else {
					opts.color = '#888';
					opts.lines = 11;
					opts.length = 21;
					opts.width = 9;
					opts.radius = 38;
					$el = this.$el.find('.ooyala-search-spinner')
				}

				model.spinner = new Spinner(opts);
				model.spinner.spin($el[0]);
			}
			else if(model.spinner) {
				button.removeAttr('disabled');
				model.spinner.stop();
			}
		},

		/**
		 * Add flags to container for various states
		 */
		updateFlags: function(model) {
			this.$el.toggleClass('have-searched', !!model.get('searched'));
			this.$el.toggleClass('have-results', this.collection.length > 0);
		},

		/**
		 * Are there more images to be loaded?
		 */
		haveMore: function() {
			return this.collection.length > 0 && this.collection.hasMore();
		},

		/**
		 * Add .have-more flag to container when there are more results
		 * to be loaded.
		 */
		updateHaveMore: function(model, collection) {
			this.$el.toggleClass('have-more', this.haveMore());
			this.$el.toggleClass('no-more', !this.haveMore());
		}
	});

	/**
	 * Attachments
	 */
	ooyala.view.Attachments = media.view.Attachments.extend({
		prepare: function() {
			// Create all of the Attachment views, and replace
			// the list in a single DOM operation.
			if (this.collection.length) {
				this.views.set(this.collection.map(this.createAttachmentView, this));
			} else {
				this.views.unset();
			}
		},
	});

	/**
	 * View used to display the "More" button
	 */
	ooyala.view.More = media.View.extend({
		template: media.template('ooyala-more'),
		tagName: 'li',
		className: 'ooyala-more attachment'
	});

	/**
	 * View used to show current label refinement
	 */
	ooyala.view.LabelRefinement = media.View.extend({
		template: media.template('ooyala-label-search'),
		className: 'ooyala-label-search',
	});

	/**
	 * Asset Display Options
	 */
	ooyala.view.AttachmentDisplaySettings = media.view.Settings.extend({
		template: media.template('ooyala-display-settings'),

		initialize: function() {
			// re-renders settings section after resolutions have been downloaded or status has been changed
			this.model.attachment.on('change:downloadingResolutions', this.render, this);
			this.model.attachment.on('change:status', this.changeStatus, this);
			// constrain proportions, maybe
			this.model.on('change:width change:height', this.constrainRatio, this);
			// validate additional params when they change
			this.model.on('change:additional_params_raw', this.validateParams, this);
			// user has confirmed that they wish to embed a potentially non-embeddable asset
			this.model.on('change:forceEmbed', this.render, this);
			this.options.players = ooyala.model.DisplayOptions.players();
			this.options.players.on('add remove reset sync error fetching', this.render, this);
			// add events to the default ones from media.view.Settings (so they are not overridden)
			_.extend( this.events, {
				'change .setting.resolution select': 'updateSize',
				'click .message a': 'dismissWarning',
			});
			media.view.Settings.prototype.initialize.apply(this, arguments);
		},

		// need to add some functionality after basic rendering
		render: function() {
			media.view.Settings.prototype.render.apply(this, arguments);
			// do not update size area if we are downloading the resolutions
			if ( !this.model.attachment.get('downloadingResolutions') ) this.updateSize();
			// validate additional params
			this.validateParams();
			// change field to a number type dynamically
			// (the update handler in media.view.Settings does not support HTML5 inputs)
			try {
				this.$('input[data-setting=initial_time]')[0].type = 'number';
			} catch(e) {}
			return this;
		},

		// update stuff if the attachment status changes
		changeStatus: function() {
			// add'l details may be available now that status has changed
			this.model.attachment.fetch();
			this.render();
		},

		// show the width/height inputs only when selecting custom size,
		// otherwise update these values behind the scenes
		updateSize: function() {
			var val = this.$('.setting.resolution select').val() || 'auto',
				$custom = this.$('.custom-resolution'),
				resolutions = this.model.attachment.get('resolutions') || [],
				resolution;

			// add class only if custom is selected
			$custom.toggleClass('custom-entry', (val == 'custom'));

			this.model.set('auto', (val == 'auto'));

			if ( val == 'auto' ) {
				resolution = resolutions && resolutions[0];
			}
			else if ( val != 'custom' ) {
				resolution = val.split(' x ');
			}

			if ( resolution ) {
				// update width and height values, which are also propagated to the input fields
				this.model.set('width', resolution[0]);
				this.model.set('height', resolution[1]);
			}
		},

		// constrain the other dimension if entering custom dimensions and this option is selected
		constrainRatio: function(model) {
			var field = Object.keys( model.changedAttributes() ).shift();
			// bail if...
			if (
				!_.contains( ['width','height'], field ) //not a dimension change
				|| !this.$('.custom-entry').length //not a custom entry
				|| !this.model.get('lockAspectRatio') //constrained dimensions are not desired
			) return;

			// set the *other* field based on how much this field changed
			var other = ( field == 'width' ) ? 'height' : 'width',
				attributes = {};
			attributes[other] = Math.round( this.model.get(other) * ( this.model.get(field) / this.model.previous(field) ) );
			// silent so that we don't enter an endless loop
			this.model.set( attributes, { silent: true } );
		},

		// validate the additional parameters field for valid JSON or object literal notation
		validateParams: function() {
			// try parsing the input as an object literal, then convert to JSON
			// this is attempting to normalize the input as JSON
			var params = this.model.get('additional_params_raw');
			if ( params ) {
				try {
					// encapsulating braces are optional
					if ( !/^\s*\{.+\}\s*$/.test(params) ) params = '{' + params + '}';
					// eval(), with justification:
					// Yes, this could execute some arbitrary code, but this only happening in the context of
					// wp-admin, as a means to see if this is a 'plain' JS object or string of JSON.
					// This will prevent the user from inadvertantly passing arbitrary code to the shortcode,
					// which in turn would put it right into a script tag ending up on the front end.
					params = eval( '(' + params + ')' );
					// empty objects, arrays, or primitives need not apply
					if ( typeof params == 'object' && !Array.isArray(params) && Object.keys(params).length ) {
						params = JSON.stringify( params );
					} else {
						params = false;
					}
				} catch(e) {
					//some error along the way...not valid JSON or JS object
					params = false;
				}
			}
			this.model.set('additional_params',params||'');
			this.$('.setting.additional-parameters').toggleClass('error',params===false);
		},

		// dismiss warning about the asset potentially not being embeddable
		// this allows the shortcode to still be inserted with confirmation that it may not work
		dismissWarning: function(e) {
			e.preventDefault();
			this.model.set('forceEmbed',true);
			// pretend to select the asset again so that the insert button updates
			this.controller.state().get('selection').trigger('selection:single');
		},

	});

	/**
	 * The title bar, containing the logo, "privacy" and "About" links
	 */
	ooyala.view.TitleBar = media.View.extend({
		template: media.template('ooyala-title-bar'),
		className: 'ooyala-title-bar',

		initialize: function() {
			if(this.controller.get('unsupported')) {
				return;
			}
		},

		prepare: function() {
			return this.controller.attributes;
		},

		events: {
			'click .ooyala-about-link': 'showAbout',
			'click .ooyala-upload-toggle': 'toggleUploadPanel',
			'click .ooyala-browse-link': 'showBrowser',
		},

		// toggle upload panel (clicking the Upload link will display OR hide this panel)
		toggleUploadPanel: function() {
			this.$('.ooyala-browse-link').removeClass('ooyala-browsing');
			if ( this.controller.frame.content.mode() === 'ooyala-upload-panel' ) {
				this.controller.frame.content.mode('ooyala-browse');
			} else {
				this.controller.frame.content.mode('ooyala-upload-panel');
			}
		},

		showAbout: function() {
			this.$('.ooyala-browse-link').removeClass('ooyala-browsing');
			this.controller.frame.content.mode('ooyala-about-text');
		},

		showBrowser: function() {
			this.controller.frame.content.mode('ooyala-browse');
		},
	});

	/**
	 * The primary search field. Refreshes when model changes
	 */
	ooyala.view.Search = media.view.Search.extend({
		initialize: function() {
			this.model.on('change:search', this.render, this);
		},

		ready: function() {
			this.render();
		},

		render: function() {
			media.view.Search.prototype.render.apply(this, arguments);

			this.controller.collection.results.on('change:searching', this.updateButtonState, this);
		},

		updateButtonState: function(model) {
			if(model.get('searching')) {
				this.$el.parents('.media-toolbar-primary').find('.media-button-searchButton').attr('disabled', 'disabled');
			}
			else {
				this.$el.parents('.media-toolbar-primary').find('.media-button-searchButton').removeAttr('disabled');
			}
		}
	});

	/**
	 * The "About" screen, containing text and a close button
	 */
	ooyala.view.About = media.View.extend({
		template: media.template('ooyala-about-text'),
		className: 'ooyala-about-text',

		events: {
			'click .ooyala-close': 'close',
		},

		close: function(ev) {
			ev.preventDefault();
			this.controller.frame.content.mode('ooyala-browse');
		},

		// TODO: this is not functioning
		closeAboutOnEscape: function(ev) {
			if(ev.keyCode == 27) {
				this.close();
				ev.stopPropagation();
			}
		}
	});

	/**
	 * The Upload panel
	 */
	ooyala.view.Upload = media.View.extend({
		template: media.template('ooyala-upload-panel'),
		className: 'ooyala-upload-panel',

		events: {
			'click .ooyala-close': 'close',
			'click .ooyala-start-upload': 'startUpload',
			'click .ooyala-stop-upload': 'stopUpload',
			'change input, textarea, select': 'syncSettings',
		},

		initialize: function() {

			// make an uploader, but only once
			if ( !this.controller.uploader ) {
				// browse button needs to exist in the dom before creating uploader
				this.controller.$browser = $('<a href="#" class="button" />').hide().appendTo('body');

				this.controller.uploader = new ooyala.plupload.Uploader({
					browse_button: this.controller.$browser[0],
					url: '/', //this is required for init but will be changed out before upload
					chunk_size: ooyala.chunk_size,
					max_retries: 2,
					multi_selection: false,
					// file upload events that act upon the files only
					// these cannot reference the view directly, because the view object changes with each initialization
					init: {
						UploadProgress: function(uploader,file) {
							file.model.asset.set('percent',file.percent);
						},
						UploadComplete: function(uploader,files) {
							files[0].model.finalize()
								.always(function(){ //always remove the upload from queue, success or not
									uploader.splice();
									uploader.view.render();
								});
						},
					},
				});
				// initialize the uploader
				this.controller.uploader.init();
			}
			// save view inside of uploader so it can be accessed from the uploader callbacks
			this.controller.uploader.view = this;
			this.controller.uploader.bind('FilesAdded',this.selectFile,this);
		},

		render: function() {
			media.View.prototype.render.apply(this,arguments);

			this.bindProgress();

			// replace the placeholder button with the actual one that has been bound to plupload events
			var $placeholder = this.$('.ooyala-upload-browser');
			if ( $placeholder.length ) {
				var $browser = this.controller.$browser;
				$browser.detach().text( $placeholder.text() );
				$browser[0].className = $placeholder[0].className;
				$placeholder.replaceWith( $browser.show().attr('style','') );
			}
		},

		// if there is an ongoing upload, update the progress while on the upload panel
		// TODO: change this to a progress bar
		bindProgress: function() {
			if ( this.controller.uploader.state === ooyala.plupload.STARTED ) {
				this.controller.uploader.files[0].model.asset.on('change:percent', this.progress, this);
			}
		},

		// update the uploading progress when on the panel
		progress: function(model) {
			this.$('.progress span').text( model.get('percent') );
		},

		// return to browse
		close: function() {
			this.controller.frame.content.mode('ooyala-browse');
		},

		// select file with the browse button
		// this assumes there is not a file upload in progress,
		// because the browse button should not be available in that case
		selectFile: function(uploader,files) {
			// force only one file in the queue at a time
			uploader.splice(0,Math.max(0,uploader.files.length-1));
			var file = files[0];

			// model for creating the asset and retrieving upload urls
			file.model = new ooyala.model.Upload({
				file_name: file.name,
				file_size: file.size,
				name: file.name,
			});
			// get a new url for each chunk
			file.chunkURL = function(file,chunk) {
				return file.model.get('uploading_urls')[chunk];
			};

			uploader.view.render();
		},

		//fetch the upload urls and begin the upload
		startUpload: function(e) {
			e.preventDefault();

			var file = this.controller.uploader.files[0];
			if ( !file ) return;

			// once we have the uploading urls, the upload can actually begin
			file.model.on('change:uploading_urls',
				function(model) {
					// start the upload with plupload
					this.controller.uploader.start();
					// update controls
					this.$('.ooyala-upload-controls').addClass('uploading');
					// update progress of upload while on upload panel
					this.bindProgress();
					// add the upload to the 'all' query
					ooyala.model.Query.get({}).unshift(model.asset);
					// add the upload to the selection (recently viewed)
					this.controller.get('selection').unshift(model.asset);
					this.controller.get('selection').single(model.asset);
				},
				this);
			// this will fetch the uploading urls
			file.model.save();
			// update name in case it had to be changed pre-upload
			// (e.g. if the user deleted it entirely and it was replaced with the filename)
			this.$('[data-setting="name"]').val(file.model.get('name'));
			// disable the 'Start Upload' button until upload actually starts,
			// at which point it turns into a cancel button
			$(e.target).addClass('disabled');
			// hide the "Change File" button immediately
			this.$('.ooyala-upload-browser').hide();
		},

		stopUpload: function(e) {
			e.preventDefault();
			// stop upload
			this.controller.uploader.stop();
			// clear file queue and destory upload,
			// which removes the unfinished asset through the API
			// and the corresponding attachment
			// all in one!
			this.controller.uploader.splice()[0].model.destroy();
			this.render();
		},

		// sync settings for currently selected file with its attachment model
		syncSettings: function(e) {
			var $field = $(e.target), key;
			if ( key = $field.data('setting') ) {
				this.controller.uploader.files[0].model.set(key,$field.val());
			}
		},

	});

	/**
	 * Your browser is unsupported. Wah wah.
	 */
	ooyala.view.UnsupportedBrowser = media.View.extend({
		className: 'ooyala-unsupported-browser-message',
		template: media.template('ooyala-unsupported-browser')
	});

	/**
	 * View used to display a single Ooyala result
	 * in the results grid
	 */
	ooyala.view.Attachment = media.view.Attachment.extend({
		template: media.template('ooyala-attachment'),

		initialize: function() {
			media.view.Attachment.prototype.initialize.apply(this, arguments);
			this.$el.addClass('ooyala-attachment-item');

			this.model.on('change:status', this.changeStatus, this);
			this.changeStatus();
		},

		// disable rerendering on every change while in the process of uploading
		changeStatus: function() {
			this.render();
			this.$el.addClass('status-' + this.model.get('status'));

			if ( this.model.get('status') == 'uploading' && this.model.get('percent') !== undefined ) {
				this.model.off('change', this.render);
				// this.progress() needs this element
				this.$bar = this.$('.media-progress-bar div');
				this.model.on('change:percent',this.progress,this);
			} else {
				this.model.on('change',this.render,this);
			}
		},

		// Add to selection (Recently Viewed)
		toggleSelection: function( options ) {
			var collection = this.collection,
				selection = this.options.selection,
				model = this.model;

			if(!selection)
				return;

			selection.unshift(model);
			selection.single(model);
		},
	});

	/**
	 * Settings / Info for a particular selected asset
	 * shown in the sidebar
	 */
	ooyala.view.Details = media.view.Attachment.Details.extend({

		template: media.template('ooyala-details'),
		className: 'ooyala-details',

		events: {
			'click a.show-more': 'toggleMore',
		},

		initialize: function() {

			this.options = _.extend( this.options, {
				descriptionMaxLen: 400, //maximum character length for descriptions
				maxLenThreshold: 15, //character threshold (it would be silly to chop of that or anything less)
			});
			// update as we are uploading or when the status changes
			this.model.on('change:percent', this.progress, this);
			this.model.on('change:status', this.render, this);

			// or if we get an attachment ID
			this.model.on('change:attachment_id', this.render, this);

			media.view.Attachment.Details.prototype.initialize.apply(this, arguments);

			// Load up additional details
			this.model.fetch();
		},

		// update the percentage progress
		progress: function(model) {
			this.$('.progress span').text(model.get('percent'));
		},

		// show or hide extra description text that exceeds the limit
		toggleMore: function(e) {
			e.preventDefault();
			$(e.target).html( this.$('span.more').toggleClass('show').hasClass('show') ? '(show&nbsp;less)' : '(show&nbsp;more)' );
		},

	});

	/**
	 * Media frame toolbar
	 */
	ooyala.view.Toolbar = media.view.Toolbar.extend({
		className: 'ooyala-toolbar media-toolbar',

		events: {
		},

		initialize: function(options) {
			var self = this;

			media.view.Toolbar.prototype.initialize.apply(this, arguments);

			// The past selection of assets
			this.selection = new ooyala.view.Selection({
				controller: this.controller,
				collection: options.collection
			});

			// This is the primary action button
			this.button = new media.view.Button({
				text: ooyala.text.insertAsset,
				style: 'primary',
				disabled: true,
				requires: { selection: true },
				priority: 10,
				click: function() {
					self.controller.state().insert();
				}
			});

			this.collection.on('selection:single change:attachment', this.updateButton, this);

			this.primary.set('button', this.button);
			this.secondary.set('selection', this.selection);
			this.updateButton();
		},

		// update the 'Insert' button based on state of selected asset
		updateButton: function() {
			// selected asset
			var asset = this.controller.state().get('selection').single();
			// if there is a selected asset and it is embeddable
			if(asset && (asset.canEmbed() || this.controller.state().display(asset).get('forceEmbed')) ) {
				this.button.model.set('disabled', false);
			} else {
				this.button.model.set('disabled', true);
			}
		},
	});

	/**
	 * Selection thumbnail
	 */
	ooyala.view.Attachment.Selection = ooyala.view.Attachment.extend({
		className: 'attachment selection',

		// On click, just select the model, instead of removing the model from
		// the selection.
		toggleSelection: function() {
			this.options.selection.single(this.model);
		}
	});

	/**
	 * Selection
	 */
	ooyala.view.Selection = media.View.extend({
		tagName:   'div',
		className: 'media-selection',
		template:  media.template('media-selection'),

		events: {
			'click .edit-selection':  'edit',
			'click .clear-selection': 'clear'
		},

		initialize: function() {
			_.defaults( this.options, {
				editable:  false,
				clearable: false,
			});

			this.attachments = new media.view.Attachments.Selection({
				controller: this.controller,
				collection: this.collection,
				selection:  this.collection,
				AttachmentView: ooyala.view.Attachment.Selection,
				model:      new Backbone.Model({
					edge:   40,
					gutter: 5
				})
			});

			this.views.set( '.selection-view', this.attachments );
			this.collection.on( 'add remove reset', this.refresh, this );
			this.controller.state().on( 'content:activate', this.refresh, this );
		},

		ready: function() {
			this.refresh();
		},

		refresh: function() {
			// If the selection hasn't been rendered, bail.
			if ( ! this.$el.children().length )
				return;

			var collection = this.collection,
				editing = 'edit-selection' === this.controller.content.mode();

			// If nothing is selected, display nothing.
			this.$el.toggleClass( 'empty', ! collection.length );
			this.$el.toggleClass( 'one', 1 === collection.length );
			this.$el.toggleClass( 'editing', editing );

			this.$('.count').text( ooyala.text.recentlyViewed );
		},

		edit: function( event ) {
			event.preventDefault();
			if ( this.options.editable )
				this.options.editable.call( this, this.collection );
		},

		clear: function( event ) {
			event.preventDefault();
			this.collection.reset();
		}
	});

})(jQuery);
