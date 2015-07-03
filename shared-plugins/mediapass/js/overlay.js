(function() {
    tinymce.create('tinymce.plugins.overlay', {
        init : function(ed, url) {
            ed.addButton('overlay_button', {
                title : 'Media Pass Signup Overlay',
                image : url+'/images/shortcode_overlay.png',
                onclick : function() {
                     ed.selection.setContent('[mpoverlay]' + ed.selection.getContent() + '[/mpoverlay]');
                }
            });
        },
        createControl : function(n, cm) {
            return null;
        },
    });
    tinymce.PluginManager.add('overlay_button', tinymce.plugins.overlay);
})();
