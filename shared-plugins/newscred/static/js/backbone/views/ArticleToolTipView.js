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