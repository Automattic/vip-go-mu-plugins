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