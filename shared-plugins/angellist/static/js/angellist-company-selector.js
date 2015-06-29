var angellist = angellist || {};
angellist.company_selector = {
	// Plugin / JS version
	version: '1.2',

	// English labels for inserted text. Overridden with localized values through PHP output with special event trigger
	labels: { remove: "Delete", no_results: "No results found.", search: "Add a company:", search_placeholder: "Start typing..." },

	// keep track of companies already on the page to prevent duplicates when adding
	company_ids: [],

	// create an ordered list to display companies and their input values
	create_company_list: function() {
		var results_div = jQuery( "#angellist-company-selector-results" );
		if ( results_div.length === 0 ) {
			return;
		}
		results_div.html( "" );
		angellist.company_selector.company_list = jQuery( "<ol />" ).attr( "id", "angellist-company-selector-companies" );
		results_div.append( angellist.company_selector.company_list );
		angellist.company_selector.enable_editable_company_list();
	},

	// add a company to the list
	add_company: function( company ) {
		// test for minimum properties
		if ( ! jQuery.isPlainObject( company ) || company.value === undefined || company.value === "" || company.label === undefined || jQuery.inArray( company.value, angellist.company_selector.company_ids ) > -1 ) {
			return;
		}

		if ( angellist.company_selector.company_list === undefined || angellist.company_selector.company_list.length === 0 ) {
			angellist.company_selector.create_company_list();
		}

		var current_position = angellist.company_selector.company_ids.length;
		var li = jQuery( "<li />" ).text( company.label );
		li.append( jQuery( "<input />" ).attr( { type:"hidden", name:"angellist-company[" + current_position + "][id]" } ).addClass( "angellist-company-id" ).val( company.value ) );
		li.append( jQuery( "<input />" ).attr( { type:"hidden", name:"angellist-company[" + current_position + "][name]" } ).val( company.label ) );
		li.append( angellist.company_selector.company_delete_button() );
		li.mouseenter( angellist.company_selector.company_mouseenter ).mouseleave( angellist.company_selector.company_mouseleave );
		angellist.company_selector.company_list.append( li );
		angellist.company_selector.company_ids.push( company.value );
		if ( angellist.company_selector.company_ids.length == 2 ) {
			angellist.company_selector.enable_editable_company_list();
		}
	},

	// centralize the delete button HTML and events generation
	company_delete_button: function() {
		return jQuery( "<button />" ).attr( { type:"button", title: angellist.company_selector.labels.remove } ).addClass( "angellist-delete-company" ).text( "X" ).click( angellist.company_selector.delete_company_handler ).hide();
	},

	// remove a company from the list
	delete_company_handler: function() {
		var company = jQuery(this).closest( "li" );
		if ( company.length === 0 ) {
			return;
		}
		var company_id = parseInt( company.find( ".angellist-company-id" ).val(), 10 );
		if ( typeof company_id !== "number" ) {
			return;
		}
		var position = jQuery.inArray( company_id, angellist.company_selector.company_ids );

		// remove from old list before adding to new one for proper de-duplication compare
		if ( position !== -1 ) {
			angellist.company_selector.company_ids.splice( position, 1 );
		}

		if ( angellist.company_selector.company_ids.length === 0 ) {
			angellist.company_selector.company_list.remove();
			delete angellist.company_selector.company_list;
		} else {
			company.remove();
			angellist.company_selector.company_list_onchange();
		}
	},

	// show action button as mouse enters company list item
	company_mouseenter: function() {
		jQuery(this).find( "button" ).show();
	},

	// hide action button as mouse leaves company list item
	company_mouseleave: function() {
		jQuery(this).find( "button" ).hide();
	},

	// change input names after drag and drop
	company_list_onchange: function() {
		// reorder companies and their inputs based on drag position
		angellist.company_selector.company_list.find( "li" ).each( function( index ) {
			jQuery(this).find( "input" ).each( function() {
				var input_el = jQuery(this);
				// change numeric input
				var name = input_el.attr( "name" );
				// angellist-company[([0-9]{1,2})][(id|name)]
				input_el.attr( "name", name.substring( 0, 18 ) + index + name.substring( name.indexOf( "]", 18 ) ) );
			} );
		} );
	},

	// autocomplete only exists if JS enabled. dynamically add to the DOM
	create_autocomplete_search: function() {
		var search_div = jQuery( "<div />" ).attr( "id", "angellist-company-selector-search" );
		var searchbox_id = "angellist-company-selector-searchbox";

		if ( typeof angellist.company_selector.labels.search === "string" ) { // account for bad PHP label
			search_div.append( jQuery( "<div />" ).append( jQuery( "<label />" ).attr( "for", searchbox_id ).text( angellist.company_selector.labels.search ) ) );
		}
		var searchbox = jQuery( "<input />" ).attr( { id:searchbox_id, type:"search", size:30, autocomplete:"on" } );
		if ( typeof angellist.company_selector.labels.search_placeholder === "string" ) {
			searchbox.attr( "placeholder", angellist.company_selector.labels.search_placeholder );
		}
		search_div.append( searchbox );

		angellist.company_selector.post_box.find( "div.inside" ).append( search_div );
	},

	// add an autocomplete section. type a query, select a startup from the results list
	enable_autocomplete_search: function() {
		var search_div = jQuery( "#angellist-company-selector-search" );
		if ( search_div.length === 0 ) {
			angellist.company_selector.create_autocomplete_search();
			search_div = jQuery( "#angellist-company-selector-search" );
		}
		search_div.show();
		jQuery( "#angellist-company-selector-searchbox" ).autocomplete({
			appendTo: search_div,
			disabled: false,
			focus: function() {
				return false;
			},
			minLength: 3,
			select: function( event, ui ) {
				// clear input field
				jQuery( "#angellist-company-selector-searchbox" ).val( "" );

				// add company to list
				angellist.company_selector.add_company( ui.item );

				// don't update the input with the value
				return false;
			},
			source: function( request, response ) {
				var term = jQuery.trim( request.term );
				if ( term === "" ) {
					return;
				}
				jQuery.ajax({
					url: angellist.company_selector.search_url,
					data: {
						action: 'angellist-search',
						q: term
					},
					dataType: "json",
					success: function( companies ) {
						response( jQuery.map( companies, function( company ) {
							return { label: company.name, value: parseInt( company.id, 10 ) };
						}) );
					},
					statusCode: {
						404: function() {
							response( [{label: angellist.company_selector.labels.no_results, value:""}] );
						}
					}
				});
			}
		});
	},

	// iterate through any companies not added dynamically. add a delete button with the appropriate hooks attached
	enable_company_delete: function() {
		jQuery( "#angellist-company-selector-companies li" ).each( function(){
			var company = jQuery(this);
			company.append( angellist.company_selector.company_delete_button() );
			company.mouseenter( angellist.company_selector.company_mouseenter ).mouseleave( angellist.company_selector.company_mouseleave );
		} );
	},

	// reorder companies in companies list
	enable_editable_company_list: function() {
		if ( angellist.company_selector.company_list === undefined ) {
			angellist.company_selector.company_list = jQuery( "#angellist-company-selector-companies" );
		}
		if ( angellist.company_selector.company_list.length === 0 ) {
			return;
		}

		angellist.company_selector.company_list.sortable( {
			axis: "y",
			containment: "parent",
			cursor: "move",
			dropOnEmpty: false,
			items: "li",
			update: angellist.company_selector.company_list_onchange
		} );
	},

	// turn it on
	enable: function() {
		if ( angellist.company_selector.company_ids.length > 0 ) {
		    angellist.company_selector.company_list = jQuery( "#angellist-company-selector-companies" );
		    angellist.company_selector.enable_company_delete();
		    if ( angellist.company_selector.company_ids.length > 1 ) {
				angellist.company_selector.enable_editable_company_list();
			}
		}
		angellist.company_selector.enable_autocomplete_search();
	}
};

jQuery(function() {
	angellist.company_selector.post_box = jQuery( "#angellist-company-selector" );
	if ( angellist.company_selector.post_box.length === 0 ) {
		// no AngelList post box on page. abort.
		return;
	} else {
		// load variables from our custom event
		angellist.company_selector.post_box.trigger( "angellist-company-selector-onload" );
	}

	angellist.company_selector.enable();
});