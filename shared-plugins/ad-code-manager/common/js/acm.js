( function( window, $, undefined ) {
	var document = window.document;

	var AdCodeManager = function() {
		/**
		 * A reference to the AdCodeManager object so we avoid confusion with `this` later on
		 *
		 * @type {*}
		 */
		var SELF = this;

		/**
		 * Container for cached UI elements
		 *
		 * @type {Object}
		 */
		var UI = {};

		/**
		 * Used for storing the currently edited ACM ID
		 * @type {Boolean}
		 */
		var EDIT_ID = false;

		/**
		 * Initializes the AdCodeManager when the object is instantiated. This script must always run from the footer
		 * after the DOM elements are rendered
		 *
		 * @private
		 */
		var _init = function() {
			_cacheElements();
			_bindEvents();
		};

		/**
		 * Caches useful DOM elements so we can easily reference them later without the extra lookup
		 *
		 * @private
		 */
		var _cacheElements = function() {
			UI.addMoreButton = document.getElementById( 'conditional-tpl' ).querySelector( '.add-more-conditionals' );
			UI.theList = document.getElementById( 'the-list' );
			UI.theNew = document.getElementById( 'add-adcode' );
		};

		/**
		 * Handles binding our events for the page to work
		 *
		 * @private
		 */
		var _bindEvents = function() {
			_addEvent( 'click', UI.addMoreButton, _addConditional );
			_addEvent( 'click', UI.theList, _delegateListClicks );
			_addEvent( 'click', UI.theNew, _delegateNewAdClicks );
			_addEvent( 'keydown', UI.theList, _delegateListKeyEvents );
		};

		/**
		 * Registers a DOM event for the specified element
		 *
		 * @param event The event we're hooking to
		 * @param element The element we want to monitor for the event
		 * @param callback The callback to be fired when the event is triggered
		 * @private
		 */
		var _addEvent = function( event, element, callback ) {
			if( window.addEventListener ) {
				element.addEventListener( event, callback, false );
			}
			else {
				element.attachEvent( 'on' + event, callback );
			}
		};

		/**
		 * Handles adding a new conditional row to the UI for the user
		 *
		 * @param e The event object
		 * @private
		 */
		var _addConditional = function( e ) {
			e = e || window.event;
			var target = e.srcElement || e.target;
			var parent = target.parentNode.parentNode.querySelector( '.form-new-row' );
			_addInlineConditionalRow( parent );

			_killEvent( e );
		};

		/**
		 * Kills the passed event and prevents it from bubbling up the DOM
		 *
		 * @param e The event we're killing
		 * @private
		 */
		var _killEvent = function( e ) {
			e.returnValue = false;
			e.cancelBubble = true;
			if( e.stopPropagation ) {
				e.stopPropagation();
			}
			if( e.preventDefault ) {
				e.preventDefault();
			}
		};

		/**
		 * Handles checking delegated events for the add ad code area
		 *
		 * @param e The event object
		 * @private
		 */
		var _delegateNewAdClicks = function( e ) {
			e = e || window.event;
			var target = e.srcElement || e.target;

			// check for remove conditional call
			if( _hasClass( target, 'acm-remove-conditional' ) === true ) {
				_removeInlineConditionalRow( target );
				_killEvent( e );
			}
		};

		/**
		 * Handles checking delegated key events for the inline editor and table rows
		 * @param e
		 * @private
		 */
		var _delegateListKeyEvents = function( e ) {
			e = e || window.event;
			var key = e.which || e.keyCode;

			// 13 is Enter, which avoids the default form on the page from saving
			if ( key === 13 ) {
				_saveInlineEditorChanges();
				_killEvent( e );
			}
		};

		/**
		 * Handles checking delegated events for the inline editor and table rows
		 *
		 * @param e The event object
		 * @private
		 */
		var _delegateListClicks = function( e ) {
			e = e || window.event;
			var target = e.srcElement || e.target;

			// check for ajax edit click
			if( _hasClass( target, 'acm-ajax-edit' ) === true ) {
				// close other editors
				if( EDIT_ID !== false ) {
					if( confirm( 'Are you sure you want to do this? Any unsaved data will be lost.' ) === false ) {
						_killEvent( e );
						return;
					}
					_toggleInlineEdit( false );
				}

				EDIT_ID = parseInt( target.id.replace( 'acm-edit-', '' ), 10 );
				_toggleInlineEdit( true );
				_killEvent( e );
			}

			// check for cancel button
			else if( _hasClass( target, 'cancel' ) === true && EDIT_ID !== false ) {
				_toggleInlineEdit( false );
				EDIT_ID = false;
			}

			// check for remove conditional call
			else if( _hasClass( target, 'acm-remove-conditional' ) === true ) {
				_removeInlineConditionalRow( target );
				_killEvent( e );
			}

			// check for save button
			else if( _hasClass( target, 'save' ) === true ) {
				_toggleLoader( true );
				_saveInlineEditorChanges();
				_killEvent( e );
			}

			// check for add more conditionals
			else if( _hasClass( target, 'add-more-conditionals' ) === true ) {
				_addInlineConditionalRow( UI.theList.querySelector( '#ad-code-' + EDIT_ID + ' .acm-editor-row .acm-conditional-fields .form-new-row' ) );
				_killEvent( e );
			}
		};

		/**
		 * Saves any inline editor changes that occurred
		 *
		 * @private
		 */
		var _saveInlineEditorChanges = function() {
			$.post( window.ajaxurl, _getFormData(), function( result ) {
				if( result ) {
					if( result.indexOf( '<tr' ) > -1 ) {
						$( document.getElementById( 'ad-code-' + EDIT_ID ) ).before( result).remove();
						EDIT_ID = false;
					}
					else {
						_showError( result );
					}
				}
				else {
					_showError( inlineEditL10n.error );
				}
			} );
		};

		/**
		 * Shows the error for this ad code if it exists
		 *
		 * @param html
		 * @private
		 */
		var _showError = function( html ) {
			var errorContainer = document.getElementById( 'ad-code-' + EDIT_ID ).querySelector( '.acm-editor-row .inline-edit-save .error' );
			errorContainer.innerHTML = html;
			errorContainer.style.display = 'block';
		};

		/**
		 * Custom serialization function based off of $.serializeArray() - slimmed down to exactly what we need
		 *
		 * @return {Array}
		 * @private
		 */
		var _getFormData = function() {
			var data = [];
			var fields = document.getElementById( 'ad-code-' + EDIT_ID ).querySelector( '.acm-editor-row fieldset' );
			var elements = fields.querySelectorAll( 'input, select, textarea' ), element, name;

			for( var i = 0, len = elements.length; i < len; i++ ) {
				element = elements[ i ];
				name = element.name.replace( /^\s+|\s+$/i, '' );
				if( name === '' ) {
					continue;
				}

				data.push( { name : name, value : element.value } );
			}

			return data;
		};

		/**
		 * Removes the conditional row from the perspective of the button clicked
		 *
		 * @param target The `remove` button that was clicked.
		 * @private
		 */
		var _removeInlineConditionalRow = function( target ) {
			var row = target.parentNode.parentNode;
			var parent = row.parentNode;
			parent.removeChild( row );
		};

		/**
		 * Add a new inline editor conditional row for the current ad-code
		 *
		 * @private
		 */
		var _addInlineConditionalRow = function( parent ) {
			// create a new element
			var newConditional = document.createElement( 'div' );
			newConditional.className = 'conditional-single-field';
			newConditional.innerHTML = document.getElementById( 'conditional-single-field-master' ).innerHTML;
			newConditional.querySelector( '.conditional-arguments' ).innerHTML += '<a href="#" class="acm-remove-conditional">Remove</a>';

			parent.appendChild( newConditional );
		};

		/**
		 * Toggles the loader for the form. This should only be used when the save button is clicked and we have a current
		 * EDIT_ID available
		 *
		 * @param showing Indicates whether the loader should be showing or not
		 * @private
		 */
		var _toggleLoader = function( showing ) {
			var loader = document.querySelector( '#ad-code-' + EDIT_ID + ' .acm-editor-row .inline-edit-save .waiting' );
			loader.style.display = ( showing === true ) ? 'block' : 'none';
		};

		/**
		 * Lightweight utility function that handles checking an element to see if it contains a class
		 *
		 * @param element The element we're checking against
		 * @param className The class name we're looking for
		 * @return {Boolean}
		 * @private
		 */
		var _hasClass = function( element, className ) {
			return ( ' ' + element.className + ' ' ).indexOf( ' ' + className + ' ' ) > -1;
		};

		/**
		 * Handles toggling the inline editor. We assume EDIT_ID is being handled correctly for this to work.
		 *
		 * @param visible Indicates whether we are hiding/showing the inline editor.
		 * @private
		 */
		var _toggleInlineEdit = function( visible ) {
			var row = document.getElementById( 'ad-code-' + EDIT_ID );

			if( visible === true ) {
				_toggleTableChildrenDisplay( row, false );
				_createNewInlineRow( EDIT_ID, row );
			}
			else {
				_toggleTableChildrenDisplay( row, true );
				_removeEditInlineRow( row );
			}
		};

		/**
		 * Toggles all the table children of the parent.
		 *
		 * @param parent The parent table row we're toggling td's for
		 * @param display Indicates whether or not the row children should be shown or not
		 * @private
		 */
		var _toggleTableChildrenDisplay = function( parent, display ) {
			display = ( display === true ) ? 'table-cell' : 'none';
			var children = parent.children;
			for( var i = 0, len = children.length; i < len; i++ ) {
				children[ i ].style.display = display;
			}
		};

		/**
		 * Handles creating the new inline editor row with all of the necessary UI controls. Notice that we do not rebind
		 * events because they are already handled by the delegation technique in `_delegateListClicks`
		 *
		 * @param id The ID of the ad-code you are editing
		 * @param parentToBe The DOM element that the newRow will be inserted into
		 * @private
		 */
		var _createNewInlineRow = function( id, parentToBe ) {
			var newRow = document.createElement( 'td' );
			newRow.setAttribute( 'colspan', ( parentToBe.children.length - 1 ) );
			newRow.className = 'acm-editor-row';
			newRow.innerHTML = document.getElementById( 'inline-edit' ).innerHTML;

			// fill in the rows with existing HTML here
			var data = _getDataFromRow( id );
			newRow.querySelector( 'input[name="id"]' ).value = id;
			newRow.querySelector( '.acm-conditional-fields' ).innerHTML = data.conditionalFields;
			newRow.querySelector( '.acm-column-fields' ).innerHTML = data.columnFields;
			newRow.querySelector( '.acm-priority-field' ).innerHTML = data.priority;
			newRow.querySelector( '.acm-operator-field' ).innerHTML = data.operator;

			parentToBe.appendChild( newRow );
		};

		/**
		 * Removes the editor inline row if it exists
		 *
		 * @param parent The parent DOM element where we are removing the oldRow from
		 * @private
		 */
		var _removeEditInlineRow = function( parent ) {
			var oldRow = parent.querySelector( '.acm-editor-row' );
			parent.removeChild( oldRow );
			parent.querySelector( '.column-id' ).style.display = 'none';
		};

		/**
		 * Builds an object literal containing HTML for the new Row based off of existing DOM elements and their HTML
		 *
		 * @param id The ID of the ad-code we're retrieving information from.
		 * @return {Object}
		 * @private
		 */
		var _getDataFromRow = function( id ) {
			var dataParent = document.getElementById( 'inline_' + id );
			return {
				conditionalFields : dataParent.querySelector( '.acm-conditional-fields' ).innerHTML,
				columnFields : dataParent.querySelector( '.acm-column-fields' ).innerHTML,
				priority : dataParent.querySelector( '.acm-priority-field' ).innerHTML,
				operator : dataParent.querySelector( '.acm-operator-field' ).innerHTML
			};
		};

		// fire our initialization method
		_init();
	};

	window.AdCodeManager = new AdCodeManager();

} )( window, jQuery );
