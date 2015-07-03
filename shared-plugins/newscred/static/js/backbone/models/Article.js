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