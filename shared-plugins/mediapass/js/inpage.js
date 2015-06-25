(function() {
    tinymce.create('tinymce.plugins.inpage', {
        init : function(ed, url) {
            ed.addButton('inpage_button', {
                title : 'Media Pass Signup In Page',
                image : url+'/images/shortcode_inpage.png',
                onclick : function() {
                     ed.selection.setContent('[mpinpage]' + ed.selection.getContent() + '[/mpinpage]');
                }
            });
        },
        createControl : function(n, cm) {
            return null;
        },
    });
    tinymce.PluginManager.add('inpage_button', tinymce.plugins.inpage);
})();
