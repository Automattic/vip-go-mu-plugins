ncApp.AttachImagesView = wp.Backbone.View.extend( {

    template:_.template( $( "#nc-attach-image" ).html() ),
    id:"nc-image-set-div",
    className:"postbox",
    initialize:function () {

        _.bindAll( this, "render", "getAttachedImageList" );
        this.getAttachedImageList();
        this.images = new ncApp.Images();

    },
    render:function () {
        var context = {
            image_url: ncApp.imageUrl
        };
        this.$el.html( this.template( context ) );
        return this;
    },
    getAttachedImageList:function () {
        var currentView = this;
        var query_data = {};
        var post_id = nc_getURLParameter( "post" );

        query_data['post_id'] = post_id;
        query_data['action'] = "ncajax-add-article-image-set"
        query_data['nc_get_image_set_nonce'] = NC_globals.nc_get_image_set_nonce ;


        // add image set
        $.ajax( {
            url:NC_globals.ajaxurl,
            type:'POST',
            data:query_data,
            success:function ( response ) {
                if ( response != "" ) {
                    var results = JSON.parse( response );
                    $.each( results, function ( i ) {
                        var image = new ncApp.Image( results[i] );
                        currentView.images.add( image );
                        var imageView = new ncApp.ImageView( { model:image, type:"attach" } );
                        currentView.$el.find( ".nc-thumbnail-box" ).hide().append( imageView.render().$el ).slideDown();

                    } );
                } else {

                    currentView.$el.find( ".nc-thumbnail-box" ).append( "<li  class='no-results'><p>No Image found</p></li>" );
                    currentView.$el.find( ".nc-thumbnail-box" ).slideDown( "slow" );
                }
            },
            timeout:90000,
            error:function () {

            }
        } );

    }



} );