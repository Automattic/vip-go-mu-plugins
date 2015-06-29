(function() {
	tinymce.create('tinymce.plugins.IceRevisionsPlugin', {

		init: function(ed, url) {
			// init Ice after MCE is ready and content is loaded and re-init Ice when switching from HTML to Visual mode
			ed.onLoadContent.add(function(ed, o) {
				if ( ed.id != 'content' && ed.id != 'wp_mce_fullscreen' ) // only on the main editor 
					return;

				if ( ed.isHidden() )
					return;

				if ( o.initial )
					setTimeout( function(){
						ed.execCommand('initializeice');
					}, 1000);
				else
					ed.execCommand('ice_initenv');
			});
		}
	});

	tinymce.PluginManager.add('icerevisions', tinymce.plugins.IceRevisionsPlugin);
})();
