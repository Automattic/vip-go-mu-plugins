/**
 * CampTix Admin JavaScript
 */
window.camptix = window.camptix || { models: {}, views: {}, collections: {} };

(function($){

	camptix.template_options = {
		evaluate:    /<#([\s\S]+?)#>/g,
		interpolate: /\{\{\{([\s\S]+?)\}\}\}/g,
		escape:      /\{\{([^\}]+?)\}\}(?!\})/g,
		variable:    'data'
	};

	$(document).on( 'load-notify-segments.camptix', function() {
		var Segment = Backbone.Model.extend({
			defaults: {
				field: 'ticket',
				op: 'is',
				value: null,
			},

			initialize: function() {
				this.bind( 'change', this.change, this );
				this.trigger( 'change' );

				return Backbone.Model.prototype.initialize.apply( this, arguments );
			},

			change: function() {
				var selectedField = camptix.collections.segmentFields.findWhere({ option_value: this.get( 'field' ) }),
					values;

				// Make sure the value is a valid one for select types.
				if ( selectedField.get( 'type' ) == 'select' ) {
					values = _.pluck( selectedField.get( 'values' ), 'value' );
					if ( ! _.contains( values, this.get( 'value' ) ) ) {
						this.set( 'value', _.first( values ), { silent: true } );
					}
				}
			}
		});

		var SegmentView = Backbone.View.extend({
			className: 'tix-segment-item',
			events: {
				'click .tix-delete-segment-condition': 'remove',
				'change select': 'change',
				'change input[type="text"]': 'change',
			},

			initialize: function() {
				this.model.bind( 'change', this.render, this );
				this.model.bind( 'destroy', this.remove, this );

				Backbone.View.prototype.initialize.apply( this, arguments );
			},

			render: function() {
				var selectedField = camptix.collections.segmentFields.findWhere({ option_value: this.model.get( 'field' ) })

				var data = {
					model: this.model.toJSON(),
					fields: camptix.collections.segmentFields.toJSON(),
					ops: selectedField.get( 'ops' ),
					type: selectedField.get( 'type' )
				};

				if ( data.type == 'select' ) {
					data.values = selectedField.get( 'values' );
				}

				this.template = _.template( $( '#camptix-tmpl-notify-segment-item' ).html(), null, camptix.template_options );
				this.$el.html( this.template( data ) );
				return this;
			},

			change: function() {
				var args = {
					field: this.$el.find( '.segment-field' ).val(),
					op: this.$el.find( '.segment-op' ).val(),
					value: this.$el.find( '.segment-value' ).val()
				};

				// Reset the value if the field has changed.
				if ( this.model.get( 'field' ) !== args.field )
					args.value = null;

				this.model.set( args );
				return this;
			},

			remove: function(e) {
				this.collection.remove( this.model );
				Backbone.View.prototype.remove.apply( this, arguments );
				e.preventDefault();
				return this;
			}
		});

		var Segments = Backbone.Collection.extend({
			model: Segment
		});

		var SegmentsView = Backbone.View.extend({
			initialize: function() {
				this.$query = $( '#tix-notify-segment-query' );

				this.collection.bind( 'add', this.addOne, this );
				this.collection.bind( 'add remove change', this.updateQuery, this );
			},

			render: function() {
				return this;
			},

			addOne: function( item ) {
				var view = new SegmentView({ model: item, collection: this.collection });
				$( '.tix-segments' ).append( view.render().el );
			},

			updateQuery: function() {
				this.$query.val( JSON.stringify( this.collection.toJSON() ) );
				console.log( this.$query.val() );
			},
		});

		var SegmentField = Backbone.Model.extend({
			defaults: {
				type: 'text',
				caption: '',
				option_value: '',
				ops: [ 'is', 'is not' ],
				values: []
			}
		});

		var SegmentFields = Backbone.Collection.extend({
			model: SegmentField
		});

		camptix.models.Segment = Segment;
		camptix.models.SegmentField = SegmentField;

		camptix.collections.segments = new Segments();
		camptix.collections.segmentFields = new SegmentFields();

		camptix.views.SegmentsView = new SegmentsView({ collection: camptix.collections.segments });

		$('.tix-add-segment-condition').on( 'click', function() {
			camptix.collections.segments.add( new camptix.models.Segment() );
			return false;
		});
	});

	$(document).on( 'load-questions.camptix', function() {
		var Question = Backbone.Model.extend({
			defaults: {
				post_id: 0,
				type: 'text',
				question: '',
				values: '',
				required: false,
				order: 0,
				json: ''
			}
		});

		var QuestionView = Backbone.View.extend({

			className: 'tix-item tix-item-sortable',

			events: {
				'click a.tix-item-delete': 'clear',
				'click a.tix-item-edit': 'edit'
			},

			initialize: function() {
				this.model.bind( 'change', this.render, this );
				this.model.bind( 'destroy', this.remove, this );
			},

			render: function() {
				// Update the hidden input.
				this.model.set( { json: '' }, { silent: true } );
				this.model.set( { json: JSON.stringify( this.model.toJSON() ) }, { silent: true } );

				this.$el.toggleClass( 'tix-item-required', !! this.model.get( 'required' ) );
				this.$el.data( 'tix-cid', this.model.cid );

				this.template = _.template( $( '#camptix-tmpl-question' ).html(), null, camptix.template_options );
				this.$el.html( this.template( this.model.toJSON() ) );
				return this;
			},

			clear: function(e) {
				if ( ! confirm( 'Are you sure you want to remove this question?' ) )
					return false;

				this.model.destroy();
				$( '.tix-ui-sortable' ).trigger( 'sortupdate' );
				return false;
			},

			edit: function(e) {
				camptix.views.NewQuestionForm.hide();
				camptix.views.ExistingQuestionForm.hide();
				camptix.views.EditQuestionForm.show( this.model );
				return this;
			}
		});

		var Questions = Backbone.Collection.extend({
			model: Question,

			initialize: function() {
				this.on( 'add', this._add, this );
			},

			_add: function( item ) {
				item.set( 'order', this.length );
			}
		});

		var QuestionsView = Backbone.View.extend({
			initialize: function() {
				this.collection.bind( 'add', this.addOne, this );
			},

			render: function() {
				return this;
			},

			addOne: function( item ) {
				var view = new QuestionView( { model: item } );
				$( '#tix-questions-container' ).append( view.render().el );
			}
		});

		camptix.models.Question = Question;
		camptix.questions = new Questions();
		camptix.views.QuestionsView = new QuestionsView({ collection: camptix.questions });

		var QuestionForm = Backbone.View.extend({
			template: null,
			data: {},

			initialize: function() {
				this.$container = $( '#tix-question-form' );
				this.$action = $( '#tix-add-question-action' );

				this.template = _.template( $( this.template ).html(), null, camptix.template_options );
				this.render.apply( this );
				return this;
			},

			render: function() {
				this.$el.html( this.template( this.data ) );
				this.hide.apply( this );

				this.$container.append( this.$el );
				return this;
			},

			show: function() {
				this.$action.hide();
				this.$el.show();
				return this;
			},

			hide: function() {
				this.$el.hide();
				this.$action.show();
				return this;
			}
		});

		var NewQuestionForm = QuestionForm.extend({
			template: '#camptix-tmpl-new-question-form',

			events: {
				'click .tix-cancel': 'hide',
				'click .tix-add': 'add'
			},

			render: function() {
				var that = this;
				QuestionForm.prototype.render.apply( this, arguments );
				this.$type = this.$( '#tix-add-question-type' );
				this.$type.on( 'change', function() { that.typeChange.apply( that ); } );

				this.typeChange.apply( this );
				return this;
			},

			add: function( e ) {
				var question = new camptix.models.Question();

				this.$( 'input, select' ).each( function() {
					var attr = $( this ).data( 'model-attribute' );
					var attr_type = $( this ).data( 'model-attribute-type' );

					if ( ! attr )
						return;

					var value = $( this ).val();

					// Special treatment for checkboxes.
					if ( 'checkbox' == attr_type )
						value = !! $( this ).prop('checked');

					question.set( attr, value, { silent: true } );
				});

				camptix.questions.add( question );

				// Clear form
				this.$( 'input[type="text"], select' ).val( '' );
				this.$( 'input[type="checkbox"]' ).prop( 'checked', false );
				this.typeChange.apply( this );

				e.preventDefault();
				return this;
			},

			typeChange: function() {
				var value = this.$type.val();
				var $row = this.$( '.tix-add-question-values-row' );

				if ( value && value.match( /radio|checkbox|select/ ) )
					$row.show();
				else
					$row.hide();

				return this;
			}
		});

		var EditQuestionForm = NewQuestionForm.extend({
			render: function() {
				NewQuestionForm.prototype.render.apply( this, arguments );

				this.$( 'h4' ).text( 'Edit question:' );
				this.$( '.tix-add' ).text( 'Save Question' );
				return this;
			},

			show: function( question ) {
				this.question = question;

				this.$( 'input, select' ).each( function() {
					var attr = $( this ).data( 'model-attribute' );
					var attr_type = $( this ).data( 'model-attribute-type' );

					if ( ! attr )
						return;

					// Special treatment for checkboxes.
					if ( 'checkbox' == attr_type )
						$( this ).prop( 'checked', !! question.get( attr ) );
					else
						$( this ).val( question.get( attr ) );
				} );

				this.typeChange.apply( this );
				NewQuestionForm.prototype.show.apply( this, arguments );
			},

			add: function( e ) {
				question = this.question;

				this.$( 'input, select' ).each( function() {
					var attr = $( this ).data( 'model-attribute' );
					var attr_type = $( this ).data( 'model-attribute-type' );
					var value;

					if ( ! attr )
						return;

					value = $( this ).val();

					// Special treatment for checkboxes.
					if ( 'checkbox' == attr_type )
						value = !! $( this ).prop( 'checked' );

					question.set( attr, value, { silent: false } );
				} );

				delete this.question;
				this.hide.apply( this );
				e.preventDefault();
				return this;
			}
		});

		var ExistingQuestionForm = QuestionForm.extend({
			template: '#camptix-tmpl-existing-question-form',

			events: {
				'click .tix-cancel': 'hide',
				'click .tix-add': 'add'
			},

			initialize: function() {
				QuestionForm.prototype.initialize.apply( this, arguments );
				camptix.questions.on( 'add remove', this.update_disabled, this );
			},

			render: function() {
				QuestionForm.prototype.render.apply( this, arguments );
				this.update_disabled.apply( this );
				return this;
			},

			add: function( e ) {
				this.$( '.tix-existing-checkbox:checked' ).each( function( index, checkbox ) {
					var parent = $( checkbox ).parent();
					var question = new camptix.models.Question();

					$( parent ).find( 'input' ).each( function() {
						var attr = $(this).data( 'model-attribute' );
						if ( ! attr )
							return;

						question.set( attr, $( this ).val(), { silent: true } );
					} );

					// Make sure post_id and required are correct types, not integers.
					question.set( {
						post_id: parseInt( question.get( 'post_id' ), 10 ),
						required: !! parseInt( question.get( 'required' ), 10 )
					}, { silent: true } );

					var found = camptix.questions.where( { post_id: parseInt( question.get( 'post_id' ), 10 ) } );

					// Don't add duplicate existing questions.
					if ( 0 === found.length )
						camptix.questions.add( question );

					$( checkbox ).prop( 'checked', false );
				});

				e.preventDefault();
				return this;
			},

			update_disabled: function() {
				this.$( '.tix-existing-question' ).each( function() {
					var question_id = $( this ).data( 'tix-question-id' );
					var cb = $( this ).find( '.tix-existing-checkbox' );
					var found = camptix.questions.where( { post_id: parseInt( question_id, 10 ) } );

					$( cb ).prop( 'disabled', found.length > 0 );
					$( this ).toggleClass( 'tix-disabled', found.length > 0 );
				} );

				return this;
			}
		});

		$(document).ready(function() {
			camptix.views.NewQuestionForm = new NewQuestionForm();
			camptix.views.EditQuestionForm = new EditQuestionForm();
			camptix.views.ExistingQuestionForm = new ExistingQuestionForm();

			$( '.tix-ui-sortable' ).sortable( {
				items: '.tix-item-sortable',
				handle: '.tix-item-sort-handle',
				placeholder: 'tix-item-highlight'
			} );

			$( '.tix-ui-sortable' ).on( 'sortupdate', function( e, ui ) {
				var items = $( '.tix-ui-sortable .tix-item-sortable' );
				for ( var i = 0; i < items.length; i++ ) {
					var cid = $( items[i] ).data( 'tix-cid' );
					var model = camptix.questions.get( cid );
					model.set( 'order', i + 1 );
				}
			} );

			$( '#tix-add-question-new' ).on( 'click', function() {
				camptix.views.NewQuestionForm.show();
				return false;
			} );

			$( '#tix-add-question-existing' ).on( 'click', function() {
				camptix.views.ExistingQuestionForm.show();
				return false;
			} );
		});
	} );

	$(document).ready(function(){

		$( ".tix-date-field" ).datepicker({
			dateFormat: 'yy-mm-dd',
			firstDay: 1
		});

		// Show or hide the refunds date field in Setup > General.
		$('#tix-refunds-enabled-radios input').change(function() {
			if ( $(this).val() > 0 )
				$('#tix-refunds-date').show();
			else
				$('#tix-refunds-date').hide();
		});

		// Clicking on a notify shortcode in Tools > Notify inserts it into the email body.
		$('.tix-notify-shortcode').click(function() {
			var shortcode = $(this).find('code').text();
			$('#tix-notify-body').val( $('#tix-notify-body' ).val() + ' ' + shortcode );
			return false;
		});

		// Ticket availability range in tickets and coupons metabox.
		var tix_availability_dates = $( "#tix-date-from, #tix-date-to" ).datepicker({
			dateFormat: 'yy-mm-dd',
			firstDay: 1,
			onSelect: function( selectedDate ) {
				var option = this.id == "tix-date-from" ? "minDate" : "maxDate",
					instance = $( this ).data( "datepicker" ),
					date = $.datepicker.parseDate(
						instance.settings.dateFormat ||
						$.datepicker._defaults.dateFormat,
						selectedDate, instance.settings );
				tix_availability_dates.not( this ).datepicker( "option", option, date );
			}
		});

		// Coupon applies to all/none links.
		$( '#tix-applies-to-all' ).on( 'click', function(e) {
			$( '.tix-applies-to-checkbox' ).prop( 'checked', true );
			e.preventDefault();
			return false;
		});
		$( '#tix-applies-to-none' ).on( 'click', function(e) {
			$( '.tix-applies-to-checkbox' ).prop( 'checked', false );
			e.preventDefault();
			return false;
		});


		/*
		 * Track Attendance addon
		 */

		// Mark bulk attendance
		$( '#posts-filter' ).on( 'click', 'a.tix-mark-attended', function( event ) {
			var cellTemplate,
				cell       = $( this ).parent(),
				attendeeID = $( this ).data( 'attendee-id' ),
				nonce      = $( this ).data( 'nonce' );

			event.preventDefault();

			// Show a spinner until the AJAX call is done
			cellTemplate = _.template( $( '#tmpl-tix-attendance-spinner' ).html(), null, camptix.template_options );
			cell.html( cellTemplate( {} ) );

			// Send the request to mark the ticket holder as having actually attended
			$.post(
				ajaxurl,

				{
					action:      'tix_mark_as_attended',
					attendee_id: attendeeID,
					nonce:       nonce
				},

				function( response ) {
					if ( response.hasOwnProperty( 'success' ) && true === response.success ) {
						cellTemplate = _.template( $( '#tmpl-tix-attendance-confirmed' ).html(), null, camptix.template_options );
						cell.html( cellTemplate( {} ) );
					} else {
						cellTemplate = _.template( $( '#tmpl-tix-mark-as-attended' ).html(), null, camptix.template_options );
						cell.html( cellTemplate( { 'attendee_id' : attendeeID, 'nonce': nonce } ) );
					}
				}
			);
		} );

	});
}(jQuery));