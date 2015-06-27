ncApp.MetaBoxView = wp.Backbone.View.extend( {
    template:_.template( $("#nc-metabox").html() ),
    initialize:function () {
        _.bindAll( this, "render", "searchTags", "selectSortBy", "changeTab" );

    },
    events:{
        "keyup #nc-query":"searchTags",
        "click .nc-sortby":"selectSortBy",
        "click #nc-submit":"searchResults",
        "click #nc-tab-head li a":"changeTab"
    },

    render:function () {
        /**
         * rendering MetaBox
         */

        this.$el.html( this.template() );
        this.searchTags();

        this.sort_by = this.options.sort_by;

        // initialize the Tags View
        ncApp.tagsView = new ncApp.TagsView( { collection:new ncApp.Tags() } );
        this.$el.find( ".auto-suggest-holder .clearfix" ).after( ncApp.tagsView.render().$el );

        // article tab
        ncApp.articlesView = new ncApp.ArticlesView( {
            collection:ncApp.tagsView.collection
        } );
        this.$el.find( "#nc-tab-head" ).after( ncApp.articlesView.render().$el );


        // image tab
        ncApp.imagesView = new ncApp.ImagesView( {
            collection:ncApp.tagsView.collection
        } );
        this.$el.find( "#nc-tab-head" ).after( ncApp.imagesView.render().$el );

        // myFeeds tab
        ncApp.myFeedsView = new ncApp.MyFeedsView( {
            collection:ncApp.tagsView.collection
        } );
        this.$el.find( "#nc-tab-head" ).after( ncApp.myFeedsView.render().$el );

        return this;
    },
    searchTags:function ( e ) {

        /**
         * Make auto suggestion for
         * source and topics when user will enter
         * keywords
         */

        var tagSearchRequest;
        var currentView = this;
        this.$el.find( "#nc-query" ).catcomplete( {

            source:function ( request, response ) {
                currentView.$el.find( "#nc-query" ).addClass( "searching" );


                tagSearchRequest = $.ajax( {
                    url:NC_globals.ajaxurl,
                    dataType:"json",
                    data:{
                        action:'ncajax-get-topics-sources',
                        query:request.term,
                        nc_get_source_topic_nonce: NC_globals.nc_get_source_topic_nonce
                    },
                    success:function ( data ) {
                        currentView.$el.find( "#nc-query" ).removeClass( "searching" );

                        if ( data.length > 0 ) {
                            response( $.map( data, function ( value, key ) {
                                return {
                                    label:value.name,
                                    value:value.guid,
                                    category:value.category

                                };
                            } ) );
                        }
                        else
                            response( { label:"0" } );

                    },
                    error:function () {
                        currentView.$el.find( "#nc-query" ).removeClass( "searching" );
                    },
                    abort:function () {
                        currentView.$el.find( "#nc-query" ).removeClass( "searching" );
                    }
                } );
            },
            minLength:2,
            select:function ( event, ui ) {

                var tag = new ncApp.Tag( {
                    "name":ui.item.label,
                    "guid":ui.item.value,
                    "type":ui.item.category
                } );
                ncApp.tagsView.collection.add( tag );
                currentView.$el.find( "#nc-query" ).val( "" );

                return false;
            },
            focus:function ( event, ui ) {
                return false;
            }

        } ).keypress( function ( e ) {
                if ( e.keyCode === 13 ) {
                    if ( tagSearchRequest !== undefined ) {
                        tagSearchRequest.abort();
                        currentView.searchResults();
                        return false;
                    }
                    return false;
                }
            } );

    },
    selectSortBy:function ( e ) {
        /**
         *  toggle sort attribute
         */
        this.sort_by = $( e.target ).attr( "rel" );
        this.$el.find( ".nc-sortby" ).removeClass( "active" );
        $( e.target ).addClass( "active" );
        this.searchResults();

    },
    searchResults:function () {
        /**
         * Search results generation
         */
        var active_tab = this.$el.find( "#nc-tab-head li a.active" ).attr( "index" );
        var query = this.$el.find( "#nc-query" ).val();


        if ( active_tab == 0 ) {
            // article list  tab
            ncApp.articlesView.renderArticleList( query, this.sort_by );
            ncApp.imagesView.renderImageList( query, this.sort_by );

        }
        else if ( active_tab == 1 ) {
            ncApp.imagesView.renderImageList( query, this.sort_by );
            ncApp.articlesView.renderArticleList( query, this.sort_by );
        }
        else {
            ncApp.myFeedsView.renderArticleList( this.sort_by );
            ncApp.imagesView.renderImageList( query, this.sort_by );
            ncApp.articlesView.renderArticleList( query, this.sort_by );

        }
    },
    changeTab:function ( e ) {
        var previousIndex = this.$el.find( "#nc-tab-head li a.active" ).attr( "index" );
        var currentIndex = $( e.target ).attr( "index" );

        this.$el.find( "#nc-tab-head li a.active" ).removeClass( "active" );
        $( e.target ).addClass( "active" );

        $.each( this.$el.find( ".nc-side-bar-tab-content" ), function ( i ) {
            var index = $( this ).attr( "index" );
            if ( index == previousIndex )
                $( this ).addClass( "hide" );

            if ( index == currentIndex )
                $( this ).removeClass( "hide" );
        } );

        return false;
    }

} );