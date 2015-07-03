function buildDragColumns() {
	var TP_METADATA_OPTIONS_KEY = 'theplatform_metadata_options';
	var TP_UPLOAD_OPTIONS_KEY = 'theplatform_upload_options';
	
	var $ = jQuery;
	var $optionsPageName = $( 'input[name=option_page]' );
	if ( $optionsPageName.length
			&& ( $optionsPageName.val() == TP_METADATA_OPTIONS_KEY ) || $optionsPageName.val() == TP_UPLOAD_OPTIONS_KEY )
	{
		var $table = $optionsPageName.siblings( 'table' );
		$table.css( 'display', 'none' );
		$table.after( '<div id="drag-columns"></div>' );
		var $dragColumnsContainer = $( '#drag-columns' );
		var $sortableFields = $table.find( '.sortableField' );
		var cols = { 'write': [ ], 'read': [ ], 'hide': [ ] };
		$sortableFields.each( function() {
			var name = $( this ).parent().siblings( 'th' ).html();
			var value = $( this ).val();
			if ( !value.length )
				value = 'hide';
			cols[value].push( {
				id: $( this ).attr( 'id' ),
				name: name,
				userfield: $( this ).data('userfield')
			} );
		} );

		for ( var colName in cols ) {
			$dragColumnsContainer.append(
					'<div class="colContainer">' +
					'<h3>' + capitalize( colName ) + '</h3>' +
					'<ul data-col="' + colName + '" class="sortable"></ul>' +
					'</div>'
					);
			var $col = $( 'ul[data-col=' + colName + ']' );
			for ( var i in cols[colName] ) {
				var field = cols[colName][i];
				$col.append( '<li data-id="' + field.id + '" data-userfield="' + field.userfield + '">' + field.name + '</li>' );
			}
		}
		$dragColumnsContainer.append( '<div class="clear"></div>' );

		$( ".sortable" ).sortable( {
			items: "li:not([data-id=title])",
			connectWith: ".sortable",
			receive: function( e, ui ) {
				var receiver = $( e.target ).data( 'col' );
				var itemId = $( ui.item ).data( 'id' );
				
				if ( receiver == "write" && $( ui.item ).data('userfield') == true ) {
					$(ui.sender).sortable('cancel');
				} else {
					var $selectField = $( 'select[name="' + $optionsPageName.val() + '[' + itemId + ']"]' );
					$selectField.find( 'option:selected' ).attr( 'selected', false );
					$selectField.find( 'option[value="' + receiver + '"]' ).attr( 'selected', true );
				}
			}
		} ).disableSelection();
	}
}

function capitalize( s ) {
	return s[0].toUpperCase() + s.slice( 1 );
}

jQuery( document ).ready( function() {
	buildDragColumns();
} );