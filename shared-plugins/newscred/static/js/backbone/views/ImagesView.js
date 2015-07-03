ncApp.ImagesView = wp.Backbone.View.extend( {
    template:_.template( $( "#nc-images" ).html() ),
    id:"image-tab",
    attributes:{index:1},
    className:"nc-side-bar-tab-content hide",
    initialize:function () {
        _.bindAll( this, "render",
            "renderImageList",
            "renderMoreImageList",
            "scrollImageResults",
            "insertToPost",
            "insertAsFeatureImage"
        );

        this.moreImageReq = null;
        this.imageSearchReq = null;
        this.query_data = null;
        this.images = null;


        this.tags = this.collection;


    },
    events:{
        "scroll":"scrollImageResults"
    },
    render:function () {
        this.$el.html( this.template() );
        return this;
    },
    renderImageList:function ( query, sort_by ) {

        /**
         * Render Image List
         * */

        if ( this.imageSearchReq ) {
            this.imageSearchReq.abort();
            this.imageSearchReq = null;
        }


        var currentView = this;

        // abort the more image search  call

        if ( this.moreImageReq ) {
            this.moreImageReq.abort();
            this.moreImageReq = null;
        }

        this.images = new ncApp.Images();

        var query_data = {};
        var sources = [];
        var topics = [];

        if ( this.tags.length > 0 ) {
            var tags = this.tags.toJSON();
            $.each( tags, function ( i ) {
                if ( tags[i].type == "Sources" )
                    sources.push( tags[i].guid );
                else
                    topics.push( tags[i].guid );
            } )
        }


        query_data['query'] = query;
        query_data['action'] = "ncajax-metabox-search";
        query_data['nc_search_nonce'] = NC_globals.nc_search_nonce ;
        query_data['page'] = 1;

        query_data['sources'] = sources;
        query_data['topics'] = topics;

        query_data['type'] = "image";
        query_data['sort'] = sort_by;

        this.query_data = query_data;

        $( "#nc-query" ).addClass( "searching" );

        this.imageSearchReq = $.ajax( {
            url:NC_globals.ajaxurl,
            type:'POST',
            data:query_data,
            success:function ( response ) {


                currentView.$el.find( ".default-status" ).slideUp();

                if ( currentView.$el.find( ".nc-thumbnail-box" ).html() != "" ) {
                    currentView.$el.find( ".nc-thumbnail-box" ).slideUp( "slow" ).html( "" );
                }

                if ( response != "null" ) {

                    var results = JSON.parse( response );
                    if(results.length){
                        $.each( results, function ( i ) {

                            var image = new ncApp.Image( results[i] );
                            currentView.images.add( image );
                            var imageView = new ncApp.ImageView( { model:image, type:"image" } );
                            currentView.$el.find( ".nc-thumbnail-box" ).hide().append( imageView.render().$el ).slideDown();

                        } );
                    }
                    else{
                        currentView.$el.find( ".nc-thumbnail-box" ).append( "<li  class='no-results'><p>Some problems appeared. Please try again later .</p></li>" );
                        currentView.$el.find( ".nc-thumbnail-box" ).slideDown( "slow" );
                    }
                }
                else {

                    currentView.$el.find( ".nc-thumbnail-box" ).append( "<li  class='no-results'><p>No image found.</p></li>" );
                    currentView.$el.find( ".nc-thumbnail-box" ).slideDown( "slow" );
                }

                $( "#nc-query" ).removeClass( "searching" );

                this.imageSearchReq = null;

            },
            timeout:30000,
            error:function () {
                $( "#nc-query" ).removeClass( "searching" );
            }
        } );

    },
    renderMoreImageList:function () {

        /**
         * Render More Image List
         * */


        if ( this.moreImageReq )
            return;

        var currentView = this;
        currentView.query_data["page"] = currentView.query_data["page"] + 1
        currentView.$el.find( ".nc-thumbnail-box" ).append( "<li class='searching'>Searching more images ....</li>" );

        this.moreImageReq = $.ajax( {
            url:NC_globals.ajaxurl,
            type:'POST',
            data:currentView.query_data,
            success:function ( response ) {

                if ( response != "null" ) {

                    var results = JSON.parse( response );
                    if(results.length){
                        $.each( results, function ( i ) {

                            var image = new ncApp.Image( results[i] );
                            currentView.images.add( image );
                            var imageView = new ncApp.ImageView( { model:image, type:"image"  } );
                            currentView.$el.find( ".nc-thumbnail-box" ).append( imageView.render().$el ).slideDown();

                        } );
                    }
                    else
                        currentView.$el.find( ".nc-thumbnail-box" ).append( "<li  class='no-results'><p>Some problems appeared. Please try again later .</p></li>" );


                } else {
                    currentView.$el.find( ".nc-thumbnail-box" ).append( "<li class='no-results'><p>No image found.</p></li>" );
                }
                currentView.$el.find( ".nc-thumbnail-box li.searching" ).remove();

                currentView.moreImageReq = null;

            },
            timeout:30000,
            error:function () {
                currentView.$el.find( ".nc-thumbnail-box li.searching" ).remove();
            }
        } );

    },
    scrollImageResults:function () {

        /**
         * infinite scrolling for image
         */

        var currentView = this;
        this.$el.bind( "scroll", function ( e ) {
            if ( ( $( e.target )[0].scrollHeight - $( e.target ).scrollTop() == $( e.target ).outerHeight() - 1 ) )
                currentView.renderMoreImageList();

        } );
    },
    insertToPost:function ( e ) {

        /**
         * insert tooltip image to post content
         */
        var guid = $( e.target ).attr( "guid" );
        var image;
        if ( $( e.target ).attr( "type" ) == "image" )
            image = this.images.where( {guid:guid} )[0];
        else
            image = ncApp.attachImageView.images.where( {guid:guid} )[0];


        var width = $( e.target ).closest( ".image-tooltip" ).find( ".post_width" ).val();

        var height = $( e.target ).closest( ".image-tooltip" ).find( ".post_height" ).val();

        var actual_width = $( e.target ).closest( ".image-tooltip" ).find( ".post_width" ).attr( "actual_value" );
        var actual_height = $( e.target ).closest( ".image-tooltip" ).find( ".post_height" ).attr( "actual_value" );

        var default_width = $( e.target ).closest( ".image-tooltip" ).find( ".post_width" ).attr( "default_value" );
        var default_height = $( e.target ).closest( ".image-tooltip" ).find( ".post_height" ).attr( "default_value" );

        if ( width == actual_width && default_width > 0)
            width = default_width;

        if ( height == actual_height && default_height > 0)
            height = default_height;

        var img_url = image.get( "image_large" );
        var caption = $( e.target ).closest( ".image-tooltip" ).find( ".image-caption" ).val();

        var source;

        if ( image.get( "attribution_text" ) )
            source = image.get( "attribution_text" );
        else if ( image.get( "source" ) )
            source = image.get( "source" ).name;


        var caption_html = '[caption align="alignnone" width="' + width + '" height="' + height + '" ]' +
            '<a href="' + img_url + '">' +
            '<img class="size-medium" title="' + caption + '" src="' + img_url + '?width=' + width + '&height=' + height + '" alt="' + caption + '" width="' + width + '" height="' + height + '" />' +
            '</a>' + caption + '<strong style="font-style: italic;display: block">' + source + '</strong>' +
            '[/caption]';

        // check tinyMCE active or not
        var tinyMceActive = $( ".tmce-active" ).length;

        if ( tinyMceActive ) {
            var ed = tinyMCE.getInstanceById( 'content' );
            ed.focus();
            ed.selection.setContent( caption_html );
            $( "#content-html" ).trigger( "click" );
            $( "#content-tmce" ).trigger( "click" );

        } else {
            $( "#content" ).insertAtCaret( caption_html )

        }


    },
    insertAsFeatureImage:function ( e ) {
        /**
         * add NewsCred Image as post feature image
         */
        var guid = $( e.target ).attr( "guid" );

        var image;
        if ( $( e.target ).attr( "type" ) == "image" )
            image = this.images.where( {guid:guid} )[0];
        else
            image = ncApp.attachImageView.images.where( {guid:guid} )[0];


        var query_data = {};

        var p_id = $( "#post_ID" ).val();

        query_data['p_id'] = p_id;
        query_data['url'] = image.get( "image_large" );
        query_data['caption'] = $( e.target ).closest( ".image-tooltip" ).find( ".image-caption" ).val();
        query_data['action'] = "ncajax-add-image"
        query_data['nc_add_feature_image_nonce'] = NC_globals.nc_add_feature_image_nonce ;

        var html = $( e.target ).html();

        $( e.target ).after( "<span class='pull-right searching'>Uploading..</span>" );
        $( e.target ).css( "display", "none" );

        var currentEl = $( e.target );
        $.ajax( {
            url:NC_globals.ajaxurl,
            type:'POST',
            data:query_data,
            success:function ( response ) {

                currentEl.css( "display", "inline-block" );
                currentEl.css( "opacity", "0.2" );
                currentEl.closest( "p" ).find( ".searching" ).css( "display", "none" );

                // add as feature image
                $( "#postimagediv .inside" ).html( response )

            },
            error:function () {

                currentEl.css( "display", "inline-block" );
                currentEl.closest( "p" ).find( ".searching" ).css( "display", "none" );

            }
        } );
        return false;

    }



} );