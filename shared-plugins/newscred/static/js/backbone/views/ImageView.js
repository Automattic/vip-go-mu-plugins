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