/**
 * Filter views used for searching against Getty Images API
 *
 * @package Getty Images
 * @author  bendoh, thinkoomph
 */
(function($) {
	var media = wp.media;
	var getty = gettyImages;

	/**
	 * Generic base class for top-level image filters
	 */
	var GettyImageFilter = media.View.extend({
		tagName: 'div',
		className: 'getty-filter',

		events: {
			change: 'change'
		},

		// Generate markup for the various types of filters
		initialize: function() {
			// Create a header with the text
			this.$el.append($('<em>').text(this.text));

			switch(this.type) {
				case 'bool': // Boolean value: Use a single checkbox
					var $checkbox = $('<input type="checkbox" />').attr('name', this.prop).val(1);
					this.$el.append($('<label>').prepend($checkbox));

					break;

				case 'enum': // Enumerate value: Use radio buttons
					// Create label > radio + text
					_.each(this.values, function(option) {
						if(!option) { // IE8 gives me an extra undefined value here?
							return;
						}
						var $radio = $('<input type="radio" />').attr('name', this.prop).val(option.value);
						this.$el.append($('<label>')
							.text(option.text)
							.prepend($radio));
					}, this);

					break;

				case 'set': // Set value: use checkboxes
					// Create label > checkbox + text elements
					_.each(this.values, function(option) {
						var $checkbox = $('<input type="checkbox" />').attr('name', this.prop + '[]').val(option.value);
						this.$el.append($('<label>')
							.text(option.text)
							.prepend($checkbox));
					}, this);

					break;
			}

			// Propagate changes to props over to form elements
			if(this.model) {
				this.model.on('change', this.select, this);

        if(!getty.user.settings.get(this.prop))
          getty.user.settings.set(this.prop, this.value);

				this.model.set(this.prop, getty.user.settings.get(this.prop));
				this.select();
			}
		},

		// Handle change of any input in filter, update model
		change: function(event) {
			switch(this.type) {
				case 'bool':
					if(event.target.checked)
						this.model.set(this.prop, true);
					else
						this.model.set(this.prop, false);

					break;

				case 'enum':
					this.model.set(this.prop, this.$el.find('input:checked').val());

					break;

				case 'set':
					this.model.set(this.prop, _.pluck(this.$el.find('input:checked'), 'value'));

					break;
			}

			// Save filter to persist with user
			getty.user.settings.set(this.prop, this.model.get(this.prop));
		},

		// Restore filter state from model
		select: function() {
			var value = this.model.get(this.prop);

			switch(this.type) {
				case 'bool':
					if(value)
						this.$el.find('input').attr('checked','checked');
					else
						this.$el.find('input').removeAttr('checked');

					break;

				case 'set':
					this.$el.find('input').removeAttr('checked');

					for(var i = 0; i < value.length; i++) {
						this.$el.find('input[value="' + value[i] + '"]').attr('checked', 'checked');
					}

					break;

				case 'enum':
					this.$el.find('input').removeAttr('checked');
					this.$el.find('input[value="' + value + '"]').attr('checked', 'checked');

					break;
			}
		}
	});

	/***
	 ** Specialized default filters for top-level searches
	 ***/
	media.view.GettyImageTypeFilter = GettyImageFilter.extend({
		className: 'getty-filter getty-filter-image-type',

		text: getty.text.imageType,
		type: 'set',
		prop: 'GraphicStyles',
		values: [
			{
				text: getty.text.photography,
				value: "Photography"
			},
			{
				text: getty.text.illustration,
				value: "Illustration"
			}
		],
		value: [ 'Photography', 'Illustration' ]
	});

	media.view.GettyAssetTypeFilter = GettyImageFilter.extend({
		className: 'getty-filter getty-filter-asset-type',

		text: getty.text.assetType,
		type: 'enum',
		prop: 'ImageFamilies',
		values: [
			{
				text: getty.text.editorial,
				value: "editorial"
			},
			{
				text: getty.text.creative,
				value: "creative"
			},
		],
		value: 'editorial',
	});

	media.view.GettyNudityFilter = GettyImageFilter.extend({
		className: 'getty-filter getty-filter-nudity',

		text: getty.text.excludeNudity,
		type: 'bool',
		prop: 'ExcludeNudity',

		value: true
	});

	media.view.GettyOrientationFilter = GettyImageFilter.extend({
		className: 'getty-filter getty-filter-orientation',

		text: getty.text.orientation,
		type: 'set',
		prop: 'Orientations',
		values: [
			{
				text: getty.text.horizontal,
				value: 'Horizontal'
			},
			{
				text: getty.text.vertical,
				value: 'Vertical'
			}
		],
		value: [ 'Horizontal', 'Vertical' ],
	});

	media.view.GettyEditorialSortOrderFilter = GettyImageFilter.extend({
		className: 'getty-filter getty-filter-sort-order getty-filter-editorial-sort-order',

		text: getty.text.sortOrder,
		type: 'enum',
		prop: 'EditorialSortOrder',
		values: [
			{
				text: getty.text.mostPopular,
				value: 'MostPopular'
			},
			{
				text: getty.text.mostRecent,
				value: 'MostRecent'
			}
		],
		value: 'MostPopular'
	});

	media.view.GettyCreativeSortOrderFilter = GettyImageFilter.extend({
		className: 'getty-filter getty-filter-sort-order getty-filter-creative-sort-order',

		text: getty.text.sortOrder,
		type: 'enum',
		prop: 'CreativeSortOrder',
		values: [
			{
				text: getty.text.bestMatch,
				value: 'MostPopular'
			},
			{
				text: getty.text.newest,
				value: 'MostRecent'
			}
		],
		value: 'MostPopular'
	});

	/**
	 * Let the user refine their search by entering "search within" terms
	 * or by adding categories to filter
	 */
	media.view.GettyRefinements = media.View.extend({
		className: 'getty-refinement-stack',

		events: {
			'keyup .search-refine': 'pushSearchRefinement'
		},

		initialize: function(options) {
			this.categories = options.categories || new Backbone.Collection();
			this.refinements = options.refinements || new Backbone.Collection();

			this.categories.on('add remove reset', this.render, this);
			this.refinements.on('add remove reset', this.render, this);

			this.views.set([
				new GettyActiveRefinements({
					collection: this.refinements
				}),

				new media.view.Search({
					className: 'search search-refine',
					model: new Backbone.Model(),
					attributes: {
						type: 'search',
						placeholder: getty.text.refinePlaceholder
					}
				}),

				new GettyCategoryRefinementFilter({
					collection: this.categories,
					controller: this.controller,
					refinements: this.refinements
				})
			]);
		},

		// Refine an existing search with free-form text when user hits enter
		pushSearchRefinement: function(ev) {
			if(ev.keyCode == 13) {
				ev.preventDefault();
				ev.stopPropagation();

				var $input = $(ev.target);

				this.refinements.push(new Backbone.Model({
					text: $input.val()
				}));

				$input.val('');
			}
		},
	});

	// The full collection of possible refinement categories
	var GettyCategoryRefinementFilter = media.View.extend({
		tagName: 'ul',
		className: 'getty-filter-categories',

		prop: 'Refinements',
		text: getty.text.refineCategories,
		values: [],
		type: 'set',

		initialize: function(options) {
			GettyImageFilter.prototype.initialize.apply(this, arguments);

			this.refinements = options.refinements || new Backbone.Collection();

			this.collection.on('add', this.addCategory, this);
			this.collection.on('remove', this.removeCategory, this);
			this.collection.on('reset', this.clearCategories, this);

			// Sort the categories
			this.collection.on('sort', this.sortOptions, this);

			this._viewsByCid = {};

			// Initialize from existing categories
			this.collection.each(this.addCategory, this);
		},

		addCategory: function(model, collection) {
			var view = new GettyRefinementCategory({
				model: model,
				collection: this.refinements
			});
			this._viewsByCid[model.cid] = view;
			this.views.add(view);
		},

		removeCategory: function(model, collection) {
			if(this._viewsByCid[model.cid]) {
				var view = this._viewsByCid[model.cid];
				if(view) {
					view.remove();
					delete this._viewsByCid[model.cid];
				}
			}
		},

		clearCategories: function() {
			this.views.set([]);
			this._viewsByCid = {};
		},

		sortOptions: function() {
			_.each(this._viewsByCid, function(view, cid, list) {
				view.sort();
			});
		}
	});

	// A single refinement category, which has multiple options
	var GettyRefinementCategory = media.View.extend({
		template: media.template('getty-result-refinement-category'),
		className: 'getty-refinement-category',

		events: {
			'click .getty-refinement-category-name': 'expand'
		},

		initialize: function() {
			this._viewsById = {};

			this.model.get('options').on('add', this.addOption, this);
			this.model.get('options').on('remove', this.removeOption, this);
			this.model.get('options').on('reset', this.clearOptions, this);

			this.model.on('change:expanded', this.toggleExpansion, this);

			this.toggleExpansion();

			this.model.get('options').on('change:active', this.changeActive, this);
			this.model.get('options').each(this.addOption, this);
		},

		changeActive: function(model, collection) {
			if(model.get('active')) {
				this.collection.add(model);
			}
			else {
				this.collection.remove(model);
			}
		},

		addOption: function(model) {
			if(!this._viewsById[model.id]) {
				var view = new GettyRefinementCategoryOption({
					model: model,
					collection: this.collection
				});

				this._viewsById[model.id] = view;
				this.views.add('.getty-refinement-list', view);
			}
		},

		removeOption: function(model, collection, options) {
			if(this._viewsById[model.id]) {
				this._viewsById[model.id].remove();
				delete this._viewsById[model.id];
			}
		},

		clearOptions: function() {
			_.each(this._viewsById, function(view) {
				view.remove();
			});

			this._viewsById = {};
		},

		toggleExpansion: function() {
			this.$el.toggleClass('expanded', !!this.model.get('expanded'));
		},

		expand: function() {
			this.model.set('expanded', !this.model.get('expanded'));
		},

		prepare: function() {
			return this.model.attributes;
		},

		sort: function() {
			var $ul = this.$el.find('.getty-refinement-list');

			// Sort the list!
			_.each(_.sortBy(_.map(this.views.all(), function(view) {
				return {
					id: view.model.id,
					count: view.model.get('count')
				}
			}), 'count'), function(o) {
				$ul.prepend(this._viewsById[o.id].$el);
			}, this);
		}
	});

	// A single refinement category option
	var GettyRefinementCategoryOption = media.View.extend({
		tagName: 'li',
		template: media.template('getty-result-refinement-option'),
		className: 'getty-refinement-category-option',
		initialize: function() {
			this.model.on('change:active change:text change:count', this.render, this);
		},

		events: {
			'click': 'pushRefinement',
		},

		prepare: function() {
			return this.model.attributes;
		},

		pushRefinement: function() {
			this.model.set('active', true);
		},
	});

	// The set of active refinements, allow users to remove them
	var GettyActiveRefinements = media.View.extend({
		tagName: 'ul',
		className: 'getty-active-refinements',

		initialize: function() {
			this._viewsByCid = {};

			this.collection.on('add', function(model, collection, options) {
				var view = new GettyRefinement({
					model: model,
					collection: this.collection
				})

				this._viewsByCid[model.cid] = view;

				this.views.add(view);
			}, this);

			this.collection.on('remove', function(model, collection, options) {
				var view = this._viewsByCid[model.cid];

				delete this._viewsByCid[model.cid];

				if(view)
					view.remove();
			}, this);

			this.collection.on('reset', function() {
				this.render();

				this.views.set([]);
				this._viewsByCid = {};
			}, this);
		},

		render: function() {
			this.views.set([]);

			this.collection.each(function(refinement) {
				this.views.add(new GettyRefinement({
					model: refinement,
					collection: this.collection
				}));
			}, this);
		},

		prepare: function() {
			return this.model.attributes;
		}
	});

	// A single, active refinement
	var GettyRefinement = media.View.extend({
		template: wp.template('getty-result-refinement'),
		tagName: 'li',
		className: 'getty-refinement-item',

		events: {
			'click .getty-remove-refinement': 'popRefinement',
		},

		prepare: function() {
			return this.model.attributes;
		},

		popRefinement: function(ev) {
			if(!this.model.get('category')) {
				this.collection.remove(this.model);
			}
			else {
				this.model.set('active', false);
			}
		}
	});
})(jQuery);
