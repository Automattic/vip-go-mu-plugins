/**
 * Models / Controllers for the Getty Images plugin
 *
 * @package Getty Images
 * @author  bendoh, thinkoomph
 */
(function($) {
	var media = wp.media;

	// Wrap ooyala API calls. First WordPress is invoked to compute
	// the proper signed URL for the request, and then that request is invoked
	// directly from the client
	var api = {
		// Make an API request, return a promise for the request
		request: function(method, path, params, body, context) {
			// Defer the request until we get authentication
			var result = $.Deferred();

			//convert object to JSON string now so the signature will be properly formed
			if ( body && typeof body !== 'string' ) body = JSON.stringify(body) || '';

			var payload = {
				method: method,
				path: path,
				params: params,
				body: body,
				nonce: ooyala.nonce
			};

			// Get request with signature from WordPress
			$.ajax(ooyala.sign, {
				data: JSON.stringify(payload),
				type: 'POST',
				accepts: 'application/json',
				contentType: 'application/json',
			})
				.fail(result.reject)
				.done(function(response) {
					var options = {
						data: payload.body,
						type: payload.method,
					}
					if (context) options.context = context;
					// And then use the computed URL to send the message
					$.ajax(response.data.url, options)
						.done(result.resolve)
						.fail(result.reject);
				});

			return result.promise();
		}
	};

	/**
	 * A single Ooyala result
	 */
	ooyala.model.Attachment = media.model.Attachment.extend({

		initialize: function() {
			if ( this.isNew() && this.get('embed_code')) this.set('id',this.get('embed_code'));
		},

		parse: function(resp, xhr) {
			if(!resp)
				return resp;

			// round duration to nearest 1/10 second and convert to pretty string
			if ( resp.duration != undefined ) resp.duration_string = new Number( 100 * Math.round( resp.duration/100 ) ).msToString();
			return resp;
		},

		sync: function(method, model, options) {
			if(method == 'read') {

				// get stream resolutions if we do not have them, but only for videos or ads that were processed already
				if ( _.contains( ['video','ad'], model.get('asset_type') ) && _.contains( ['live','paused'], model.get('status') ) && !model.get('resolutions') ) {

					model.set('downloadingResolutions', true);

					var defer = $.Deferred();

					api.request('GET','/v2/assets/' + this.get('id') + '/streams')
						.done(function(data) {
							var resolutions = _.zip( _.pluck(data,'video_width'), _.pluck(data,'video_height') );
							// remove duplicates
							resolutions = _.uniq(resolutions, false, function(x){return x.join(',');});
							// sort resolutions, largest first
							resolutions.sort(function(a,b){return b[0]-a[0];});
							model.set('resolutions',resolutions);

							$.post(ooyala.imageId, {
								image_url: model.get('preview_image_url'),
								post_id: $('#post_ID').val(),
								nonce: ooyala.nonce
							})
								.done(function(response) {
									model.set('attachment_id', response.data && response.data.id);
								})
								.always(function(response) {
									defer.resolve(response);
								});
						})
						.fail(function(jqXHR) {
							// If resolutions cannot be loaded, falls back on default dimensions automatically
							switch (jqXHR.status) {
								// 404 is returned when this asset does not have streams,
								// so save an empty array so we don't try again
								case 404:
									model.set('resolutions',[]);
								break;

								// TODO: perhaps deal with other kinds of errors here
							}

							defer.resolve(jqXHR);
						})
						.always(function() {
							model.unset('downloadingResolutions');
						});

						return defer;
				}
			} else if (method=='update') {
				// we only allow name and description to be edited at this time
				return api.request('PATCH','/v2/assets/' + this.get('id'), null, _.pick(this.toJSON(), ['name','description']))
					.done(function(data){
						model.set(data);
					});
			}
		},

		/**
		 * Sideload the image and set it to the featured image
		 */
		setFeatured: function() {
			var model = this;

			return $.post(ooyala.download, {
				image_url: this.get('preview_image_url'),
				post_id: $('#post_ID').val(),
				nonce: ooyala.nonce
			})
				.done(function(response) {
					wp.media.featuredImage.set(response.data.id);

					model.set('attachment_id', response.data && response.data.id);
				});
		},

		// fetch the asset...the normal fetch method is essentially no-op'ed (it only pulls extra info needed for details panel)
		forceFetch: function() {
			return api.request('GET','/v2/assets/' + this.get('id'), null, null, this)
				.done(function(data){
					this.set(this.parse(data));
				});
		},

		// can this asset be embedded? only status of live can be embedded properly
		canEmbed: function() {
			return this.get('status') === 'live';
		},

	}, {
		create: function(attrs) {
			return Attachments.all.push(new ooyala.model.Attachment(attrs));
		},

		get: _.memoize(function( id, attachment) {
			return Attachments.all.push(attachment || new ooyala.model.Attachment({ id: id }));
		})
	});

	/**
	 * The collection of Ooyala search results.
	 */
	var Attachments = ooyala.model.Attachments = media.model.Attachments.extend({
		model: ooyala.model.Attachment,

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
			var query = ooyala.model.Query.get(this.propsQueue.toJSON());

			if(query !== this.mirroring) {
				this.reset();
				this.props.clear();
				this.props.set(this.propsQueue.attributes);

				this.results.set('searching', true);
				this.results.set('searched', false);

				this.mirror(query);
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
				if (!this.props.get('search') && !this.props.get('label'))
					return true;

				// check if search matches any field on asset and label matches asset label
				return ( !this.props.get('search') || _.any(['name', 'embed_code', 'description'], function(key) {
					var value = attachment.get(key);
					return value && -1 !== value.search(this.props.get('search'));
					}, this) )
					&&
					( !this.props.get('label') || _.any(attachment.get('labels')||[], function(label) {
						return this.props.get('label') == label.name;
					}, this) );
			}
		}
	}	);

	// Cache all attachments here. TODO: Memory clean up?
	Attachments.all = new Attachments();

	/**
	 * The Ooyala query and parsing model
	 */
	ooyala.model.Query = media.model.Query.extend({
		initialize: function( models, options ) {
			media.model.Query.prototype.initialize.apply(this, arguments);

			options = options || {};

			// Track refinement options and total
			this.results = new Backbone.Model();
			this.results.set('searching', false);
			this.results.set('searched', false);
		},

		// Override more() to return a more-deferred deferred object
		// and not bother trying to use Backbone sync() or fetch() methods
		// to get the data, since this is a very custom workflow
		more: function( options ) {
			if ( this._more && 'pending' === this._more.state() )
				return this._more;

			// bail if we have no more to get!
			if(this.results.get('searched') && !this.hasMore())
				return $.Deferred().resolveWith(this).promise();

			// Proxy the deferment from the API query so we can retry if necessary
			if('deferred' in this) {
				this.deferred.retry++;
			} else {
				// Flag the search as executing
				this.results.set('searching', true);
				this.deferred = $.Deferred();
				this.deferred.retry = 0;
			}

			// configure params
			if ( this.results.get('nextPage') ) {
				var params = {};
				// parse out params from next page query string
				this.results.get('nextPage').replace(/([^?=&]+)=([^&]+)/g, function(str, key, value) { params[key] = decodeURIComponent(value); } );
			} else {
				// Build searchPhrase from any main query + refinements
				var searchPhrase = this.props.get('search'),
					label = this.props.get('label'),
					whereParts = [];

				// start with our defaults
				var params = { include: 'labels', limit: this.args.posts_per_page };

				if(searchPhrase) {
					// escape single quotes
					searchPhrase = searchPhrase.replace(/'/g, "\\'");
					// generate 'where' clause
					whereParts.push( _.map(['description','name','embed_code'],
						function(field){ return field + "='" + searchPhrase + "'"; } )
						.join(' OR ')
					);
				}
				if (label) {
					whereParts.push( "labels INCLUDES '" + label.replace(/'/g, "\\'") + "'" );
				}
				if (whereParts.length>0) {
					params.where = whereParts.join(' AND ');
				}
			}

			this._more = api.request('GET', '/v2/assets', params, null, this)
				.then(function(response){
					// consider the possibility that no results is an API error
					// this.deferred.retry = Math.max(this.deferred.retry,2) would ensure it would only be retried once
					if (response.items && response.items.length == 0) return $.Deferred().rejectWith(this,response).promise();
					return response;
				})
				.done(function(response) {
					this.results.set('nextPage', response.next_page || false);

					// adds (and updates) to results without removing
					this.set(this.parse(response), { remove: false });

					this.deferred.resolve(response);
					delete this.deferred;
				})
				.fail(function(response) {
					if(this.deferred.retry < 3) {
						this.more();
					}
					else {
						this.deferred.reject(response);
						delete this.deferred;
						// this is where you could display some sort of API error
						// but if so, need to distinguish from the 'no results "errors"' we are throwing
					}
				})
				.always(function() {
					// searching is over
					if ( !this.deferred ) {
						this.results.set('searching', false);
						this.results.set('searched', true);
					}
				});

			return (this.deferred ? this.deferred : $.Deferred().reject()).promise();
		},

		// We have more if there's a next page
		hasMore: function() {
			return !!this.results.get('nextPage');
		},

		parse: function(response, xhr) {

			var attachments = _.map(response.items, function(item) {
				var id, attachment, newAttributes;

				id = item['embed_code'];

				attachment = ooyala.model.Attachment.get(id);

				// Update our local result cache if any of the attributes changed
				newAttributes = attachment.parse(item, xhr);

				if(!_.isEqual(attachment.attributes, newAttributes))
					attachment.set(newAttributes);

				return attachment;
			});

			return attachments;
		},
	}, {
		defaultArgs: {
			posts_per_page: ooyala.perPage,
		}
	});

	// Ensure our query object gets used instead, there's no other way
	// to inject a custom query object into ooyala.model.Query.get
	// so we must override. This function caches distinct queries
	// so that re-queries come back instantly. Though there's no memory cleanup...
	ooyala.model.Query.get = (function(){
		var queries = [];
		var Query = ooyala.model.Query;

		return function(props, options) {
			var args     = {},
					defaults = Query.defaultProps,
					query;

			// Remove the `query` property. This isn't linked to a query,
			// this *is* the query.
			delete props.query;

			// Fill default args.
			_.defaults(props, defaults);

			// Generate the query `args` object.
			// Correct any differing property names.
			_.each(props, function(value, prop) {
				if(_.isNull(value))
					return;

				args[Query.propmap[prop] || prop] = value;
			});

			// Fill any other default query args.
			_.defaults(args, Query.defaultArgs);

			// Search the query cache for matches.
			query = _.find(queries, function(query) {
				return _.isEqual(query.args, args);
			});

			// Otherwise, create a new query and add it to the cache.
			if(!query) {
				query = new Query([], _.extend(options || {}, {
					props: props,
					args:  args
				}));

				// Only push successful queries into the cache.
				query.more().done(function() {
					queries.push(query);
				});
			}
			else if (!query.cached) {
				// Flag that this was a cached query
				query.cached = true;

				// Reverse the models for cached queries
				// because there's a bug in the media library
				// that causes the images to come back
				// in the reverse order when it's cached as
				// of WP 4.0.
				query.models.reverse();
			}

			return query;
		};
	}());

	/**
	 * Player display options for a particular asset
	 */
	ooyala.model.DisplayOptions = Backbone.Model.extend({

		defaults: _.extend( { lockAspectRatio:true }, ooyala.playerDefaults ),

		/*
		 * NOTE: Is this object (DisplayOptions) even necessary? – it's only saving
		 * a player_id property (copied from Asset model) and then a reference to
		 * the attachment/Asset itself.
		 *
		 * This is only referenced ONCE anywhere (in ooyala.State.insert ->
		 * ooyala.State.display), and most of the other properties used in
		 * rendering the shortcode are derived from the attachment object anyway.
		 *
		 * Food for thought. I don't want to refactor this un-necessarily, but it
		 * seems that this holdover pattern is purely vestigial and can be trimmed
		 * safely. bcd 2015.07.29
		 */

		initialize: function() {
			this.attachment = this.get('attachment');

			if(!this.attachment) {
				return;
			}

			// select the asset's player id by default
			this.set( 'player_id', this.attachment.get('player_id') );

		},

		sync: _.noop,

	}, {
		// retrieve all players associated with the users account and cache for future use
		players: function() {
			return this._players = this._players || new ooyala.model.Players();
		},
	});

	/**
	 * Collection of players that are configured on the user's account
	 */
	ooyala.model.Players = Backbone.Collection.extend({

		isFetching: false,

		initialize: function() {
			this.fetch();
			this.on('sync error', function() { this.isFetching = false; }, this);
		},

		fetch: function() {
			this.isFetching = true;
			this.trigger('fetching');
			return Backbone.Collection.prototype.fetch.apply(this,arguments);
		},

		parse: function(response,options) {
			return response.items || [];
		},

		sync: function(method, collection, options) {
			if ( method == 'read' ) {
				return api.request('GET', '/v2/players')
					.done(options.success)
					.fail(options.error);
			}
		}

	});

	/**
	 * A file that is uploading or about to be
	 */
	ooyala.model.Upload = Backbone.Model.extend({

		// default attributes for new asset
		defaults: {
			asset_type: 'video',
			chunk_size: ooyala.chunk_size,
		},

		initialize: function() {
			// keep these attrs in sync with the asset attachment
			this.on('change:name change:description', this.syncSettings, this);
		},

		parse: function(response,options) {
			if ( this.isNew() ) {

				// retrieve the upload urls
				api.request('GET','/v2/assets/' + response.embed_code + '/uploading_urls', null, null, this )
						.done(function(data) {
							this.set('uploading_urls',data);
						});

				// make a new asset
				response.percent = 0; // define percent, which lets us know it's an active upload
				this.asset = ooyala.model.Attachment.create(response);

				return { id: response.embed_code };
			}
			return response;
		},

		// these are the required attributes to start an upload
		validate: function(attrs,options) {
			if( !attrs.file_name || !attrs.file_size ) {
				return 'Missing required parameters.';
			}
		},

		sync: function(method, collection, options) {
			if ( method == 'create' ) {
				// create the asset on the server
				// name is required, so use original filename if it got deleted by the user
				if (!this.get('name')) this.set('name',this.get('file_name'));
				return api.request('POST', '/v2/assets', null, this.toJSON())
					.done(options.success)
					.fail(options.error);
			} else if ( method == 'delete' && !this.isNew() ) {
				// delete the asset that was created on the server
				return api.request('DELETE', '/v2/assets/' + this.id)
					.done(options.success)
					.fail(options.error);
			}
		},

		// once the file has finished uploading
		finalize: function() {
			// let the API know that we are done with the upload
			return api.request('PUT','/v2/assets/' + this.id + '/upload_status', null, {status:'uploaded'}, this )
						.done(function(data) {
							// set the status response
							this.asset.set(data);
							// save the asset again, since user can edit title and description during upload
							this.asset.save();
							// TODO: should we make the delay smarter based off file size to determine estimated processing time?
							window.setTimeout(this.pollStatus,ooyala.pollingDelay,this);
						})
						.fail(function(jqXHR){
							this.asset.set('status','error');
						});
		},

		// poll the api to check for the progress of the asset (i.e. check for a status change)
		pollStatus: function(model) {
			model.asset.forceFetch().done(function(data){
				// schedule another poll if the status is still an 'in progress' one
				if (['uploaded','processing'].indexOf(data.status)>-1) {
					window.setTimeout(model.pollStatus,ooyala.pollingFrequency,model);
					//TODO: max re-polls? the assumption is _eventually_ the asset will be processed (and thus cease polling)
				} else if (data.status=='live') {
					// if future status is desired to be paused, set that now
					if ( model.get('futureStatus') == 'paused' ) {
						api.request('PATCH','/v2/assets/' + model.asset.get('id'), null, {status:'paused'}, model )
							.done(function(data){
								this.asset.set(data);
							});
					} else {
						// alert the user that the asset can now be embedded
						alert( ooyala.text.successMsg.replace( '%s', model.get('name') ) );
					}
				} else {
					// assumed to be some sort of error here
					alert( ooyala.text.errorMsg.replace( '%s', model.get('name') ) );
				}
			});
			//TODO: this will stop polling on the first failed check...maybe we want to continue polling to keep trying
		},

		// destroy this upload
		destroy: function() {
			// remove the corresponding asset
			if (this.asset) this.asset.destroy();
			Backbone.Model.prototype.destroy.apply(this,arguments);
		},

		// pass along changes in the upload model to the asset attachment
		syncSettings: function(model) {
			if ( model.asset ) {
				model.asset.set(model.changedAttributes());
			}
		},

	});

})(jQuery);
