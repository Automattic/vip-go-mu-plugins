var TaxonomyImagesCreateAssociation;

jQuery( document ).ready( function( $ ) {
	var ID = 0, below;

	/* Get window that opened the thickbox. */
	below = window.dialogArguments || opener || parent || top;

	if ( null !== below && 'taxonomyImagesPlugin' in below ) {
		/* Set the value of ID. */
		if ( 'tt_id' in below.taxonomyImagesPlugin ) {
			ID = parseInt( below.taxonomyImagesPlugin.tt_id );
			if ( isNaN( ID ) ) {
				ID = 0;
			}
		}
		/* Replace term name. */
		if ( 'term_name' in below.taxonomyImagesPlugin ) {
			$( '.create-association .term-name' ).html( TaxonomyImagesModal.termBefore + below.taxonomyImagesPlugin.term_name + TaxonomyImagesModal.termAfter );
		}
	}

	if ( 0 < ID ) {
		$( 'body' ).addClass( 'taxonomy-images-modal' );

		var buttons = $( '.taxonomy-images-modal .create-association' );

		/* Add hidden input to search form. */
		$( '#filter' ).prepend( '<input type="hidden" name="taxonomy_images_plugin" value="' + ID + '" />' );

		if ( 'image_id' in below.taxonomyImagesPlugin ) {
			buttons.each( function( i, e ) {
				var image_id = $( e ).parent().find( '.taxonomy-image-button-image-id' ).val();
				if ( image_id == below.taxonomyImagesPlugin.image_id ) {
					$( e ).hide();
					$( e ).parent().find( '.remove-association' ).css( 'display', 'inline' );
				}
			} );
		}
	}

	$( '.taxonomy-images-modal' ).on( 'click', '.remove-association', function () {
		var button = $( this );
		originalText = button.html();
		button.html( TaxonomyImagesModal.removing );

		$.ajax( {
			url: ajaxurl,
			type: "POST",
			dataType: 'json',
			data: {
				'action'   : 'taxonomy_image_plugin_remove_association',
				'wp_nonce' : $( this ).parent().find( '.taxonomy-image-button-nonce-remove' ).val(),
				'tt_id'    : ID
				},
			cache: false,
			success: function ( response ) {
				if ( 'good' === response.status ) {
					button.html( TaxonomyImagesModal.removed ).fadeOut( 200, function() {
						$( this ).hide();
						var selector = parent.document.getElementById( 'taxonomy_image_plugin_' + ID );
						$( selector ).attr( 'src', below.taxonomyImagesPlugin.img_src );
						$( this ).parent().find( '.create-association' ).show();
						$( this ).html( originalText );
					} );
				}
				else if ( 'bad' === response.status ) {
					alert( response.why );
				}
			}
		} );
	} );

	$( '.taxonomy-images-modal' ).on( 'click', ' .create-association', function () {
		var button, selector, originalText;
		if ( 0 == ID ) {
			return;
		}

		button = $( this );
		originalText = button.html();
		button.html( TaxonomyImagesModal.associating );

		$.ajax( {
			url      : ajaxurl,
			type     : "POST",
			dataType : 'json',
			data: {
				'action'        : 'taxonomy_image_create_association',
				'wp_nonce'      : $( this ).parent().find( '.taxonomy-image-button-nonce-create' ).val(),
				'attachment_id' : $( this ).parent().find( '.taxonomy-image-button-image-id' ).val(),
				'tt_id'         : parseInt( ID )
				},
			success: function ( response ) {
				if ( 'good' === response.status ) {
					var parent_id = button.parent().attr( 'id' );

					/* Set state of all other buttons. */
					$( '.taxonomy-image-modal-control' ).each( function( i, e ) {
						if ( parent_id == $( e ).attr( 'id' ) ) {
							return true;
						}
						$( e ).find( '.create-association' ).show();
						$( e ).find( '.remove-association' ).hide();
					} );

					selector = parent.document.getElementById( 'taxonomy-image-control-' + ID );

					/* Update the image on the screen below */
					$( selector ).find( '.taxonomy-image-thumbnail img' ).each( function ( i, e ) {
						$( e ).attr( 'src', response.attachment_thumb_src );
					} );

					/* Show delete control on the screen below */
					$( selector ).find( '.remove' ).each( function ( i, e ) {
						$( e ).removeClass( 'hide' );
					} );

					button.show().html( TaxonomyImagesModal.success ).fadeOut( 200, function() {
						var remove = button.parent().find( '.remove-association' );
						if ( undefined !== remove[0] ) {
							$( remove ).css( 'display', 'inline' );
							button.hide();
							button.html( originalText );
						}
					} );
				}
				else if ( 'bad' === response.status ) {
					alert( response.why );
				}
			}
		} );
	} );
} );