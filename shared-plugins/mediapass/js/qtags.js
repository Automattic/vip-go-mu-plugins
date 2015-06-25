function mp_init_quicktags(){
    QTags.addButton('mpoverlay', 'mediapass overlay', function(){
    	QTags.insertContent('[mpoverlay][/mpoverlay]');
    });
    
    QTags.addButton('mpinpage', 'mediapass in-page', function(){
    	MM.ContentEditorExtensionsInstance.wrapSelectedTextWith('[mpinpage]','[/mpinpage]');
    });
    
    QTags.addButton('mpvideo', 'mediapass video', function(){
    	MM.ContentEditorExtensionsInstance.wrapSelectedTextWith('[mpvideo]','[/mpvideo]');
    });
}