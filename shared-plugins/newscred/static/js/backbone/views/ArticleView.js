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