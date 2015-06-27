(function() {
    tinymce.create('tinymce.plugins.video', {
        init : function(ed, url) {
            ed.addButton('video_button', {
                title : 'Embedded Video Overlay',
                image : url+'/images/shortcode_video.png',
                onclick : function() {
                     ed.selection.setContent('[mpvideo width="600" height="480" delay="20" title="Sample Title" vid="12345"] Paste_Your_Video_Code_Here' + ed.selection.getContent() + '[/mpvideo]');
                }
            });
        },
        createControl : function(n, cm) {
            return null;
        },
    });
    tinymce.PluginManager.add('video_button', tinymce.plugins.video);
})();
