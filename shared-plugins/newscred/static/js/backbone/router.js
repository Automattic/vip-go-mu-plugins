ncApp.MetaBoxRouter = Backbone.Router.extend( {

    initialize:function () {

        /**
         * initialize the MEtaBox
         * Router
         */

        ncApp.metaBox = new ncApp.MetaBoxView( {"query":"", "sort_by":"relevance"} );

        $( window ).load( function () {
            $( "#nc-metabox-wrap" ).hide().prepend( ncApp.metaBox.render().$el ).slideDown( 1000 );
        } );

        var myDocument = $( document );
        /**
         *  article tooltip
         *  attached image set slider
         */

        $( ".nc-attached-prev" ).attr( "rel", 0 );
        $( ".nc-attached-next" ).attr( "rel", 5 );

        myDocument.on('click', '.nc-attached-next', function ( e ) {

            if ( $( ".nc-attached-next" ).attr( "rel" ) > $( ".images-holder li" ).size() ) {
                e.preventDefault();
                return false;
            }

            var from = $( ".nc-attached-prev" ).attr( "rel" );
            var to = parseInt( from ) + 4;

            $( '.images-holder ul li' ).slice( from, to ).effect( "drop", "", 500, function () {
                $( ".nc-attached-next" ).attr( "rel", parseInt( to ) + 5 );
                $( ".nc-attached-prev" ).attr( "rel", parseInt( from ) + 4 );
                if ( $( ".nc-attached-prev" ).attr( "rel" ) != 0 )
                    $( ".nc-attached-prev" ).removeClass( "inactive" );
                else
                    $( ".nc-attached-prev" ).addClass( "inactive" );

            } );
        } );

        myDocument.on('click', '.nc-attached-prev', function ( e ) {

            if ( $( ".nc-attached-prev" ).attr( "rel" ) == 0 ) {
                e.preventDefault();
                return false;
            }

            var to = $( ".nc-attached-prev" ).attr( "rel" );
            var from = parseInt( to ) - 4;

            $( '.images-holder ul li' ).slice( from, to ).show( "drop", "", 500, function () {
                $( ".nc-attached-next" ).attr( "rel", parseInt( to ) + 1 );
                $( ".nc-attached-prev" ).attr( "rel", parseInt( from ) );
            } );
        } );


        /**
         *  image insert to post from article tab
         */

        myDocument.on( "click", ".nc-insert-to-post-article", function ( e ) {
            ncApp.articlesView.imageInsertToPost( e );
        } );


        /**
         *  image insert as feature image from article tab
         */

        myDocument.on( "click", ".nc-insert-feature-image-article", function ( e ) {
            ncApp.articlesView.imageInsertAsFeatureImage( e );
        } );


        /**
         *  image insert to post from Image tab
         */
        myDocument.on( "click", ".nc-image-insert-to-post", function ( e ) {
            ncApp.imagesView.insertToPost( e );
        } )


        /**
         *  NewsCred image insert as a feature image from Image tab
         */
        myDocument.on( "click", ".nc-add-feature-image", function ( e ) {
            ncApp.imagesView.insertAsFeatureImage( e );
        } )

        /**
         *  Remove Feature image
         */
        myDocument.on( "click", "#nc-remove-post-thumbnail", function ( e ) {

            var query_data = {};
            var p_id = $( "#post_ID" ).val();
            query_data['p_id'] = p_id;
            query_data['action'] = "ncajax-remove-feature-image"
            query_data['nc_remove_feature_image_nonce'] = NC_globals.nc_remove_feature_image_nonce ;

            $.ajax( {
                url:NC_globals.ajaxurl,
                type:'POST',
                data:query_data,
                success:function ( response ) {
                    $( "#postimagediv .inside" ).html( response )
                },
                timeout:90000,
                error:function () {

                }
            } );

        } );

        /**
         * insert auto publish  post image sets
         * if nc_image_set post meta exist
         */

        if ( nc_getURLParameter( "post" ) != "null" ) {
            ncApp.attachImageView = new ncApp.AttachImagesView();
            $( "#normal-sortables" ).prepend( ncApp.attachImageView.render().$el );
        }
    }
} );




