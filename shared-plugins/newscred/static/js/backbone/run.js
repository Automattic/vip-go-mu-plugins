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

ncApp.metaBoxRouter = new ncApp.MetaBoxRouter();