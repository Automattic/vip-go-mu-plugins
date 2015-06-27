var $nc = jQuery.noConflict();(function($){/**
 *  @package NewsCred WordPress Plugin
 *  @author  Md Imranur Rahman <imran.aspire@gmail.com>
 *
 *  main  run  file
 **/

var ncApp = this.ncApp || {};

ncApp.domain = NC_globals.domain;
ncApp.isLogin = NC_globals.is_login;
ncApp.imageUrl = NC_globals.imageurl;

ncApp.defaultWidth = NC_globals.default_width;
ncApp.defaultHeight = NC_globals.default_height;
ncApp.Article = Backbone.Model.extend( {
    defaults:{
        guid:null,
        title:null,
        categories:[],
        description:null,
        image_set:[],
        published_at:null,
        source:null,
        author:null,
        topics:[]

    }
} );
ncApp.Image = Backbone.Model.extend( {
    defaults:{
        guid:null,
        caption:null,
        description:null,
        attribution_text:null,
        published_at:null,
        source:null,
        image_large:null

    }
} );
ncApp.Tag = Backbone.Model.extend( {
    defaults:{
        name:null,
        guid:null,
        type:null
    }
} );
ncApp.Articles = Backbone.Collection.extend( {
    model:ncApp.Article
} );
ncApp.Images = Backbone.Collection.extend( {
    model:ncApp.Imaegs
} );
ncApp.Tags = Backbone.Collection.extend( {
    model:ncApp.Tag
} );
ncApp.ArticleToolTipView = wp.Backbone.View.extend( {
    template:_.template( $( "#nc-article-tooltip" ).html() ),
    className:"articles-tooltip-container tooltip-container",
    initialize:function () {
        _.bindAll( this, "render" );
    },
    render:function () {
        var context = {
            model:this.model.toJSON(),
            published_at:nc_time_ago( this.model.get( "published_at" ) ),
            description: this.model.get( "description" ).replace(/<\/?[^>]+(>|$)/g, "").substring( 0, 400 ),
            type:this.options.type,
            image_num:this.model.get( "image_set" ).length
        };
        this.$el.html( this.template( context ) );
        return this;
    }
} );
ncApp.ArticleView = wp.Backbone.View.extend( {

    template:_.template( $( "#nc-article" ).html() ),
    tagName:'li',
    className:"articles-tooltip",
    initialize:function () {

        _.bindAll( this, "render",
            "tooltipShow",
            "insertToPost",
            "insertContent",
            "insertCategory",
            "removeContent"
        );


    },
    events:{
        "mouseenter":"tooltipShow",
        "click .nc-click-to-insert":"insertToPost",
        "click .nc-remove-article":"removeArticle",
        "click .nc-tp":"customClick"

    },

    render:function () {

        var context = {
            model:this.model.toJSON(),
            published_at:nc_time_ago( this.model.get( "published_at" ) ),
            imageUrl:ncApp.imageUrl
        };
        this.$el.html( this.template( context ) );
        return this;
    },
    tooltipShow:function ( e ) {
        /**
         * Show tooltip when hover the article
         */

        var currentView = this;
        var toolTipView = new ncApp.ArticleToolTipView( { model:currentView.model, type:currentView.options.type } );

        // make the tooltipster instance
        currentView.$el.find( ".nc-on-hover" ).tooltipster( {
            content:toolTipView.render().$el.html(),
            interactive:true,
            position:'left',
            theme:'.tooltipster-punk',
            fixedWidth:500,
            maxWidth:500,
//            interactiveTolerance: 2000,
            functionAfter:function ( origin ) {

                currentView.$el.find( ".nc-on-hover" ).effect( "drop", "", 1, "" );
                //currentView.$el.find(".nc-on-hover").tooltipster("destroy");

            }
        } );

        this.$el.find( ".nc-on-hover" ).show( "drop", {direction:'right'}, 200, function () {
            currentView.$el.find( ".nc-on-hover" ).tooltipster( "show" );
        } );
    },
    insertToPost:function ( e ) {
        /**
         * Insert to post click :
         */

        $( ".nc-click-to-insert" ).removeClass( "off" );
        $( ".nc-remove-article" ).addClass( "off" );

        this.insertContent();
        this.insertCategory();
        this.insertTag();


        this.$el.find( ".off" ).removeClass( "off" );
        $( e.target ).addClass( "off" );
    },
    insertContent:function () {

        /* add title */
        $( "#title-prompt-text" ).html( "" );
        $( "#title" ).val( this.model.get( "title" ) );


        /* add author */
        var author;
        if ( !this.model.get( "author" ) )
            author = this.model.get( "source" ).name;
        else
            author = this.model.get( "author" ) + " for " + this.model.get( "source" ).name;

        $( "#nc-post-author" ).val( author );

        /* add publish date */
        if ( $( "#nc_publish_time" ).val() ) {

            var publish_date = new Date( this.model.get( "published_at" ) );

            var mm = ("0" + (publish_date.getMonth() + 1)).slice( -2 );
            var dd = publish_date.getDate();
            var yy = publish_date.getFullYear();
            var hh = publish_date.getHours();
            var ii = publish_date.getMinutes();

            $( "#mm" ).val( mm );
            $( "#jj" ).val( dd )
            $( "#aa" ).val( yy );
            $( "#hh" ).val( hh )
            $( "#mn" ).val( ii );
        }


        /* add tags */
        if ( $( "#nc_tags" ).val() ) {
            var tags = $( this ).closest( 'li' ).find( ".nc-tags" ).html();
            $( "#new-tag-post_tag" ).val( tags );
        }


        /* add content  */
        var content = this.model.get( "description" );
        // check tinyMCE active or not
        var tinyMceActive = $( ".tmce-active" ).length;
        if ( tinyMceActive ) {
            tinyMCE.get( 'content' ).setContent( content );
        } else {
            $( "#content" ).val( "" );
            $( "#content" ).insertAtCaret( content )
        }

    },
    insertCategory:function () {

        /**
         * add category
         */

        var categoies = this.model.get( "categories" );

        $( "#taxonomy-category .nc-cat-list" ).remove();

        if ( categoies.length > 0  && $('#nc_categories').val() > 0 ) {
            var cat_html_str = '<div class="tagchecklist nc-cat-list">';
            $.each( categoies, function ( i ) {
                cat_html_str += '<span><a  class="ntdelbutton nc-new-category">X</a>&nbsp;' + categoies[i] + '<input name="nc-cat-list[]" value="' + categoies[i] + '" type="hidden" /></span>'

            } )
            cat_html_str += "</div><input type='hidden' name='top' value='23'>";

            $( "#taxonomy-category" ).append( cat_html_str );
        }

        $( ".nc-cat-list span a.nc-new-category" ).bind( "click", this.removeCategory )

    },
    removeCategory:function () {
        /**
         * remove category
         *
         */
        $( this ).parent().remove();
    },
    insertTag:function () {

        var tags = this.model.get( "topics" );

        /* add tags */
        if ( tags.length > 0 && $( "#nc_tags" ).val() ) {
            var tags_array = [];
            $.each( tags, function ( i ) {
                tags_array.push( tags[i].name );
            } )
            $( "#new-tag-post_tag" ).val( tags_array.join( "," ) );
        }

    },
    removeContent:function ( e ) {

        // check tinyMCE active or not
        var tinyMceActive = $( ".tmce-active" ).length;

        if ( tinyMceActive ) {
            tinyMCE.get( 'content' ).setContent( "" );
        } else {
            $( "#content" ).val( "" );
        }

    },
    removeArticle:function ( e ) {
        this.$el.find( ".off" ).removeClass( "off" );
        $( e.target ).addClass( "off" );
        this.removeContent();
    },
    customClick:function () {
        alert( "SDFSDF" )
    }

} );
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
            var ed = tinyMCE.activeEditor.selection;
            ed.getContent();
            ed.setContent( caption_html );
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
ncApp.ImageToolTipView = wp.Backbone.View.extend( {
    template:_.template( $( "#nc-image-tooltip" ).html() ),
    className:"articles-tooltip-container tooltip-container",
    initialize:function () {
        _.bindAll( this, "render" );
    },
    render:function () {
        var context = {
            model:this.model.toJSON(),
            published_at:nc_time_ago( this.model.get( "published_at" ) ),
            imageUrl:ncApp.imageUrl,
            defaultWidth:ncApp.defaultWidth,
            defaultHeight:ncApp.defaultHeight,
            type:this.options.type
        };
        this.$el.html( this.template( context ) );
        return this;
    }

} );
ncApp.ImageView = wp.Backbone.View.extend( {

    template:_.template( $( "#nc-image" ).html() ),
    tagName:'li',
    initialize:function () {

        _.bindAll( this, "render",
            "tooltipShow"
        );


    },
    events:{
        "mouseenter":"tooltipShow"
    },
    render:function () {

        var context = {
            model:this.model.toJSON()
        };
        this.$el.html( this.template( context ) );
        return this;
    },

    tooltipShow:function ( e ) {
        /**
         * Show tooltip when hover the image
         */
        var currentView = this;
        var toolTipView = new ncApp.ImageToolTipView( { model:currentView.model, type:currentView.options.type } );

        // make the tooltipster instance
        currentView.$el.find( ".img-tooltip" ).tooltipster( {
            content:toolTipView.render().$el.html(),
            interactive:true,
            position:'left',
//            animation: 'grow',
            theme:'.tooltipster-punk',
            fixedWidth:300,
            maxWidth:300,
//            interactiveTolerance: 450,
//            delay: 500

        } );

        this.$el.find( ".img-tooltip" ).show( "drop", {direction:'right'}, 200, function () {
            currentView.$el.find( ".img-tooltip" ).tooltipster( "show" );
        } );
    }


} );
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
            var ed = tinyMCE.activeEditor.selection;
            ed.getContent();
            ed.setContent( caption_html );
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
ncApp.TagView = wp.Backbone.View.extend( {

    template:_.template( $( "#nc-tag" ).html() ),
    tagName:'span',
    className:"tag tooltips",
    initialize:function () {
        _.bindAll( this, "render", "removeTag" );
    },
    events:{
        "click .topic_remove":"removeTag"
    },

    render:function () {
        var context = {
            model:this.model.toJSON()
        };
        this.$el.html( this.template( context ) );
        return this;
    },
    removeTag:function () {
        var currentView = this;
        currentView.$el.fadeOut( 500, function () {
            currentView.$el.remove();
            currentView.model.destroy();
        } );
    }


} );
ncApp.TagsView = wp.Backbone.View.extend( {

    className:"nc-fliter-tag",
    initialize:function () {
        _.bindAll( this, "render", "addOne", "clearAllTags" );
        this.collection.on( 'add', this.addOne, this );
        this.collection.on( 'remove', this.clearAllButton, this );
    },
    events:{
        "click .nc-filter-clearll":"clearAllTags"
    },

    render:function () {
        this.$el.html( "" );
        return this;
    },
    addOne:function ( tag ) {
        var tagView = new ncApp.TagView( {model:tag} );
        this.$el.prepend( tagView.render().$el );

        if ( this.collection.length == 1 ) {
            var html = '<a href="javascript:;" class="nc-filter-clearll">Clear All</a>';
            this.$el.find( ".tag" ).after( html );
        }
    },
    clearAllButton:function () {

        if ( this.collection.length == 0 ) {
            this.$el.find( ".nc-filter-clearll" ).remove();
        }
    },
    clearAllTags:function () {
        var currentView = this;
        this.$el.fadeOut( 500, function () {
            currentView.collection.reset();
            currentView.render();
            currentView.$el.css( "display", "block" );
        } );
    }

} );
/**
 * jquery ui autocomplete with category
 */
$.widget( "custom.catcomplete", $.ui.autocomplete, {
    _renderMenu:function ( ul, items ) {
        var that = this,
            currentCategory = "";
        if ( items[0].label != "0" ) {
            $.each( items, function ( index, item ) {
                if ( item.category != currentCategory ) {
                    ul.append( "<li class='ui-autocomplete-category'>" + item.category + "</li>" );
                    currentCategory = item.category;
                }
                that._renderItemData( ul, item );
            } );
        } else
            ul.append( "<li class='ui-autocomplete-category'>No search results.</li>" );
    }
} );


/**
 * time ago from a sepeciice data
 * @param time
 */
function nc_time_ago( time ) {

    switch ( typeof time ) {
        case 'number':
            break;
        case 'string':
            time = +new Date( time );
            break;
        case 'object':
            if ( time.constructor === Date ) time = time.getTime();
            break;
        default:
            time = +new Date();
    }
    var time_formats = [
        [60, 'seconds', 1],
        // 60
        [120, '1 minute ago', '1 minute ago'],
        // 60*2
        [3600, 'minutes', 60],
        // 60*60, 60
        [7200, '1 hour ago', '1 hour ago'],
        // 60*60*2
        [86400, 'hours', 3600],
        // 60*60*24, 60*60
        [172800, 'Yesterday', 'Tomorrow'],
        // 60*60*24*2
        [604800, 'days', 86400],
        // 60*60*24*7, 60*60*24
        [1209600, 'Last week', 'Next week'],
        // 60*60*24*7*4*2
        [2419200, 'weeks', 604800],
        // 60*60*24*7*4, 60*60*24*7
        [4838400, 'Last month', 'Next month'],
        // 60*60*24*7*4*2
        [29030400, 'months', 2419200],
        // 60*60*24*7*4*12, 60*60*24*7*4
        [58060800, 'Last year', 'Next year'],
        // 60*60*24*7*4*12*2
        [2903040000, 'years', 29030400],
        // 60*60*24*7*4*12*100, 60*60*24*7*4*12
        [5806080000, 'Last century', 'Next century'],
        // 60*60*24*7*4*12*100*2
        [58060800000, 'centuries', 2903040000] // 60*60*24*7*4*12*100*20, 60*60*24*7*4*12*100
    ];
    var seconds = (+new Date() - time) / 1000,
        token = 'ago', list_choice = 1;

    if ( seconds == 0 ) {
        return 'Just now'
    }
    if ( seconds < 0 ) {
        seconds = Math.abs( seconds );
        token = 'ago';
        list_choice = 2;
    }
    var i = 0, format;
    while ( format = time_formats[i++] )
        if ( seconds < format[0] ) {
            if ( typeof format[2] == 'string' )
                return format[list_choice];
            else
                return Math.floor( seconds / format[2] ) + ' ' + format[1] + ' ' + token;
        }
    return time;
}

/**
 * insert text in mouse cursor position in TEXTAREA
 */
$.fn.extend( {
    insertAtCaret:function ( myValue ) {
        return this.each( function ( i ) {
            if ( document.selection ) {
                //For browsers like Internet Explorer
                this.focus();
                sel = document.selection.createRange();
                sel.text = myValue;
                this.focus();
            }
            else if ( this.selectionStart || this.selectionStart == '0' ) {
                //For browsers like Firefox and Webkit based
                var startPos = this.selectionStart;
                var endPos = this.selectionEnd;
                var scrollTop = this.scrollTop;
                this.value = this.value.substring( 0, startPos ) + myValue + this.value.substring( endPos, this.value.length );
                this.focus();
                this.selectionStart = startPos + myValue.length;
                this.selectionEnd = startPos + myValue.length;
                this.scrollTop = scrollTop;
            } else {
                this.value += myValue;
                this.focus();
            }
        } )
    }
} );

// get current url parameter
function nc_getURLParameter( name ) {
    return decodeURI(
        (RegExp( name + '=' + '(.+?)(&|$)' ).exec( location.search ) || [, null])[1]
    );
}
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





// load required script
function require( script ) {
    $.ajax( {
        url:script,
        dataType:"script",
        async:false,
        success:function () {
        },
        error:function ( ex ) {
            throw new Error( "Could not load script " + script );
        }
    } );

}
// load common settings
// BackBone instance  load
var scripts = {
    "models":["Tag", "Article", "Image"],
    "collections":["Tags", "Articles", "Images"],
    "views":[
        "MetaBoxView",
        "TagsView",
        "TagView",
        "ArticlesView",
        "ArticleView",
        "ArticleToolTipView",
        "ImagesView",
        "ImageView",
        "ImageToolTipView",
        "MyFeedsView",
        "AttachImagesView"
    ]

};

ncApp.metaBoxRouter = new ncApp.MetaBoxRouter();})($nc);