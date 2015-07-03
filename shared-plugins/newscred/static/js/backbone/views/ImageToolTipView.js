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