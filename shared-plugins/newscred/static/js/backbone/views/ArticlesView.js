ncApp.ArticlesView = wp.Backbone.View.extend( {
    template:_.template( $("#nc-articles" ).html() ),
    id:"article-tab",
    className:"nc-side-bar-tab-content",
    attributes:{index:0},
    initialize:function () {

        _.bindAll( this, "render",
            "renderArticleList",
            "scrollArticleResults",
            "renderMoreArticleList",
            "imageInsertToPost",
            "imageInsertAsFeatureImage"
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
        "scroll":"scrollArticleResults"
    },
    render:function () {

        this.$el.html( this.template() );
        return this;
    },
    renderArticleList:function ( query, sort_by ) {

        /**
         * Render Article List
         * */

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


        query_data['query'] = query
        query_data['action'] = "ncajax-metabox-search";
        query_data['nc_search_nonce'] = NC_globals.nc_search_nonce ;
        query_data['page'] = 1;

        query_data['sources'] = sources;
        query_data['topics'] = topics;

        query_data['type'] = "article";
        query_data['sort'] = sort_by;

        this.query_data = query_data;

        $( "#nc-query" ).addClass( "searching" );

        this.articleSearchReq = $.ajax( {
            url:NC_globals.ajaxurl,
            type:'POST',
            data:query_data,
            success:function ( response ) {
                currentView.$el.find( ".default-status" ).slideUp();

                if ( currentView.$el.find( ".nc-article-list" ).html() != "" ) {
                    currentView.$el.find( ".nc-article-list" ).slideUp( "slow" ).html( "" );
                }

                if ( response != "null" ) {

                    var results = JSON.parse( response );
                    if(results.length){
                        $.each( results, function ( i ) {

                            var article = new ncApp.Article( results[i] );
                            currentView.articles.add( article );
                            var articleView = new ncApp.ArticleView( { model:article, type:"articles"} );
                            currentView.$el.find( ".nc-article-list" ).hide().append( articleView.render().$el ).slideDown();

                        } );
                    }else{
                        currentView.$el.find( ".nc-article-list" ).append( "<li class='no-results'><p>Some problems appeared. Please try again later .</p></li>" );
                        currentView.$el.find( ".nc-article-list" ).slideDown( "slow" );
                    }
                }
                else {
                    currentView.$el.find( ".nc-article-list" ).append( "<li class='no-results'><p>No article found.</p></li>" );
                    currentView.$el.find( ".nc-article-list" ).slideDown( "slow" );
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
         * Render More Article List
         * */


        if ( this.moreArticleReq )
            return;

        var currentView = this;


        currentView.query_data["page"] = currentView.query_data["page"] + 1

        currentView.$el.find( ".nc-article-list" ).append( "<li class='searching'>Searching more articles ....</li>" );


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
                            var articleView = new ncApp.ArticleView( { model:article, type:"articles" } );
                            currentView.$el.find( ".nc-article-list" ).append( articleView.render().$el );

                        } );

                    }else{
                        currentView.$el.find( ".nc-article-list" ).append( "<li class='no-results'><p>Some problems appeared. Please try again later .</p></li>" );

                    }

                } else {
                    currentView.$el.find( ".nc-article-list" ).append( "<li  class='no-results'><p>No article found.</p></li>" );
                }
                currentView.$el.find( ".nc-article-list li.searching" ).remove();

                currentView.moreArticleReq = null;

            },
            timeout:30000,
            error:function () {
                currentView.$el.find( ".nc-article-list li.searching" ).remove();
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
    },
    imageInsertToPost:function ( e ) {

        /**
         * insert tooltip image to post content from article tab
         */

        var article_guid = $( e.target ).attr( "article" );
        var guid = $( e.target ).attr( "guid" );

        var article;
        if ( $( e.target ).attr( "type" ) == "articles" )
            article = this.articles.where( {guid:article_guid} )[0];
        else
            article = ncApp.myFeedsView.articles.where( {guid:article_guid} )[0];

        var images = $.grep( article.get( "image_set" ), function ( img ) {
            return img.guid == guid;
        } );
        var image = images[0];


        var width = this.defaultImageWidth;
        var height = this.defaultImageHeight;


        var img_url = image.image_large;
        var caption = image.caption;

        var source;

        if ( image.attribution_text )
            source = image.attribution_text;
        else if ( image.source )
            source = image.source.name;


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
    imageInsertAsFeatureImage:function ( e ) {

        /**
         * add NewsCred Image as post feature image from article tab
         */

        var article_guid = $( e.target ).attr( "article" );
        var guid = $( e.target ).attr( "guid" );

        var article;
        if ( $( e.target ).attr( "type" ) == "articles" )
            article = this.articles.where( {guid:article_guid} )[0];
        else
            article = ncApp.myFeedsView.articles.where( {guid:article_guid} )[0];


        var images = $.grep( article.get( "image_set" ), function ( img ) {
            return img.guid == guid;
        } );
        var image = images[0];

        var query_data = {};

        var p_id = $( "#post_ID" ).val();

        query_data['p_id'] = p_id;
        query_data['url'] = image.image_large;
        query_data['caption'] = image.caption;
        query_data['action'] = "ncajax-add-image"
        query_data['nc_add_feature_image_nonce'] = NC_globals.nc_add_feature_image_nonce ;
        $( e.target ).closest( ".article-tooltip" ).find( ".attached-image-loading" ).css( "display", "inline-block" )


        var currentEl = $( e.target );
        $.ajax( {
            url:NC_globals.ajaxurl,
            type:'POST',
            data:query_data,
            success:function ( response ) {

                currentEl.closest( ".article-tooltip" ).find( ".attached-image-loading" ).css( "display", "none" )

                currentEl.css( "opacity", "0.2" );

                // add as feature image
                $( "#postimagediv .inside" ).html( response )

            },
            error:function () {

                currentEl.closest( ".article-tooltip" ).find( ".attached-image-loading" ).css( "display", "none" )
            }
        } );
        return false;

    }

} );