/**
 * Models / Controllers for the Getty Images plugin
 *
 * @package Getty Images
 * @author  bendoh, thinkoomph
 */
(function($) {
	var media = wp.media;
	var getty = gettyImages;
	var GettyAttachments;

	// Simple Getty API module. Relies on CORS support
	var api = {
		id: '10787',
		key: 'fQGtDPrif9akkGsXkde0gC30E1JdUpmWFOuMQxkF+xI=',
		anonymous: {
			username: 'wordpressplugin_api',
			password: 'vNua0JpFtpWe3lQ'
		},
		endpoints: {
			CreateSession: 'https://connect.gettyimages.com/v1/session/CreateSession',
			CreateApplicationSession: 'https://connect.gettyimages.com/v1/session/CreateApplicationSession',
			RenewSession: 'https://connect.gettyimages.com/v1/session/RenewSession',
			GetActiveProductOfferings: '//connect.gettyimages.com/v1/data/GetActiveProductOfferings',
			GetAllProductOfferings: '//connect.gettyimages.com/v1/data/GetAllProductOfferings',
			SearchForImages: '//connect.gettyimages.com/v2/search/SearchForImages',
			GetImageDetails: '//connect.gettyimages.com/v1/search/GetImageDetails',
			GetLargestImageDownloadAuthorizations: '//connect.gettyimages.com/v1/download/GetLargestImageDownloadAuthorizations',
			GetImageDownloadAuthorizations: '//connect.gettyimages.com/v1/download/GetImageDownloadAuthorizations',
			CreateDownloadRequest: 'https://connect.gettyimages.com/v1/download/CreateDownloadRequest',
			GetEventDetails: '//connect.gettyimages.com/v1/search/GetEventDetails'
		},

		// Make an API request, return a promise for the request
		request: function(endpoint, body) {
			var payload = {
				RequestHeader: {
					CoordinationId: '',
					Token: ''
				}
			};

			var url = api.endpoints[endpoint];
			if(!url) {
				return;
			}

			// Have we a session?
			var session = getty.user.get('session');

			// Is it valid?
			if(session && session.expires < new Date().getTime() / 1000) {
				getty.user.logout();
				session = false;
			}

			// Assume that the request body name is the same as the endpoint name +
			// "RequestBody", as appears to be the case for almost all of the API calls,
			// except "CreateDownloadRequest"
			if(endpoint == 'CreateDownloadRequest') {
				payload['CreateDownloadRequestBody'] = body;
			}
			else if(endpoint == 'SearchForImages') {
				payload['SearchForImagesRequestBody'] = body;
			}
			else {
				payload[endpoint + 'RequestBody'] = body;
			}

			// Defer the request until we get authentication
			var result = $.Deferred();
			var defer = $.Deferred();

			defer
				.done(function() {
					// Even if we have a valid session, providing it as a token
					// to CreateSession results in an error.
					if(endpoint != 'CreateSession' && endpoint != 'CreateApplicationSession') {
						var tokens = session ? session : getty.user.anonymous;

						// Choose correct session token based on request protocol
						if(url.match(/^https:/)) {
							payload.RequestHeader.Token = tokens.secure;
						}
						else {
							payload.RequestHeader.Token = tokens.token;
						}

						if(!payload.RequestHeader.Token) {
							result.reject({ Message: "No valid token found" });
						}
					}

					$.ajax(url, {
						data: JSON.stringify(payload),
						type: 'POST',
						accepts: 'application/json',
						contentType: 'application/json',
					})
						.fail(result.reject)
						.done(function(data) {
							if(data.ResponseHeader.Status == 'error') {
								result.reject(data.ResponseHeader.StatusList);
							}
							else {
								result.resolve(data[endpoint + 'Result']);
							}
						});
				})
				.fail(function() {
					// API must be unavailable. Messaging!
					result.reject();
				});

			// Try unauthenticated credentials for requests with no session or https
			if(!session && !url.match(/^https:/)) {
				getty.user.createApplicationSession()
					.done(defer.resolve)
					.fail(defer.reject);
			}
			else {
				defer.resolve();
			}

			return result.promise();
		}
	};

	/**
	 * Attach plugin nonce to any WP AJAX calls
	 */
	getty.post = function(action, data) {
		data = data || {};

		data.nonce = getty.nonce;

		return media.post(action, data);
	}

	/**
	 * A single Getty Image result
	 */
	media.model.GettyAttachment = media.model.Attachment.extend({
		parse: function( resp, xhr ) {
			if ( ! resp )
				return resp;

			var dateRegexp = /^\/Date\((\d+)(-\d+)?\)\/$/;

			this.set('sizes', gettyImages.sizes);

			// Convert date strings into Date objects.
			_.each([ 'DateCreated', 'DateSubmitted' ], function(field) {
				var match = resp[field].match(dateRegexp);

				if(match)
					resp[field] = new Date(parseInt(match[1])).toString();
			});

			return resp;
		},

		sync: function(method, model, options) {
			if(method == 'read') {
				this.getWpDetails();

				// Clone the args so manipulation is non-destructive.
				var args = _.clone( this.args );

				this.set('downloadingDetails', true);

				var self = this;
				return api.request('GetImageDetails', {
					CountryCode: 'USA',
					ImageIds: [ model.get('ImageId') ]
				})
					.done(function(data) {
						self.set('haveDetails', true);
						self.set(self.parse(data.Images[0], this));

						if(getty.user.get('loggedIn')) {
							self.getDownloadAuthorizations();
						}
					})
					.fail(function(data) {
						// TODO: Error condition
					})
					.always(function() {
						self.unset('downloadingDetails');
					});
			}
		},

		getDownloadAuthorizations: function() {
			var sizes = this.get('SizesDownloadableImages');

			// No downloadable image sizes anyway. Do nothing.
			if(!sizes) {
				return;
			}

			var request = {
				ImageSizes: _.map(sizes, function(size) {
					return {
						ImageId: this.get('ImageId'),
						SizeKey: size.SizeKey
					}
				}, this)
			};

			sizes = _.groupBy(sizes, 'SizeKey');

			this.set('authorizing', true);

			// Get download authorizations for all sizes
			var self = this;

			api.request('GetImageDownloadAuthorizations', request)
				.done(function(data) {
					var authorizations = {};
					var sizeKeys = {};

					// Aggregate all authorizations for this image into the model
					_.each(data.Images, function(image) {
						if(image.ImageId == self.get('ImageId') && image.Status == 'available') {
							var largestSizeKey;

							_.each(image.Authorizations, function(auth) {
								if(!authorizations[auth.ProductOfferingType]) {
									authorizations[auth.ProductOfferingType] = [];
								}

								image = _.extend(image, sizes[image.SizeKey][0]);
								image = _.extend(image, auth);
								authorizations[auth.ProductOfferingType].push(image);
								sizeKeys[auth.ProductOfferingType] = image.SizeKey;
							});

							delete image.Authorizations;
						}
					});

					self.set('SizeKeys', sizeKeys);
					self.set('DownloadAuthorizations', authorizations);
				})
				.always(function() {
					self.unset('authorizing');
				});
		},

		download: function() {
			var downloadToken = this.get('DownloadToken');

			if(downloadToken) {
				this.downloadWithToken(downloadToken);
				return;
			}
		},

		// Download a single size of a single image given a download token
		downloadWithToken: function(token) {
			var self = this;

			this.set('downloading', true);

			api.request('CreateDownloadRequest', {
				DownloadItems: [ { DownloadToken: token } ]
			})
				.done(function(response) {
					var meta = self.toJSON();

					delete meta.attachment;
					delete meta[''];

					// Get the URL, forward to WP for sideloading
					getty.post( 'getty_images_download', {
						url: response.DownloadUrls[0].UrlAttachment,
						post_id: $('#post_ID').val(),
						meta: meta
					})
						.done(function(wpresponse) {
							self.downloaded(wpresponse);
						})
						.always(function() {
							self.unset('downloading');
							self.unset('DownloadToken');

							var refreshState = function(state) {
								var lib;
								
								if(!state) {
									return;
								}

								lib = state.get('library');

								if(!lib) {
									return;
								}

								var r = parseInt(lib.props.get('getty-refresh'));

								if(!r || isNaN(r)) {
									r = 1;
								}

								lib.props.set('getty-refresh', r + 1);
							}

							// Force all other attachment queries to refresh
							for(var frame in media.frames) {
								_.each([ 'insert', 'select', 'gallery', 'featured-image' ], function(state) {
									refreshState(media.frames[frame].state(state));

									var modal = media.editor.get();
									if(modal) {
										refreshState(modal.state(state));
									}
								});
							}
						});
				});
		},

		// Image downloaded! Yay!
		downloaded: function(response) {
			var s = window.getty_s;
			this.set('attachment', new media.model.Attachment(response));

			// Clear out any cached queries in media library
			if(s) {
				s.events = 'event6';
				s.prop1 = s.eVar1 = s.prop2 = s.eVar2 = '';
				s.prop3 = s.eVar3 = this.get('ImageId');
				getty.tl();
			}
		},

		// Get WordPress image details
		getWpDetails: function() {
			// Get the URL, forward to WP for sideloading
			var self = this;
			getty.post( 'getty_image_details', {
				ImageId: this.get('ImageId')
			})
				.done(function(response) {
					self.set('attachment', new media.model.Attachment(response));
				});
		}
	}, {
		create: function( attrs ) {
			return GettyAttachments.all.push( new media.model.GettyAttachment(attrs) );
		},

		get: _.memoize( function( id, attachment ) {
			return GettyAttachments.all.push( attachment || new media.model.GettyAttachment({ id: id }) );
		})
	});

	/**
	 * The collection of Getty Images search resulggts.
	 */
	GettyAttachments = media.model.GettyAttachments = media.model.Attachments.extend({
		model: media.model.GettyAttachment,

		initialize: function(models, options) {
			options = options || {};

			// Keep a controller reference
			this.controller = options.controller;

			// Queried search properties
			this.props = new Backbone.Model();

			// Queue up query changes in this model, but don't refresh search
			// until executed
			this.propsQueue = new Backbone.Model();

			// Result data: total, refinements
			this.results = new Backbone.Model();

			// Propagate changes to props to propsQueue:
			this.props.on('change', this.changeQueuedProps, this);
		},

		// Forward changes to search props to the queue
		changeQueuedProps: function() {
			this.propsQueue.set(this.props.attributes);
		},

		// Observe changes to result properties in mirrored collection
		observe: function(attachments) {
			attachments.results.on('change', this.syncResults, this);

			return media.model.Attachments.prototype.observe.apply(this, arguments);
		},

		// Stop observing result properties in mirrored collection
		unobserve: function(attachments) {
			attachments.results.off('change', this.syncResults, this);

			return media.model.Attachments.prototype.unobserve.apply(this, arguments);
		},

		// Sync up results with mirrored query
		syncResults: function(model) {
			this.results.set(model.changed);
		},

		// Perform a search with queued search properties
		search: function() {
			var searchTerm = this.propsQueue.get('search'),
				s = window.getty_s;

			if(typeof searchTerm != 'string' || !searchTerm.match(/[^\s]/)) {
				return;
			}

			if(this.propsQueue.get('Refinements') && this.propsQueue.get('Refinements').length == 0) {
				this.propsQueue.unset('Refinements');
			}

			if(getty.user.settings.get('mode') == 'embed') {
				this.propsQueue.set('EmbedContentOnly', "true");
			}
			else {
				this.propsQueue.unset('EmbedContentOnly');
			}

			var query = media.model.GettyQuery.get(this.propsQueue.toJSON());

			if(query !== this.mirroring) {
				this.reset();
				this.props.clear();
				this.props.set(this.propsQueue.attributes);

				this.results.unset('total');
				this.results.set('searching', true);
				this.results.set('searched', false);

				this.mirror(query);

				if(s) {
					s.events = 'event2';
					s.prop1 = s.eVar1 = s.prop3 = s.eVar3 = '';
					s.prop2 = s.eVar2 = this.props.get('search');
					getty.tl();
				}
			}

			// Force reset of attributes for cached queries
			if(query.cached) {
				this.results.clear();
				this.results.set(query.results.attributes);
			}
		},
	}, {
		filters: {
			search: function(attachment) {
				if (!this.props.get('search'))
          return true;

        return _.any(['Title', 'Caption', 'ImageId', 'UrlComp', 'ImageFamily'], function(key) {
          var value = attachment.get(key);
          return value && -1 !== value.search(this.props.get('search'));
        }, this);
			}
		}
	}	);

	// Cache all attachments here. TODO: Memory clean up?
	GettyAttachments.all = new GettyAttachments();

	var GettyEventRefinements = new Backbone.Collection();

	/**
	 * The Getty Images query and parsing model
	 */
	media.model.GettyQuery = media.model.Query.extend({
		initialize: function( models, options ) {
			media.model.Query.prototype.initialize.apply(this, arguments);

			options = options || {};

			// Track refinement options and total
			this.results = new Backbone.Model();
			this.results.unset('total');
			this.results.set('searching', false);
			this.results.set('searched', false);

			// Track number of results returned from API separately from
			// the size of the collection, as the API MAY give back duplicates
			// which will cause the paging to screw up since the collection
			// will have fewer results than were actually returned.
			this.numberResults = 0;
		},

		// Override more() to return a more-deferred deferred object
		// and not bother trying to use Backbone sync() or fetch() methods
		// to get the data, since this is a very custom workflow
		more: function( options ) {
			if ( this._more && 'pending' === this._more.state() )
				return this._more;

			if(!this.hasMore())
				return $.Deferred().resolveWith(this).promise();

			if(_.isEmpty(this.props.get('search')))
				return $.Deferred().resolveWith(this).promise();

			// Flag the search as executing
			this.results.set('searching', true);

			// Build searchPhrase from any main query + refinements
			var searchPhrase = this.props.get('search');

			if(this.props.get('SearchWithin')) {
				searchPhrase += ' ' + this.props.get('SearchWithin');
			}

			var args = _.clone(this.args);
			this.page = Math.floor(this.numberResults / args.posts_per_page);
			var request = {
				Query: {
					SearchPhrase: searchPhrase,
				},
				ResultOptions: {
					IncludeKeywords: false,
					ItemCount: args.posts_per_page,
					ItemStartNumber: this.page * args.posts_per_page + 1,
					EditorialSortOrder: this.props.get('EditorialSortOrder'),
					CreativeSortOrder: this.props.get('CreativeSortOrder')
				},
				// Could write this much more compactly, but expanding it for clarity
				Filter: {
					// Note that this value expects array but we only ever pass one value
					ImageFamilies: [ this.props.get('ImageFamilies') ],
					GraphicStyles: this.props.get('GraphicStyles'),
					Orientations: this.props.get('Orientations'),
					ExcludeNudity: this.props.get('ExcludeNudity'),
					Refinements: this.props.get('Refinements'),
					EmbedContentOnly: this.props.get('EmbedContentOnly')
				}
			};

			// Make logical tweaks to filter based on what the API can do:
			if(this.props.get('ImageFamilies') == 'editorial') {
				delete request.ResultOptions.CreativeSortOrder;
				// Can't choose Graphic Style for editorial, since only photography
				// is available
				delete request.Filter.GraphicStyles;
			}
			else if(this.props.get('ImageFamilies') == 'creative') {
				// Editorial Sort Order doesn't make any sense for creative
				delete request.ResultOptions.EditorialSortOrder;
			}

			// Get refinement options for the first page of results only
			if(this.page == 0) {
				request.ResultOptions.RefinementOptionsSet = 'RefinementSet2';
			}

			// Add in any applicable product offerings so the user only gets
			// results for product offerings he owns
			var products = getty.user.get('products');

			if(products && products.length) {
				request.Filter.ProductOfferings = products;
			}

			// Proxy the deferment from the API query so we can retry if
			// necessary
			if('deferred' in this) {
				this.deferred.retry++;
			}
			else {
				this.deferred = $.Deferred();
				this.deferred.retry = 0;
			}

			var self = this;
			this._more = api.request('SearchForImages', request)
				.done(function(response) {
					self.set(self.parse(response), { remove: false });

					self.numberResults += response.Images.length;

					if(response.Images.length == 0 || response.Images.length < args.posts_per_page) {
						self._hasMore = false;
					}

					self.deferred.resolve(response);
					delete self.deferred;
				})
				.fail(function(response) {
					if(self.deferred.retry < 3) {
						self.more();
					}
					else {
						self.deferred.reject(response);
						delete self.deferred;
					}
				})
				.always(function() {
					self.results.set('searching', false);
				});

			return (this.deferred ? this.deferred : $.Deferred().reject()).promise();
		},

		parse: function(response, xhr) {
			this.results.set('total', response.ItemTotalCount);

			if(this.page == 0 && response.RefinementOptions && typeof response.RefinementOptions == "object") {
				var refinements = new Backbone.Collection();
				var eventRefinementQueue = [];

				var refinementCategories = _.groupBy(response.RefinementOptions, 'Category');

				// Remove "AssetFamily" category because it's already a primary search
				// parameter
				delete refinementCategories.AssetFamily;
				// Remove "PhotographerName" category because of API issues (See GETTY-37)
				delete refinementCategories.PhotographerName;

				_.each(refinementCategories, function(options, category) {
					var categoryModel = new Backbone.Model({
						id: category,
						options: new Backbone.Collection()
					});

					refinements.add(categoryModel);

					_.each(options, function(option) {
						var optionModel = new Backbone.Model({
							id: option.Id,
							text: option.Text,
							category: category,
							count: option.ImageCount
						});

						categoryModel.get('options').add(optionModel);

						if(category == "Event") {
							var eventRefinement = GettyEventRefinements.get(optionModel.id);

							// Check if this is a known event
							if(!eventRefinement || !eventRefinement.get('event')) {
								// If not, add event ID to lookup queue and save
								// reference in cache
								GettyEventRefinements.add(optionModel);

								eventRefinementQueue.push(optionModel.id);
							}
							else {
								// If so, update text and set event reference
								optionModel.set('text', eventRefinement.get('text'));
								optionModel.set('event', eventRefinement.get('event'));
							}
						}
					});
				});

				this.results.set('refinements', refinements);

				if(eventRefinementQueue.length) {
					// Get any unknown event IDs from the API
					api.request('GetEventDetails', {
						EventIds: eventRefinementQueue
					})
						.done(function(result) {
							_.each(result.EventResult, function(evt) {
								var eventRefinement = GettyEventRefinements.get(evt.EventId);

								if(eventRefinement) {
									eventRefinement.set('text', evt.Event.EventName);
									eventRefinement.set('event', evt.Event);
								}
							});
						});
				}
			}

			this.results.set('searched', true);

			var attachments = _.map(response.Images, function(item) {
				var id, attachment, newAttributes;

				id = item.ImageId;

				attachment = wp.media.model.GettyAttachment.get( id );
				newAttributes = attachment.parse( item, xhr );

				if ( ! _.isEqual( attachment.attributes, newAttributes ) )
					attachment.set( newAttributes );

				return attachment;
			});

			this.getImageDownloadAuths(attachments);

			return attachments;
		},

		getImageDownloadAuths: function(attachments) {
			// Get the set of images which don't already have
			// known DownloadAuths
			var images = _.map( _.filter(attachments, function(attachment) {
				return !attachment.get('LargestDownloadAuthorizations')
			}), function(attachment) {
				return { ImageId: attachment.get('ImageId') };
			});

			// Why bother with an empty set?
			if(images.length == 0)
				return;

			return api.request('GetLargestImageDownloadAuthorizations', {
				Images: images
			})
				.done(function(response) {
					_.each(response.Images, function(auth) {
						var model = media.model.GettyAttachments.all.get(auth.ImageId);

						model.set('Status', auth.Status);

						if(auth.Authorizations.length > 0) {
							if(!model.get('ProductOffering')) {
								model.set('ProductOffering', auth.Authorizations[0].ProductOfferingType);
							}
							if(!model.get('DownloadSizeKey')) {
								var sizeKeys = {};

								sizeKeys[auth.Authorizations[0].ProductOfferingType] = auth.Authorizations[0].SizeKey;
								model.set('SizeKeys', sizeKeys);
								model.set('DownloadSizeKey', auth.Authorizations[0].SizeKey);
							}

							model.set('LargestDownloadAuthorizations', auth.Authorizations);
						}
					});
				});
		}
	}, {
		defaultArgs: {
			posts_per_page: 50
		}
	});

	// Ensure our query object gets used instead, there's no other way
	// to inject a custom query object into media.model.GettyQuery.get
	// so we must override. This function caches distinct queries
	// so that re-queries come back instantly. Though there's no memory cleanup...
	media.model.GettyQuery.get = (function(){
		var queries = [];
		var Query = media.model.GettyQuery;

		return function( props, options ) {
			var args     = {},
					defaults = Query.defaultProps,
					query;

			// Remove the `query` property. This isn't linked to a query,
			// this *is* the query.
			delete props.query;

			// Fill default args.
			_.defaults( props, defaults );

			// Generate the query `args` object.
			// Correct any differing property names.
			_.each( props, function( value, prop ) {
				if ( _.isNull( value ) )
					return;

				args[ Query.propmap[ prop ] || prop ] = value;
			});

			// Fill any other default query args.
			_.defaults( args, Query.defaultArgs );

			// Search the query cache for matches.
			query = _.find( queries, function( query ) {
				return _.isEqual( query.args, args );
			});

			// Otherwise, create a new query and add it to the cache.
			if ( ! query ) {
				query = new Query( [], _.extend( options || {}, {
					props: props,
					args:  args
				} ) );

				// Only push successful queries into the cache.
				query.more().done(function() {
					queries.push(query);
				});
			}
			else {
				// Flag that this was a cached query
				query.cached = true;

				// Reverse the models for cached queries
				// because there's a bug in the media library
				// that causes the images to come back
				// in the reverse order when it's cached
				query.models.reverse();
			}

			return query;
		};
	}());

	/**
	 * Getty user and session management
	 */
	media.model.GettyUser = Backbone.Model.extend({
		defaults: {
			id: 'getty-images-login',
			username: '',
			loginToken: '',
			seriesToken: '',
			loginTime: 0,
			loggedIn: false
		},

		initialize: function() {
			var settings = {};

			try {
				settings = JSON.parse($.cookie('wpGIc')) || {};
			} catch(ex) {
			}

			this.settings = new Backbone.Model(settings);
			this.settings.on('change', this.updateUserSettings, this);
		},

		updateUserSettings: function(model, values) {
			$.cookie('wpGIc', JSON.stringify(model));
		},

		// Create an application-level session for anonymous searching,
		// return a promise object
		createApplicationSession: function() {
			// If we couldn't log in, create or restore an anonymous session.
			// Refresh the session only if it's 1 minute before or any time after
			// expiration
			var session = this.anonymous;

			if(!session || session.expires < new Date().getTime() / 1000 - 60) {
				// No session or most definitely timed out session, create a new one
				var self = this;

				session = this.anonymous = { promise: $.Deferred() };

				api.request('CreateApplicationSession', {
					SystemId: api.id,
					SystemPassword: api.key
				})
					.done(function(result) {
						session.token = result.Token;
						session.expires = parseInt(result.TokenDurationMinutes * 60) + parseInt(new Date().getTime() / 1000);

						session.promise.resolve();
					})
					.fail(function(result) {
						session.promise.reject()
					});
			}

			return session.promise;
		},

		// Restore login session from cookie.  Sets loggedIn if the session
		// expiration has not passed yet.
		restore: function() {
			var loginCookie = jQuery.cookie('wpGIs');

			this.unset('error');

			var loggedIn = false;

			if(loginCookie && loginCookie.indexOf(':') > -1) {
				var un_token_series = loginCookie.split(':');

				if(un_token_series.length == 4) {
					this.set('username', un_token_series[0]);

					this.set('session', {
						token: un_token_series[1],
						secure: un_token_series[2],
						expires: un_token_series[3]
					});

					// Consider ourselves logged in if expiration hasn't passed.
					// If less than 5 minutes away, try to refresh from server.
					var diff = un_token_series[3] - new Date().getTime() / 1000;

					if(diff > 0) {
						loggedIn = true;

						// Pull in available product offerings for the user
						var self = this;
						api.request('GetActiveProductOfferings')
							.done(function(result) {
								self.set('products', result.ActiveProductOfferings);
								self.set('ProductOffering', result.ActiveProductOfferings[0]);
							});

						// Refresh session on a timer to keep it alive,
						// either immediately or when the session is 2 minutes from expiring
						//
						// refresh() will call restore() on success, restore() will re-set the timer
						// if the refresh was successful.
						var self = this;

						this.refreshTimer = setTimeout(function() {
							self.refresh();
						}, Math.max(diff - 1*60, 0) * 1000);
					}
				}
			}

			this.set('loggedIn', loggedIn);
		},

		login: function(password) {
			var s = window.getty_s;

			this.set('loggingIn', true);
			this.unset('error');

			var self = this;

			return api.request('CreateSession', {
				SystemId: api.id,
				SystemPassword: api.key,
				UserName: this.get('username'),
				UserPassword: password
			})
				.done(function(result) {
					// Stick the session data in a persistent cookie
					self.refreshSession(result);

					if(s) {
						s.events = 'event1';
						s.prop2 = s.eVar2 = s.prop3 = s.eVar3 = '';
						s.prop1 = s.eVar1 = self.get('username');
						getty.tl();
					}
				})
				.fail(function(statuses) {
					self.set('loggedIn', false);
					self.trigger('change:loggedIn');
					self.set('promptLogin', true);
					self.set('error', statuses[0].Message);
				})
				.always(function() {
					self.set('loggingIn', false);
				});
		},

		refreshSession: function(result) {
			// Pluck the values we need to save the session.
			var session = [
				this.get('username'),
				result.Token,
				result.SecureToken,
				parseInt(result.TokenDurationMinutes * 60) + parseInt(new Date().getTime() / 1000)
			];

			// Save it in a cookie: nom.
			$.cookie('wpGIs', session.join(':'));

			// Restore from the cookie
			this.restore();
		},

		// Try to refresh GettyUser session in the API.
		// Keep session in cookie
		refresh: function() {
			clearTimeout(this.refreshTimer);
			delete this.refreshTimer;

			var self = this;

			// Return any existing restoration promise
			if(self.refreshing) {
				return self.refreshing;
			}

			self.unset('error');
			self.set('loggingIn', true);

			self.refreshing = api.request('RenewSession', {
				SystemId: api.id,
				SystemPassword: api.key
			})
				.always(function() {
					delete self.refreshing;
					self.set('loggingIn', false);
				})
				.done(function(result) {
					// Stick the session data in a persistent cookie
					self.refreshSession(result);
				})
				.fail(function(data) {
					self.set('error', data.message);
				});

			// Return actual promise object if we're less than 1 minute away
			// from expiration,
			if(self.get('loggedIn') && self.get('expires') - new Date().getTime() < 1*60*1000)  {
				return self.refreshing;
			}
			else {
				// Otherwise return affirmative immediately
				return $.Deferred().resolveWith(this, {success: true}).promise();
			}
		},

		logout: function() {
			// Log out, which will clear (most) of the cookie values out
			// so the username can be retained but the login and session
			// tokens get erased
			var self = this;

			// Throw away expired sessions.
			$.cookie('wpGIs', '');

			// Throw away invalidated attributes
			self.unset('session');
			self.unset('products');

			self.set('loggedIn', false);

			// Throw away all download auths and image statuses
			media.model.GettyAttachments.all.each(function(image) {
				image.unset('SizesDownloadableImages');
				image.unset('Authorizations');
				image.unset('Status');
			});

			// Trash cookie but keep the username for convenience
			$.cookie('wpGIs', [ self.get('username'), '', '', '' ].join(':'));

			// Use application session
			this.createApplicationSession();
		}
	});

	/**
	 * Display options based on an existing attachment
	 */
	media.model.GettyDisplayOptions = Backbone.Model.extend({
		initialize: function() {
			this.attachment = this.get('attachment');

			if(!this.attachment) {
				return;
			}

			this.attachment.on('change:attachment', function() {
				this.wpAttachment = this.attachment.get('attachment');
				this.fetch();
			}, this);
			this.wpAttachment = this.attachment.get('attachment');
			this.set('caption', this.attachment.get('Caption'));
			this.set('alt', this.attachment.get('Title'));
			this.set('sizes', _.clone(getty.sizes));
			this.fetch();
		},

		sync: function(method, model, options) {
			if(method == 'read') {
				// If there's an attachment, pull the largest size in the database,
				// calculate potential sizes based on that
				this.image = new Image();
				var url;

				if(!this.wpAttachment) {
					url = this.attachment.get('UrlWatermarkComp');

					if(url == "unavailable") {
						url = this.attachment.get('UrlComp');
					}

					this.set('caption', this.attachment.get('Caption'));
					this.set('alt', this.attachment.get('Title'));
				}
				else {
					url = this.wpAttachment.get('url');
					this.set('caption', this.wpAttachment.get('caption'));
					this.set('alt', this.wpAttachment.get('alt'));
				}

				$(this.image).on('load', this.loadImage());
				this.image.src = url;
			}
		},

		// Closure-in-closure because we can't control the binding of
		// 'this' with jQuery-registered event handlers
		loadImage: function() {
			var self = this;

			return function(ev) {
				var sizes = {};
				var ar = this.width / this.height;

				// Constrain image to image sizes
				_.each(gettyImages.sizes, function(size, slug) {
					var cr = size.width / size.height;
					var s = { label: size.label };

					s.url = this.src;

					if(ar > cr) {
						// Constrain to width
						s.width = parseInt(size.width);
						s.height = parseInt(size.width / ar);
					}
					else {
						// Constrain to height (or square!)
						s.width = parseInt(ar * size.height);
						s.height = parseInt(size.height);
					}

					sizes[slug] = s;
				}, this);

				sizes.full = {
					label: getty.text.fullSize,
					width: this.width,
					height: this.height,
					url: this.src
				}

				self.set('sizes', sizes);
			}
		}
	});

})(jQuery);
