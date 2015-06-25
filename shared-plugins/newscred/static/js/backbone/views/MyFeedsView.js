ncApp.MyFeedsView = wp.Backbone.View.extend( {
    template:_.template( $( "#nc-myfeeds" ).html() ),
    id:"myfeed-tab",
    attributes:{index:2},
    className:"nc-side-bar-tab-content hide",
    initialize:function () {
        _.bindAll( this,
            "render",
            "renderArticleList",
            "scrollArticleResults",
            "renderMoreArticleList"
        );

        this.articles = null;
        this.query_data = null;
        this.moreArticleReq = null;
        this.articleSearchReq = null;
        this.tags = this.collection;

        this.defaultImageWidth = ncApp.defaultWidth;
        this.defaultImageHeight = ncApp.defaultHeight;


    },
    events:{
        "change #myFeedsList":"renderArticleList",
        "scroll":"scrollArticleResults"
    },
    render:function () {
        this.$el.html( this.template() );
        this.getAllMyFeeds();

        return this;
    },
    getAllMyFeeds:function () {

        /**
         * get all myFeeds for the
         * drop down in MyFeeds Tab
         */

        var currentView = this;

        var query_data = {};
        query_data['action'] = "ncajax-get-all-myfeeds";
        query_data['nc_get_myfeeds_nonce'] = NC_globals.nc_get_myfeeds_nonce ;

        this.$el.find( "#myFeedsList" ).select2( {
            placeholder:"Select a MyFeed"
        } );

        $.ajax( {
            url:NC_globals.ajaxurl,
            type:'POST',
            data:query_data,
            success:function ( response ) {
                $.each( JSON.parse( response ), function () {
                    currentView.$el.find( "#myFeedsList" ).append( new Option( this.text, this.id ) );
                } )
            }
        } );
    },
    renderArticleList:function ( e, sort_by ) {
        /**
         * Render Article List for myFeeds
         * */

        var query = $( "#nc-query" ).val();

        if ( !sort_by )
            sort_by = $( ".nc-sortby.active" ).attr( "rel" );

        if ( this.articleSearchReq ) {
            this.articleSearchReq.abort();
            this.articleSearchReq = null;
        }


        var currentView = this;

        // abort the more article call
        if ( this.moreArticleReq ) {
            this.moreArticleReq.abort();
            this.moreArticleReq = null;
        }

        this.articles = new ncApp.Articles();

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

        query_data['type'] = "myFeeds";
        query_data['myfeed_id'] = this.$el.find( "#myFeedsList" ).val();
        query_data['sort'] = sort_by;

        this.query_data = query_data;

        $( "#nc-query" ).addClass( "searching" );

        this.articleSearchReq = $.ajax( {
            url:NC_globals.ajaxurl,
            type:'POST',
            data:query_data,
            success:function ( response ) {

                if ( currentView.$el.find( ".nc-myFeeds-list" ).html() != "" ) {
                    currentView.$el.find( ".nc-myFeeds-list" ).slideUp( "slow" ).html( "" );
                }

                if ( response != "null" ) {

                    var results = JSON.parse( response );

                    if(results.length){

                        $.each( results, function ( i ) {

                            var article = new ncApp.Article( results[i] );
                            currentView.articles.add( article );
                            var articleView = new ncApp.ArticleView( { model:article, type:"myFeeds"} );
                            currentView.$el.find( ".nc-myFeeds-list" ).hide().append( articleView.render().$el ).slideDown();

                        } );
                    }else{
                        currentView.$el.find( ".nc-myFeeds-list" ).append( "<li class='no-results'><p>Some problems appeared. Please try again later .</p></li>" );
                        currentView.$el.find( ".nc-myFeeds-list" ).slideDown( "slow" );
                    }

                }
                else {
                    currentView.$el.find( ".nc-myFeeds-list" ).append( "<li class='no-results'><p>No article found.</p></li>" );
                    currentView.$el.find( ".nc-myFeeds-list" ).slideDown( "slow" );
                }

                $( "#nc-query" ).removeClass( "searching" );

                this.articleSearchReq = null;

            },
            timeout:30000,
            error:function () {
                $( "#nc-query" ).removeClass( "searching" );
            }
        } );
    },
    renderMoreArticleList:function () {

        /**
         * Render More Article List for myFeeds
         * */


        if ( this.moreArticleReq )
            return;

        var currentView = this;


        currentView.query_data["page"] = currentView.query_data["page"] + 1

        currentView.$el.find( ".nc-myFeeds-list" ).append( "<li class='searching'>Searching more articles ....</li>" );


        this.moreArticleReq = $.ajax( {
            url:NC_globals.ajaxurl,
            type:'POST',
            data:currentView.query_data,
            success:function ( response ) {

                if ( response != "null" ) {

                    var results = JSON.parse( response );

                    if(results.length){

                        $.each( results, function ( i ) {

                            var article = new ncApp.Article( results[i] );
                            currentView.articles.add( article );
                            var articleView = new ncApp.ArticleView( { model:article, type:"myFeeds" } );
                            currentView.$el.find( ".nc-myFeeds-list" ).append( articleView.render().$el );

                        } );
                    }else{
                        currentView.$el.find( ".nc-myFeeds-list" ).append( "<li class='no-results'><p>Some problems appeared. Please try again later .</p></li>" );
                        currentView.$el.find( ".nc-myFeeds-list" ).slideDown( "slow" );
                    }
                } else {
                    currentView.$el.find( ".nc-myFeeds-list" ).append( "<li  class='no-results'><p>No article found.</p></li>" );
                }
                currentView.$el.find( ".nc-myFeeds-list li.searching" ).remove();

                currentView.moreArticleReq = null;

            },
            timeout:30000,
            error:function () {
                currentView.$el.find( ".nc-myFeeds-list li.searching" ).remove();
            }
        } );

    },
    scrollArticleResults:function () {

        /**
         * infinite scrolling for articles
         */

        var currentView = this;
        this.$el.bind( "scroll", function ( e ) {
            if ( ( $( e.target )[0].scrollHeight - $( e.target ).scrollTop() == $( e.target ).outerHeight() - 1 ) )
                currentView.renderMoreArticleList();

        } );
    }

} );