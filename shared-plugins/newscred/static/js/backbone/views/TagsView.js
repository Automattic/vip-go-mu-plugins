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